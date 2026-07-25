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
    $cleanRuleText = static function ($value, string $field, int $maxLength = 128): string {
        if (!is_string($value)) {
            throw new RuntimeException("{$field} must be a string");
        }
        if (mb_strlen($value, 'UTF-8') > $maxLength) {
            throw new RuntimeException("{$field} is too long");
        }
        return $value;
    };
    $cleanProfile = static function ($profile) use ($cleanRuleText): string {
        $profile = $cleanRuleText((string)$profile, 'Rule profile', 80);
        if ($profile === '' || trim($profile) !== $profile ||
            preg_match('/[\x00-\x1F\x7F]/u', $profile)) {
            throw new RuntimeException('Rule profile may not be blank or contain control characters');
        }
        return $profile;
    };
    $appendProfiles = $payload['appendProfiles'] ?? [];
    if (!is_array($appendProfiles) || count($appendProfiles) > 20) {
        throw new RuntimeException('appendProfiles must be an array with at most 20 items');
    }
    $seenAppendProfiles = [];
    foreach ($appendProfiles as $profile) {
        $profile = $cleanProfile($profile);
        if (isset($seenAppendProfiles[$profile])) {
            throw new RuntimeException("Duplicate append profile {$profile}");
        }
        $seenAppendProfiles[$profile] = true;
    }
    $segmentRuleCount = 0;
    foreach (['i2i', 'i2j', 'j2i', 'j2j'] as $name) {
        if (!is_array($payload['rules'][$name] ?? null)) {
            throw new RuntimeException("Missing rules.{$name}");
        }
        if (count($payload['rules'][$name]) > 2000) {
            throw new RuntimeException("Too many profiles in rules.{$name}");
        }
        foreach ($payload['rules'][$name] as $profile => $rules) {
            $cleanProfile($profile);
            if (!is_array($rules)) {
                throw new RuntimeException("Rule profile {$profile} must be an array");
            }
            if (count($rules) > 10000) {
                throw new RuntimeException("Too many rules in {$name}.{$profile}");
            }
            foreach ($rules as $ruleIndex => $rule) {
                $segmentRuleCount++;
                if ($segmentRuleCount > 50000) {
                    throw new RuntimeException('Rule bundle contains too many segment rules');
                }
                if (!is_array($rule) || !in_array(count($rule), [6, 7], true)) {
                    throw new RuntimeException("Each {$name} rule must contain six fields and optional !");
                }
                for ($fieldIndex = 0; $fieldIndex < 6; $fieldIndex++) {
                    $cleanRuleText(
                        $rule[$fieldIndex] ?? null,
                        "{$name}.{$profile}[{$ruleIndex}][{$fieldIndex}]"
                    );
                }
                if (count($rule) === 7 && $rule[6] !== '!') {
                    throw new RuntimeException('The seventh rule field must be !');
                }
            }
        }
    }
    $toneRuleCount = 0;
    foreach (['j2i', 'j2j'] as $name) {
        if (!is_array($payload['tones'][$name] ?? null)) {
            throw new RuntimeException("Missing tones.{$name}");
        }
        if (count($payload['tones'][$name]) > 2000) {
            throw new RuntimeException("Too many profiles in tones.{$name}");
        }
        foreach ($payload['tones'][$name] as $profile => $categories) {
            $cleanProfile($profile);
            if (!is_array($categories)) {
                throw new RuntimeException("Tone profile {$name}.{$profile} must be an object");
            }
            foreach ($categories as $category => $mapping) {
                $category = $cleanRuleText((string)$category, 'Tone category', 24);
                if (!in_array($category, ['舒聲', '入聲'], true)) {
                    throw new RuntimeException("Unsupported tone category {$category}");
                }
                if (!is_array($mapping) || count($mapping) > 1000) {
                    throw new RuntimeException("Invalid tone mapping {$name}.{$profile}.{$category}");
                }
                foreach ($mapping as $sourceTone => $targetTone) {
                    $toneRuleCount++;
                    if ($toneRuleCount > 30000) {
                        throw new RuntimeException('Rule bundle contains too many tone mappings');
                    }
                    $cleanRuleText((string)$sourceTone, 'Source tone', 32);
                    if (!is_string($targetTone) && !is_int($targetTone) && !is_float($targetTone)) {
                        throw new RuntimeException("Target tone in {$name}.{$profile}.{$category} must be scalar");
                    }
                    $cleanRuleText((string)$targetTone, 'Target tone', 32);
                }
            }
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
