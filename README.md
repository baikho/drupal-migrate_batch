# Migrate Batch

A Drupal module that provides batch migration processing with automatic offset tracking.

## Overview

This module extends Drupal's migration system by providing commands to process migration items in configurable batches with automatic progress tracking. Unlike standard `drush migrate:import --limit`, these commands maintain state between runs and can cycle through all source items continuously.

## Installation

### Standard Drupal Installation

1. Download and install the module from [Drupal.org](https://www.drupal.org/project/migrate_batch)
2. Enable the module: `drush en migrate_batch`

**Requirements:**
- **PHP 8.1+**
- **Drupal 10+** with the `migrate` module enabled
- **Drush 11+** (optional, required only for command-line usage - uses modern attribute-based commands)

## Usage

### Programmatic Usage

You can use the `migrate_batch` service directly in your custom modules, hooks, or other Drupal code:

```php
/** @var \Drupal\migrate_batch\Service\MigrateBatchService $batch */
$batch = \Drupal::service('migrate_batch');

// Process a batch of default item amount (20).
$batch->next('my_migration');

// Process next batch of 50 items.
$batch->next('my_migration', 50);

// Check current offset.
$offset = $batch->getOffset('my_migration');
echo "Current offset: $offset";

// Set offset to a specific value.
$batch->setOffset('my_migration', 100);

// Reset offset back to 0.
$batch->resetOffset('my_migration');
```

**Note:** The service automatically tracks progress using Drupal's State API. Each call to `next()` processes the next batch and advances the offset.

#### Service API Reference

- **`next(string $migrationId, int $batchSize = 50): void`**
  Processes the next batch of items for the specified migration.

- **`getOffset(string $migrationId): int`**
  Returns the current offset for a migration.

- **`setOffset(string $migrationId, int $offset): MigrateBatchService`**
  Sets the offset for a migration to a specific value.

- **`resetOffset(string $migrationId): MigrateBatchService`**
  Resets the offset for a migration back to 0.

- **`getDefaultLimit(): int`**
  Returns the default batch size limit.

#### Usage Examples

**In a custom module:**
```php
/**
 * Implements hook_cron().
 */
#[Hook('cron')]
public function cron(): void {
  /** @var \Drupal\migrate_batch\Service\MigrateBatchService $batch */
  $batch = \Drupal::service('migrate_batch');
  // Process 100 items per cron run.
  $batch->next('my_custom_migration', 100);
}
```

**In a controller or form submit:**
```php
public function processBatch() {
  /** @var \Drupal\migrate_batch\Service\MigrateBatchService $batch */
  $batch = \Drupal::service('migrate_batch');
  $batch->next('user_import', 50);
  \Drupal::messenger()->addMessage('Processed next 50 users.');
}
```

### Admin Interface

The module provides an admin interface at **Administration → Content → Migrate Batch States** (`/admin/content/migrate/batch-states`) where you can:

- **View all migrations** organized by group with their current batch offsets
- **Reset batch offsets** back to 0 for any migration
- **Run individual batches** directly from the UI (50 items per batch)
- **Monitor batch progress** through offset tracking

**Note:** Access to this interface requires the `administer migrate batch states` permission. The admin interface is optimized for performance and only loads migration status when needed, making it fast even with many migrations.

### Drush Commands

**Note:** These commands require Drush 11+ due to the use of modern PHP 8.1+ attributes for command definition. The core functionality works without Drush by calling the service directly.

#### Basic Batch Processing

Process items in batches with automatic offset tracking:

```bash
# Process the next default amount of items, automatically tracking progress
drush migrate:batch-next my_migration

# Run again to process the next 50 items in sequence
drush migrate:batch-next my_migration 50
```

### Manual Offset Control

Override the stored offset when needed by resetting and running multiple batches:

```bash
# Reset offset to 0, then process first 25 items
drush migrate:batch-offset:reset my_migration
drush migrate:batch-next my_migration 25

# Process next 15 items (items 25-40)
drush migrate:batch-next my_migration 25
```

### Offset Management

```bash
# Check current batch offset for a migration
drush migrate:batch-offset my_migration

# Set batch offset to a specific value
drush migrate:batch-offset:set my_migration 100

# Reset batch offset back to 0
drush migrate:batch-offset:reset my_migration
```

## Commands

### `migrate:batch-next` (alias: `mbn`)

Main command for processing the next batch of migration items.

### `migrate:batch-offset` (alias: `mbo`)

Check the current batch offset for a migration.

### `migrate:batch-offset:set` (alias: `mbos`)

Set the batch offset for a migration to a specific value.

### `migrate:batch-offset:reset` (alias: `mbor`)

Reset the batch offset for a migration back to 0.

## How It Works

1. **State Tracking**: Uses Drupal's State API to store the current offset for each migration
2. **Direct API Execution**: Uses `MigrateExecutable` directly (like Drush's `migrate:import`) instead of shell execution for better performance
3. **Source Limiting**: Passes LIMIT/OFFSET directly to the migration source configuration
4. **Automatic Advancement**: After successful processing, automatically increments the offset
5. **Cyclic Behavior**: When reaching the end of available items, wraps back to offset 0

## Technical Details

### State Storage

Progress is stored using Drupal's State API with keys in the format:
`migrate_batch.offset.{migration_id}`

### Offset Calculation

- After successful processing: `new_offset = (current_offset + limit) % total_items`
- When no items found at current offset: Automatically resets to 0

## Integration

This module works with any Drupal migration. For optimal performance with large datasets, source plugins should use the `BatchableSourceTrait` to support batch processing:

### Source Plugin Integration

The `BatchableSourceTrait` provides three key methods for batch processing:

- **`isBatchRequest()`**: Returns TRUE when the migration is running in batch mode
- **`getBatchLimit()`**: Returns the number of items to process in this batch
- **`getBatchOffset()`**: Returns the starting position for this batch

Use these methods in your source plugin's `initializeIterator()` method to apply LIMIT and OFFSET to your data retrieval:

```php
use Drupal\migrate_batch\Traits\BatchableSourceTrait;

class MySourcePlugin extends SomeBaseClass {

  use BatchableSourceTrait;

  /**
   * {@inheritDoc}
   */
  protected function initializeIterator(): DataParserPluginInterface {
    // Apply batch parameters to your data source here
    if ($this->isBatchRequest()) {
      // Modify your data source to use batch limit and offset
      $limit = $this->getBatchLimit();
      $offset = $this->getBatchOffset();

      // Apply to your specific data source (API, database, files, etc.)
      $this->applyBatchParameters($limit, $offset);
    }

    return parent::initializeIterator();
  }

  /**
   * Apply batch parameters to your specific data source.
   */
  protected function applyBatchParameters(?int $limit, int $offset): void {
    // Implementation depends on your data source type
    // Examples:
    // - SQL: Add LIMIT/OFFSET to query
    // - API: Add to request parameters
    // - Files: Slice the file list
  }
}
```

### SQL-Based Sources

For Drupal's `SqlBase` source plugins, override the `query()` method to apply batch parameters:

```php
use Drupal\migrate_batch\Traits\BatchableSourceTrait;

class MySqlSource extends SqlBase {

  use BatchableSourceTrait;

  public function query() {
    $query = parent::query();

    // Apply batch parameters using Drupal's query range method
    // range($offset, $limit) automatically handles LIMIT and OFFSET
    if ($this->isBatchRequest()) {
      if ($limit = $this->getBatchLimit()) {
        $query->range($this->getBatchOffset(), $limit);
      }
    }

    return $query;
  }
}
```

For other source types, apply batch parameters in the appropriate method where your data source is initialized or queried.

### Why Use the Trait?

**Performance Benefits:**
- Large datasets are processed in manageable chunks
- Reduces memory usage and execution time
- Allows resuming interrupted migrations

**Flexibility:**
- Batch parameters are applied at runtime, not stored in config
- Works with any data source (SQL, APIs, files, etc.)
- Compatible with existing migration configurations

**Integration:**
- The trait works seamlessly with the `MigrateBatchService`
- No changes needed to migration YAML files
- Maintains full compatibility with standard Drupal migrations

## Permissions

The module defines the following permission:

- **`administer migrate batch`** - Access the migrate batch states admin interface and manage batch processing operations. This permission is restricted and should only be granted to trusted administrators.

## Compatibility

- **Drupal**: >=10.x
- **PHP**: 8.1+
- **Drush**: 11+ (optional, enhances with CLI commands)
- **Migration Framework**: Any Drupal migration using the core migrate API

## Maintainers

* Sang Lostrie (baikho) - https://www.drupal.org/u/baikho
