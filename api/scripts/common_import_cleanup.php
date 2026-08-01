<?php
/**
 * Prune browser-import staging data and non-current release snapshots.
 * Compatible with PHP 7.4 and MySQL 5.7.
 *
 * Current releases, recently active jobs, jobs currently publishing, and the
 * latest published saved config for every owner/rule-profile pair are always
 * retained. Common release metadata and current common_entries are never
 * deleted.
 *
 * Usage:
 *   php api/scripts/common_import_cleanup.php
 *   php api/scripts/common_import_cleanup.php --older-than-days=30
 *   php api/scripts/common_import_cleanup.php --older-than-days=30 --apply
 *   php api/scripts/common_import_cleanup.php --older-than-days=30 --apply --optimize
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../core/db.php';

$options = getopt('', ['apply', 'optimize', 'older-than-days:', 'help']);
if (isset($options['help'])) {
    echo "Usage:\n";
    echo "  php api/scripts/common_import_cleanup.php [--older-than-days=30] [--apply] [--optimize]\n\n";
    echo "Without --apply, only the cleanup plan is printed.\n";
    echo "--optimize rebuilds affected InnoDB tables after deletion and may lock them.\n";
    exit(0);
}

$apply = isset($options['apply']);
$optimize = isset($options['optimize']);
$olderThanDays = isset($options['older-than-days'])
    ? filter_var($options['older-than-days'], FILTER_VALIDATE_INT)
    : 30;
if ($olderThanDays === false || $olderThanDays < 0 || $olderThanDays > 36500) {
    throw new RuntimeException('--older-than-days must be an integer from 0 to 36500');
}
if ($optimize && !$apply) {
    throw new RuntimeException('--optimize requires --apply');
}

function commonImportCleanupTableExists(PDO $dbh, $tableName) {
    $stmt = $dbh->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $stmt->execute([$tableName]);
    return (int)$stmt->fetchColumn() > 0;
}

function commonImportCleanupBytes($bytes) {
    $value = (float)$bytes;
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $unit = 0;
    while ($value >= 1024 && $unit < count($units) - 1) {
        $value /= 1024;
        $unit++;
    }
    return number_format($value, $unit === 0 ? 0 : 2) . ' ' . $units[$unit];
}

function commonImportCleanupScalar(PDO $dbh, $sql) {
    $value = $dbh->query($sql)->fetchColumn();
    return $value === false || $value === null ? 0 : $value;
}

$requiredTables = [
    'i_area_list',
    'common_releases',
    'common_import_jobs',
    'common_import_chunks',
    'common_import_rows',
    'common_release_snapshots',
];
foreach ($requiredTables as $tableName) {
    if (!commonImportCleanupTableExists($dbh, $tableName)) {
        throw new RuntimeException("Required table {$tableName} does not exist; run common_import_schema.php first");
    }
}

$cutoff = $dbh->query(
    "SELECT DATE_SUB(NOW(), INTERVAL " . (int)$olderThanDays . " DAY)"
)->fetchColumn();

$dbh->exec(
    "CREATE TEMPORARY TABLE `tmp_common_import_cleanup_jobs` (
       `id` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
       PRIMARY KEY (`id`)
     ) ENGINE=InnoDB"
);
$jobStmt = $dbh->prepare(
    "INSERT INTO `tmp_common_import_cleanup_jobs` (`id`)
     SELECT j.`id`
     FROM `common_import_jobs` AS j
     WHERE j.`status` <> 'publishing'
       AND COALESCE(j.`published_at`, j.`updated_at`, j.`created_at`) < ?
       AND NOT EXISTS (
         SELECT 1
         FROM `i_area_list` AS a
         JOIN `common_releases` AS current_release
           ON current_release.`id` = a.`current_release_id`
         WHERE current_release.`import_job_id` = j.`id`
       )
       AND NOT (
         j.`status` = 'published'
         AND NOT EXISTS (
           SELECT 1
           FROM `common_import_jobs` AS newer
           WHERE newer.`created_by` = j.`created_by`
             AND newer.`rule_profile` = j.`rule_profile`
             AND newer.`status` = 'published'
             AND (
               newer.`published_at` > j.`published_at`
               OR (newer.`published_at` = j.`published_at` AND newer.`id` > j.`id`)
             )
         )
       )"
);
$jobStmt->execute([$cutoff]);

$dbh->exec(
    "CREATE TEMPORARY TABLE `tmp_common_import_cleanup_snapshots` (
       `release_id` BIGINT UNSIGNED NOT NULL,
       PRIMARY KEY (`release_id`)
     ) ENGINE=InnoDB"
);
$snapshotStmt = $dbh->prepare(
    "INSERT INTO `tmp_common_import_cleanup_snapshots` (`release_id`)
     SELECT snapshot.`release_id`
     FROM `common_release_snapshots` AS snapshot
     JOIN `common_releases` AS release_row ON release_row.`id` = snapshot.`release_id`
     WHERE release_row.`published_at` < ?
       AND NOT EXISTS (
         SELECT 1 FROM `i_area_list` AS a
         WHERE a.`current_release_id` = snapshot.`release_id`
       )"
);
$snapshotStmt->execute([$cutoff]);

$jobCount = (int)commonImportCleanupScalar(
    $dbh,
    "SELECT COUNT(*) FROM `tmp_common_import_cleanup_jobs`"
);
$chunkCount = (int)commonImportCleanupScalar(
    $dbh,
    "SELECT COUNT(*)
     FROM `common_import_chunks` AS chunk_row
     JOIN `tmp_common_import_cleanup_jobs` AS cleanup ON cleanup.`id` = chunk_row.`job_id`"
);
$chunkBytes = (int)commonImportCleanupScalar(
    $dbh,
    "SELECT COALESCE(SUM(OCTET_LENGTH(chunk_row.`payload_gzip`)), 0)
     FROM `common_import_chunks` AS chunk_row
     JOIN `tmp_common_import_cleanup_jobs` AS cleanup ON cleanup.`id` = chunk_row.`job_id`"
);
$rowCount = (int)commonImportCleanupScalar(
    $dbh,
    "SELECT COUNT(*)
     FROM `common_import_rows` AS import_row
     JOIN `tmp_common_import_cleanup_jobs` AS cleanup ON cleanup.`id` = import_row.`job_id`"
);
$rowTextBytes = (int)commonImportCleanupScalar(
    $dbh,
    "SELECT COALESCE(SUM(
       OCTET_LENGTH(import_row.`chara`) + OCTET_LENGTH(import_row.`initial`) +
       OCTET_LENGTH(import_row.`nuclei`) + OCTET_LENGTH(import_row.`coda`) +
       OCTET_LENGTH(import_row.`tone`) + OCTET_LENGTH(import_row.`ipa`) +
       OCTET_LENGTH(import_row.`note`)
     ), 0)
     FROM `common_import_rows` AS import_row
     JOIN `tmp_common_import_cleanup_jobs` AS cleanup ON cleanup.`id` = import_row.`job_id`"
);
$snapshotCount = (int)commonImportCleanupScalar(
    $dbh,
    "SELECT COUNT(*) FROM `tmp_common_import_cleanup_snapshots`"
);
$snapshotBytes = (int)commonImportCleanupScalar(
    $dbh,
    "SELECT COALESCE(SUM(snapshot.`compressed_bytes`), 0)
     FROM `common_release_snapshots` AS snapshot
     JOIN `tmp_common_import_cleanup_snapshots` AS cleanup
       ON cleanup.`release_id` = snapshot.`release_id`"
);

echo 'Mode: ' . ($apply ? 'apply' : 'dry-run') . PHP_EOL;
echo 'Database: ' . $dbh->query('SELECT DATABASE()')->fetchColumn() . PHP_EOL;
echo 'Cutoff: ' . $cutoff . " ({$olderThanDays} days)" . PHP_EOL;
echo "Protected: recent/publishing jobs, current releases, and latest published saved config per owner/rule profile" . PHP_EOL;
echo "Import jobs to delete: {$jobCount}" . PHP_EOL;
echo "  Cascaded chunks: {$chunkCount} (" . commonImportCleanupBytes($chunkBytes) . " compressed payload)" . PHP_EOL;
echo "  Cascaded rows: {$rowCount} (" . commonImportCleanupBytes($rowTextBytes) . " text payload, indexes/overhead excluded)" . PHP_EOL;
echo "Non-current snapshots to delete: {$snapshotCount} (" . commonImportCleanupBytes($snapshotBytes) . ")" . PHP_EOL;
echo "Common releases and common_entries to delete: 0" . PHP_EOL;

if (!$apply) {
    echo "No changes applied. Add --apply after reviewing this plan." . PHP_EOL;
    exit(0);
}

$dbh->beginTransaction();
try {
    $deletedSnapshots = $dbh->exec(
        "DELETE snapshot
         FROM `common_release_snapshots` AS snapshot
         JOIN `tmp_common_import_cleanup_snapshots` AS cleanup
           ON cleanup.`release_id` = snapshot.`release_id`"
    );
    $deletedJobs = $dbh->exec(
        "DELETE job_row
         FROM `common_import_jobs` AS job_row
         JOIN `tmp_common_import_cleanup_jobs` AS cleanup ON cleanup.`id` = job_row.`id`"
    );
    $dbh->commit();
} catch (Throwable $error) {
    if ($dbh->inTransaction()) {
        $dbh->rollBack();
    }
    throw $error;
}

echo "Deleted import jobs: {$deletedJobs}" . PHP_EOL;
echo "Deleted snapshots: {$deletedSnapshots}" . PHP_EOL;

if ($optimize && ($deletedJobs > 0 || $deletedSnapshots > 0)) {
    echo "Optimizing affected tables; this may take time and lock them..." . PHP_EOL;
    foreach ([
        'common_import_rows',
        'common_import_chunks',
        'common_import_jobs',
        'common_release_snapshots',
    ] as $tableName) {
        $result = $dbh->query("OPTIMIZE TABLE `{$tableName}`")->fetchAll(PDO::FETCH_ASSOC);
        $last = end($result);
        echo "  {$tableName}: " . ($last['Msg_text'] ?? 'completed') . PHP_EOL;
    }
}

echo "Cleanup completed." . PHP_EOL;
