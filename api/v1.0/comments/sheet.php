<?php
/**
 * 字表评论 API
 *
 * GET    /api/v1.0/comments/sheet?key={鍵}
 *        → 获取某条目的评论列表
 *
 * POST   /api/v1.0/comments/sheet
 *        Body: { "sheet_key": ">12345679123456", "content": "评论内容" }
 *
 * PUT    /api/v1.0/comments/sheet
 *        Body: { "comment_id": 1, "content": "修改后内容" }
 *
 * DELETE /api/v1.0/comments/sheet
 *        Body: { "comment_id": 1 }
 *
 * GET    /api/v1.0/comments/sheet?comment_id={id}&versions=1
 *        → 版本历史
 */

header('Content-Type: application/json; charset=utf-8');

include_once(__DIR__ . '/../../../connectDB.php');

function outputJson($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// ========== GET ==========
if ($method === 'GET') {

    // 版本历史
    if (isset($_GET['comment_id']) && isset($_GET['versions'])) {
        $commentId = (int) $_GET['comment_id'];
        try {
            $stmt = $dbh->prepare("
                SELECT cv.`id`, cv.`content`, cv.`created_at`,
                       u.`nickname`, u.`email`
                FROM `sheet_comment_versions` cv
                JOIN `users` u ON cv.`editor_id` = u.`id`
                WHERE cv.`comment_id` = :cid
                ORDER BY cv.`created_at` DESC
            ");
            $stmt->execute([':cid' => $commentId]);
            outputJson(['versions' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            outputJson(['error' => 'Database error'], 500);
        }
    }

    // 评论列表
    $sheetKey = $_GET['key'] ?? '';
    if (!$sheetKey) {
        outputJson(['error' => 'Missing key parameter'], 400);
    }

    try {
        $stmt = $dbh->prepare("
            SELECT c.`id`, c.`sheet_key`, c.`content`, c.`is_deleted`, c.`created_at`, c.`updated_at`,
                   u.`id` AS user_id, u.`nickname`, u.`email`, u.`role`
            FROM `sheet_comments` c
            JOIN `users` u ON c.`user_id` = u.`id`
            WHERE c.`sheet_key` = :skey
            ORDER BY c.`created_at` ASC
        ");
        $stmt->execute([':skey' => $sheetKey]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($comments as &$c) {
            $c['id'] = (int) $c['id'];
            $c['user_id'] = (int) $c['user_id'];
            if ($c['is_deleted']) $c['content'] = null;
        }

        outputJson(['comments' => $comments]);
    } catch (PDOException $e) {
        outputJson(['error' => 'Database error'], 500);
    }
}

// ========== POST ==========
if ($method === 'POST') {
    include_once(__DIR__ . '/../../middleware/auth.php');
    include_once(__DIR__ . '/../../middleware/csrf.php');
    validateCsrf();

    $input = json_decode(file_get_contents('php://input'), true);
    $sheetKey = $input['sheet_key'] ?? '';
    $content = trim($input['content'] ?? '');

    if (!$sheetKey || !$content) {
        outputJson(['error' => 'Missing sheet_key or content'], 400);
    }
    if (mb_strlen($content) > 5000) {
        outputJson(['error' => 'Comment too long'], 400);
    }

    $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

    try {
        $dbh->beginTransaction();

        $stmt = $dbh->prepare("INSERT INTO `sheet_comments` (`sheet_key`, `user_id`, `content`) VALUES (:skey, :uid, :content)");
        $stmt->execute([':skey' => $sheetKey, ':uid' => $currentUserId, ':content' => $content]);
        $commentId = $dbh->lastInsertId();

        $stmt = $dbh->prepare("INSERT INTO `sheet_comment_versions` (`comment_id`, `content`, `editor_id`) VALUES (:cid, :content, :uid)");
        $stmt->execute([':cid' => $commentId, ':content' => $content, ':uid' => $currentUserId]);

        $dbh->commit();
        outputJson(['success' => true, 'comment_id' => (int)$commentId], 201);
    } catch (PDOException $e) {
        $dbh->rollBack();
        outputJson(['error' => 'Database error'], 500);
    }
}

// ========== PUT ==========
if ($method === 'PUT') {
    include_once(__DIR__ . '/../../middleware/auth.php');
    include_once(__DIR__ . '/../../middleware/csrf.php');
    validateCsrf();

    $input = json_decode(file_get_contents('php://input'), true);
    $commentId = (int) ($input['comment_id'] ?? 0);
    $content = trim($input['content'] ?? '');

    if (!$commentId || !$content) outputJson(['error' => 'Missing comment_id or content'], 400);
    if (mb_strlen($content) > 5000) outputJson(['error' => 'Comment too long'], 400);

    $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

    try {
        $stmt = $dbh->prepare("SELECT `user_id`, `is_deleted` FROM `sheet_comments` WHERE `id` = :cid");
        $stmt->execute([':cid' => $commentId]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$comment) outputJson(['error' => 'Comment not found'], 404);
        if ($comment['is_deleted']) outputJson(['error' => 'Cannot edit deleted comment'], 400);
        if ((int)$comment['user_id'] !== $currentUserId) outputJson(['error' => 'Can only edit your own comments'], 403);

        $dbh->beginTransaction();
        $stmt = $dbh->prepare("UPDATE `sheet_comments` SET `content` = :content WHERE `id` = :cid");
        $stmt->execute([':content' => $content, ':cid' => $commentId]);

        $stmt = $dbh->prepare("INSERT INTO `sheet_comment_versions` (`comment_id`, `content`, `editor_id`) VALUES (:cid, :content, :uid)");
        $stmt->execute([':cid' => $commentId, ':content' => $content, ':uid' => $currentUserId]);

        $dbh->commit();
        outputJson(['success' => true]);
    } catch (PDOException $e) {
        $dbh->rollBack();
        outputJson(['error' => 'Database error'], 500);
    }
}

// ========== DELETE ==========
if ($method === 'DELETE') {
    include_once(__DIR__ . '/../../middleware/auth.php');
    include_once(__DIR__ . '/../../middleware/role.php');
    include_once(__DIR__ . '/../../middleware/csrf.php');
    validateCsrf();

    $input = json_decode(file_get_contents('php://input'), true);
    $commentId = (int) ($input['comment_id'] ?? 0);
    if (!$commentId) outputJson(['error' => 'Missing comment_id'], 400);

    try {
        $stmt = $dbh->prepare("SELECT `user_id` FROM `sheet_comments` WHERE `id` = :cid AND `is_deleted` = 0");
        $stmt->execute([':cid' => $commentId]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$comment) outputJson(['error' => 'Comment not found or already deleted'], 404);

        if ((int)$comment['user_id'] !== $currentUserId && getRoleLevel($currentUserRole) < getRoleLevel('admin')) {
            outputJson(['error' => 'Cannot delete others\' comments'], 403);
        }

        $stmt = $dbh->prepare("UPDATE `sheet_comments` SET `is_deleted` = 1 WHERE `id` = :cid");
        $stmt->execute([':cid' => $commentId]);
        outputJson(['success' => true]);
    } catch (PDOException $e) {
        outputJson(['error' => 'Database error'], 500);
    }
}

outputJson(['error' => 'Method not allowed'], 405);
