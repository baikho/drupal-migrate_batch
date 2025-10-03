<?php

namespace Drupal\migrate_batch\Drush\Commands;

use Drupal\migrate_batch\Service\MigrateBatchService;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides Drush commands for batch migration processing.
 */
final class MigrateBatchCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * The migrate batch service.
   */
  protected MigrateBatchService $migrateBatchService;

  /**
   * Constructs a new MigrateBatchCommands.
   *
   * @param \Drupal\migrate_batch\Service\MigrateBatchService $migrateBatchService
   *   The migrate batch service.
   */
  public function __construct(
    #[Autowire(service: 'migrate_batch')]
    MigrateBatchService $migrateBatchService,
  ) {
    parent::__construct();
    $this->migrateBatchService = $migrateBatchService;
  }

  /**
   * Process the next batch of items for a migration.
   *
   * Processes the next batch of items from a migration using the current offset.
   * The offset is automatically advanced after processing.
   *
   * @command migrate:batch-next
   *
   * @aliases mbn, migrate-batch-next
   */
  #[CLI\Command(name: 'migrate:batch-next', aliases: ['mbn', 'migrate-batch-next'])]
  #[CLI\Argument(name: 'migrationId', description: 'The migration ID to process')]
  #[CLI\Option(name: 'limit', description: 'Number of items per batch')]
  #[CLI\Option(name: 'offset', description: 'Offset to start processing from')]
  public function batch(string $migrationId, $options = ['limit' => NULL, 'offset' => NULL]): void {
    $limit = $options['limit'] ?? $this->migrateBatchService->getDefaultLimit();
    $offset = $options['offset'];

    $message = 'Processing migration "@migration" with batch size @limit';
    $params = [
      '@migration' => $migrationId,
      '@limit' => $limit,
    ];

    if ($offset !== NULL) {
      $message .= ' starting from offset @offset';
      $params['@offset'] = $offset;
    }

    $this->io()->info(dt($message, $params));

    try {
      $this->migrateBatchService->next($migrationId, $limit, $offset);
      $currentOffset = $this->migrateBatchService->getOffset($migrationId);
      $this->io()->success(dt('Batch processed. Current offset for "@migration": @offset', [
        '@migration' => $migrationId,
        '@offset' => $currentOffset,
      ]));
    }
    catch (\Exception $e) {
      $this->io()->error($e->getMessage());
    }
  }

  /**
   * Checks the batch offset for a migration.
   *
   * @command migrate:batch-offset
   *
   * @aliases mbo, migrate-batch-offset
   */
  #[CLI\Command(name: 'migrate:batch-offset', aliases: ['mbo', 'migrate-batch-offset'])]
  #[CLI\Argument(name: 'migrationId', description: 'The migration ID to check')]
  public function getBatchOffset(string $migrationId): void {
    $offset = $this->migrateBatchService->getOffset($migrationId);
    $this->io()->success(dt('Current batch offset for migration "@migration": @offset', [
      '@migration' => $migrationId,
      '@offset' => $offset,
    ]));
  }

  /**
   * Sets the batch offset for a migration.
   *
   * @command migrate:batch-offset:set
   *
   * @aliases mbos, migrate-batch-offset-set
   */
  #[CLI\Command(name: 'migrate:batch-offset:set', aliases: ['mbos', 'migrate-batch-offset-set'])]
  #[CLI\Argument(name: 'migrationId', description: 'The migration ID')]
  #[CLI\Argument(name: 'offset', description: 'The offset to set')]
  public function setBatchOffset(string $migrationId, int $offset): void {
    $this->migrateBatchService->setOffset($migrationId, $offset);
    $this->io()->success(dt('Set batch offset for migration "@migration" to @offset.', [
      '@migration' => $migrationId,
      '@offset' => $offset,
    ]));
  }

  /**
   * Resets the batch offset for a migration.
   *
   * @command migrate:batch-offset:reset
   *
   * @aliases mbor, migrate-batch-offset-reset
   */
  #[CLI\Command(name: 'migrate:batch-offset:reset', aliases: ['mbor', 'migrate-batch-offset-reset'])]
  #[CLI\Argument(name: 'migrationId', description: 'The migration ID to reset')]
  public function resetBatchOffset(string $migrationId): void {
    $this->migrateBatchService->resetOffset($migrationId);
    $this->io()->success(dt('Reset batch offset for migration "@migration" to 0.', [
      '@migration' => $migrationId,
    ]));
  }

}
