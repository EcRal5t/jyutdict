<?php
/**
 * Shared session and OAuth state helpers.
 */

function jyutdictLoadAuthConfig() {
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $path = __DIR__ . '/../config/oauth.php';
    $config = file_exists($path) ? require($path) : [];
    return is_array($config) ? $config : [];
}

function jyutdictRequestScheme() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        return strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
    }
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return 'https';
    }
    if (($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return 'https';
    }
    return 'http';
}

function jyutdictRequestOrigin() {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return jyutdictRequestScheme() . '://' . $host;
}

function jyutdictSessionLifetime($config = null) {
    $config = $config ?? jyutdictLoadAuthConfig();
    return max(0, (int)($config['session_lifetime'] ?? 604800));
}

function jyutdictCookieSecure($config = null) {
    $config = $config ?? jyutdictLoadAuthConfig();
    $redirectScheme = !empty($config['redirect_uri']) ? parse_url($config['redirect_uri'], PHP_URL_SCHEME) : null;
    return $redirectScheme === 'https' || jyutdictRequestScheme() === 'https';
}

function jyutdictSessionCookieParams($config = null) {
    return [
        'lifetime' => jyutdictSessionLifetime($config),
        'path' => '/',
        'secure' => jyutdictCookieSecure($config),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function jyutdictSetCookieOptions($config = null, $expires = 0, $path = '/') {
    return [
        'expires' => $expires,
        'path' => $path,
        'secure' => jyutdictCookieSecure($config),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function startAppSession($config = null) {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $config = $config ?? jyutdictLoadAuthConfig();
    $lifetime = jyutdictSessionLifetime($config);
    ini_set('session.gc_maxlifetime', (string)$lifetime);
    session_set_cookie_params(jyutdictSessionCookieParams($config));
    session_start();
}

function jyutdictOAuthStateLifetime($config = null) {
    $config = $config ?? jyutdictLoadAuthConfig();
    return max(60, (int)($config['oauth_state_lifetime'] ?? 600));
}

function jyutdictOAuthStateCookieName($state) {
    return 'oauth_state_' . substr(hash('sha256', (string)$state), 0, 16);
}

function jyutdictOAuthStateCookieValue($state, $config = null) {
    $config = $config ?? jyutdictLoadAuthConfig();
    $secret = (string)($config['oauth_state_secret'] ?? $config['client_secret'] ?? '');
    if ($secret === '') {
        $secret = __DIR__;
    }
    return hash_hmac('sha256', (string)$state, $secret);
}

function jyutdictCleanupOAuthStates($config = null) {
    if (!isset($_SESSION['oauth_states']) || !is_array($_SESSION['oauth_states'])) {
        $_SESSION['oauth_states'] = [];
        return;
    }

    $oldestValid = time() - jyutdictOAuthStateLifetime($config);
    foreach ($_SESSION['oauth_states'] as $state => $createdAt) {
        if ((int)$createdAt < $oldestValid) {
            unset($_SESSION['oauth_states'][$state]);
        }
    }
}

function rememberOAuthState($state, $config = null) {
    $config = $config ?? jyutdictLoadAuthConfig();
    startAppSession($config);
    jyutdictCleanupOAuthStates($config);

    $_SESSION['oauth_states'][$state] = time();
    $_SESSION['oauth_state'] = $state;

    setcookie(
        jyutdictOAuthStateCookieName($state),
        jyutdictOAuthStateCookieValue($state, $config),
        jyutdictSetCookieOptions($config, time() + jyutdictOAuthStateLifetime($config), '/api/auth/google')
    );
}

function validateOAuthState($state, $config = null, &$reason = '') {
    $config = $config ?? jyutdictLoadAuthConfig();
    startAppSession($config);

    if (!is_string($state) || $state === '') {
        $reason = 'missing_state';
        return false;
    }

    jyutdictCleanupOAuthStates($config);

    $sessionValid = isset($_SESSION['oauth_states'][$state]);
    $legacySessionValid = isset($_SESSION['oauth_state']) && hash_equals((string)$_SESSION['oauth_state'], $state);
    $cookieName = jyutdictOAuthStateCookieName($state);
    $cookieValue = $_COOKIE[$cookieName] ?? '';
    $cookieValid = is_string($cookieValue) && $cookieValue !== ''
        && hash_equals(jyutdictOAuthStateCookieValue($state, $config), $cookieValue);

    if ($sessionValid || $legacySessionValid || $cookieValid) {
        $reason = $sessionValid ? 'session_match' : ($legacySessionValid ? 'legacy_session_match' : 'cookie_match');
        return true;
    }

    if (!isset($_COOKIE[session_name()])) {
        $reason = 'missing_session_cookie';
    } elseif (empty($_SESSION['oauth_states']) && !isset($_SESSION['oauth_state'])) {
        $reason = 'missing_session_state';
    } elseif ($cookieValue === '') {
        $reason = 'missing_state_cookie';
    } else {
        $reason = 'state_mismatch';
    }

    return false;
}

function consumeOAuthState($state, $config = null) {
    $config = $config ?? jyutdictLoadAuthConfig();

    if (isset($_SESSION['oauth_states'][$state])) {
        unset($_SESSION['oauth_states'][$state]);
    }
    if (isset($_SESSION['oauth_state']) && hash_equals((string)$_SESSION['oauth_state'], (string)$state)) {
        unset($_SESSION['oauth_state']);
    }

    setcookie(
        jyutdictOAuthStateCookieName($state),
        '',
        jyutdictSetCookieOptions($config, time() - 3600, '/api/auth/google')
    );
}

function jyutdictHashForLog($value) {
    if (!is_string($value) || $value === '') {
        return null;
    }
    return substr(hash('sha256', $value), 0, 12);
}

function oauthStateDebugContext($state, $config = null) {
    $config = $config ?? jyutdictLoadAuthConfig();
    $cookieName = is_string($state) && $state !== '' ? jyutdictOAuthStateCookieName($state) : '';
    $sessionStates = $_SESSION['oauth_states'] ?? [];

    return [
        'request_state_hash' => jyutdictHashForLog(is_string($state) ? $state : ''),
        'session_id_hash' => jyutdictHashForLog(session_id()),
        'session_cookie_present' => isset($_COOKIE[session_name()]),
        'session_state_count' => is_array($sessionStates) ? count($sessionStates) : 0,
        'legacy_session_state_present' => isset($_SESSION['oauth_state']),
        'state_cookie_name' => $cookieName,
        'state_cookie_present' => $cookieName !== '' && isset($_COOKIE[$cookieName]),
        'session_cookie_params' => session_get_cookie_params(),
        'session_save_path' => session_save_path(),
        'request_origin' => jyutdictRequestOrigin(),
        'redirect_uri' => $config['redirect_uri'] ?? null,
        'forwarded_proto' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null,
        'https' => $_SERVER['HTTPS'] ?? null,
        'server_port' => $_SERVER['SERVER_PORT'] ?? null,
    ];
}

function logOAuthStateEvent($event, $context = [], $config = null, $force = false) {
    $config = $config ?? jyutdictLoadAuthConfig();
    if (!$force && empty($config['oauth_debug'])) {
        return;
    }

    $payload = array_merge([
        'event' => $event,
        'time' => gmdate('c'),
        'host' => $_SERVER['HTTP_HOST'] ?? null,
        'method' => $_SERVER['REQUEST_METHOD'] ?? null,
    ], $context);

    error_log('[OAUTH-STATE] ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}
