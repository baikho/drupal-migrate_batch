<?php

namespace Drupal\migrate_batch\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\migrate_batch\Service\MigrateBatchService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for displaying migrate batch states in the admin interface.
 */
class MigrateBatchController extends ControllerBase {

  /**
   * The migrate batch service.
   *
   * @var \Drupal\migrate_batch\Service\MigrateBatchService
   */
  protected MigrateBatchService $migrateBatchService;

  /**
   * Constructs a new MigrateBatchStatesController.
   *
   * @param \Drupal\migrate_batch\Service\MigrateBatchService $migrateBatchService
   *   The migrate batch service.
   */
  public function __construct(MigrateBatchService $migrateBatchService) {
    $this->migrateBatchService = $migrateBatchService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('migrate_batch')
    );
  }

  /**
   * Displays the migrate batch states overview page.
   */
  public function overview() {
    $header = [
      $this->t('Migration ID'),
      $this->t('Group'),
      $this->t('Batch Offset'),
      $this->t('Operations'),
    ];

    $rows = [];

    // Get all migration IDs from the plugin manager (lightweight)
    $migrationManager = \Drupal::service('plugin.manager.migration');
    $migrationDefinitions = $migrationManager->getDefinitions();

    foreach ($migrationDefinitions as $migrationId => $definition) {
      // Get batch offset (this is the main data we need)
      $batchOffset = $this->migrateBatchService->getOffset($migrationId);

      // Get migration group.
      $group = $definition['migration_group'] ?? $this->t('Default');

      // Build operations (always available)
      $operations = [];

      // Run batch link first - check status only when needed.
      $canRunBatch = TRUE;
      if ($batchOffset > 0) {
        // Only check status for migrations that have been started.
        try {
          $migration = $migrationManager->createInstance($migrationId);
          $status = $migration->getStatus();
          // Only allow running if idle.
          $canRunBatch = ($status === 0);
        }
        catch (\Exception $e) {
          $canRunBatch = FALSE;
        }
      }

      if ($canRunBatch) {
        $runUrl = Url::fromRoute('migrate_batch.admin_run', [
          'migrationId' => $migrationId,
        ]);
        $operations[] = [
          'title' => $this->t('Run Batch'),
          'url' => $runUrl,
        ];
      }

      // Reset offset link second.
      $resetUrl = Url::fromRoute('migrate_batch.admin_reset', [
        'migrationId' => $migrationId,
      ]);
      $operations[] = [
        'title' => $this->t('Reset Offset'),
        'url' => $resetUrl,
      ];

      $rows[] = [
        $migrationId,
        $group,
        number_format($batchOffset),
        [
          'data' => [
            '#type' => 'operations',
            '#links' => $operations,
          ],
        ],
      ];
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No migrations found.'),
    ];

    $build['#attached']['library'][] = 'core/drupal.tableheader';

    return $build;
  }

  /**
   * Runs a batch for a migration.
   *
   * @param string $migrationId
   *   The migration ID.
   */
  public function run(string $migrationId) {
    try {
      // Check if migration is idle before running.
      $migrationManager = \Drupal::service('plugin.manager.migration');
      $migration = $migrationManager->createInstance($migrationId);

      // Not idle.
      if ($migration->getStatus() !== 0) {
        $this->messenger()->addError($this->t('Cannot run batch for migration @migration: migration is not idle.', [
          '@migration' => $migrationId,
        ]));
        return $this->redirect('migrate_batch.admin_states');
      }

      // Use default batch size.
      $this->migrateBatchService->next($migrationId);

      $offset = $this->migrateBatchService->getOffset($migrationId);
      $this->messenger()->addMessage($this->t('Batch processed for migration @migration. New offset: @offset.', [
        '@migration' => $migrationId,
        '@offset' => $offset,
      ]));
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Failed to run batch for migration @migration: @error', [
        '@migration' => $migrationId,
        '@error' => $e->getMessage(),
      ]));
    }

    return $this->redirect('migrate_batch.admin_states');
  }

  /**
   * Resets the batch offset for a migration.
   *
   * @param string $migrationId
   *   The migration ID.
   */
  public function reset(string $migrationId) {
    $this->migrateBatchService->resetOffset($migrationId);

    $this->messenger()->addMessage($this->t('Batch offset reset for migration @migration.', [
      '@migration' => $migrationId,
    ]));

    return $this->redirect('migrate_batch.admin_states');
  }

  /**
   * Sets the batch offset for a migration.
   *
   * @param string $migrationId
   *   The migration ID.
   * @param int $offset
   *   The offset to set.
   */
  public function set(string $migrationId, int $offset) {
    $this->migrateBatchService->setOffset($migrationId, $offset);

    $this->messenger()->addMessage($this->t('Batch offset set to @offset for migration @migration.', [
      '@offset' => $offset,
      '@migration' => $migrationId,
    ]));

    return $this->redirect('migrate_batch.admin_states');
  }

  /**
   * Gets a human-readable status label.
   *
   * @param int $status
   *   The migration status code.
   *
   * @return string
   *   The status label.
   */
  protected function getStatusLabel(int $status): string {
    $labels = [
      0 => $this->t('Idle'),
      1 => $this->t('Importing'),
      2 => $this->t('Rolling back'),
      3 => $this->t('Stopping'),
    ];

    return $labels[$status] ?? $this->t('Unknown');
  }

}
