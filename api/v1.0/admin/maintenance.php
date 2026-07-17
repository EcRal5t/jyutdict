<?php
/** Common-entry queue monitoring and owner-controlled requeue operations. */

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

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    $queue = $dbh->query(
        "SELECT q.`area_id`, q.`legacy_table`, q.`source_ref`, q.`requested_generation`,
                q.`processed_generation`, q.`status`, q.`attempt_count`, q.`requested_at`,
                q.`started_at`, q.`completed_at`, q.`last_error`,
                a.`first`, a.`second`, a.`third`, a.`archived_at`
         FROM `common_sync_queue` AS q
         JOIN `i_area_list` AS a ON a.`id` = q.`area_id`
         ORDER BY q.`processed_generation` < q.`requested_generation` DESC,
                  FIELD(q.`status`, 'failed', 'processing', 'pending', 'done'),
                  q.`requested_at` DESC, q.`area_id`"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($queue as &$row) {
        foreach (['area_id', 'requested_generation', 'processed_generation', 'attempt_count'] as $field) {
            $row[$field] = (int)$row[$field];
        }
        $row['outstanding'] = $row['processed_generation'] < $row['requested_generation'];
    }
    unset($row);
    $summary = $dbh->query(
        "SELECT `status`, COUNT(*) AS `count`,
                SUM(`processed_generation` < `requested_generation`) AS `outstanding`
         FROM `common_sync_queue` GROUP BY `status`"
    )->fetchAll(PDO::FETCH_ASSOC);
    $worker = $dbh->query(
        "SELECT `worker_name`, `last_seen_at`, `last_started_at`, `last_finished_at`,
                `last_status`, `last_processed`, `last_failures`, `last_error`,
                IF(`last_seen_at` IS NULL OR `last_seen_at` < DATE_SUB(NOW(), INTERVAL 10 MINUTE), 1, 0) AS `is_stale`
         FROM `maintenance_worker_state` WHERE `worker_name` = 'common_sync_queue'"
    )->fetch(PDO::FETCH_ASSOC);
    if ($worker) {
        foreach (['last_processed', 'last_failures', 'is_stale'] as $field) {
            $worker[$field] = (int)$worker[$field];
        }
    }
    outputJson(['summary' => $summary, 'worker' => $worker ?: null, 'queue' => $queue]);
}

if ($method !== 'POST') {
    outputJson(['error' => 'Method not allowed'], 405);
}
if (!isOwner()) {
    outputJson(['error' => 'Only owner may change the sync queue'], 403);
}
validateCsrf();
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    outputJson(['error' => 'Invalid JSON body'], 400);
}
$operation = (string)($input['action'] ?? '');
$areaId = isset($input['area_id']) ? (int)$input['area_id'] : null;
$requestId = jyutdictMaintenanceRequestId();
$sheetname = null;
$before = null;

try {
    if ($operation === 'enqueue') {
        if (!$areaId) {
            throw new RuntimeException('area_id is required');
        }
        $area = jyutdictMaintenanceGetArea($dbh, $areaId);
        $sheetname = $area['sheetname'];
        if ($area['archived_at'] !== null) {
            throw new RuntimeException('Archived locations cannot be manually synchronized');
        }
        $stmt = $dbh->prepare("SELECT * FROM `common_sync_queue` WHERE `area_id` = ?");
        $stmt->execute([$areaId]);
        $before = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        jyutdictMaintenanceEnqueue($dbh, $area, 'admin manual resync');
        $stmt->execute([$areaId]);
        $after = $stmt->fetch(PDO::FETCH_ASSOC);
        jyutdictMaintenanceAudit($dbh, $currentUserId, $requestId, 'sync_enqueue', 'success', $areaId, $sheetname, $before, $after);
        outputJson(['success' => true, 'queue' => $after]);
    }

    if ($operation === 'retry') {
        if (!$areaId) {
            throw new RuntimeException('area_id is required');
        }
        $area = jyutdictMaintenanceGetArea($dbh, $areaId);
        $sheetname = $area['sheetname'];
        $stmt = $dbh->prepare("SELECT * FROM `common_sync_queue` WHERE `area_id` = ?");
        $stmt->execute([$areaId]);
        $before = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$before || $before['status'] !== 'failed' || (int)$before['processed_generation'] >= (int)$before['requested_generation']) {
            throw new RuntimeException('Location has no failed outstanding sync');
        }
        $stmt = $dbh->prepare(
            "UPDATE `common_sync_queue`
             SET `status` = 'pending', `attempt_count` = 0, `requested_at` = NOW(),
                 `started_at` = NULL, `completed_at` = NULL, `last_error` = NULL
             WHERE `area_id` = ?"
        );
        $stmt->execute([$areaId]);
        $afterStmt = $dbh->prepare("SELECT * FROM `common_sync_queue` WHERE `area_id` = ?");
        $afterStmt->execute([$areaId]);
        $after = $afterStmt->fetch(PDO::FETCH_ASSOC);
        jyutdictMaintenanceAudit($dbh, $currentUserId, $requestId, 'sync_retry', 'success', $areaId, $sheetname, $before, $after);
        outputJson(['success' => true, 'queue' => $after]);
    }

    if ($operation === 'retry_all_failed') {
        $before = $dbh->query(
            "SELECT `area_id`, `legacy_table`, `attempt_count`, `last_error`
             FROM `common_sync_queue`
             WHERE `status` = 'failed' AND `processed_generation` < `requested_generation`"
        )->fetchAll(PDO::FETCH_ASSOC);
        $count = count($before);
        $dbh->exec(
            "UPDATE `common_sync_queue`
             SET `status` = 'pending', `attempt_count` = 0, `requested_at` = NOW(),
                 `started_at` = NULL, `completed_at` = NULL, `last_error` = NULL
             WHERE `status` = 'failed' AND `processed_generation` < `requested_generation`"
        );
        jyutdictMaintenanceAudit($dbh, $currentUserId, $requestId, 'sync_retry_all', 'success', null, null, $before, ['count' => $count]);
        outputJson(['success' => true, 'count' => $count]);
    }

    throw new RuntimeException('Unknown maintenance action');
} catch (Throwable $error) {
    try {
        jyutdictMaintenanceAudit(
            $dbh,
            $currentUserId,
            $requestId,
            'sync_' . ($operation ?: 'unknown'),
            'failed',
            $areaId,
            $sheetname,
            $before,
            null,
            $error->getMessage()
        );
    } catch (Throwable $auditError) {
        error_log('Unable to write maintenance audit: ' . $auditError->getMessage());
    }
    outputJson(['error' => $error->getMessage()], 400);
}
