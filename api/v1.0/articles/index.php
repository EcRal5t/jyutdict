<?php
/**
 * 文章 API
 *
 * GET    /api/v1.0/articles/?list=1[&search={keyword}]
 *        → 获取所有有文章的地点列表（公开，无需登录）
 *
 * GET    /api/v1.0/articles/?location_name={name}
 *        → 获取某地点的文章（公开，无需登录）
 *
 * POST   /api/v1.0/articles/
 *        → 创建或更新文章（编纂者：限已分配地点；管理员+：任意地点）
 *        Body: { "location_name": "廣州", "content": "# 标题...", "edit_summary": "初稿" }
 *
 * GET    /api/v1.0/articles/?location_name={}&versions=1
 *        → 获取文章版本历史列表
 *
 * GET    /api/v1.0/articles/?version_id={id}
 *        → 获取某个版本的完整内容
 *
 * POST   /api/v1.0/articles/?action=rollback
 *        → 回滚到指定版本（管理员+）
 *        Body: { "article_id": 1, "version_id": 5 }
 *
 * DELETE /api/v1.0/articles/?location_name={name}
 *        → 删除文章及其所有版本历史（管理员+）
 */

header('Content-Type: application/json; charset=utf-8');

include_once(__DIR__ . '/../../core/db.php');
include_once(__DIR__ . '/../../core/helpers.php');



$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ========== GET：公开读取 ==========
if ($method === 'GET') {

    // --- 获取所有有文章的地点列表 ---
    if (isset($_GET['list'])) {
        $search = $_GET['search'] ?? '';
        try {
            $sql = "
                SELECT a.`location_name`, a.`updated_at`,
                       u.`nickname` AS author_nickname,
                       LEFT(a.`content`, 200) AS excerpt
                FROM `articles` a
                JOIN `users` u ON a.`author_id` = u.`id`
            ";
            $params = [];

            if ($search) {
                $sql .= " WHERE a.`location_name` LIKE :search";
                $params[':search'] = '%' . $search . '%';
            }

            $sql .= " ORDER BY a.`updated_at` DESC";

            $stmt = $dbh->prepare($sql);
            $stmt->execute($params);
            $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 清理 excerpt：去掉 Markdown 标记的前 200 字符
            foreach ($articles as &$art) {
                $art['excerpt'] = mb_substr(
                    preg_replace('/[#*\[\]`>_~]/', '', $art['excerpt']),
                    0, 100
                );
            }

            outputJson(['articles' => $articles]);
        } catch (PDOException $e) {
            outputJson(['error' => 'Database error'], 500);
        }
    }

    // --- 获取单个版本内容 ---
    if (isset($_GET['version_id'])) {
        $versionId = (int) $_GET['version_id'];
        try {
            $stmt = $dbh->prepare("
                SELECT av.`id`, av.`article_id`, av.`content`, av.`edit_summary`, av.`created_at`,
                       u.`nickname`, u.`email`, u.`role`
                FROM `article_versions` av
                JOIN `users` u ON av.`editor_id` = u.`id`
                WHERE av.`id` = :vid
            ");
            $stmt->execute([':vid' => $versionId]);
            $version = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$version) {
                outputJson(['error' => 'Version not found'], 404);
            }
            outputJson(['version' => $version]);
        } catch (PDOException $e) {
            outputJson(['error' => 'Database error'], 500);
        }
    }

    // --- 需要 location_name ---
    $locationName = $_GET['location_name'] ?? '';

    if (!$locationName) {
        outputJson(['error' => 'Missing location_name parameter'], 400);
    }

    // --- 获取版本历史 ---
    if (isset($_GET['versions'])) {
        try {
            // 先找文章
            $stmt = $dbh->prepare("SELECT `id` FROM `articles` WHERE `location_name` = :lname");
            $stmt->execute([':lname' => $locationName]);
            $article = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$article) {
                outputJson(['versions' => []]);
            }

            $stmt = $dbh->prepare("
                SELECT av.`id`, av.`edit_summary`, av.`created_at`,
                       u.`nickname`, u.`email`, u.`role`
                FROM `article_versions` av
                JOIN `users` u ON av.`editor_id` = u.`id`
                WHERE av.`article_id` = :aid
                ORDER BY av.`created_at` DESC
            ");
            $stmt->execute([':aid' => $article['id']]);
            $versions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            outputJson(['article_id' => (int)$article['id'], 'versions' => $versions]);
        } catch (PDOException $e) {
            outputJson(['error' => 'Database error'], 500);
        }
    }

    // --- 获取文章内容 ---
    try {
        $stmt = $dbh->prepare("
            SELECT a.`id`, a.`content`, a.`updated_at`,
                   u.`nickname`, u.`email`, u.`role`
            FROM `articles` a
            JOIN `users` u ON a.`author_id` = u.`id`
            WHERE a.`location_name` = :lname
        ");
        $stmt->execute([':lname' => $locationName]);
        $article = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$article) {
            outputJson(['article' => null]); // 该地点暂无文章
        }

        outputJson(['article' => $article]);
    } catch (PDOException $e) {
        outputJson(['error' => 'Database error'], 500);
    }
}

