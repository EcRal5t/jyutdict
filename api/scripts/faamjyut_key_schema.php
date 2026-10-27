<?php
/** Enforce unique, nonempty stable entry keys after source data has been repaired. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../core/db.php';
$apply = in_array('--apply', $argv, true);
echo 'Mode: ' . ($apply ? 'apply' : 'dry-run') . PHP_EOL;

$invalid = (int)$dbh->query(
    "SELECT COUNT(*) FROM `j_faamjyut` WHERE `鍵` IS NULL OR `鍵` NOT REGEXP '^>[0-9]{1,15}$'"
)->fetchColumn();
$duplicates = (int)$dbh->query(
    'SELECT COUNT(*) FROM (SELECT `鍵` FROM `j_faamjyut` GROUP BY `鍵` HAVING COUNT(*) > 1) AS duplicate_keys'
)->fetchColumn();
if ($invalid || $duplicates) {
    fwrite(STDERR, "Refusing schema changes: {$invalid} invalid keys, {$duplicates} duplicate groups. Repair the source identities first.\n");
    exit(1);
}

$indexes = $dbh->query(
    "SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'j_faamjyut' ORDER BY INDEX_NAME, SEQ_IN_INDEX"
)->fetchAll(PDO::FETCH_ASSOC);
$groups = [];
foreach ($indexes as $index) {
    $groups[$index['INDEX_NAME']][] = $index;
}
$unique = false;
foreach ($groups as $index) {
    if (count($index) === 1 && (int)$index[0]['NON_UNIQUE'] === 0
        && $index[0]['COLUMN_NAME'] === '鍵' && $index[0]['SUB_PART'] === null) {
        $unique = true;
    }
}
$nullable = $dbh->query(
    "SELECT IS_NULLABLE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'j_faamjyut' AND COLUMN_NAME = '鍵'"
)->fetchColumn();
$operations = [];
if ($nullable !== 'NO') {
    $operations[] = 'MODIFY COLUMN `鍵` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL';
}
if (!$unique) {
    if (isset($groups['uq_sheet_key'])) {
        fwrite(STDERR, "Index uq_sheet_key exists with a different definition; inspect it before proceeding.\n");
        exit(1);
    }
    $operations[] = 'ADD UNIQUE KEY `uq_sheet_key` (`鍵`)';
}
if (!$operations) {
    echo "Stable entry keys are already non-null and unique.\n";
    exit;
}
$sql = 'ALTER TABLE `j_faamjyut` ' . implode(', ', $operations);
echo $sql . ";\n";
if ($apply) {
    $dbh->exec($sql);
    echo "Applied. Existing definitions and entry keys were not rewritten.\n";
}
