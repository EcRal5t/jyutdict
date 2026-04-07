<?php
/**
 * Google OAuth 认证端点
 *
 * 流程：
 * 1. 前端引导用户跳转到 Google 授权页面
 * 2. Google 授权后重定向到此端点（action=callback）
 * 3. 此端点用 authorization code 换取 access_token
 * 4. 用 access_token 获取用户信息
 * 5. 在数据库中查找或创建用户
 * 6. 创建 session，重定向回前端
 *
 * 用法：
 * - GET /api/auth/google                    → 跳转到 Google 授权页
 * - GET /api/auth/google?action=callback    → Google 回调处理
 */

// 先加载配置（session_start 之前需要知道 session_lifetime）
include_once(__DIR__ . '/../../connectDB.php');
$config = require(__DIR__ . '/../config/oauth.php');

// 设置 Session 参数（必须在 session_start 之前）
$lifetime = $config['session_lifetime'] ?? 604800;
ini_set('session.gc_maxlifetime', $lifetime);
session_set_cookie_params([
    'lifetime' => $lifetime,
    'path'     => '/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? 'login';

// ========== 辅助函数 ==========

function outputJson($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function httpPost($url, $data, $proxy = '') {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    // SSL 配置（开发环境）
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    // 代理配置
    if (!empty($proxy)) {
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
    }
    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['error' => 'curl_error', 'message' => $error];
    }
    curl_close($ch);
    $decoded = json_decode($response, true);
    if ($decoded === null) {
        return ['error' => 'json_decode_error', 'raw' => $response];
    }
    return $decoded;
}

function httpGet($url, $headers = [], $proxy = '') {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    // 代理配置
    if (!empty($proxy)) {
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
    }
    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['error' => 'curl_error', 'message' => $error];
    }
    curl_close($ch);
    $decoded = json_decode($response, true);
    if ($decoded === null) {
        return ['error' => 'json_decode_error', 'raw' => $response];
    }
    return $decoded;
}

// ========== action=login：跳转到 Google ==========

if ($action === 'login') {
    // 生成 CSRF state token
    $state = bin2hex(random_bytes(32));
    $_SESSION['oauth_state'] = $state;

    $params = http_build_query([
        'client_id'     => $config['client_id'],
        'redirect_uri'  => $config['redirect_uri'],
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'access_type'   => 'online',
        'prompt'        => 'select_account',
    ]);

    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
    exit;
}

// ========== action=callback：处理 Google 回调 ==========

if ($action === 'callback') {
    // 1. 验证 state 防 CSRF
    if (!isset($_GET['state']) || !isset($_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
        outputJson(['error' => 'Invalid state parameter (CSRF check failed)'], 403);
    }
    unset($_SESSION['oauth_state']);

    // 2. 检查错误
    if (isset($_GET['error'])) {
        // 用户拒绝授权等情况，重定向回前端
        header('Location: ' . $config['frontend_url'] . '?auth_error=' . urlencode($_GET['error']));
        exit;
    }

    // 3. 用 code 换取 access_token
    $code = $_GET['code'] ?? '';
    if (!$code) {
        outputJson(['error' => 'Missing authorization code'], 400);
    }

    $proxy = $config['proxy'] ?? '';
    $tokenResponse = httpPost('https://oauth2.googleapis.com/token', [
        'code'          => $code,
        'client_id'     => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'redirect_uri'  => $config['redirect_uri'],
        'grant_type'    => 'authorization_code',
    ], $proxy);

    if (!isset($tokenResponse['access_token'])) {
        outputJson(['error' => 'Failed to exchange code for token', 'detail' => $tokenResponse], 500);
    }

    $accessToken = $tokenResponse['access_token'];

    // 4. 用 access_token 获取用户信息
    $userInfo = httpGet(
        'https://www.googleapis.com/oauth2/v2/userinfo',
        ['Authorization: Bearer ' . $accessToken],
        $proxy
    );

    if (!isset($userInfo['id'])) {
        outputJson(['error' => 'Failed to get user info from Google'], 500);
    }

    $googleId = $userInfo['id'];
    $email = $userInfo['email'] ?? '';
    $name = $userInfo['name'] ?? '';

    // 5. 查找或创建用户
    try {
        $stmt = $dbh->prepare("SELECT `id`, `nickname`, `role` FROM `users` WHERE `google_id` = :google_id");
        $stmt->execute([':google_id' => $googleId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // 已有用户，更新登录信息
            $userId = $user['id'];
        } else {
            // 新用户，自动注册
            $stmt = $dbh->prepare("INSERT INTO `users` (`google_id`, `email`, `nickname`, `role`) VALUES (:google_id, :email, :nickname, 'user')");
            $stmt->execute([
                ':google_id' => $googleId,
                ':email'     => $email,
                ':nickname'  => $name ?: null,
            ]);
            $userId = $dbh->lastInsertId();

            // 重新查询获取完整用户信息
            $stmt = $dbh->prepare("SELECT `id`, `nickname`, `role` FROM `users` WHERE `id` = :id");
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        outputJson(['error' => 'Database error during user lookup/creation'], 500);
    }

    // 6. 设置 Session
    session_regenerate_id(true); // 防 Session Fixation
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['login_time'] = time();

    // 7. 重定向回前端
    header('Location: ' . $config['frontend_url'] . '?auth_success=1');
    exit;
}

outputJson(['error' => 'Unknown action'], 400);
