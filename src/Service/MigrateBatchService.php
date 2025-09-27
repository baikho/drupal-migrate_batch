<?php

namespace Drupal\migrate_batch\Service;

use Drupal\Core\State\StateInterface;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Plugin\MigrationPluginManager;

/**
 * Service for batch migration processing with offset tracking.
 */
class MigrateBatchService {

  /**
   * The migration plugin manager.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManager
   */
  protected MigrationPluginManager $migrationManager;

  /**
   * The state object.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * Cache for total row counts per migration.
   *
   * @var array
   */
  protected array $totalRowsCache = [];

  /**
   * The default batch size to use if none provided.
   */
  protected int $defaultLimit = 20;

  /**
   * Constructs a checkpoints object.
   *
   * @param \Drupal\migrate\Plugin\MigrationPluginManager $migration_manager
   *   The migration manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(MigrationPluginManager $migration_manager, StateInterface $state) {
    $this->migrationManager = $migration_manager;
    $this->state = $state;
  }

  /**
   * Process the next batch of items from a migration.
   *
   * @param string $migrationId
   *   The migration ID.
   * @param int|null $limit
   *   The batch limit.
   */
  public function next(string $migrationId, ?int $limit = NULL): void {
    // Load current offset from state.
    $offset = $this->getOffset($migrationId);
    $limit = $limit ?? $this->defaultLimit;

    // Instantiate a sliced migration with source config directly.
    (new MigrateExecutable($this->migrationManager->createInstance($migrationId, [
      'source' => [
        'batch_offset' => $offset,
        'batch_limit' => $limit,
        'batch_request' => TRUE,
      ],
    ])))->import();

    // Now fetch the total count from the migration.
    $newOffset = $offset + $limit;
    $total = $this->getTotalRows($migrationId);
    if ($newOffset >= $total) {
      // Reset to zero.
      $this->state->set($this->getStateKey($migrationId), 0);
    }
    else {
      // Persist the updated offset.
      $this->state->set($this->getStateKey($migrationId), $newOffset);
    }
  }

  /**
   * Returns a unique state key for a migration.
   */
  protected function getStateKey(string $migrationId): string {
    return "migrate_batch.offset.$migrationId";
  }

  /**
   * Get the total count by migration ID.
   */
  public function getTotalRows(string $migrationId): int {
    // Cache the total count since it doesn't change often.
    if (!isset($this->totalRowsCache[$migrationId])) {
      $migration = $this->migrationManager->createInstance($migrationId);
      $this->totalRowsCache[$migrationId] = $migration->getSourcePlugin()->count();
    }
    return $this->totalRowsCache[$migrationId];
  }

  /**
   * Get default batch limit.
   */
  public function getDefaultLimit(): int {
    return $this->defaultLimit;
  }

  /**
   * Get current offset by migration ID.
   */
  public function getOffset(string $migrationId): int {
    return (int) $this->state->get($this->getStateKey($migrationId), 0);
  }

  /**
   * Set offset for a migration by ID.
   */
  public function setOffset(string $migrationId, int $offset): MigrateBatchService {
    $this->state->set($this->getStateKey($migrationId), $offset);
    return $this;
  }

  /**
   * Reset offset for a migration by ID.
   */
  public function resetOffset(string $migrationId): MigrateBatchService {
    $this->state->set($this->getStateKey($migrationId), 0);
    return $this;
  }

}
