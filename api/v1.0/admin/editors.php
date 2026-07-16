<?php
/**
 * 编纂者地点分配 API
 *
 * GET    /api/v1.0/admin/editors?editor_id={id}              → 获取某编纂者的分配地点
 * GET    /api/v1.0/admin/editors?location_name={name}        → 获取某地点的编纂者
 * GET    /api/v1.0/admin/editors?list_locations=1             → 获取所有可分配的地点列表
 * POST   /api/v1.0/admin/editors                             → 分配地点给编纂者
 *   Body: { "editor_id": 123, "location_name": "廣州" }
 * DELETE /api/v1.0/admin/editors                             → 取消分配
 *   Body: { "editor_id": 123, "location_name": "廣州" }
 */

header('Content-Type: application/json; charset=utf-8');

include_once(__DIR__ . '/../../core/db.php');
include_once(__DIR__ . '/../../core/helpers.php');
include_once(__DIR__ . '/../../middleware/auth.php');
include_once(__DIR__ . '/../../middleware/role.php');
include_once(__DIR__ . '/../../middleware/csrf.php');

requireRole('admin');



$method = $_SERVER['REQUEST_METHOD'];

// ========== GET ==========
if ($method === 'GET') {

    // 获取所有可分配的地点列表（合并去重）
    if (isset($_GET['list_locations'])) {
        try {
            $locationSet = []; // name => { name, sources: [] }

            // i_area_list 地点
            $stmt = $dbh->prepare("SELECT `first`, `second`, `third` FROM `i_area_list` WHERE `is_visible` = 1 ORDER BY `sort_order`, `id`");
            $stmt->execute();
            $areaRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($areaRows as $row) {
                $name = $row['second'] . ($row['third'] ?: '');
                if (!isset($locationSet[$name])) {
                    $locationSet[$name] = [
                        'name' => $name,
                        'first' => $row['first'],
                    ];
                }
            }

            // i_faamjyut 地点（仅 kind=1，即真正的地名）
            $stmt = $dbh->prepare("SELECT `fullname`, `fullname_note` FROM `i_faamjyut` WHERE `kind` = 1 ORDER BY `id`");
            $stmt->execute();
            $faamjyutRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($faamjyutRows as $row) {
                $name = $row['fullname'] . ($row['fullname_note'] ?: '');
                if (!isset($locationSet[$name])) {
                    $locationSet[$name] = [
                        'name' => $name,
                        'first' => '',
                    ];
                }
            }

            outputJson([
                'locations' => array_values($locationSet),
            ]);
        } catch (PDOException $e) {
            outputJson(['error' => 'Database error'], 500);
        }
    }

    // 查询某编纂者的分配地点
    if (isset($_GET['editor_id'])) {
        $editorId = (int) $_GET['editor_id'];
        try {
            $stmt = $dbh->prepare("
                SELECT el.`location_name`, el.`assigned_at`,
                       u2.`nickname` AS assigned_by_name
                FROM `editor_locations` el
                LEFT JOIN `users` u2 ON el.`assigned_by` = u2.`id`
                WHERE el.`editor_id` = :eid
                ORDER BY el.`assigned_at` DESC
            ");
            $stmt->execute([':eid' => $editorId]);
            $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            outputJson(['editor_id' => $editorId, 'locations' => $locations]);
        } catch (PDOException $e) {
            outputJson(['error' => 'Database error'], 500);
        }
    }

    // 查询某地点的编纂者
    if (isset($_GET['location_name'])) {
        $locName = $_GET['location_name'];
        try {
            $stmt = $dbh->prepare("
                SELECT el.`editor_id`, u.`nickname`, u.`email`, el.`assigned_at`
                FROM `editor_locations` el
                JOIN `users` u ON el.`editor_id` = u.`id`
                WHERE el.`location_name` = :lname
            ");
            $stmt->execute([':lname' => $locName]);
            $editors = $stmt->fetchAll(PDO::FETCH_ASSOC);
            outputJson(['location_name' => $locName, 'editors' => $editors]);
        } catch (PDOException $e) {
            outputJson(['error' => 'Database error'], 500);
        }
    }

    outputJson(['error' => 'Missing query parameters'], 400);
}

// ========== POST：分配地点 ==========
if ($method === 'POST') {
    validateCsrf();

    $input = json_decode(file_get_contents('php://input'), true);
    $editorId = (int) ($input['editor_id'] ?? 0);
    $locName = $input['location_name'] ?? '';

    if (!$editorId || !$locName) {
        outputJson(['error' => 'Missing required fields: editor_id, location_name'], 400);
    }

    try {
        // 验证目标用户是编纂者
        $stmt = $dbh->prepare("SELECT `role` FROM `users` WHERE `id` = :id");
        $stmt->execute([':id' => $editorId]);
        $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$targetUser) {
            outputJson(['error' => 'User not found'], 404);
        }
        if ($targetUser['role'] !== 'editor') {
            outputJson(['error' => 'User is not an editor. Promote to editor first.'], 400);
        }

        // 插入分配记录
        $stmt = $dbh->prepare("
            INSERT INTO `editor_locations` (`editor_id`, `location_name`, `assigned_by`)
            VALUES (:eid, :lname, :assignee)
        ");
        $stmt->execute([
            ':eid' => $editorId,
            ':lname' => $locName,
            ':assignee' => $currentUserId,
        ]);

        outputJson(['success' => true]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            outputJson(['error' => 'This location is already assigned to this editor'], 409);
        }
        outputJson(['error' => 'Database error'], 500);
    }
}

// ========== DELETE：取消分配 ==========
if ($method === 'DELETE') {
    validateCsrf();

    $input = json_decode(file_get_contents('php://input'), true);
    $editorId = (int) ($input['editor_id'] ?? 0);
    $locName = $input['location_name'] ?? '';

    if (!$editorId || !$locName) {
        outputJson(['error' => 'Missing required fields'], 400);
    }

    try {
        $stmt = $dbh->prepare("
            DELETE FROM `editor_locations`
            WHERE `editor_id` = :eid AND `location_name` = :lname
        ");
        $stmt->execute([':eid' => $editorId, ':lname' => $locName]);

        if ($stmt->rowCount() === 0) {
            outputJson(['error' => 'Assignment not found'], 404);
        }

        outputJson(['success' => true]);
    } catch (PDOException $e) {
        outputJson(['error' => 'Database error'], 500);
    }
}

outputJson(['error' => 'Method not allowed'], 405);
