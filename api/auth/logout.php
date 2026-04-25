<?php
/**
 * 用户登出
 *
 * POST /api/auth/logout
 *
 * 响应：{ "success": true }
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// 清除所有 Session 数据
$_SESSION = [];

// 删除 Session Cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

echo json_encode(['success' => true]);
