<?php
/**
 * 编纂者地点分配 API
 *
 * GET    /api/v1.0/admin/editors?editor_id={id}              → 获取某编纂者的分配地点
 * GET    /api/v1.0/admin/editors?location_source=area&location_id={id}  → 获取某地点的编纂者
 * GET    /api/v1.0/admin/editors?list_locations=1             → 获取所有可分配的地点列表
 * POST   /api/v1.0/admin/editors                             → 分配地点给编纂者
 *   Body: { "editor_id": 123, "location_source": "area", "location_id": 5 }
 * DELETE /api/v1.0/admin/editors                             → 取消分配
 *   Body: { "editor_id": 123, "location_source": "area", "location_id": 5 }
 */

header('Content-Type: application/json; charset=utf-8');

include_once(__DIR__ . '/../../../connectDB.php');
include_once(__DIR__ . '/../../middleware/auth.php');
include_once(__DIR__ . '/../../middleware/role.php');
include_once(__DIR__ . '/../../middleware/csrf.php');

requireRole('admin');

function outputJson($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// ========== GET ==========
if ($method === 'GET') {

    // 获取所有可分配的地点列表
    if (isset($_GET['list_locations'])) {
        try {
            // i_area_list 的地点
            $stmt = $dbh->prepare("SELECT `id`, `first`, `second`, `third` FROM `i_area_list` ORDER BY `id`");
            $stmt->execute();
            $areaLocations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // i_faamjyut 的地点（kind=1 表示城市类别的列）
            $stmt = $dbh->prepare("SELECT `id`, `col`, `fullname` FROM `i_faamjyut` WHERE `kind` IN (1, 2) ORDER BY `id`");
            $stmt->execute();
            $faamjyutLocations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            outputJson([
                'area_locations' => $areaLocations,
                'faamjyut_locations' => $faamjyutLocations,
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
                SELECT el.`location_source`, el.`location_id`, el.`assigned_at`,
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
    if (isset($_GET['location_source']) && isset($_GET['location_id'])) {
        $locSource = $_GET['location_source'];
        $locId = (int) $_GET['location_id'];
        try {
            $stmt = $dbh->prepare("
                SELECT el.`editor_id`, u.`nickname`, u.`email`, el.`assigned_at`
                FROM `editor_locations` el
                JOIN `users` u ON el.`editor_id` = u.`id`
                WHERE el.`location_source` = :source AND el.`location_id` = :lid
            ");
            $stmt->execute([':source' => $locSource, ':lid' => $locId]);
            $editors = $stmt->fetchAll(PDO::FETCH_ASSOC);
            outputJson(['location_source' => $locSource, 'location_id' => $locId, 'editors' => $editors]);
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
    $locSource = $input['location_source'] ?? '';
    $locId = (int) ($input['location_id'] ?? 0);

    if (!$editorId || !$locSource || !$locId) {
        outputJson(['error' => 'Missing required fields: editor_id, location_source, location_id'], 400);
    }

    if (!in_array($locSource, ['area', 'faamjyut'])) {
        outputJson(['error' => 'Invalid location_source (must be area or faamjyut)'], 400);
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
            INSERT INTO `editor_locations` (`editor_id`, `location_source`, `location_id`, `assigned_by`)
            VALUES (:eid, :source, :lid, :assignee)
        ");
        $stmt->execute([
            ':eid' => $editorId,
            ':source' => $locSource,
            ':lid' => $locId,
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
    $locSource = $input['location_source'] ?? '';
    $locId = (int) ($input['location_id'] ?? 0);

    if (!$editorId || !$locSource || !$locId) {
        outputJson(['error' => 'Missing required fields'], 400);
    }

    try {
        $stmt = $dbh->prepare("
            DELETE FROM `editor_locations`
            WHERE `editor_id` = :eid AND `location_source` = :source AND `location_id` = :lid
        ");
        $stmt->execute([':eid' => $editorId, ':source' => $locSource, ':lid' => $locId]);

        if ($stmt->rowCount() === 0) {
            outputJson(['error' => 'Assignment not found'], 404);
        }

        outputJson(['success' => true]);
    } catch (PDOException $e) {
        outputJson(['error' => 'Database error'], 500);
    }
}

outputJson(['error' => 'Method not allowed'], 405);