// ========== POST：创建/更新/回滚 ==========
if ($method === 'POST') {
    include_once(__DIR__ . '/../../middleware/auth.php');
    include_once(__DIR__ . '/../../middleware/role.php');
    include_once(__DIR__ . '/../../middleware/csrf.php');
    validateCsrf();

    // --- 回滚操作 ---
    if ($action === 'rollback') {
        requireRole('admin');

        $input = json_decode(file_get_contents('php://input'), true);
        $articleId = (int) ($input['article_id'] ?? 0);
        $versionId = (int) ($input['version_id'] ?? 0);

        if (!$articleId || !$versionId) {
            outputJson(['error' => 'Missing article_id or version_id'], 400);
        }

        try {
            // 获取目标版本内容
            $stmt = $dbh->prepare("SELECT `content` FROM `article_versions` WHERE `id` = :vid AND `article_id` = :aid");
            $stmt->execute([':vid' => $versionId, ':aid' => $articleId]);
            $version = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$version) {
                outputJson(['error' => 'Version not found for this article'], 404);
            }

            $dbh->beginTransaction();

            // 更新文章内容
            $stmt = $dbh->prepare("UPDATE `articles` SET `content` = :content, `author_id` = :uid WHERE `id` = :aid");
            $stmt->execute([':content' => $version['content'], ':uid' => $currentUserId, ':aid' => $articleId]);

            // 记录回滚为新版本
            $stmt = $dbh->prepare("
                INSERT INTO `article_versions` (`article_id`, `content`, `editor_id`, `edit_summary`)
                VALUES (:aid, :content, :uid, :summary)
            ");
            $stmt->execute([
                ':aid' => $articleId,
                ':content' => $version['content'],
                ':uid' => $currentUserId,
                ':summary' => "回滚至版本 #$versionId",
            ]);

            $dbh->commit();
            outputJson(['success' => true]);
        } catch (PDOException $e) {
            $dbh->rollBack();
            outputJson(['error' => 'Database error during rollback'], 500);
        }
    }

    // --- 创建/更新文章 ---
    requireRole('editor'); // 至少是编纂者

    $input = json_decode(file_get_contents('php://input'), true);
    $locName = $input['location_name'] ?? '';
    $content = $input['content'] ?? '';
    $editSummary = $input['edit_summary'] ?? null;

    if (!$locName) {
        outputJson(['error' => 'Missing location_name'], 400);
    }

    // 权限检查：编纂者只能编辑已分配地点，管理员+可以编辑任意地点
    if (getRoleLevel($currentUserRole) < getRoleLevel('admin')) {
        $stmt = $dbh->prepare("
            SELECT COUNT(*) FROM `editor_locations`
            WHERE `editor_id` = :eid AND `location_name` = :lname
        ");
        $stmt->execute([':eid' => $currentUserId, ':lname' => $locName]);
        $assigned = $stmt->fetchColumn();

        if (!$assigned) {
            outputJson(['error' => 'You are not assigned to this location'], 403);
        }
    }

    try {
        $dbh->beginTransaction();

        // 查找已有文章
        $stmt = $dbh->prepare("SELECT `id` FROM `articles` WHERE `location_name` = :lname");
        $stmt->execute([':lname' => $locName]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // 更新
            $articleId = $existing['id'];
            $stmt = $dbh->prepare("UPDATE `articles` SET `content` = :content, `author_id` = :uid WHERE `id` = :aid");
            $stmt->execute([':content' => $content, ':uid' => $currentUserId, ':aid' => $articleId]);
        } else {
            // 新建
            $stmt = $dbh->prepare("
                INSERT INTO `articles` (`location_name`, `content`, `author_id`)
                VALUES (:lname, :content, :uid)
            ");
            $stmt->execute([':lname' => $locName, ':content' => $content, ':uid' => $currentUserId]);
            $articleId = $dbh->lastInsertId();
        }

        // 记录版本
        $stmt = $dbh->prepare("
            INSERT INTO `article_versions` (`article_id`, `content`, `editor_id`, `edit_summary`)
            VALUES (:aid, :content, :uid, :summary)
        ");
        $stmt->execute([
            ':aid' => $articleId,
            ':content' => $content,
            ':uid' => $currentUserId,
            ':summary' => $editSummary,
        ]);

        $dbh->commit();
        outputJson(['success' => true, 'article_id' => (int)$articleId]);
    } catch (PDOException $e) {
        $dbh->rollBack();
        outputJson(['error' => 'Database error'], 500);
    }
}

// ========== DELETE：删除文章（管理员+） ==========
if ($method === 'DELETE') {
    include_once(__DIR__ . '/../../middleware/auth.php');
    include_once(__DIR__ . '/../../middleware/role.php');
    include_once(__DIR__ . '/../../middleware/csrf.php');
    validateCsrf();

    requireRole('admin');

    $locationName = $_GET['location_name'] ?? '';
    if (!$locationName) {
        outputJson(['error' => 'Missing location_name parameter'], 400);
    }

    try {
        // 查找文章
        $stmt = $dbh->prepare("SELECT `id` FROM `articles` WHERE `location_name` = :lname");
        $stmt->execute([':lname' => $locationName]);
        $article = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$article) {
            outputJson(['error' => 'Article not found'], 404);
        }

        $articleId = $article['id'];

        $dbh->beginTransaction();

        // 删除版本历史
        $stmt = $dbh->prepare("DELETE FROM `article_versions` WHERE `article_id` = :aid");
        $stmt->execute([':aid' => $articleId]);

        // 删除文章
        $stmt = $dbh->prepare("DELETE FROM `articles` WHERE `id` = :aid");
        $stmt->execute([':aid' => $articleId]);

        $dbh->commit();
        outputJson(['success' => true]);
    } catch (PDOException $e) {
        $dbh->rollBack();
        outputJson(['error' => 'Database error during deletion'], 500);
    }
}

outputJson(['error' => 'Method not allowed'], 405);
