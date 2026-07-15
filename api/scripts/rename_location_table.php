<?php
/**
 * Safely rename one legacy location table without changing its stable area ID.
 * Compatible with PHP 7.4 and MySQL 5.7.
 *
 * Usage:
 *   php api/scripts/rename_location_table.php --from=z_vuising --to=z_seanvui_vuising
 *   php api/scripts/rename_location_table.php --from=z_vuising --to=z_seanvui_vuising --apply
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/LocationLookup.php';

$options = getopt('', ['from:', 'to:', 'apply']);
$from = isset($options['from']) ? (string)$options['from'] : '';
$to = isset($options['to']) ? (string)$options['to'] : '';
$apply = isset($options['apply']);

if ($from === '' || $to === '') {
    throw new RuntimeException('Both --from and --to are required');
}
jyutdictAssertTableName($from);
jyutdictAssertTableName($to);
if ($from === $to) {
    throw new RuntimeException('--from and --to must be different');
}
if (strlen($from) > 64 || strlen($to) > 64) {
    throw new RuntimeException('Location table names may not exceed 64 ASCII characters');
}

function renameLocationTableExists(PDO $dbh, $tableName) {
    $stmt = $dbh->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND TABLE_TYPE = 'BASE TABLE'"
    );
    $stmt->execute([$tableName]);
    return (int)$stmt->fetchColumn() === 1;
}

function renameLocationOptionalCount(PDO $dbh, $tableName, $whereSql, array $params) {
    $stmt = $dbh->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $stmt->execute([$tableName]);
    if ((int)$stmt->fetchColumn() !== 1) {
        return 0;
    }
    $stmt = $dbh->prepare("SELECT COUNT(*) FROM `{$tableName}` WHERE {$whereSql}");
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

$lock = $dbh->query("SELECT GET_LOCK('jyutdict_location_table_rename', 0)")->fetchColumn();
if ((int)$lock !== 1) {
    throw new RuntimeException('Another location table rename is already running');
}

$physicalRenamed = false;
try {
    $stmt = $dbh->prepare(
        "SELECT `id`, `first`, `second`, `third`, `sheetname`, `is_visible`, `sort_order`
         FROM `i_area_list` WHERE `sheetname` = ?"
    );
    $stmt->execute([$from]);
    $area = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$area) {
        throw new RuntimeException("No i_area_list row uses sheetname {$from}");
    }
    $areaId = (int)$area['id'];

    $stmt = $dbh->prepare("SELECT `id` FROM `i_area_list` WHERE `sheetname` = ?");
    $stmt->execute([$to]);
    $conflictingAreaId = $stmt->fetchColumn();
    if ($conflictingAreaId !== false) {
        throw new RuntimeException("Target sheetname {$to} is already used by area {$conflictingAreaId}");
    }
    if (!renameLocationTableExists($dbh, $from)) {
        throw new RuntimeException("Source table {$from} does not exist");
    }
    if (renameLocationTableExists($dbh, $to)) {
        throw new RuntimeException("Target table {$to} already exists");
    }

    $newLegacyRows = renameLocationOptionalCount(
        $dbh,
        'common_entries',
        '`area_id` = ? AND `legacy_table` = ?',
        [$areaId, $to]
    );
    if ($newLegacyRows > 0) {
        throw new RuntimeException("common_entries already contains {$newLegacyRows} rows using {$to}");
    }
    $queueConflict = renameLocationOptionalCount(
        $dbh,
        'common_sync_queue',
        '`area_id` <> ? AND `legacy_table` = ?',
        [$areaId, $to]
    );
    if ($queueConflict > 0) {
        throw new RuntimeException("common_sync_queue already uses {$to} for another area");
    }

    $summary = [
        'mode' => $apply ? 'apply' : 'dry-run',
        'area_id' => $areaId,
        'location' => $area['first'] . '/' . $area['second'] . '/' . $area['third'],
        'from' => $from,
        'to' => $to,
        'physical_rows' => (int)$dbh->query("SELECT COUNT(*) FROM `{$from}`")->fetchColumn(),
        'common_entries_to_update' => renameLocationOptionalCount(
            $dbh,
            'common_entries',
            '`area_id` = ? AND `legacy_table` = ?',
            [$areaId, $from]
        ),
    ];
    echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

    if (!$apply) {
        echo "No changes applied. Re-run with --apply after checking the summary.\n";
        exit(0);
    }

    // RENAME TABLE is atomic but causes an implicit commit. If a later metadata
    // update fails, the catch block attempts the inverse rename immediately.
    $dbh->exec("RENAME TABLE `{$from}` TO `{$to}`");
    $physicalRenamed = true;

    $dbh->beginTransaction();
    $stmt = $dbh->prepare(
        "SELECT `sheetname` FROM `i_area_list` WHERE `id` = ? FOR UPDATE"
    );
    $stmt->execute([$areaId]);
    if ($stmt->fetchColumn() !== $from) {
        throw new RuntimeException('i_area_list changed after preflight; refusing metadata update');
    }

    $stmt = $dbh->prepare("UPDATE `i_area_list` SET `sheetname` = ? WHERE `id` = ?");
    $stmt->execute([$to, $areaId]);

    if (renameLocationTableExists($dbh, 'common_entries')) {
        $stmt = $dbh->prepare(
            "UPDATE `common_entries` SET `legacy_table` = ?
             WHERE `area_id` = ? AND `legacy_table` = ?"
        );
        $stmt->execute([$to, $areaId, $from]);
    }
    if (renameLocationTableExists($dbh, 'common_sync_queue')) {
        $stmt = $dbh->prepare(
            "UPDATE `common_sync_queue` SET `legacy_table` = ?
             WHERE `area_id` = ? AND `legacy_table` = ?"
        );
        $stmt->execute([$to, $areaId, $from]);
    }
    $dbh->commit();

    echo json_encode([
        'status' => 'renamed',
        'area_id' => $areaId,
        'from' => $from,
        'to' => $to,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $error) {
    if ($dbh->inTransaction()) {
        $dbh->rollBack();
    }
    if ($physicalRenamed && renameLocationTableExists($dbh, $to) && !renameLocationTableExists($dbh, $from)) {
        try {
            $dbh->exec("RENAME TABLE `{$to}` TO `{$from}`");
        } catch (Throwable $rollbackError) {
            fwrite(
                STDERR,
                "CRITICAL: metadata update failed and physical rename could not be reverted: " .
                $rollbackError->getMessage() . PHP_EOL
            );
        }
    }
    throw $error;
} finally {
    $dbh->query("SELECT RELEASE_LOCK('jyutdict_location_table_rename')")->fetchColumn();
}
