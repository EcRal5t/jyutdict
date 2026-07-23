<?php
/**
 * Add optional location metadata and reserve per-location phonology articles.
 * Compatible with PHP 7.4 and MySQL 5.7.
 *
 * Usage:
 *   php api/scripts/location_content_schema.php
 *   php api/scripts/location_content_schema.php --apply
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../core/db.php';

$apply = in_array('--apply', $argv, true);

function locationContentColumnExists(PDO $dbh, $table, $column) {
    $stmt = $dbh->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function locationContentIndexExists(PDO $dbh, $table, $index) {
    $stmt = $dbh->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([$table, $index]);
    return (int)$stmt->fetchColumn() > 0;
}

function locationContentRun(PDO $dbh, $sql, $apply) {
    echo $sql . ";\n";
    if ($apply) {
        $dbh->exec($sql);
    }
}

echo 'Mode: ' . ($apply ? 'apply' : 'dry-run') . PHP_EOL;
$changes = 0;

if (!locationContentColumnExists($dbh, 'i_area_list', 'detailed_name')) {
    locationContentRun(
        $dbh,
        'ALTER TABLE `i_area_list` ADD COLUMN `detailed_name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL AFTER `third`',
        $apply
    );
    $changes++;
}

if (!locationContentColumnExists($dbh, 'i_area_list', 'sheet_info')) {
    locationContentRun(
        $dbh,
        'ALTER TABLE `i_area_list` ADD COLUMN `sheet_info` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL AFTER `detailed_name`',
        $apply
    );
    $changes++;
}

if (!locationContentColumnExists($dbh, 'articles', 'article_type')) {
    locationContentRun(
        $dbh,
        "ALTER TABLE `articles` ADD COLUMN `article_type` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'location' AFTER `location_name`",
        $apply
    );
    $changes++;
}

if (locationContentIndexExists($dbh, 'articles', 'uk_location_name')) {
    locationContentRun($dbh, 'ALTER TABLE `articles` DROP INDEX `uk_location_name`', $apply);
    $changes++;
}

if (!locationContentIndexExists($dbh, 'articles', 'uk_location_article_type')) {
    locationContentRun(
        $dbh,
        'ALTER TABLE `articles` ADD UNIQUE KEY `uk_location_article_type` (`location_name`, `article_type`)',
        $apply
    );
    $changes++;
}

echo $changes === 0 ? "Schema already current.\n" : "Planned/applied changes: {$changes}\n";

