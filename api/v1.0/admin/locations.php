<?php
/** Admin location catalogue API. The database is the sole catalogue authority. */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/LocationMaintenance.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/role.php';
require_once __DIR__ . '/../../middleware/csrf.php';

requireRole('admin');

try {
    jyutdictMaintenanceRequireSchema($dbh);
} catch (Throwable $error) {
    outputJson(['error' => $error->getMessage(), 'code' => 'MAINTENANCE_SCHEMA_NOT_INSTALLED'], 503);
}

function locationAdminInput() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        throw new RuntimeException('Invalid JSON body');
    }
    return $input;
}

function locationAdminRequireOwner() {
    if (!isOwner()) {
        outputJson(['error' => 'Only owner may perform this operation'], 403);
    }
}

function locationAdminAuditFailure(PDO $dbh, $userId, $requestId, $action, $areaId, $sheetname, Throwable $error) {
    try {
        jyutdictMaintenanceAudit(
            $dbh,
            $userId,
            $requestId,
            $action,
            'failed',
            $areaId,
            $sheetname,
            null,
            null,
            $error->getMessage()
        );
    } catch (Throwable $auditError) {
        error_log('Unable to write maintenance audit: ' . $auditError->getMessage());
    }
}

function locationAdminOutputError(Throwable $error) {
    $message = $error->getMessage();
    $status = strpos($message, 'already') !== false ? 409 : 400;
    outputJson(['error' => $message], $status);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $rows = $dbh->query(
        "SELECT a.`id`, a.`longitude`, a.`latitude`, a.`first`, a.`second`, a.`third`,
                a.`sheetname`, a.`current_release_id`, a.`color`, a.`is_visible`,
                a.`sort_order`, a.`archived_at`, a.`archived_by`,
                r.`release_no`, r.`entry_count`, r.`character_count`, r.`published_at`,
                q.`status` AS `queue_status`, q.`requested_generation`, q.`processed_generation`,
                q.`attempt_count`, q.`requested_at`, q.`completed_at`, q.`last_error`,
                IF(t.`TABLE_NAME` IS NULL, 0, 1) AS `physical_table_exists`
         FROM `i_area_list` AS a
         LEFT JOIN `common_releases` AS r ON r.`id` = a.`current_release_id`
         LEFT JOIN `common_sync_queue` AS q ON q.`area_id` = a.`id`
         LEFT JOIN information_schema.TABLES AS t
           ON t.`TABLE_SCHEMA` = DATABASE() AND t.`TABLE_NAME` = a.`sheetname`
              AND t.`TABLE_TYPE` = 'BASE TABLE'
         ORDER BY a.`archived_at` IS NOT NULL, a.`sort_order`, a.`id`"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        foreach (['id', 'is_visible', 'sort_order', 'release_no', 'entry_count', 'character_count',
                  'requested_generation', 'processed_generation', 'attempt_count', 'physical_table_exists'] as $field) {
            $row[$field] = $row[$field] === null ? null : (int)$row[$field];
        }
        $row['longitude'] = (float)$row['longitude'];
        $row['latitude'] = (float)$row['latitude'];
        $row['current_release_id'] = $row['current_release_id'] === null ? null : (int)$row['current_release_id'];
        $row['archived_by'] = $row['archived_by'] === null ? null : (int)$row['archived_by'];
    }
    unset($row);
    outputJson(['locations' => $rows]);
}

validateCsrf();
$requestId = jyutdictMaintenanceRequestId();
$action = 'location_unknown';
$areaId = null;
$sheetname = null;

