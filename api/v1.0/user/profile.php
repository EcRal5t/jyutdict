<?php
/**
 * 用户资料 API
 *
 * GET  /api/v1.0/user/profile          → 获取当前用户完整资料
 * PUT  /api/v1.0/user/profile          → 更新昵称
 *   Body: { "nickname": "新昵称" }
 */

header('Content-Type: application/json; charset=utf-8');

include_once(__DIR__ . '/../../core/db.php');
include_once(__DIR__ . '/../../core/helpers.php');
include_once(__DIR__ . '/../../middleware/auth.php');
include_once(__DIR__ . '/../../middleware/csrf.php');



$method = $_SERVER['REQUEST_METHOD'];

// ========== GET：获取资料 ==========
if ($method === 'GET') {
    try {
        $stmt = $dbh->prepare("
            SELECT `id`, `email`, `nickname`, `role`, `created_at`
            FROM `users`
            WHERE `id` = :id
        ");
        $stmt->execute([':id' => $currentUserId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            outputJson(['error' => 'User not found'], 404);
        }

        $user['id'] = (int)$user['id'];

        // 获取编纂者分配的地点
        $locations = [];
        if ($user['role'] === 'editor') {
            $stmt2 = $dbh->prepare("SELECT `location_name` FROM `editor_locations` WHERE `editor_id` = :eid");
            $stmt2->execute([':eid' => $currentUserId]);
            $locations = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        }
        $user['assigned_locations'] = $locations;

        // 获取评论计数
        $stmt = $dbh->prepare("SELECT COUNT(*) FROM `char_comments` WHERE `user_id` = :uid AND `is_deleted` = 0");
        $stmt->execute([':uid' => $currentUserId]);
        $charCommentCount = $stmt->fetchColumn();

        $stmt = $dbh->prepare("SELECT COUNT(*) FROM `sheet_comments` WHERE `user_id` = :uid AND `is_deleted` = 0");
        $stmt->execute([':uid' => $currentUserId]);
        $sheetCommentCount = $stmt->fetchColumn();

        $user['comment_count'] = (int)$charCommentCount + (int)$sheetCommentCount;

        outputJson(['user' => $user]);
    } catch (PDOException $e) {
        outputJson(['error' => 'Database error'], 500);
    }
}

// ========== PUT：更新昵称 ==========
if ($method === 'PUT') {
    validateCsrf();

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['nickname'])) {
        outputJson(['error' => 'Missing nickname field'], 400);
    }

    $nickname = trim($input['nickname']);

    // 验证昵称
    if (mb_strlen($nickname) < 1 || mb_strlen($nickname) > 50) {
        outputJson(['error' => 'Nickname must be 1-50 characters'], 400);
    }

    // 检查敏感字符（基本 XSS 防护）
    $nickname = htmlspecialchars($nickname, ENT_QUOTES, 'UTF-8');

    try {
        $stmt = $dbh->prepare("UPDATE `users` SET `nickname` = :nickname WHERE `id` = :id");
        $stmt->execute([':nickname' => $nickname, ':id' => $currentUserId]);

        outputJson(['success' => true, 'nickname' => $nickname]);
    } catch (PDOException $e) {
        outputJson(['error' => 'Database error'], 500);
    }
}

outputJson(['error' => 'Method not allowed'], 405);
