<?php
/**
 * 用户登出
 *
 * POST /api/auth/logout
 *
 * 响应：{ "success": true }
 */

include_once(__DIR__ . '/../core/session.php');
startAppSession();
header('Content-Type: application/json; charset=utf-8');

// 清除所有 Session 数据
$_SESSION = [];

// 删除 Session Cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'] ?? '/',
        'domain' => $params['domain'] ?? '',
        'secure' => $params['secure'] ?? false,
        'httponly' => $params['httponly'] ?? true,
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}

session_destroy();

echo json_encode(['success' => true]);
