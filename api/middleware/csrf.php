<?php
/**
 * CSRF Token 中间件
 *
 * 对于 POST/PUT/DELETE 请求，验证请求头中的 CSRF Token。
 * 前端需要在每个写入请求的 Header 中携带：X-CSRF-Token
 *
 * Token 在用户登录时生成，存于 Session 中，前端通过 /api/auth/me 获取。
 *
 * 用法（在需要 CSRF 保护的端点中引入）：
 *   include_once(__DIR__ . '/../middleware/csrf.php');
 *   validateCsrf();
 */

function generateCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        include_once(__DIR__ . '/../core/session.php');
        startAppSession();
    }
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrf() {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // 只对写入操作验证
    if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
        return;
    }

    if (session_status() === PHP_SESSION_NONE) {
        include_once(__DIR__ . '/../core/session.php');
        startAppSession();
    }

    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (!$token || !$sessionToken || !hash_equals($sessionToken, $token)) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF token validation failed'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
