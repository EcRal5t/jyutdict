<?php
/**
 * Install browser-side common-sheet import and generated phonology storage.
 * Compatible with PHP 7.4 and MySQL 5.7.
 *
 * Usage:
 *   php api/scripts/common_import_schema.php
 *   php api/scripts/common_import_schema.php --apply
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../core/db.php';

const COMMON_IMPORT_SCHEMA_VERSION = '20260724_browser_common_import_v1';

$apply = in_array('--apply', $argv, true);

function cisTableExists(PDO $dbh, $table) {
    $stmt = $dbh->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function cisColumnExists(PDO $dbh, $table, $column) {
    $stmt = $dbh->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function cisIndexExists(PDO $dbh, $table, $index) {
    $stmt = $dbh->prepare(
        "SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?"
    );
    $stmt->execute([$table, $index]);
    return (int)$stmt->fetchColumn() > 0;
}

function cisConstraintExists(PDO $dbh, $constraint) {
    $stmt = $dbh->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ?"
    );
    $stmt->execute([$constraint]);
    return (int)$stmt->fetchColumn() > 0;
}

function cisRun(PDO $dbh, $sql, $apply) {
    echo $sql . ";\n";
    if ($apply) {
        $dbh->exec($sql);
    }
}

function cisAddColumn(PDO $dbh, $table, $column, $definition, $apply, &$changes) {
    if (!cisColumnExists($dbh, $table, $column)) {
        cisRun($dbh, "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}", $apply);
        $changes++;
    }
}

echo 'Mode: ' . ($apply ? 'apply' : 'dry-run') . PHP_EOL;
echo 'Database: ' . $dbh->query('SELECT DATABASE()')->fetchColumn() . PHP_EOL;
echo 'Server: ' . $dbh->getAttribute(PDO::ATTR_SERVER_VERSION) . PHP_EOL;

if (!cisTableExists($dbh, 'common_releases') || !cisTableExists($dbh, 'common_entries')) {
    throw new RuntimeException('Run api/scripts/common_entries_schema.php --apply first');
}

$changes = 0;

if (!cisTableExists($dbh, 'common_rule_bundles')) {
    cisRun($dbh, "CREATE TABLE `common_rule_bundles` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `version` VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
      `payload_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
      `payload_hash` BINARY(32) NOT NULL,
      `is_active` TINYINT(1) NOT NULL DEFAULT 0,
      `created_by` INT UNSIGNED NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_crb_version` (`version`),
      KEY `idx_crb_active_created` (`is_active`, `created_at`),
      CONSTRAINT `fk_crb_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON UPDATE RESTRICT ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin ROW_FORMAT=DYNAMIC", $apply);
    $changes++;
}

if (!cisTableExists($dbh, 'common_import_jobs')) {
    cisRun($dbh, "CREATE TABLE `common_import_jobs` (
      `id` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
      `created_by` INT UNSIGNED NOT NULL,
      `area_id` INT NULL,
      `rule_bundle_id` BIGINT UNSIGNED NOT NULL,
      `status` ENUM('receiving','ready','publishing','published','failed','aborted')
        CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'receiving',
      `source_filename` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
      `source_date` DATE NOT NULL,
      `source_sheet` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
      `converter_version` VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
      `rule_profile` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
      `config_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
      `new_area_json` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
      `stable_metadata_json` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
      `expected_chunk_count` INT UNSIGNED NOT NULL,
      `expected_row_count` INT UNSIGNED NOT NULL,
      `character_count` INT UNSIGNED NOT NULL,
      `syllable_count` INT UNSIGNED NOT NULL,
      `toneless_syllable_count` INT UNSIGNED NOT NULL,
      `skipped_row_count` INT UNSIGNED NOT NULL,
      `content_hash` BINARY(32) NOT NULL,
      `received_chunk_count` INT UNSIGNED NOT NULL DEFAULT 0,
      `received_row_count` INT UNSIGNED NOT NULL DEFAULT 0,
      `published_release_id` BIGINT UNSIGNED NULL,
      `last_error` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      `published_at` DATETIME NULL,
      PRIMARY KEY (`id`),
      KEY `idx_cij_owner_created` (`created_by`, `created_at`),
      KEY `idx_cij_status_updated` (`status`, `updated_at`),
      KEY `idx_cij_area_created` (`area_id`, `created_at`),
      KEY `idx_cij_rule_bundle` (`rule_bundle_id`),
      CONSTRAINT `fk_cij_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
      CONSTRAINT `fk_cij_area` FOREIGN KEY (`area_id`) REFERENCES `i_area_list` (`id`)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
      CONSTRAINT `fk_cij_rule_bundle` FOREIGN KEY (`rule_bundle_id`) REFERENCES `common_rule_bundles` (`id`)
        ON UPDATE RESTRICT ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin ROW_FORMAT=DYNAMIC", $apply);
    $changes++;
}

if (!cisTableExists($dbh, 'common_import_chunks')) {
    cisRun($dbh, "CREATE TABLE `common_import_chunks` (
      `job_id` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
      `chunk_no` INT UNSIGNED NOT NULL,
      `row_count` INT UNSIGNED NOT NULL,
      `payload_hash` BINARY(32) NOT NULL,
      `payload_gzip` MEDIUMBLOB NOT NULL,
      `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`job_id`, `chunk_no`),
      CONSTRAINT `fk_cic_job` FOREIGN KEY (`job_id`) REFERENCES `common_import_jobs` (`id`)
        ON UPDATE RESTRICT ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin ROW_FORMAT=DYNAMIC", $apply);
    $changes++;
}

if (!cisTableExists($dbh, 'common_import_rows')) {
    cisRun($dbh, "CREATE TABLE `common_import_rows` (
      `job_id` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
      `row_no` INT UNSIGNED NOT NULL,
      `display_order` BIGINT UNSIGNED NOT NULL,
      `chara` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
      `initial` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
      `nuclei` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
      `coda` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
      `tone` VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
      `ipa` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
      `note` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
      `alt_group` SMALLINT UNSIGNED NULL,
      `source_row` INT UNSIGNED NULL,
      `row_hash` BINARY(32) NOT NULL,
      PRIMARY KEY (`job_id`, `row_no`),
      KEY `idx_cir_job_display` (`job_id`, `display_order`, `row_no`),
      KEY `idx_cir_job_chara` (`job_id`, `chara`),
      CONSTRAINT `fk_cir_job` FOREIGN KEY (`job_id`) REFERENCES `common_import_jobs` (`id`)
        ON UPDATE RESTRICT ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin ROW_FORMAT=DYNAMIC", $apply);
    $changes++;
}

if (!cisTableExists($dbh, 'common_release_snapshots')) {
    cisRun($dbh, "CREATE TABLE `common_release_snapshots` (
      `release_id` BIGINT UNSIGNED NOT NULL,
      `area_id` INT NOT NULL,
      `schema_version` SMALLINT UNSIGNED NOT NULL,
      `entry_count` INT UNSIGNED NOT NULL,
      `payload_hash` BINARY(32) NOT NULL,
      `payload_gzip` MEDIUMBLOB NOT NULL,
      `compressed_bytes` INT UNSIGNED NOT NULL,
      `uncompressed_bytes` INT UNSIGNED NOT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`release_id`),
      KEY `idx_crs_area_release` (`area_id`, `release_id`),
      CONSTRAINT `fk_crs_release_area` FOREIGN KEY (`release_id`, `area_id`)
        REFERENCES `common_releases` (`id`, `area_id`)
        ON UPDATE RESTRICT ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin ROW_FORMAT=DYNAMIC", $apply);
    $changes++;
}

if (!cisTableExists($dbh, 'phonology_reports')) {
    cisRun($dbh, "CREATE TABLE `phonology_reports` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `area_id` INT NOT NULL,
      `source_release_id` BIGINT UNSIGNED NOT NULL,
      `revision_no` INT UNSIGNED NOT NULL,
      `schema_version` SMALLINT UNSIGNED NOT NULL,
      `generator_version` VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
      `middle_chinese_version` VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
      `payload_hash` BINARY(32) NOT NULL,
      `payload_gzip` MEDIUMBLOB NOT NULL,
      `compressed_bytes` INT UNSIGNED NOT NULL,
      `uncompressed_bytes` INT UNSIGNED NOT NULL,
      `created_by` INT UNSIGNED NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_pr_area_revision` (`area_id`, `revision_no`),
      UNIQUE KEY `uq_pr_id_area` (`id`, `area_id`),
      KEY `idx_pr_release_area` (`source_release_id`, `area_id`),
      KEY `idx_pr_created_by` (`created_by`),
      CONSTRAINT `fk_pr_area` FOREIGN KEY (`area_id`) REFERENCES `i_area_list` (`id`)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
      CONSTRAINT `fk_pr_release_area` FOREIGN KEY (`source_release_id`, `area_id`)
        REFERENCES `common_releases` (`id`, `area_id`)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
      CONSTRAINT `fk_pr_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON UPDATE RESTRICT ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin ROW_FORMAT=DYNAMIC", $apply);
    $changes++;
}

cisAddColumn($dbh, 'i_area_list', 'sheet_author',
    "TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL AFTER `detailed_name`",
    $apply, $changes);
cisAddColumn($dbh, 'i_area_list', 'current_phonology_id',
    "BIGINT UNSIGNED NULL AFTER `current_release_id`",
    $apply, $changes);

cisAddColumn($dbh, 'common_releases', 'source_filename',
    "VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL AFTER `source_ref`",
    $apply, $changes);
cisAddColumn($dbh, 'common_releases', 'source_date',
    "DATE NULL AFTER `source_filename`",
    $apply, $changes);
cisAddColumn($dbh, 'common_releases', 'source_sheet',
    "VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL AFTER `source_date`",
    $apply, $changes);
cisAddColumn($dbh, 'common_releases', 'syllable_count',
    "INT UNSIGNED NULL AFTER `character_count`",
    $apply, $changes);
cisAddColumn($dbh, 'common_releases', 'toneless_syllable_count',
    "INT UNSIGNED NULL AFTER `syllable_count`",
    $apply, $changes);
cisAddColumn($dbh, 'common_releases', 'skipped_row_count',
    "INT UNSIGNED NULL AFTER `toneless_syllable_count`",
    $apply, $changes);
cisAddColumn($dbh, 'common_releases', 'import_job_id',
    "CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER `skipped_row_count`",
    $apply, $changes);
cisAddColumn($dbh, 'common_releases', 'rule_bundle_id',
    "BIGINT UNSIGNED NULL AFTER `import_job_id`",
    $apply, $changes);
cisAddColumn($dbh, 'common_releases', 'rule_profile',
    "VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL AFTER `rule_bundle_id`",
    $apply, $changes);
cisAddColumn($dbh, 'common_releases', 'converter_version',
    "VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER `rule_profile`",
    $apply, $changes);

if (!cisIndexExists($dbh, 'i_area_list', 'idx_ial_current_phonology_area')) {
    cisRun($dbh,
        "ALTER TABLE `i_area_list` ADD KEY `idx_ial_current_phonology_area` (`current_phonology_id`, `id`)",
        $apply);
    $changes++;
}
if (!cisConstraintExists($dbh, 'fk_ial_current_phonology_area')) {
    cisRun($dbh,
        "ALTER TABLE `i_area_list` ADD CONSTRAINT `fk_ial_current_phonology_area`
         FOREIGN KEY (`current_phonology_id`, `id`) REFERENCES `phonology_reports` (`id`, `area_id`)
         ON UPDATE RESTRICT ON DELETE RESTRICT",
        $apply);
    $changes++;
}
if (!cisIndexExists($dbh, 'common_releases', 'idx_cr_import_job')) {
    cisRun($dbh, "ALTER TABLE `common_releases` ADD KEY `idx_cr_import_job` (`import_job_id`)", $apply);
    $changes++;
}
if (!cisIndexExists($dbh, 'common_releases', 'idx_cr_rule_bundle')) {
    cisRun($dbh, "ALTER TABLE `common_releases` ADD KEY `idx_cr_rule_bundle` (`rule_bundle_id`)", $apply);
    $changes++;
}
if (!cisConstraintExists($dbh, 'fk_cr_import_job')) {
    cisRun($dbh,
        "ALTER TABLE `common_releases` ADD CONSTRAINT `fk_cr_import_job`
         FOREIGN KEY (`import_job_id`) REFERENCES `common_import_jobs` (`id`)
         ON UPDATE RESTRICT ON DELETE SET NULL",
        $apply);
    $changes++;
}
if (!cisConstraintExists($dbh, 'fk_cr_rule_bundle')) {
    cisRun($dbh,
        "ALTER TABLE `common_releases` ADD CONSTRAINT `fk_cr_rule_bundle`
         FOREIGN KEY (`rule_bundle_id`) REFERENCES `common_rule_bundles` (`id`)
         ON UPDATE RESTRICT ON DELETE SET NULL",
        $apply);
    $changes++;
}
if (!cisConstraintExists($dbh, 'fk_cij_published_release')) {
    cisRun($dbh,
        "ALTER TABLE `common_import_jobs` ADD CONSTRAINT `fk_cij_published_release`
         FOREIGN KEY (`published_release_id`) REFERENCES `common_releases` (`id`)
         ON UPDATE RESTRICT ON DELETE SET NULL",
        $apply);
    $changes++;
}

if ($apply) {
    $fixture = __DIR__ . '/../data/common_rule_bundle_v1.json';
    if (!is_file($fixture)) {
        throw new RuntimeException('Missing api/data/common_rule_bundle_v1.json');
    }
    $payload = file_get_contents($fixture);
    $decoded = json_decode($payload, true);
    if (!is_array($decoded) || empty($decoded['bundleVersion'])) {
        throw new RuntimeException('Invalid common rule bundle fixture');
    }
    $version = (string)$decoded['bundleVersion'];
    $hash = hash('sha256', $payload, true);
    $dbh->beginTransaction();
    try {
        $activeBundleCount = (int)$dbh->query(
            "SELECT COUNT(*) FROM `common_rule_bundles` WHERE `is_active` = 1"
        )->fetchColumn();
        if ($activeBundleCount === 0) {
            $existingStmt = $dbh->prepare(
                "SELECT `payload_hash` FROM `common_rule_bundles` WHERE `version` = ?"
            );
            $existingStmt->execute([$version]);
            $existingHash = $existingStmt->fetchColumn();
            if ($existingHash !== false && !hash_equals($existingHash, $hash)) {
                throw new RuntimeException('Seed rule bundle version already exists with different content');
            }
            $stmt = $dbh->prepare(
                "INSERT INTO `common_rule_bundles`
                 (`version`, `payload_json`, `payload_hash`, `is_active`, `created_by`)
                 VALUES (?, ?, ?, 1, NULL)
                 ON DUPLICATE KEY UPDATE `is_active` = 1"
            );
            $stmt->execute([$version, $payload, $hash]);
        }

        $migrationStmt = $dbh->prepare(
            "SELECT COUNT(*) FROM `schema_migrations` WHERE `version` = ?"
        );
        $migrationStmt->execute([COMMON_IMPORT_SCHEMA_VERSION]);
        if ((int)$migrationStmt->fetchColumn() === 0) {
            // One-time split of the legacy preformatted sheet_info. Generated
            // statistics belong to the release; stable attribution belongs to
            // i_area_list. The legacy text remains available as a fallback.
            $legacyRows = $dbh->query(
                "SELECT `id`, `detailed_name`, `sheet_author`, `sheet_info`, `current_release_id`
                 FROM `i_area_list` WHERE COALESCE(`sheet_info`, '') <> ''"
            )->fetchAll(PDO::FETCH_ASSOC);
            $areaUpdate = $dbh->prepare(
                "UPDATE `i_area_list`
                 SET `detailed_name` = COALESCE(NULLIF(`detailed_name`, ''), ?),
                     `sheet_author` = COALESCE(NULLIF(`sheet_author`, ''), ?)
                 WHERE `id` = ?"
            );
            $releaseUpdate = $dbh->prepare(
                "UPDATE `common_releases`
                 SET `source_date` = COALESCE(`source_date`, ?),
                     `character_count` = COALESCE(?, `character_count`),
                     `syllable_count` = COALESCE(`syllable_count`, ?),
                     `toneless_syllable_count` = COALESCE(`toneless_syllable_count`, ?),
                     `skipped_row_count` = COALESCE(`skipped_row_count`, ?)
                 WHERE `id` = ?"
            );
            foreach ($legacyRows as $legacy) {
                $parsed = [
                    'detailed_name' => null, 'sheet_author' => null, 'source_date' => null,
                    'character_count' => null, 'syllable_count' => null,
                    'toneless_syllable_count' => null, 'skipped_row_count' => null,
                ];
                foreach (preg_split('/\R/u', (string)$legacy['sheet_info']) as $line) {
                    if (preg_match('/^地點[：:]\s*(.+)$/u', trim($line), $match)) {
                        $parsed['detailed_name'] = trim($match[1]);
                    } elseif (preg_match('/^(?:負責人|作者)[：:]\s*(.+)$/u', trim($line), $match)) {
                        $parsed['sheet_author'] = trim($match[1]);
                    } elseif (preg_match('/^版本[：:]\s*(\d{4}-\d{2}-\d{2})$/u', trim($line), $match)) {
                        $parsed['source_date'] = $match[1];
                    } elseif (preg_match('/^字數[：:]\s*(\d+)$/u', trim($line), $match)) {
                        $parsed['character_count'] = (int)$match[1];
                    } elseif (preg_match('/^音節數[：:]\s*(\d+)$/u', trim($line), $match)) {
                        $parsed['syllable_count'] = (int)$match[1];
                    } elseif (preg_match('/^不帶調音節數[：:]\s*(\d+)$/u', trim($line), $match)) {
                        $parsed['toneless_syllable_count'] = (int)$match[1];
                    } elseif (preg_match('/^略過行數[：:]\s*(\d+)$/u', trim($line), $match)) {
                        $parsed['skipped_row_count'] = (int)$match[1];
                    }
                }
                $areaUpdate->execute([
                    $parsed['detailed_name'], $parsed['sheet_author'], (int)$legacy['id'],
                ]);
                if ($legacy['current_release_id'] !== null) {
                    $releaseUpdate->execute([
                        $parsed['source_date'], $parsed['character_count'], $parsed['syllable_count'],
                        $parsed['toneless_syllable_count'], $parsed['skipped_row_count'],
                        (int)$legacy['current_release_id'],
                    ]);
                }
            }
        }
        $stmt = $dbh->prepare(
            "INSERT INTO `schema_migrations` (`version`) VALUES (?)
             ON DUPLICATE KEY UPDATE `version` = VALUES(`version`)"
        );
        $stmt->execute([COMMON_IMPORT_SCHEMA_VERSION]);
        $dbh->commit();
    } catch (Throwable $error) {
        if ($dbh->inTransaction()) {
            $dbh->rollBack();
        }
        throw $error;
    }
}

echo 'Statements: ' . $changes . PHP_EOL;
echo $apply ? "Schema migration completed.\n" : "No changes applied.\n";
