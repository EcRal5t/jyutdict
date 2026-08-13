<?php
/**
 * Minimal Google OpenID Connect ID-token verifier for PHP 7.4 deployments.
 *
 * Google publishes signing certificates as a kid => PEM map. The certificates
 * are cached according to Google's Cache-Control response and used to verify
 * RS256 JWT signatures locally; tokeninfo is intentionally not used as a
 * production authentication dependency.
 */

class JyutdictGoogleIdTokenException extends RuntimeException {}

function jyutdictBase64UrlDecode($value) {
    if (!is_string($value) || $value === '') {
        throw new JyutdictGoogleIdTokenException('Malformed ID token');
    }

    $remainder = strlen($value) % 4;
    if ($remainder !== 0) {
        $value .= str_repeat('=', 4 - $remainder);
    }
    $decoded = base64_decode(strtr($value, '-_', '+/'), true);
    if ($decoded === false) {
        throw new JyutdictGoogleIdTokenException('Malformed ID token encoding');
    }
    return $decoded;
}

function jyutdictDecodeJwtPart($value) {
    $decoded = json_decode(jyutdictBase64UrlDecode($value), true);
    if (!is_array($decoded)) {
        throw new JyutdictGoogleIdTokenException('Malformed ID token JSON');
    }
    return $decoded;
}

function jyutdictGoogleCertificateCachePath() {
    $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'jyutdict-' . substr(hash('sha256', __DIR__), 0, 16);
    if (!is_dir($directory)) {
        @mkdir($directory, 0700, true);
    }
    return $directory . DIRECTORY_SEPARATOR . 'google-id-token-certs.json';
}

function jyutdictReadGoogleCertificateCache($allowStale = false) {
    $path = jyutdictGoogleCertificateCachePath();
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    $cached = json_decode((string)@file_get_contents($path), true);
    if (!is_array($cached) || empty($cached['certificates']) || !is_array($cached['certificates'])) {
        return null;
    }

    $now = time();
    $expiresAt = (int)($cached['expires_at'] ?? 0);
    $fetchedAt = (int)($cached['fetched_at'] ?? 0);
    if ($expiresAt > $now + 10 || ($allowStale && $fetchedAt > $now - 604800)) {
        return $cached['certificates'];
    }
    return null;
}

function jyutdictFetchGoogleCertificates() {
    $cached = jyutdictReadGoogleCertificateCache(false);
    if ($cached !== null) {
        return $cached;
    }

    $maxAge = 3600;
    $curl = curl_init('https://www.googleapis.com/oauth2/v1/certs');
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    curl_setopt($curl, CURLOPT_USERAGENT, 'Jyutdict-Server/1.0');
    curl_setopt($curl, CURLOPT_HEADERFUNCTION, function ($curlHandle, $line) use (&$maxAge) {
        if (stripos($line, 'Cache-Control:') === 0
            && preg_match('/max-age\s*=\s*(\d+)/i', $line, $matches)) {
            $maxAge = (int)$matches[1];
        }
        return strlen($line);
    });

    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    $certificates = is_string($response) ? json_decode($response, true) : null;
    if ($status !== 200 || !is_array($certificates) || !$certificates) {
        $stale = jyutdictReadGoogleCertificateCache(true);
        if ($stale !== null) {
            return $stale;
        }
        throw new JyutdictGoogleIdTokenException(
            'Unable to retrieve Google signing certificates' . ($curlError !== '' ? ': ' . $curlError : '')
        );
    }

    foreach ($certificates as $keyId => $certificate) {
        if (!is_string($keyId)
            || !is_string($certificate)
            || strpos($certificate, 'BEGIN CERTIFICATE') === false) {
            throw new JyutdictGoogleIdTokenException('Invalid Google signing certificate response');
        }
    }

    $now = time();
    $cachePayload = json_encode([
        'fetched_at' => $now,
        'expires_at' => $now + max(300, min($maxAge, 86400)),
        'certificates' => $certificates,
    ], JSON_UNESCAPED_SLASHES);
    if (is_string($cachePayload)) {
        $cachePath = jyutdictGoogleCertificateCachePath();
        if (@file_put_contents($cachePath, $cachePayload, LOCK_EX) !== false) {
            @chmod($cachePath, 0600);
        }
    }

    return $certificates;
}

