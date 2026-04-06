<?php
/**
 * 管理员用户管理 API
 *
 * GET  /api/v1.0/admin/users                      → 用户列表（分页、搜索）
 *   Query: page=1, per_page=20, search=关键词, role=user|editor|admin|owner
 *
 * GET  /api/v1.0/admin/users?id={userId}           → 用户详情
 *
 * PUT  /api/v1.0/admin/users                       → 更新用户角色
 *   Body: { "user_id": 123, "new_role": "editor" }
 */

header('Content-Type: application/json; charset=utf-8');

include_once(__DIR__ . '/../../../connectDB.php');
include_once(__DIR__ . '/../../middleware/auth.php');
include_once(__DIR__ . '/../../middleware/role.php');
include_once(__DIR__ . '/../../middleware/csrf.php');

function outputJson($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// ========== GET：用户列表或详情 ==========
if ($method === 'GET') {
    requireRole('admin'); // 管理员及以上

    // 如果指定了 id，返回单个用户详情
    if (isset($_GET['id'])) {
        $targetId = (int) $_GET['id'];
        try {
            $stmt = $dbh->prepare("SELECT `id`, `email`, `nickname`, `role`, `created_at` FROM `users` WHERE `id` = :id");
            $stmt->execute([':id' => $targetId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                outputJson(['error' => 'User not found'], 404);
            }

            $user['id'] = (int)$user['id'];

            // 获取评论历史（最近 50 条）
            $stmt = $dbh->prepare("
                (SELECT 'char' AS type, `id`, `chara` AS target, `content`, `created_at` FROM `char_comments` WHERE `user_id` = :uid AND `is_deleted` = 0 ORDER BY `created_at` DESC LIMIT 50)
                UNION ALL
                (SELECT 'sheet' AS type, `id`, `sheet_key` AS target, `content`, `created_at` FROM `sheet_comments` WHERE `user_id` = :uid2 AND `is_deleted` = 0 ORDER BY `created_at` DESC LIMIT 50)
                ORDER BY `created_at` DESC LIMIT 50
            ");
            $stmt->execute([':uid' => $targetId, ':uid2' => $targetId]);
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 获取分配的地点
            $stmt = $dbh->prepare("SELECT `location_source`, `location_id`, `assigned_at` FROM `editor_locations` WHERE `editor_id` = :eid");
            $stmt->execute([':eid' => $targetId]);
            $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $user['recent_comments'] = $comments;
            $user['assigned_locations'] = $locations;

            outputJson(['user' => $user]);
        } catch (PDOException $e) {
            outputJson(['error' => 'Database error'], 500);
        }
    }

    // 用户列表
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;
    $search = $_GET['search'] ?? '';
    $roleFilter = $_GET['role'] ?? '';

    try {
        $where = [];
        $params = [];

        if ($search) {
            $where[] = "(`email` LIKE :search OR `nickname` LIKE :search2)";
            $params[':search'] = "%$search%";
            $params[':search2'] = "%$search%";
        }
        if ($roleFilter && in_array($roleFilter, ['user', 'editor', 'admin', 'owner'])) {
            $where[] = "`role` = :role";
            $params[':role'] = $roleFilter;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // 总数
        $stmt = $dbh->prepare("SELECT COUNT(*) FROM `users` $whereClause");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // 分页查询
        $stmt = $dbh->prepare("
            SELECT `id`, `email`, `nickname`, `role`, `created_at`
            FROM `users`
            $whereClause
            ORDER BY `created_at` DESC
            LIMIT $perPage OFFSET $offset
        ");
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 转换 id 为 int
        foreach ($users as &$u) {
            $u['id'] = (int) $u['id'];
        }

        outputJson([
            'users' => $users,
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
}

// ========== PUT：更新用户角色 ==========
if ($method === 'PUT') {
    requireRole('admin');
    validateCsrf();

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['user_id']) || !isset($input['new_role'])) {
        outputJson(['error' => 'Missing user_id or new_role'], 400);
    }

    $targetUserId = (int) $input['user_id'];
    $newRole = $input['new_role'];

    // 验证角色值
    $validRoles = ['user', 'editor', 'admin', 'owner'];
    if (!in_array($newRole, $validRoles)) {
        outputJson(['error' => 'Invalid role'], 400);
    }

    // 权限限制
    // 1. 不能修改自己的角色
    if ($targetUserId === $currentUserId) {
        outputJson(['error' => 'Cannot change your own role'], 403);
    }

    // 2. 只有 Owner 可以设置/撤销管理员和 Owner
    if (in_array($newRole, ['admin', 'owner']) && !isOwner()) {
        outputJson(['error' => 'Only Owner can set admin/owner roles'], 403);
    }

    // 3. 管理员不能修改 Owner 或其他管理员的角色
    try {
        $stmt = $dbh->prepare("SELECT `role` FROM `users` WHERE `id` = :id");
        $stmt->execute([':id' => $targetUserId]);
        $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$targetUser) {
            outputJson(['error' => 'Target user not found'], 404);
        }

        $targetCurrentRole = $targetUser['role'];

        // 管理员不能动管理员和 Owner
        if (!isOwner() && in_array($targetCurrentRole, ['admin', 'owner'])) {
            outputJson(['error' => 'Only Owner can modify admin/owner users'], 403);
        }

        // 不能将 Owner 角色赋予他人（Owner 只有一个，通过数据库手动设置）
        if ($newRole === 'owner') {
            outputJson(['error' => 'Owner can only be set via database'], 403);
        }

        // 执行更新
        $stmt = $dbh->prepare("UPDATE `users` SET `role` = :role WHERE `id` = :id");
        $stmt->execute([':role' => $newRole, ':id' => $targetUserId]);

        // 如果降级为普通用户，清除其所有地点分配
        if ($newRole === 'user') {
            $stmt = $dbh->prepare("DELETE FROM `editor_locations` WHERE `editor_id` = :eid");
            $stmt->execute([':eid' => $targetUserId]);
        }

        outputJson(['success' => true, 'user_id' => $targetUserId, 'new_role' => $newRole]);
    } catch (PDOException $e) {
        outputJson(['error' => 'Database error'], 500);
    }
}

outputJson(['error' => 'Method not allowed'], 405);
