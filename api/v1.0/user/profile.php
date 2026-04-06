<?php
/**
 * 用户资料 API
 *
 * GET  /api/v1.0/user/profile          → 获取当前用户完整资料
 * PUT  /api/v1.0/user/profile          → 更新昵称
 *   Body: { "nickname": "新昵称" }
 */

header('Content-Type: application/json; charset=utf-8');

include_once(__DIR__ . '/../../../connectDB.php');
include_once(__DIR__ . '/../../middleware/auth.php');
include_once(__DIR__ . '/../../middleware/csrf.php');

function outputJson($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// ========== GET：获取资料 ==========
if ($method === 'GET') {
    try {
        $stmt = $dbh->prepare("
            SELECT u.`id`, u.`email`, u.`nickname`, u.`role`, u.`created_at`,
                   GROUP_CONCAT(
                       CONCAT(el.`location_source`, ':', el.`location_id`) SEPARATOR ','
                   ) AS assigned_locations_raw
            FROM `users` u
            LEFT JOIN `editor_locations` el ON u.`id` = el.`editor_id`
            WHERE u.`id` = :id
            GROUP BY u.`id`
        ");
        $stmt->execute([':id' => $currentUserId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            outputJson(['error' => 'User not found'], 404);
        }

        // 解析 assigned_locations
        $locations = [];
        if ($user['assigned_locations_raw']) {
            foreach (explode(',', $user['assigned_locations_raw']) as $loc) {
                list($source, $locId) = explode(':', $loc);
                $locations[] = ['location_source' => $source, 'location_id' => (int)$locId];
            }
        }
        unset($user['assigned_locations_raw']);
        $user['assigned_locations'] = $locations;
        $user['id'] = (int)$user['id'];

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
