<?php
/**
 * 当前用户的评论列表
 *
 * GET /api/v1.0/user/comments?page=1&per_page=20
 * → 返回当前用户的所有评论（字评论 + 字表评论合并，按时间倒序）
 */

header('Content-Type: application/json; charset=utf-8');

include_once(__DIR__ . '/../../../connectDB.php');
include_once(__DIR__ . '/../../middleware/auth.php');

function outputJson($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
$offset = ($page - 1) * $perPage;

try {
    // 合并查询字评论和字表评论
    $stmt = $dbh->prepare("
        (SELECT 'char' AS type, `id`, `chara` AS target, `content`, `is_deleted`, `created_at`, `updated_at`
         FROM `char_comments` WHERE `user_id` = :uid1)
        UNION ALL
        (SELECT 'sheet' AS type, `id`, `sheet_key` AS target, `content`, `is_deleted`, `created_at`, `updated_at`
         FROM `sheet_comments` WHERE `user_id` = :uid2)
        ORDER BY `created_at` DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute([':uid1' => $currentUserId, ':uid2' => $currentUserId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 总数
    $stmt = $dbh->prepare("
        SELECT
            (SELECT COUNT(*) FROM `char_comments` WHERE `user_id` = :uid1) +
            (SELECT COUNT(*) FROM `sheet_comments` WHERE `user_id` = :uid2) AS total
    ");
    $stmt->execute([':uid1' => $currentUserId, ':uid2' => $currentUserId]);
    $total = (int) $stmt->fetchColumn();

    outputJson([
        'comments' => $comments,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        ]
    ]);
} catch (PDOException $e) {
    outputJson(['error' => 'Database error'], 500);
}
