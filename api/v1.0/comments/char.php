<?php
/**
 * 字详情评论 API
 *
 * GET    /api/v1.0/comments/char?chara={字}
 *        → 获取某字的评论列表（公开，无需登录）
 *
 * POST   /api/v1.0/comments/char
 *        → 发表评论（需登录）
 *        Body: { "chara": "字", "content": "评论内容" }
 *
 * PUT    /api/v1.0/comments/char
 *        → 修改评论（仅限自己的评论）
 *        Body: { "comment_id": 1, "content": "修改后内容" }
 *
 * DELETE /api/v1.0/comments/char
 *        → 删除评论（自己的或管理员可删任何人的）
 *        Body: { "comment_id": 1 }
 *
 * GET    /api/v1.0/comments/char?comment_id={id}&versions=1
 *        → 获取某条评论的版本历史
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
                FROM `char_comment_versions` cv
                JOIN `users` u ON cv.`editor_id` = u.`id`
                WHERE cv.`comment_id` = :cid
                ORDER BY cv.`created_at` DESC
            ");
            $stmt->execute([':cid' => $commentId]);
            $versions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            outputJson(['versions' => $versions]);
        } catch (PDOException $e) {
            outputJson(['error' => 'Database error'], 500);
        }
    }

    // 评论列表
    $chara = $_GET['chara'] ?? '';
    if (!$chara) {
        outputJson(['error' => 'Missing chara parameter'], 400);
    }

    try {
        $stmt = $dbh->prepare("
            SELECT c.`id`, c.`chara`, c.`content`, c.`is_deleted`, c.`created_at`, c.`updated_at`,
                   u.`id` AS user_id, u.`nickname`, u.`email`, u.`role`
            FROM `char_comments` c
            JOIN `users` u ON c.`user_id` = u.`id`
            WHERE c.`chara` = :chara
            ORDER BY c.`created_at` ASC
        ");
        $stmt->execute([':chara' => $chara]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 对已删除的评论隐藏内容
        foreach ($comments as &$c) {
            $c['id'] = (int) $c['id'];
            $c['user_id'] = (int) $c['user_id'];
            if ($c['is_deleted']) {
                $c['content'] = null; // 前端显示"该评论已删除"
            }
        }

        outputJson(['comments' => $comments]);
    } catch (PDOException $e) {
        outputJson(['error' => 'Database error'], 500);
    }
}

// ========== POST：发表评论 ==========
if ($method === 'POST') {
    include_once(__DIR__ . '/../../middleware/auth.php');
    include_once(__DIR__ . '/../../middleware/csrf.php');
    validateCsrf();

    $input = json_decode(file_get_contents('php://input'), true);
    $chara = $input['chara'] ?? '';
    $content = trim($input['content'] ?? '');

    if (!$chara || !$content) {
        outputJson(['error' => 'Missing chara or content'], 400);
    }
    if (mb_strlen($content) > 5000) {
        outputJson(['error' => 'Comment too long (max 5000 characters)'], 400);
    }

    // XSS 防护
    $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

    try {
        $dbh->beginTransaction();

        $stmt = $dbh->prepare("
            INSERT INTO `char_comments` (`chara`, `user_id`, `content`)
            VALUES (:chara, :uid, :content)
        ");
        $stmt->execute([':chara' => $chara, ':uid' => $currentUserId, ':content' => $content]);
        $commentId = $dbh->lastInsertId();

        // 记录初始版本
        $stmt = $dbh->prepare("
            INSERT INTO `char_comment_versions` (`comment_id`, `content`, `editor_id`)
            VALUES (:cid, :content, :uid)
        ");
        $stmt->execute([':cid' => $commentId, ':content' => $content, ':uid' => $currentUserId]);

        $dbh->commit();
        outputJson(['success' => true, 'comment_id' => (int)$commentId], 201);
    } catch (PDOException $e) {
        $dbh->rollBack();
        outputJson(['error' => 'Database error'], 500);
    }
}

// ========== PUT：修改评论 ==========
if ($method === 'PUT') {
    include_once(__DIR__ . '/../../middleware/auth.php');
    include_once(__DIR__ . '/../../middleware/csrf.php');
    validateCsrf();

    $input = json_decode(file_get_contents('php://input'), true);
    $commentId = (int) ($input['comment_id'] ?? 0);
    $content = trim($input['content'] ?? '');

    if (!$commentId || !$content) {
        outputJson(['error' => 'Missing comment_id or content'], 400);
    }
    if (mb_strlen($content) > 5000) {
        outputJson(['error' => 'Comment too long'], 400);
    }

    $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

    try {
        // 验证评论所有权
        $stmt = $dbh->prepare("SELECT `user_id`, `is_deleted` FROM `char_comments` WHERE `id` = :cid");
        $stmt->execute([':cid' => $commentId]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$comment) {
            outputJson(['error' => 'Comment not found'], 404);
        }
        if ($comment['is_deleted']) {
            outputJson(['error' => 'Cannot edit a deleted comment'], 400);
        }
        if ((int)$comment['user_id'] !== $currentUserId) {
            outputJson(['error' => 'You can only edit your own comments'], 403);
        }

        $dbh->beginTransaction();

        // 更新评论内容
        $stmt = $dbh->prepare("UPDATE `char_comments` SET `content` = :content WHERE `id` = :cid");
        $stmt->execute([':content' => $content, ':cid' => $commentId]);

        // 记录版本
        $stmt = $dbh->prepare("
            INSERT INTO `char_comment_versions` (`comment_id`, `content`, `editor_id`)
            VALUES (:cid, :content, :uid)
        ");
        $stmt->execute([':cid' => $commentId, ':content' => $content, ':uid' => $currentUserId]);

        $dbh->commit();
        outputJson(['success' => true]);
    } catch (PDOException $e) {
        $dbh->rollBack();
        outputJson(['error' => 'Database error'], 500);
    }
}

// ========== DELETE：删除评论 ==========
if ($method === 'DELETE') {
    include_once(__DIR__ . '/../../middleware/auth.php');
    include_once(__DIR__ . '/../../middleware/role.php');
    include_once(__DIR__ . '/../../middleware/csrf.php');
    validateCsrf();

    $input = json_decode(file_get_contents('php://input'), true);
    $commentId = (int) ($input['comment_id'] ?? 0);

    if (!$commentId) {
        outputJson(['error' => 'Missing comment_id'], 400);
    }

    try {
        $stmt = $dbh->prepare("SELECT `user_id` FROM `char_comments` WHERE `id` = :cid AND `is_deleted` = 0");
        $stmt->execute([':cid' => $commentId]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$comment) {
            outputJson(['error' => 'Comment not found or already deleted'], 404);
        }

        // 只有作者自己或管理员+可以删除
        if ((int)$comment['user_id'] !== $currentUserId && getRoleLevel($currentUserRole) < getRoleLevel('admin')) {
            outputJson(['error' => 'You can only delete your own comments'], 403);
        }

        // 软删除
        $stmt = $dbh->prepare("UPDATE `char_comments` SET `is_deleted` = 1 WHERE `id` = :cid");
        $stmt->execute([':cid' => $commentId]);

        outputJson(['success' => true]);
    } catch (PDOException $e) {
        outputJson(['error' => 'Database error'], 500);
    }
}

outputJson(['error' => 'Method not allowed'], 405);
