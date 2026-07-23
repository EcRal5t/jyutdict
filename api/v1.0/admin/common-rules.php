<?php
/** Owner-only versioned conversion-rule bundles. */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/CommonImport.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/role.php';
require_once __DIR__ . '/../../middleware/csrf.php';

requireRole('owner');

try {
    jyutdictCommonImportRequireSchema($dbh);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET') {
        $active = jyutdictCommonImportActiveBundle($dbh);
        $payload = json_decode($active['payload_json'], true);
        if (!is_array($payload)) {
            throw new RuntimeException('Active rule bundle is invalid JSON');
        }
        $history = $dbh->query(
            "SELECT `id`, `version`, HEX(`payload_hash`) AS `payload_hash`,
                    `is_active`, `created_by`, `created_at`
             FROM `common_rule_bundles`
             ORDER BY `created_at` DESC, `id` DESC
             LIMIT 20"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($history as &$row) {
            $row['id'] = (int)$row['id'];
            $row['is_active'] = (int)$row['is_active'];
            $row['created_by'] = $row['created_by'] === null ? null : (int)$row['created_by'];
            $row['payload_hash'] = strtolower($row['payload_hash']);
        }
        unset($row);
        outputJson([
            'active' => [
                'id' => $active['id'],
                'version' => $active['version'],
                'payload_hash' => $active['payload_hash'],
                'created_at' => $active['created_at'],
                'payload' => $payload,
            ],
            'history' => $history,
        ]);
    }
    if ($method !== 'POST') {
        throw new RuntimeException('Method not allowed');
    }
    validateCsrf();
    $input = jyutdictCommonImportJsonBody();
    $version = jyutdictCommonImportCleanText($input['version'] ?? '', 'version', 80);
    if (!preg_match('/^[A-Za-z0-9._-]+$/', $version)) {
        throw new RuntimeException('Rule bundle version may only contain letters, numbers, dot, underscore and dash');
    }
    $payload = $input['payload'] ?? null;
    if (!is_array($payload) ||
        (int)($payload['schemaVersion'] ?? 0) !== 1 ||
        !is_array($payload['rules'] ?? null) ||
        !is_array($payload['tones'] ?? null)) {
        throw new RuntimeException('Invalid rule bundle structure');
    }
    foreach (['i2i', 'i2j', 'j2i', 'j2j'] as $name) {
        if (!is_array($payload['rules'][$name] ?? null)) {
            throw new RuntimeException("Missing rules.{$name}");
        }
        foreach ($payload['rules'][$name] as $profile => $rules) {
            if (!is_array($rules)) {
                throw new RuntimeException("Rule profile {$profile} must be an array");
            }
            foreach ($rules as $rule) {
                if (!is_array($rule) || !in_array(count($rule), [6, 7], true)) {
                    throw new RuntimeException("Each {$name} rule must contain six fields and optional !");
                }
                if (count($rule) === 7 && $rule[6] !== '!') {
                    throw new RuntimeException('The seventh rule field must be !');
                }
            }
        }
    }
    foreach (['j2i', 'j2j'] as $name) {
        if (!is_array($payload['tones'][$name] ?? null)) {
            throw new RuntimeException("Missing tones.{$name}");
        }
    }
    $payload['bundleVersion'] = $version;
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || strlen($json) > 2097152) {
        throw new RuntimeException('Rule bundle is too large');
    }
    $hash = hash('sha256', $json);

    $dbh->beginTransaction();
    try {
        $dbh->exec("UPDATE `common_rule_bundles` SET `is_active` = 0");
        $stmt = $dbh->prepare(
            "INSERT INTO `common_rule_bundles`
             (`version`, `payload_json`, `payload_hash`, `is_active`, `created_by`)
             VALUES (?, ?, ?, 1, ?)"
        );
        $stmt->execute([$version, $json, hex2bin($hash), $currentUserId]);
        $id = (int)$dbh->lastInsertId();
        $dbh->commit();
        outputJson([
            'success' => true,
            'active' => ['id' => $id, 'version' => $version, 'payload_hash' => $hash],
        ], 201);
    } catch (Throwable $error) {
        if ($dbh->inTransaction()) {
            $dbh->rollBack();
        }
        if ($error instanceof PDOException && (string)$error->getCode() === '23000') {
            throw new RuntimeException('Rule bundle version already exists');
        }
        throw $error;
    }
} catch (Throwable $error) {
    $status = stripos($error->getMessage(), 'already exists') !== false ? 409 : 400;
    outputJson(['error' => $error->getMessage()], $status);
}

