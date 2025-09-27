<?php

namespace Drupal\migrate_batch\Traits;

/**
 * Provides runtime batch parameters for migrate source plugins.
 *
 * This trait enables migrate source plugins to support batch processing by
 * accepting and using batch parameters (offset, limit, and request flag) that
 * are passed dynamically during migration execution. It allows the migration
 * service to control pagination and batching behavior at runtime without
 * requiring these parameters to be stored in the migration configuration.
 *
 * Source plugins using this trait should check isBatchRequest() to determine
 * if batch processing is active, and if so, apply getBatchOffset() and
 * getBatchLimit() to their data retrieval queries.
 *
 * Example usage in a source plugin:
 * @code
 * if ($this->isBatchRequest()) {
 *   $query->range($this->getBatchOffset(), $this->getBatchLimit());
 * }
 * @endcode
 *
 * @see \Drupal\migrate_batch\Service\MigrateBatchService
 */
trait BatchableSourceTrait {

  /**
   * Determines if the current migration execution is a batch request.
   *
   * This flag indicates whether the migration is being run in batch mode
   * through the MigrateBatchService. When TRUE, the source plugin should
   * apply the batch offset and limit to its data retrieval. When FALSE,
   * the migration is running normally and should process all available items.
   *
   * @return bool
   *   TRUE if this is a batch request, FALSE for normal migration execution.
   */
  public function isBatchRequest(): bool {
    return $this->configuration['batch_request'] ?? FALSE;
  }

  /**
   * Gets the batch size limit for the current migration batch.
   *
   * This method returns the number of items to process in this batch.
   * If no limit is specified, returns NULL (indicating no limit should be applied).
   * Use this value to limit the number of items retrieved from the source.
   *
   * @return int|null
   *   The batch limit, or NULL if no limit is set.
   */
  public function getBatchLimit(): ?int {
    return $this->configuration['batch_limit'] ?? NULL;
  }

  /**
   * Gets the starting offset for the current migration batch.
   *
   * This method returns the zero-based index from which to start processing
   * items in this batch. Defaults to 0 if no offset is specified.
   * Use this value to skip items that have already been processed.
   *
   * @return int
   *   The batch offset (starting position).
   */
  public function getBatchOffset(): int {
    return $this->configuration['batch_offset'] ?? 0;
  }

}
