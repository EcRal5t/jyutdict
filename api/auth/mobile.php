<?php
/**
 * Native Android Google sign-in bridge.
 *
 * GET  /api/auth/mobile -> returns a short-lived nonce bound to PHP Session.
 * POST /api/auth/mobile -> verifies {"id_token":"..."}, signs the matching
 *                          Google user into that session, and returns user/CSRF.
 */

include_once(__DIR__ . '/../core/session.php');
include_once(__DIR__ . '/../core/helpers.php');

startAppSession();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $nonce = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $_SESSION['mobile_auth_nonce'] = $nonce;
    $_SESSION['mobile_auth_nonce_created_at'] = time();
    outputJson(['nonce' => $nonce]);
}

if ($method !== 'POST') {
    outputJson(['error' => 'Method not allowed'], 405);
}

$nonce = $_SESSION['mobile_auth_nonce'] ?? '';
$nonceCreatedAt = (int)($_SESSION['mobile_auth_nonce_created_at'] ?? 0);
unset($_SESSION['mobile_auth_nonce'], $_SESSION['mobile_auth_nonce_created_at']);
if (!is_string($nonce) || $nonce === '' || $nonceCreatedAt < time() - 300) {
    outputJson(['error' => 'Login challenge missing or expired'], 400);
}

$input = json_decode(file_get_contents('php://input'), true);
$idToken = is_array($input) ? ($input['id_token'] ?? '') : '';
if (!is_string($idToken) || $idToken === '') {
    outputJson(['error' => 'Missing ID token'], 400);
}

$config = jyutdictLoadAuthConfig();
$expectedAudience = trim((string)($config['client_id'] ?? ''));
if ($expectedAudience === '') {
    error_log('[MOBILE-AUTH] Missing OAuth client_id configuration');
    outputJson(['error' => 'Authentication is not configured'], 503);
}

include_once(__DIR__ . '/../core/GoogleIdTokenVerifier.php');
include_once(__DIR__ . '/../core/GoogleUserAuth.php');
include_once(__DIR__ . '/../middleware/csrf.php');
include_once(__DIR__ . '/../core/db.php');

try {
    $claims = jyutdictVerifyGoogleIdToken($idToken, $expectedAudience, $nonce);
    $user = jyutdictFindOrCreateGoogleUser(
        $dbh,
        $claims['sub'],
        $claims['email'],
        $claims['name'] ?? ''
    );

    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['login_time'] = time();

    outputJson([
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'nickname' => $user['nickname'],
            'role' => $user['role'],
            'created_at' => $user['created_at'],
            'assigned_locations' => [],
        ],
        'csrf_token' => generateCsrfToken(),
    ]);
} catch (JyutdictGoogleIdTokenException $exception) {
    error_log('[MOBILE-AUTH] ID token rejected: ' . $exception->getMessage());
    outputJson(['error' => 'Google sign-in could not be verified'], 401);
} catch (Throwable $exception) {
    error_log('[MOBILE-AUTH] Login failed: ' . $exception->getMessage());
    outputJson(['error' => 'Unable to complete sign-in'], 500);
}
