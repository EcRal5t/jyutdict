<?php
/**
 * Synchronize legacy per-location tables into common_entries.
 *
 * The legacy per-location table remains the Excel SQL import target. Run this
 * script after every import. Public common reads see either the old complete
 * state or the new complete state because each area is synchronized in one
 * transaction.
 *
 * Usage:
 *   php api/scripts/sync_common_entries.php --all
 *   php api/scripts/sync_common_entries.php --all --apply
 *   php api/scripts/sync_common_entries.php --area-id=17 --apply --source-ref="file.xlsx"
 *   php api/scripts/sync_common_entries.php --table=z_gwongzau_wongcyung --apply
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/LocationLookup.php';
require_once __DIR__ . '/../core/CommonEntries.php';

$options = getopt('', ['all', 'apply', 'area-id:', 'table:', 'source-ref:', 'message:', 'verify-only']);
$apply = isset($options['apply']);
$verifyOnly = isset($options['verify-only']);
$all = isset($options['all']);
$areaIdOption = isset($options['area-id']) ? (int)$options['area-id'] : null;
$tableOption = isset($options['table']) ? (string)$options['table'] : null;
$sourceRef = isset($options['source-ref']) ? (string)$options['source-ref'] : null;
$message = isset($options['message']) ? (string)$options['message'] : '';

if ($apply && $verifyOnly) {
    throw new RuntimeException('--apply and --verify-only cannot be combined');
}
if (($all ? 1 : 0) + ($areaIdOption !== null ? 1 : 0) + ($tableOption !== null ? 1 : 0) !== 1) {
    throw new RuntimeException('Choose exactly one of --all, --area-id, or --table');
}
if ($tableOption !== null) {
    jyutdictAssertTableName($tableOption);
}
if (mb_strlen($message, 'UTF-8') > 500) {
    throw new RuntimeException('--message is longer than 500 characters');
}
if ($sourceRef !== null && mb_strlen($sourceRef, 'UTF-8') > 255) {
    throw new RuntimeException('--source-ref is longer than 255 characters');
}

function commonSyncTableExists(PDO $dbh, $tableName) {
    $stmt = $dbh->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $stmt->execute([$tableName]);
    return (int)$stmt->fetchColumn() > 0;
}

function commonSyncValidateLegacyTable(PDO $dbh, $tableName) {
    jyutdictAssertTableName($tableName);
    $required = ['id', 'chara', 'initial', 'nuclei', 'coda', 'tone', 'ipa', 'note', 'alt_group'];
    $stmt = $dbh->prepare(
        "SELECT `COLUMN_NAME` FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $stmt->execute([$tableName]);
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($required as $column) {
        if (!in_array($column, $columns, true)) {
            throw new RuntimeException("Missing column {$tableName}.{$column}");
        }
    }
}

function commonSyncValidateRow(array $row, $tableName) {
    $limits = [
        'chara' => 16,
        'initial' => 32,
        'nuclei' => 32,
        'coda' => 16,
        'tone' => 8,
        'ipa' => 128,
    ];
    foreach ($limits as $field => $limit) {
        if (mb_strlen((string)$row[$field], 'UTF-8') > $limit) {
            throw new RuntimeException("Value too long at {$tableName}.{$field}, legacy id {$row['legacy_row_id']}");
        }
    }
    if ($row['alt_group'] !== null && ($row['alt_group'] < 0 || $row['alt_group'] > 65535)) {
        throw new RuntimeException("Invalid alt_group at {$tableName}, legacy id {$row['legacy_row_id']}");
    }
}

function commonSyncLoadLegacyRows(PDO $dbh, $tableName) {
    $sql = "SELECT `id` AS `legacy_row_id`, `chara`, `initial`, `nuclei`, `coda`, `tone`, `ipa`, `note`, `alt_group`
            FROM `{$tableName}` ORDER BY `id`";
    $rows = $dbh->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['legacy_row_id'] = (int)$row['legacy_row_id'];
        $row = array_merge($row, jyutdictCommonNormalizeRow($row));
        commonSyncValidateRow($row, $tableName);
        $row['computed_hash'] = jyutdictCommonRowHash($row);
    }
    unset($row);
    return $rows;
}

function commonSyncLoadCurrentRows(PDO $dbh, $areaId) {
    $stmt = $dbh->prepare(
        "SELECT `id`, `display_order`, `chara`, `initial`, `nuclei`, `coda`, `tone`, `ipa`, `note`,
                `alt_group`, `row_revision`, `row_hash`, `legacy_table`, `legacy_row_id`
         FROM `common_entries` WHERE `area_id` = ?
         ORDER BY `display_order`, `id`"
    );
    $stmt->execute([$areaId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['display_order'] = (int)$row['display_order'];
        $row['row_revision'] = (int)$row['row_revision'];
        $row['legacy_row_id'] = $row['legacy_row_id'] === null ? null : (int)$row['legacy_row_id'];
        $row = array_merge($row, jyutdictCommonNormalizeRow($row));
        $computed = jyutdictCommonRowHash($row);
        if (!hash_equals($computed, $row['row_hash'])) {
            throw new RuntimeException("Stored row_hash mismatch for common_entries.id={$row['id']}");
        }
        $row['computed_hash'] = $computed;
    }
    unset($row);
    return $rows;
}

function commonSyncMatchRows(array $currentRows, array $sourceRows) {
    $buckets = [];
    foreach ($currentRows as $index => $row) {
        $buckets[bin2hex($row['computed_hash'])][] = $index;
    }

    $usedCurrent = [];
    $matchedSource = [];
    foreach ($sourceRows as $sourceIndex => $sourceRow) {
        $hash = bin2hex($sourceRow['computed_hash']);
        $matchIndex = null;
        foreach ($buckets[$hash] ?? [] as $candidateIndex) {
            if (!isset($usedCurrent[$candidateIndex]) &&
                jyutdictCommonRowsEqual($currentRows[$candidateIndex], $sourceRow)) {
                $matchIndex = $candidateIndex;
                break;
            }
        }
        if ($matchIndex !== null) {
            $usedCurrent[$matchIndex] = true;
            $matchedSource[$sourceIndex] = $matchIndex;
        }
    }

    return [$matchedSource, $usedCurrent];
}

function commonSyncReorderedIds(array $currentRows, array $sourceRows, array $matchedSource) {
    $matchedIds = [];
    $newOrder = [];
    foreach ($matchedSource as $sourceIndex => $currentIndex) {
        $id = $currentRows[$currentIndex]['id'];
        $matchedIds[$id] = true;
        $newOrder[] = $id;
    }
    $oldOrder = [];
    foreach ($currentRows as $row) {
        if (isset($matchedIds[$row['id']])) {
            $oldOrder[] = $row['id'];
        }
    }
    if ($oldOrder === $newOrder) {
        return [];
    }

    $oldPositions = array_flip($oldOrder);
    $reordered = [];
    foreach ($newOrder as $position => $id) {
        if (!isset($oldPositions[$id]) || $oldPositions[$id] !== $position) {
            $reordered[$id] = true;
        }
    }
    return $reordered;
}

function commonSyncCharacterCount(array $rows) {
    $characters = [];
    foreach ($rows as $row) {
        $characters[$row['chara']] = true;
    }
    return count($characters);
}

function commonSyncInsertRows(PDO $dbh, $areaId, $releaseId, $tableName, array $sourceRows, array $matchedSource) {
    $pending = [];
    foreach ($sourceRows as $sourceIndex => $row) {
        if (!isset($matchedSource[$sourceIndex])) {
            $pending[] = $row;
        }
    }
    foreach (array_chunk($pending, 250) as $batch) {
        $values = [];
        $params = [];
        foreach ($batch as $row) {
            $values[] = '(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
            array_push(
                $params,
                $areaId,
                $row['legacy_row_id'],
                $row['chara'],
                $row['initial'],
                $row['nuclei'],
                $row['coda'],
                $row['tone'],
                $row['ipa'],
                $row['note'],
                $row['alt_group'],
                $row['computed_hash'],
                $releaseId,
                $releaseId,
                $tableName,
                $row['legacy_row_id'],
                1
            );
        }
        $sql =
            "INSERT INTO `common_entries`
             (`area_id`, `display_order`, `chara`, `initial`, `nuclei`, `coda`, `tone`, `ipa`, `note`,
              `alt_group`, `row_hash`, `created_release_id`, `last_changed_release_id`,
              `legacy_table`, `legacy_row_id`, `row_revision`)
             VALUES " . implode(',', $values);
        $stmt = $dbh->prepare($sql);
        $stmt->execute($params);
    }
}

function commonSyncDeleteRows(PDO $dbh, $areaId, array $currentRows, array $usedCurrent) {
    $ids = [];
    foreach ($currentRows as $index => $row) {
        if (!isset($usedCurrent[$index])) {
            $ids[] = $row['id'];
        }
    }
    foreach (array_chunk($ids, 500) as $batch) {
        $placeholders = implode(',', array_fill(0, count($batch), '?'));
        $stmt = $dbh->prepare(
            "DELETE FROM `common_entries` WHERE `area_id` = ? AND `id` IN ({$placeholders})"
        );
        $stmt->execute(array_merge([$areaId], $batch));
    }
}

function commonSyncUpdateMatchedRows(
    PDO $dbh,
    $areaId,
    $releaseId,
    $tableName,
    array $currentRows,
    array $sourceRows,
    array $matchedSource,
    array $reorderedIds
) {
    if (!$matchedSource) {
        return;
    }

    $clear = $dbh->prepare(
        "UPDATE `common_entries`
         SET `legacy_table` = NULL, `legacy_row_id` = NULL, `updated_at` = `updated_at`
         WHERE `area_id` = ?"
    );
    $clear->execute([$areaId]);

    $stable = $dbh->prepare(
        "UPDATE `common_entries`
         SET `display_order` = ?, `legacy_table` = ?, `legacy_row_id` = ?, `updated_at` = `updated_at`
         WHERE `area_id` = ? AND `id` = ?"
    );
    $reordered = $dbh->prepare(
        "UPDATE `common_entries`
         SET `display_order` = ?, `legacy_table` = ?, `legacy_row_id` = ?,
             `row_revision` = `row_revision` + 1, `last_changed_release_id` = ?,
             `updated_at` = CURRENT_TIMESTAMP
         WHERE `area_id` = ? AND `id` = ?"
    );

    foreach ($matchedSource as $sourceIndex => $currentIndex) {
        $source = $sourceRows[$sourceIndex];
        $id = $currentRows[$currentIndex]['id'];
        if (isset($reorderedIds[$id])) {
            $reordered->execute([
                $source['legacy_row_id'], $tableName, $source['legacy_row_id'],
                $releaseId, $areaId, $id,
            ]);
        } else {
            $stable->execute([
                $source['legacy_row_id'], $tableName, $source['legacy_row_id'], $areaId, $id,
            ]);
        }
    }
}

function commonSyncArea(PDO $dbh, array $area, $apply, $sourceRef, $message) {
    $areaId = (int)$area['id'];
    $tableName = jyutdictAssertTableName($area['sheetname']);
    commonSyncValidateLegacyTable($dbh, $tableName);

    $dbh->beginTransaction();
    try {
        $areaStmt = $dbh->prepare(
            "SELECT `current_release_id` FROM `i_area_list` WHERE `id` = ? FOR UPDATE"
        );
        $areaStmt->execute([$areaId]);
        $parentReleaseId = $areaStmt->fetchColumn();
        $parentReleaseId = $parentReleaseId === null ? null : (int)$parentReleaseId;

        $sourceRows = commonSyncLoadLegacyRows($dbh, $tableName);
        $currentRows = commonSyncLoadCurrentRows($dbh, $areaId);
        list($matchedSource, $usedCurrent) = commonSyncMatchRows($currentRows, $sourceRows);
        $reorderedIds = commonSyncReorderedIds($currentRows, $sourceRows, $matchedSource);

        $sourceHash = jyutdictCommonContentHash($sourceRows);
        $currentHash = jyutdictCommonContentHash($currentRows);
        $insertCount = count($sourceRows) - count($matchedSource);
        $deleteCount = count($currentRows) - count($usedCurrent);
        $updateCount = count($reorderedIds);
        $needsRelease = $parentReleaseId === null || !hash_equals($sourceHash, $currentHash);

        $result = [
            'area_id' => $areaId,
            'legacy_table' => $tableName,
            'legacy_count' => count($sourceRows),
            'common_count_before' => count($currentRows),
            'matched_count' => count($matchedSource),
            'insert_count' => $insertCount,
            'update_count' => $updateCount,
            'delete_count' => $deleteCount,
            'content_hash' => bin2hex($sourceHash),
            'status' => $needsRelease ? 'drift' : 'equal',
        ];

        if (!$apply) {
            $dbh->rollBack();
            return $result;
        }

        $releaseId = $parentReleaseId;
        if ($needsRelease) {
            $releaseNoStmt = $dbh->prepare(
                "SELECT COALESCE(MAX(`release_no`), 0) + 1 FROM `common_releases` WHERE `area_id` = ?"
            );
            $releaseNoStmt->execute([$areaId]);
            $releaseNo = (int)$releaseNoStmt->fetchColumn();
            $sourceType = $parentReleaseId === null ? 'baseline' : 'legacy_sync';
            $releaseInsertCount = $parentReleaseId === null ? 0 : $insertCount;
            $releaseUpdateCount = $parentReleaseId === null ? 0 : $updateCount;
            $releaseDeleteCount = $parentReleaseId === null ? 0 : $deleteCount;
            $releaseStmt = $dbh->prepare(
                "INSERT INTO `common_releases`
                 (`area_id`, `release_no`, `parent_release_id`, `source_type`, `source_ref`,
                  `insert_count`, `update_count`, `delete_count`, `entry_count`, `character_count`,
                  `content_hash`, `message`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $releaseStmt->execute([
                $areaId,
                $releaseNo,
                $parentReleaseId,
                $sourceType,
                $sourceRef === null ? $tableName : $sourceRef,
                $releaseInsertCount,
                $releaseUpdateCount,
                $releaseDeleteCount,
                count($sourceRows),
                commonSyncCharacterCount($sourceRows),
                $sourceHash,
                $message,
            ]);
            $releaseId = (int)$dbh->lastInsertId();
        }

        commonSyncDeleteRows($dbh, $areaId, $currentRows, $usedCurrent);
        commonSyncUpdateMatchedRows(
            $dbh,
            $areaId,
            $releaseId,
            $tableName,
            $currentRows,
            $sourceRows,
            $matchedSource,
            $reorderedIds
        );
        if ($needsRelease) {
            commonSyncInsertRows($dbh, $areaId, $releaseId, $tableName, $sourceRows, $matchedSource);
        }

        $verifiedRows = commonSyncLoadCurrentRows($dbh, $areaId);
        $verifiedHash = jyutdictCommonContentHash($verifiedRows);
        if (count($verifiedRows) !== count($sourceRows) || !hash_equals($sourceHash, $verifiedHash)) {
            throw new RuntimeException("Post-sync verification failed for area {$areaId}");
        }

        if ($needsRelease) {
            $pointer = $dbh->prepare(
                "UPDATE `i_area_list` SET `current_release_id` = ? WHERE `id` = ?"
            );
            $pointer->execute([$releaseId, $areaId]);
            $result['release_id'] = $releaseId;
        }
        $dbh->commit();
        $result['status'] = $needsRelease ? 'synced' : 'equal';
        return $result;
    } catch (Throwable $error) {
        if ($dbh->inTransaction()) {
            $dbh->rollBack();
        }
        throw $error;
    }
}

if (!commonSyncTableExists($dbh, 'common_releases') || !commonSyncTableExists($dbh, 'common_entries')) {
    throw new RuntimeException('Common schema is not installed; run common_entries_schema.php --apply first');
}

$areas = jyutdictLoadAreas($dbh);
if (!$all) {
    $areas = array_values(array_filter($areas, function ($area) use ($areaIdOption, $tableOption) {
        if ($areaIdOption !== null) {
            return (int)$area['id'] === $areaIdOption;
        }
        return $area['sheetname'] === $tableOption;
    }));
    if (count($areas) !== 1) {
        throw new RuntimeException('Selected area was not found in i_area_list');
    }
}

$lockStmt = $dbh->query("SELECT GET_LOCK('jyutdict_common_entries_sync', 0)");
if ((int)$lockStmt->fetchColumn() !== 1) {
    throw new RuntimeException('Another common entry sync is running');
}

$driftCount = 0;
try {
    echo 'Mode: ' . ($apply ? 'apply' : ($verifyOnly ? 'verify-only' : 'dry-run')) . PHP_EOL;
    foreach ($areas as $area) {
        $result = commonSyncArea($dbh, $area, $apply, $sourceRef, $message);
        if ($result['status'] === 'drift') {
            $driftCount++;
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
    if ($apply && $all) {
        $analyze = $dbh->query('ANALYZE TABLE `common_entries`, `common_releases`');
        $analyze->fetchAll(PDO::FETCH_ASSOC);
        $analyze->closeCursor();
        echo "Optimizer statistics refreshed.\n";
    }
} finally {
    $dbh->query("SELECT RELEASE_LOCK('jyutdict_common_entries_sync')")->fetchColumn();
}

if ($verifyOnly && $driftCount > 0) {
    fwrite(STDERR, "Verification found {$driftCount} area(s) with drift.\n");
    exit(2);
}
