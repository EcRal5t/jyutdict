<?php
/**
 * 获取当前登录用户信息
 *
 * GET /api/auth/me
 *
 * 响应：
 * - 已登录：{ "user": { "id", "email", "nickname", "role" }, "csrf_token": "..." }
 * - 未登录：{ "user": null }
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

include_once(__DIR__ . '/../../connectDB.php');
include_once(__DIR__ . '/../middleware/csrf.php');

function outputJson($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// 检查 Session
if (!isset($_SESSION['user_id'])) {
    outputJson(['user' => null]);
}

$userId = $_SESSION['user_id'];

try {
    $stmt = $dbh->prepare("SELECT `id`, `email`, `nickname`, `role`, `created_at` FROM `users` WHERE `id` = :id");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Session 中的用户 ID 在数据库中不存在（被删除？）
        session_destroy();
        outputJson(['user' => null]);
    }

    // 如果是编纂者，附带负责地点列表
    $assignedLocations = [];
    if ($user['role'] === 'editor') {
        $stmt = $dbh->prepare("SELECT `location_source`, `location_name` FROM `editor_locations` WHERE `editor_id` = :eid");
        $stmt->execute([':eid' => $userId]);
        $assignedLocations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    outputJson([
        'user' => [
            'id'       => (int) $user['id'],
            'email'    => $user['email'],
            'nickname' => $user['nickname'],
            'role'     => $user['role'],
            'created_at' => $user['created_at'],
            'assigned_locations' => $assignedLocations,
        ],
        'csrf_token' => generateCsrfToken(),
    ]);

} catch (PDOException $e) {
    outputJson(['error' => 'Database error'], 500);
}
