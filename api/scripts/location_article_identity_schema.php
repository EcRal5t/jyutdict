<?php
/**
 * Install lightweight location article redirects and seed the Dinghu mapping.
 * Compatible with PHP 7.4 and MySQL 5.7.
 *
 * Usage:
 *   php api/scripts/location_article_identity_schema.php
 *   php api/scripts/location_article_identity_schema.php --apply
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../core/db.php';

$apply = in_array('--apply', $argv, true);
$canonical = '肇慶鼎湖甫草';
$aliases = ['鼎湖', '肇慶甫草'];

function locationIdentityTableExists(PDO $dbh, $table) {
    $stmt = $dbh->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function locationIdentityCreateSql() {
    return "CREATE TABLE `location_name_redirects` (
      `alias_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
      `canonical_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
      `created_by` int unsigned NULL,
      `updated_by` int unsigned NULL,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`alias_name`),
      KEY `idx_location_redirect_canonical` (`canonical_name`),
      KEY `fk_location_redirect_created_by` (`created_by`),
      KEY `fk_location_redirect_updated_by` (`updated_by`),
      CONSTRAINT `fk_location_redirect_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
      CONSTRAINT `fk_location_redirect_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin";
}

function locationIdentityArticleRows(PDO $dbh, array $names) {
    $placeholders = implode(',', array_fill(0, count($names), '?'));
    $stmt = $dbh->prepare(
        "SELECT `id`, `location_name` FROM `articles`
         WHERE `article_type` = 'location' AND `location_name` IN ({$placeholders})
         ORDER BY `id`"
    );
    $stmt->execute($names);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

echo 'Mode: ' . ($apply ? 'apply' : 'dry-run') . PHP_EOL;
$tableExists = locationIdentityTableExists($dbh, 'location_name_redirects');
if (!$tableExists) {
    echo locationIdentityCreateSql() . ";\n";
} else {
    $stmt = $dbh->prepare(
        "SELECT `canonical_name` FROM `location_name_redirects`
         WHERE `alias_name` = ?"
    );
    foreach ($aliases as $alias) {
        $stmt->execute([$alias]);
        $existingTarget = $stmt->fetchColumn();
        if ($existingTarget !== false && $existingTarget !== $canonical) {
            fwrite(STDERR, "Conflict: {$alias} already redirects to {$existingTarget}" . PHP_EOL);
            exit(1);
        }
    }
}

$names = array_merge([$canonical], $aliases);
$articleRows = locationIdentityArticleRows($dbh, $names);
if (count($articleRows) > 1) {
    $labels = array_map(function ($row) {
        return "#{$row['id']} {$row['location_name']}";
    }, $articleRows);
    fwrite(STDERR, 'Conflict: multiple location articles exist: ' . implode(', ', $labels) . PHP_EOL);
    exit(1);
}

foreach ($aliases as $alias) {
    echo "Redirect: {$alias} -> {$canonical}\n";
}
if (count($articleRows) === 1 && $articleRows[0]['location_name'] !== $canonical) {
    echo "Rename article #{$articleRows[0]['id']}: {$articleRows[0]['location_name']} -> {$canonical}\n";
}
echo "Normalize editor assignments from aliases to {$canonical}\n";

if (!$apply) {
    exit(0);
}

try {
    if (!$tableExists) {
        $dbh->exec(locationIdentityCreateSql());
    }
    $dbh->beginTransaction();

    $articleRows = locationIdentityArticleRows($dbh, $names);
    if (count($articleRows) > 1) {
        throw new RuntimeException('Multiple location articles exist for the seeded identity');
    }
    if (count($articleRows) === 1 && $articleRows[0]['location_name'] !== $canonical) {
        $stmt = $dbh->prepare("UPDATE `articles` SET `location_name` = ? WHERE `id` = ?");
        $stmt->execute([$canonical, (int)$articleRows[0]['id']]);
    }

    $redirectStmt = $dbh->prepare(
        "INSERT IGNORE INTO `location_name_redirects`
         (`alias_name`, `canonical_name`, `created_by`, `updated_by`)
         VALUES (?, ?, NULL, NULL)"
    );
    foreach ($aliases as $alias) {
        $redirectStmt->execute([$alias, $canonical]);
    }

    $placeholders = implode(',', array_fill(0, count($aliases), '?'));
    $stmt = $dbh->prepare(
        "SELECT `editor_id`, `location_name`, `assigned_by`, `assigned_at`
         FROM `editor_locations` WHERE `location_name` IN ({$placeholders})"
    );
    $stmt->execute($aliases);
    $assignmentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $insertAssignment = $dbh->prepare(
        "INSERT IGNORE INTO `editor_locations`
         (`editor_id`, `location_name`, `assigned_by`, `assigned_at`)
         VALUES (?, ?, ?, ?)"
    );
    foreach ($assignmentRows as $row) {
        $insertAssignment->execute([
            (int)$row['editor_id'],
            $canonical,
            (int)$row['assigned_by'],
            $row['assigned_at'],
        ]);
    }
    $deleteAssignments = $dbh->prepare(
        "DELETE FROM `editor_locations` WHERE `location_name` IN ({$placeholders})"
    );
    $deleteAssignments->execute($aliases);

    $dbh->commit();
    echo "Applied successfully.\n";
} catch (Throwable $error) {
    if ($dbh->inTransaction()) {
        $dbh->rollBack();
    }
    fwrite(STDERR, 'Failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
