<?php
/** Owner-only structural actions for the location catalogue. */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/LocationMaintenance.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/role.php';
require_once __DIR__ . '/../../middleware/csrf.php';

requireRole('admin');
if (!isOwner()) {
    outputJson(['error' => 'Only owner may perform structural location operations'], 403);
}
validateCsrf();

try {
    jyutdictMaintenanceRequireSchema($dbh);
} catch (Throwable $error) {
    outputJson(['error' => $error->getMessage(), 'code' => 'MAINTENANCE_SCHEMA_NOT_INSTALLED'], 503);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    outputJson(['error' => 'Invalid JSON body'], 400);
}
$operation = (string)($input['action'] ?? '');
$areaId = (int)($input['id'] ?? 0);
$requestId = jyutdictMaintenanceRequestId();
$sheetname = null;
$before = null;

try {
    if ($areaId < 1) {
        throw new RuntimeException('Location id is required');
    }
    $before = jyutdictMaintenanceGetArea($dbh, $areaId);
    $sheetname = $before['sheetname'];

    if ($operation === 'rename_preview') {
        $preview = jyutdictMaintenanceRenamePreview($dbh, $areaId, $input['new_sheetname'] ?? '');
        outputJson(['preview' => $preview]);
    }

    if ($operation === 'rename_apply') {
        if (($input['confirm_sheetname'] ?? '') !== $sheetname) {
            throw new RuntimeException('Type the current sheetname to confirm rename');
        }
        $preview = jyutdictMaintenanceRename($dbh, $areaId, $input['new_sheetname'] ?? '');
        $after = jyutdictMaintenanceGetArea($dbh, $areaId);
        jyutdictMaintenanceAudit(
            $dbh,
            $currentUserId,
            $requestId,
            'location_rename',
            'success',
            $areaId,
            $sheetname,
            $before,
            ['location' => $after, 'preview' => $preview]
        );
        outputJson(['success' => true, 'location' => $after, 'preview' => $preview]);
    }

    if ($operation === 'archive') {
        if (($input['confirm_sheetname'] ?? '') !== $sheetname) {
            throw new RuntimeException('Type the current sheetname to confirm archive');
        }
        if ($before['archived_at'] !== null) {
            throw new RuntimeException('Location is already archived');
        }
        $dbh->beginTransaction();
        $locked = jyutdictMaintenanceGetArea($dbh, $areaId, true);
        if ($locked['archived_at'] !== null) {
            throw new RuntimeException('Location is already archived');
        }
        $newOrder = (int)$dbh->query(
            "SELECT COALESCE(MAX(`sort_order`), 0) + 10 FROM `i_area_list`"
        )->fetchColumn();
        $stmt = $dbh->prepare(
            "UPDATE `i_area_list`
             SET `is_visible` = 0, `archived_at` = NOW(), `archived_by` = ?, `sort_order` = ?
             WHERE `id` = ?"
        );
        $stmt->execute([$currentUserId, $newOrder, $areaId]);
        $after = jyutdictMaintenanceGetArea($dbh, $areaId);
        jyutdictMaintenanceAudit($dbh, $currentUserId, $requestId, 'location_archive', 'success', $areaId, $sheetname, $before, $after);
        $dbh->commit();
        outputJson(['success' => true, 'location' => $after]);
    }

    if ($operation === 'restore') {
        if ($before['archived_at'] === null) {
            throw new RuntimeException('Location is not archived');
        }
        $dbh->beginTransaction();
        $locked = jyutdictMaintenanceGetArea($dbh, $areaId, true);
        $newOrder = (int)$dbh->query(
            "SELECT COALESCE(MAX(`sort_order`), 0) + 10 FROM `i_area_list` WHERE `archived_at` IS NULL"
        )->fetchColumn();
        $stmt = $dbh->prepare(
            "UPDATE `i_area_list`
             SET `is_visible` = 0, `archived_at` = NULL, `archived_by` = NULL, `sort_order` = ?
             WHERE `id` = ?"
        );
        $stmt->execute([$newOrder, $areaId]);
        $after = jyutdictMaintenanceGetArea($dbh, $areaId);
        jyutdictMaintenanceAudit($dbh, $currentUserId, $requestId, 'location_restore', 'success', $areaId, $sheetname, $before, $after);
        $dbh->commit();
        outputJson(['success' => true, 'location' => $after]);
    }

    throw new RuntimeException('Unknown location action');
} catch (Throwable $error) {
    if ($dbh->inTransaction()) {
        $dbh->rollBack();
    }
    if ($operation !== 'rename_preview') {
        try {
            jyutdictMaintenanceAudit(
                $dbh,
                $currentUserId,
                $requestId,
                'location_' . ($operation ?: 'unknown'),
                'failed',
                $areaId ?: null,
                $sheetname,
                $before,
                null,
                $error->getMessage()
            );
        } catch (Throwable $auditError) {
            error_log('Unable to write maintenance audit: ' . $auditError->getMessage());
        }
    }
    $status = strpos($error->getMessage(), 'already') !== false ? 409 : 400;
    outputJson(['error' => $error->getMessage()], $status);
}
