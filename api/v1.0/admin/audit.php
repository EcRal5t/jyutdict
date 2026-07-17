<?php
/** Paginated maintenance audit log for administrators. */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/LocationMaintenance.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/role.php';

requireRole('admin');

try {
    jyutdictMaintenanceRequireSchema($dbh);
} catch (Throwable $error) {
    outputJson(['error' => $error->getMessage(), 'code' => 'MAINTENANCE_SCHEMA_NOT_INSTALLED'], 503);
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(100, max(1, (int)($_GET['per_page'] ?? 30)));
$offset = ($page - 1) * $perPage;
$action = trim((string)($_GET['action'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$where = [];
$params = [];
if ($action !== '') {
    $where[] = 'e.`action` = ?';
    $params[] = $action;
}
if (in_array($status, ['success', 'failed'], true)) {
    $where[] = 'e.`status` = ?';
    $params[] = $status;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = $dbh->prepare("SELECT COUNT(*) FROM `admin_maintenance_events` AS e {$whereSql}");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$stmt = $dbh->prepare(
    "SELECT e.`id`, e.`user_id`, u.`nickname`, u.`email`, e.`request_id`, e.`action`,
            e.`area_id`, e.`sheetname`, e.`status`, e.`before_json`, e.`after_json`,
            e.`error_message`, e.`created_at`
     FROM `admin_maintenance_events` AS e
     LEFT JOIN `users` AS u ON u.`id` = e.`user_id`
     {$whereSql}
     ORDER BY e.`id` DESC LIMIT {$perPage} OFFSET {$offset}"
);
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($events as &$event) {
    $event['id'] = (int)$event['id'];
    $event['user_id'] = $event['user_id'] === null ? null : (int)$event['user_id'];
    $event['area_id'] = $event['area_id'] === null ? null : (int)$event['area_id'];
    foreach (['before_json', 'after_json'] as $field) {
        $event[$field] = $event[$field] === null ? null : json_decode($event[$field], true);
    }
}
unset($event);
outputJson([
    'events' => $events,
    'pagination' => [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => (int)ceil($total / $perPage),
    ],
]);
