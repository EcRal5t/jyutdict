<?php
/**
 * 认证中间件
 *
 * 在需要登录的 API 端点中引入此文件。
 * 引入后，如果用户未登录，会直接返回 401 错误并终止脚本。
 * 如果已登录，会设置以下变量供后续使用：
 * - $currentUserId (int)
 * - $currentUserRole (string: 'user'|'editor'|'admin'|'owner')
 *
 * 用法：
 *   include_once(__DIR__ . '/../middleware/auth.php');
 *   // 到达这里说明已登录，可使用 $currentUserId 和 $currentUserRole
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: Please login first'], JSON_UNESCAPED_UNICODE);
    exit;
}

$currentUserId = (int) $_SESSION['user_id'];

// 从数据库获取最新角色（而非 Session 缓存），确保角色变更即时生效
global $dbh;
$stmt = $dbh->prepare("SELECT `role` FROM `users` WHERE `id` = :id");
$stmt->execute([':id' => $currentUserId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$currentUserRole = $user ? $user['role'] : 'user';

// 同步更新 Session
$_SESSION['user_role'] = $currentUserRole;
