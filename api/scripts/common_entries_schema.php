<?php
/**
 * Install the unified current-state tables used by public location lookups.
 * Compatible with PHP 7.4 and MySQL 5.7.
 *
 * Usage:
 *   php api/scripts/common_entries_schema.php
 *   php api/scripts/common_entries_schema.php --apply
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../core/db.php';

const COMMON_SCHEMA_VERSION = '20260717_common_entries_v4_admin_maintenance';

$apply = in_array('--apply', $argv, true);

function commonSchemaTableExists(PDO $dbh, $tableName) {
    $stmt = $dbh->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $stmt->execute([$tableName]);
    return (int)$stmt->fetchColumn() > 0;
}

function commonSchemaColumnExists(PDO $dbh, $tableName, $columnName) {
    $stmt = $dbh->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$tableName, $columnName]);
    return (int)$stmt->fetchColumn() > 0;
}

function commonSchemaConstraintExists(PDO $dbh, $constraintName) {
    $stmt = $dbh->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ?"
    );
    $stmt->execute([$constraintName]);
    return (int)$stmt->fetchColumn() > 0;
}

function commonSchemaIndexExists(PDO $dbh, $tableName, $indexName) {
    $stmt = $dbh->prepare(
        "SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?"
    );
    $stmt->execute([$tableName, $indexName]);
    return (int)$stmt->fetchColumn() > 0;
}

function commonSchemaColumnType(PDO $dbh, $tableName, $columnName) {
    $stmt = $dbh->prepare(
        "SELECT `COLUMN_TYPE` FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$tableName, $columnName]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : strtolower((string)$value);
}

function commonSchemaRun(PDO $dbh, $sql, $apply) {
    echo $sql . ";\n";
    if ($apply) {
        $dbh->exec($sql);
    }
}

echo 'Mode: ' . ($apply ? 'apply' : 'dry-run') . PHP_EOL;
echo 'Database: ' . $dbh->query('SELECT DATABASE()')->fetchColumn() . PHP_EOL;
echo 'Server: ' . $dbh->getAttribute(PDO::ATTR_SERVER_VERSION) . PHP_EOL;

$changes = 0;

if (!commonSchemaTableExists($dbh, 'schema_migrations')) {
    commonSchemaRun($dbh, "CREATE TABLE `schema_migrations` (
      `version` VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
      `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`version`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin ROW_FORMAT=DYNAMIC", $apply);
    $changes++;
}

if (!commonSchemaTableExists($dbh, 'common_releases')) {
    commonSchemaRun($dbh, "CREATE TABLE `common_releases` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `area_id` INT NOT NULL,
      `release_no` INT UNSIGNED NOT NULL,
      `parent_release_id` BIGINT UNSIGNED NULL,
      `source_type` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
      `source_ref` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
      `insert_count` INT UNSIGNED NOT NULL DEFAULT 0,
      `update_count` INT UNSIGNED NOT NULL DEFAULT 0,
      `delete_count` INT UNSIGNED NOT NULL DEFAULT 0,
      `entry_count` INT UNSIGNED NOT NULL,
      `character_count` INT UNSIGNED NOT NULL,
      `content_hash` BINARY(32) NOT NULL,
      `message` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '',
      `published_by` INT UNSIGNED NULL,
      `published_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_cr_area_release_no` (`area_id`, `release_no`),
      UNIQUE KEY `uq_cr_id_area` (`id`, `area_id`),
      KEY `idx_cr_parent_area` (`parent_release_id`, `area_id`),
      KEY `idx_cr_published_by` (`published_by`),
      CONSTRAINT `fk_cr_area` FOREIGN KEY (`area_id`) REFERENCES `i_area_list` (`id`)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
      CONSTRAINT `fk_cr_parent_area` FOREIGN KEY (`parent_release_id`, `area_id`)
        REFERENCES `common_releases` (`id`, `area_id`)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
      CONSTRAINT `fk_cr_published_by` FOREIGN KEY (`published_by`) REFERENCES `users` (`id`)
        ON UPDATE RESTRICT ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin ROW_FORMAT=DYNAMIC", $apply);
    $changes++;
}

if (!commonSchemaTableExists($dbh, 'common_entries')) {
    commonSchemaRun($dbh, "CREATE TABLE `common_entries` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `area_id` INT NOT NULL,
      `display_order` BIGINT UNSIGNED NOT NULL,
      `chara` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
      `initial` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
      `nuclei` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
      `coda` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
      `tone` VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
      `ipa` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
      `note` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
      `alt_group` SMALLINT UNSIGNED NULL,
      `row_revision` INT UNSIGNED NOT NULL DEFAULT 1,
      `row_hash` BINARY(32) NOT NULL,
      `created_release_id` BIGINT UNSIGNED NOT NULL,
      `last_changed_release_id` BIGINT UNSIGNED NOT NULL,
      `source_import_id` BIGINT UNSIGNED NULL,
      `source_sheet` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
      `source_row` INT UNSIGNED NULL,
      `legacy_table` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
      `legacy_row_id` INT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_ce_legacy_source` (`area_id`, `legacy_table`, `legacy_row_id`),
      KEY `idx_ce_chara` (`chara`, `area_id`, `display_order`, `id`),
      KEY `idx_ce_pron` (`initial`, `nuclei`, `coda`, `tone`, `area_id`, `display_order`, `id`),
      KEY `idx_ce_nuclei` (`nuclei`, `coda`, `tone`, `area_id`, `display_order`, `id`),
      KEY `idx_ce_created_release` (`created_release_id`, `area_id`),
      KEY `idx_ce_changed_release` (`last_changed_release_id`, `area_id`),
      CONSTRAINT `fk_ce_area` FOREIGN KEY (`area_id`) REFERENCES `i_area_list` (`id`)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
      CONSTRAINT `fk_ce_created_release` FOREIGN KEY (`created_release_id`, `area_id`)
        REFERENCES `common_releases` (`id`, `area_id`)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
      CONSTRAINT `fk_ce_changed_release` FOREIGN KEY (`last_changed_release_id`, `area_id`)
        REFERENCES `common_releases` (`id`, `area_id`)
        ON UPDATE RESTRICT ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin ROW_FORMAT=DYNAMIC", $apply);
    $changes++;
}

if (!commonSchemaTableExists($dbh, 'common_sync_queue')) {
    commonSchemaRun($dbh, "CREATE TABLE `common_sync_queue` (
      `area_id` INT NOT NULL,
      `legacy_table` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
      `source_ref` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
      `requested_generation` BIGINT UNSIGNED NOT NULL DEFAULT 1,
      `processed_generation` BIGINT UNSIGNED NOT NULL DEFAULT 0,
      `status` ENUM('pending','processing','done','failed') CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending',
      `attempt_count` INT UNSIGNED NOT NULL DEFAULT 0,
      `requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `started_at` DATETIME NULL,
      `completed_at` DATETIME NULL,
      `last_error` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
      PRIMARY KEY (`area_id`),
      UNIQUE KEY `uq_csq_legacy_table` (`legacy_table`),
      KEY `idx_csq_pending` (`status`, `requested_at`),
      CONSTRAINT `fk_csq_area` FOREIGN KEY (`area_id`) REFERENCES `i_area_list` (`id`)
        ON UPDATE RESTRICT ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin ROW_FORMAT=DYNAMIC", $apply);
    $changes++;
}

if (!commonSchemaTableExists($dbh, 'admin_maintenance_events')) {
    commonSchemaRun($dbh, "CREATE TABLE `admin_maintenance_events` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `user_id` INT UNSIGNED NULL,
      `request_id` CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
      `action` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
      `area_id` INT NULL,
      `sheetname` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
      `status` ENUM('success','failed') CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
      `before_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
      `after_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
      `error_message` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_ame_created` (`created_at`),
      KEY `idx_ame_area_created` (`area_id`, `created_at`),
      KEY `idx_ame_user_created` (`user_id`, `created_at`),
      CONSTRAINT `fk_ame_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON UPDATE RESTRICT ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin ROW_FORMAT=DYNAMIC", $apply);
    $changes++;
}

if (!commonSchemaTableExists($dbh, 'maintenance_worker_state')) {
    commonSchemaRun($dbh, "CREATE TABLE `maintenance_worker_state` (
      `worker_name` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
      `last_seen_at` DATETIME NULL,
      `last_started_at` DATETIME NULL,
      `last_finished_at` DATETIME NULL,
      `last_status` ENUM('idle','running','success','failed') CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'idle',
      `last_processed` INT UNSIGNED NOT NULL DEFAULT 0,
      `last_failures` INT UNSIGNED NOT NULL DEFAULT 0,
      `last_error` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
      PRIMARY KEY (`worker_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin ROW_FORMAT=DYNAMIC", $apply);
    $changes++;
}

if (!commonSchemaColumnExists($dbh, 'i_area_list', 'current_release_id')) {
    commonSchemaRun(
        $dbh,
        "ALTER TABLE `i_area_list` ADD COLUMN `current_release_id` BIGINT UNSIGNED NULL AFTER `sheetname`, ADD KEY `idx_ial_current_release_area` (`current_release_id`, `id`)",
        $apply
    );
    $changes++;
}

if (!commonSchemaColumnExists($dbh, 'i_area_list', 'is_visible')) {
    commonSchemaRun(
        $dbh,
        "ALTER TABLE `i_area_list` ADD COLUMN `is_visible` TINYINT(1) NOT NULL DEFAULT 1 AFTER `color`",
        $apply
    );
    $changes++;
}

if (!commonSchemaColumnExists($dbh, 'i_area_list', 'sort_order')) {
    commonSchemaRun(
        $dbh,
        "ALTER TABLE `i_area_list` ADD COLUMN `sort_order` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `is_visible`",
        $apply
    );
    $changes++;
    if ($apply) {
        $dbh->exec("UPDATE `i_area_list` SET `sort_order` = `id` * 10 WHERE `sort_order` = 0");
    }
}

if (!commonSchemaColumnExists($dbh, 'i_area_list', 'archived_at')) {
    commonSchemaRun(
        $dbh,
        "ALTER TABLE `i_area_list` ADD COLUMN `archived_at` DATETIME NULL AFTER `sort_order`",
        $apply
    );
    $changes++;
}

if (!commonSchemaColumnExists($dbh, 'i_area_list', 'archived_by')) {
    commonSchemaRun(
        $dbh,
        "ALTER TABLE `i_area_list` ADD COLUMN `archived_by` INT UNSIGNED NULL AFTER `archived_at`",
        $apply
    );
    $changes++;
}

if (commonSchemaColumnType($dbh, 'i_area_list', 'sheetname') !== 'varchar(64)') {
    commonSchemaRun(
        $dbh,
        "ALTER TABLE `i_area_list` MODIFY COLUMN `sheetname` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL",
        $apply
    );
    $changes++;
}

if (!commonSchemaIndexExists($dbh, 'i_area_list', 'uq_ial_sheetname')) {
    commonSchemaRun(
        $dbh,
        "ALTER TABLE `i_area_list` ADD UNIQUE KEY `uq_ial_sheetname` (`sheetname`)",
        $apply
    );
    $changes++;
}

if (!commonSchemaIndexExists($dbh, 'i_area_list', 'idx_ial_visible_order')) {
    commonSchemaRun(
        $dbh,
        "ALTER TABLE `i_area_list` ADD KEY `idx_ial_visible_order` (`is_visible`, `sort_order`, `id`)",
        $apply
    );
    $changes++;
}

if (!commonSchemaIndexExists($dbh, 'i_area_list', 'idx_ial_archive_order')) {
    commonSchemaRun(
        $dbh,
        "ALTER TABLE `i_area_list` ADD KEY `idx_ial_archive_order` (`archived_at`, `sort_order`, `id`)",
        $apply
    );
    $changes++;
}

if (!commonSchemaConstraintExists($dbh, 'fk_ial_archived_by')) {
    commonSchemaRun(
        $dbh,
        "ALTER TABLE `i_area_list` ADD CONSTRAINT `fk_ial_archived_by` FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`) ON UPDATE RESTRICT ON DELETE SET NULL",
        $apply
    );
    $changes++;
}

if (!commonSchemaConstraintExists($dbh, 'fk_ial_current_release_area')) {
    commonSchemaRun(
        $dbh,
        "ALTER TABLE `i_area_list` ADD CONSTRAINT `fk_ial_current_release_area` FOREIGN KEY (`current_release_id`, `id`) REFERENCES `common_releases` (`id`, `area_id`) ON UPDATE RESTRICT ON DELETE RESTRICT",
        $apply
    );
    $changes++;
}

if ($apply) {
    $stmt = $dbh->prepare(
        "INSERT INTO `schema_migrations` (`version`) VALUES (?)
         ON DUPLICATE KEY UPDATE `version` = VALUES(`version`)"
    );
    $stmt->execute([COMMON_SCHEMA_VERSION]);
}

echo 'Statements: ' . $changes . PHP_EOL;
echo $apply ? "Schema migration completed.\n" : "No changes applied.\n";