try {
    $input = locationAdminInput();

    if ($method === 'POST') {
        locationAdminRequireOwner();
        $action = 'location_create';
        $sheetname = jyutdictMaintenanceValidateSheetname($input['sheetname'] ?? '');
        if (jyutdictMaintenanceTableExists($dbh, $sheetname)) {
            throw new RuntimeException('A physical table already uses this sheetname');
        }
        $metadata = jyutdictMaintenanceValidateMetadata($input, [
            'first' => '', 'second' => '', 'third' => '',
            'longitude' => 0.0, 'latitude' => 0.0, 'color' => '#CCCCCC',
        ]);
        $dbh->beginTransaction();
        $sortOrder = (int)$dbh->query(
            "SELECT COALESCE(MAX(`sort_order`), 0) + 10 FROM `i_area_list` WHERE `archived_at` IS NULL"
        )->fetchColumn();
        $stmt = $dbh->prepare(
            "INSERT INTO `i_area_list`
             (`longitude`, `latitude`, `first`, `second`, `third`, `sheetname`, `color`,
              `is_visible`, `sort_order`, `archived_at`, `archived_by`)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, NULL, NULL)"
        );
        $stmt->execute([
            $metadata['longitude'], $metadata['latitude'], $metadata['first'], $metadata['second'],
            $metadata['third'], $sheetname, $metadata['color'], $sortOrder,
        ]);
        $areaId = (int)$dbh->lastInsertId();
        $after = jyutdictMaintenanceGetArea($dbh, $areaId);
        jyutdictMaintenanceAudit($dbh, $currentUserId, $requestId, $action, 'success', $areaId, $sheetname, null, $after);
        $dbh->commit();
        outputJson(['success' => true, 'location' => $after], 201);
    }

    if ($method === 'PATCH') {
        $action = 'location_update';
        $areaId = (int)($input['id'] ?? 0);
        if ($areaId < 1) {
            throw new RuntimeException('Location id is required');
        }
        $dbh->beginTransaction();
        $before = jyutdictMaintenanceGetArea($dbh, $areaId, true);
        $sheetname = $before['sheetname'];
        if (isset($input['sheetname']) && (string)$input['sheetname'] !== $sheetname) {
            throw new RuntimeException('Use the rename operation to change sheetname');
        }
        $updated = jyutdictMaintenanceValidateMetadata($input, $before);
        $isVisible = array_key_exists('is_visible', $input) ? ((int)(bool)$input['is_visible']) : $before['is_visible'];
        if ($isVisible && $before['archived_at'] !== null) {
            throw new RuntimeException('Archived locations cannot be visible');
        }
        if ($isVisible && $before['current_release_id'] === null) {
            throw new RuntimeException('Location must complete its first sync before it can be visible');
        }
        $stmt = $dbh->prepare(
            "UPDATE `i_area_list`
             SET `longitude` = ?, `latitude` = ?, `first` = ?, `second` = ?, `third` = ?,
                 `color` = ?, `is_visible` = ? WHERE `id` = ?"
        );
        $stmt->execute([
            $updated['longitude'], $updated['latitude'], $updated['first'], $updated['second'],
            $updated['third'], $updated['color'], $isVisible, $areaId,
        ]);
        $after = jyutdictMaintenanceGetArea($dbh, $areaId);
        jyutdictMaintenanceAudit($dbh, $currentUserId, $requestId, $action, 'success', $areaId, $sheetname, $before, $after);
        $dbh->commit();
        outputJson(['success' => true, 'location' => $after]);
    }

    if ($method === 'PUT') {
        $action = 'location_reorder';
        $ids = $input['ordered_ids'] ?? null;
        if (!is_array($ids) || !$ids) {
            throw new RuntimeException('ordered_ids must be a non-empty array');
        }
        $ids = array_map('intval', $ids);
        if (count($ids) !== count(array_unique($ids))) {
            throw new RuntimeException('ordered_ids contains duplicates');
        }
        $expected = array_map('intval', $dbh->query(
            "SELECT `id` FROM `i_area_list` WHERE `archived_at` IS NULL ORDER BY `sort_order`, `id`"
        )->fetchAll(PDO::FETCH_COLUMN));
        $left = $ids;
        $right = $expected;
        sort($left, SORT_NUMERIC);
        sort($right, SORT_NUMERIC);
        if ($left !== $right) {
            throw new RuntimeException('ordered_ids must contain every active location exactly once');
        }
        $before = $expected;
        $dbh->beginTransaction();
        $stmt = $dbh->prepare("UPDATE `i_area_list` SET `sort_order` = ? WHERE `id` = ? AND `archived_at` IS NULL");
        foreach ($ids as $index => $id) {
            $stmt->execute([($index + 1) * 10, $id]);
        }
        jyutdictMaintenanceAudit($dbh, $currentUserId, $requestId, $action, 'success', null, null, $before, $ids);
        $dbh->commit();
        outputJson(['success' => true, 'ordered_ids' => $ids]);
    }

    if ($method === 'DELETE') {
        locationAdminRequireOwner();
        $action = 'location_delete_empty';
        $areaId = (int)($input['id'] ?? 0);
        $before = jyutdictMaintenanceGetArea($dbh, $areaId);
        $sheetname = $before['sheetname'];
        if (($input['confirm_sheetname'] ?? '') !== $sheetname) {
            throw new RuntimeException('Type the current sheetname to confirm deletion');
        }
        if ($before['current_release_id'] !== null ||
            jyutdictMaintenanceOptionalCount($dbh, 'common_releases', '`area_id` = ?', [$areaId]) > 0 ||
            jyutdictMaintenanceOptionalCount($dbh, 'common_entries', '`area_id` = ?', [$areaId]) > 0) {
            throw new RuntimeException('Published locations must be archived and cannot be permanently deleted');
        }
        if (jyutdictMaintenanceTableExists($dbh, $sheetname)) {
            throw new RuntimeException('Location with a physical table cannot be permanently deleted');
        }
        $dbh->beginTransaction();
        $stmt = $dbh->prepare("DELETE FROM `common_sync_queue` WHERE `area_id` = ?");
        $stmt->execute([$areaId]);
        $stmt = $dbh->prepare("DELETE FROM `i_area_list` WHERE `id` = ?");
        $stmt->execute([$areaId]);
        jyutdictMaintenanceAudit($dbh, $currentUserId, $requestId, $action, 'success', $areaId, $sheetname, $before, null);
        $dbh->commit();
        outputJson(['success' => true]);
    }

    throw new RuntimeException('Method not allowed');
} catch (Throwable $error) {
    if ($dbh->inTransaction()) {
        $dbh->rollBack();
    }
    locationAdminAuditFailure($dbh, $currentUserId, $requestId, $action, $areaId, $sheetname, $error);
    locationAdminOutputError($error);
}