function jyutdictAudienceMatches($audienceClaim, $expectedAudience) {
    if (is_string($audienceClaim)) {
        return hash_equals($expectedAudience, $audienceClaim);
    }
    if (!is_array($audienceClaim)) {
        return false;
    }
    foreach ($audienceClaim as $audience) {
        if (is_string($audience) && hash_equals($expectedAudience, $audience)) {
            return true;
        }
    }
    return false;
}

function jyutdictVerifyGoogleIdToken($idToken, $expectedAudience, $expectedNonce) {
    $idToken = trim((string)$idToken);
    $expectedAudience = trim((string)$expectedAudience);
    $expectedNonce = (string)$expectedNonce;
    if ($idToken === '' || strlen($idToken) > 20000) {
        throw new JyutdictGoogleIdTokenException('Missing or oversized ID token');
    }
    if ($expectedAudience === '' || $expectedNonce === '') {
        throw new JyutdictGoogleIdTokenException('Server authentication configuration is incomplete');
    }

    $parts = explode('.', $idToken);
    if (count($parts) !== 3) {
        throw new JyutdictGoogleIdTokenException('Malformed ID token');
    }

    $header = jyutdictDecodeJwtPart($parts[0]);
    $claims = jyutdictDecodeJwtPart($parts[1]);
    if (($header['alg'] ?? '') !== 'RS256' || empty($header['kid']) || !is_string($header['kid'])) {
        throw new JyutdictGoogleIdTokenException('Unsupported ID token signature');
    }

    $certificates = jyutdictFetchGoogleCertificates();
    $certificate = $certificates[$header['kid']] ?? null;
    if (!is_string($certificate)) {
        throw new JyutdictGoogleIdTokenException('Unknown Google signing key');
    }

    $signature = jyutdictBase64UrlDecode($parts[2]);
    $verification = openssl_verify(
        $parts[0] . '.' . $parts[1],
        $signature,
        $certificate,
        OPENSSL_ALGO_SHA256
    );
    if ($verification !== 1) {
        throw new JyutdictGoogleIdTokenException('Invalid ID token signature');
    }

    $issuer = $claims['iss'] ?? '';
    if ($issuer !== 'https://accounts.google.com' && $issuer !== 'accounts.google.com') {
        throw new JyutdictGoogleIdTokenException('Invalid ID token issuer');
    }
    if (!jyutdictAudienceMatches($claims['aud'] ?? null, $expectedAudience)) {
        throw new JyutdictGoogleIdTokenException('Invalid ID token audience');
    }
    if (is_array($claims['aud'] ?? null)
        && count($claims['aud']) > 1
        && (!isset($claims['azp'])
            || !is_string($claims['azp'])
            || !hash_equals($expectedAudience, $claims['azp']))) {
        throw new JyutdictGoogleIdTokenException('Invalid ID token authorized party');
    }

    $now = time();
    $clockSkew = 60;
    if (!isset($claims['exp']) || (int)$claims['exp'] < $now - $clockSkew) {
        throw new JyutdictGoogleIdTokenException('Expired ID token');
    }
    if (isset($claims['iat']) && (int)$claims['iat'] > $now + $clockSkew) {
        throw new JyutdictGoogleIdTokenException('ID token issued in the future');
    }
    if (isset($claims['nbf']) && (int)$claims['nbf'] > $now + $clockSkew) {
        throw new JyutdictGoogleIdTokenException('ID token is not active');
    }
    if (!isset($claims['nonce'])
        || !is_string($claims['nonce'])
        || !hash_equals($expectedNonce, $claims['nonce'])) {
        throw new JyutdictGoogleIdTokenException('Invalid ID token nonce');
    }
    if (empty($claims['sub']) || !is_string($claims['sub']) || strlen($claims['sub']) > 255) {
        throw new JyutdictGoogleIdTokenException('Invalid Google account identifier');
    }

    $emailVerified = $claims['email_verified'] ?? false;
    if ($emailVerified !== true && $emailVerified !== 'true' && $emailVerified !== 1) {
        throw new JyutdictGoogleIdTokenException('Google email address is not verified');
    }
    if (empty($claims['email']) || !is_string($claims['email'])) {
        throw new JyutdictGoogleIdTokenException('Google email address is missing');
    }

    return $claims;
}
