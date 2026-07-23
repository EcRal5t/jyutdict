<?php
/** Owner-only source feed and immutable publication endpoint for generated phonology. */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/CommonImport.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/role.php';
require_once __DIR__ . '/../../middleware/csrf.php';

requireRole('owner');

function phonologyRebuildArea(PDO $dbh, $areaId) {
    $stmt = $dbh->prepare(
        "SELECT a.`id`, a.`first`, a.`second`, a.`third`, a.`detailed_name`,
                a.`current_release_id`, a.`current_phonology_id`,
                r.`release_no`, r.`entry_count`, r.`source_filename`, r.`source_date`,
                p.`revision_no` AS `phonology_revision`,
                p.`source_release_id` AS `phonology_source_release_id`,
                p.`created_at` AS `phonology_created_at`
         FROM `i_area_list` AS a
         LEFT JOIN `common_releases` AS r ON r.`id` = a.`current_release_id`
         LEFT JOIN `phonology_reports` AS p ON p.`id` = a.`current_phonology_id`
         WHERE a.`id` = ? AND a.`archived_at` IS NULL"
    );
    $stmt->execute([$areaId]);
    $area = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$area || $area['current_release_id'] === null) {
        throw new RuntimeException('Published location not found');
    }
    foreach ([
        'id', 'current_release_id', 'current_phonology_id', 'release_no', 'entry_count',
        'phonology_revision', 'phonology_source_release_id',
    ] as $field) {
        $area[$field] = $area[$field] === null ? null : (int)$area[$field];
    }
    $area['name'] = implode('', array_filter([$area['second'], $area['third']]));
    if ($area['name'] === '') {
        $area['name'] = (string)$area['first'];
    }
    return $area;
}

function phonologyValidatePayload($payload, $areaId, $releaseId) {
    if (!is_array($payload) ||
        (int)($payload['schemaVersion'] ?? 0) !== 1 ||
        (int)($payload['areaId'] ?? 0) !== $areaId ||
        (int)($payload['sourceReleaseId'] ?? 0) !== $releaseId) {
        throw new RuntimeException('Phonology payload identity does not match the requested release');
    }
    $generator = jyutdictCommonImportCleanText(
        $payload['generatorVersion'] ?? '',
        'generatorVersion',
        80
    );
    $middleChinese = jyutdictCommonImportCleanText(
        $payload['middleChineseVersion'] ?? '',
        'middleChineseVersion',
        80
    );
    if (!preg_match('/^[A-Za-z0-9._-]+$/', $generator) ||
        !preg_match('/^[A-Za-z0-9._-]+$/', $middleChinese)) {
        throw new RuntimeException('Invalid phonology version identifier');
    }
    $expected = ['initials', 'finals', 'reverse-initials', 'reverse-finals'];
    $sections = $payload['sections'] ?? null;
    if (!is_array($sections) || count($sections) !== 4) {
        throw new RuntimeException('Phonology payload must contain four sections');
    }
    foreach ($sections as $index => $section) {
        if (!is_array($section) || ($section['id'] ?? '') !== $expected[$index] ||
            !is_array($section['rules'] ?? null) || count($section['rules']) > 2000) {
            throw new RuntimeException('Invalid phonology section structure');
        }
        foreach ($section['rules'] as $rule) {
            if (!is_array($rule) ||
                !is_string($rule['base'] ?? null) ||
                !is_array($rule['conditions'] ?? null) ||
                !is_array($rule['outcomes'] ?? null)) {
                throw new RuntimeException('Invalid phonology rule structure');
            }
            jyutdictCommonImportCleanText($rule['base'], 'phonology base', 128);
            if (count($rule['conditions']) > 4 || count($rule['outcomes']) > 64) {
                throw new RuntimeException('Phonology rule contains too many branches');
            }
            foreach ($rule['conditions'] as $condition) {
                if (!is_array($condition) || count($condition) !== 2) {
                    throw new RuntimeException('Invalid phonology condition');
                }
                jyutdictCommonImportCleanText($condition[0], 'condition label', 32);
                jyutdictCommonImportCleanText($condition[1], 'condition value', 128);
            }
            foreach ($rule['outcomes'] as $outcome) {
                $regularCount = (int)($outcome['charCount'] ?? 0);
                $checkedCount = (int)($outcome['checkedCharCount'] ?? 0);
                if (!is_array($outcome) || !is_string($outcome['value'] ?? null) ||
                    $regularCount + $checkedCount < 1 ||
                    !is_array($outcome['examples'] ?? null) ||
                    count($outcome['examples']) > 8) {
                    throw new RuntimeException('Invalid phonology outcome');
                }
                // A zero modern initial is represented internally by an empty string.
                // The public table renders it as ∅, matching the standalone converter.
                jyutdictCommonImportCleanText($outcome['value'], 'outcome value', 128, true);
                foreach ($outcome['examples'] as $example) {
                    if (!is_array($example) || !is_string($example['char'] ?? null) ||
                        !is_array($example['pronunciations'] ?? null)) {
                        throw new RuntimeException('Invalid phonology example');
                    }
                    jyutdictCommonImportCleanText($example['char'], 'example char', 16);
                    foreach ($example['pronunciations'] as $pronunciation) {
                        jyutdictCommonImportCleanText($pronunciation, 'example pronunciation', 128);
                    }
                    if (array_key_exists('note', $example)) {
                        jyutdictCommonImportCleanText($example['note'], 'example note', 512, true);
                    }
                }
            }
        }
    }
    return [$generator, $middleChinese];
}

try {
    jyutdictCommonImportRequireSchema($dbh);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $areaId = (int)($_GET['area_id'] ?? 0);
    if ($areaId < 1) {
        throw new RuntimeException('area_id is required');
    }
    $area = phonologyRebuildArea($dbh, $areaId);

    if ($method === 'GET') {
        $after = max(0, (int)($_GET['after'] ?? 0));
        $limit = min(2500, max(100, (int)($_GET['limit'] ?? 1500)));
        $stmt = $dbh->prepare(
            "SELECT `id`, `chara`, `initial`, `nuclei`, `coda`, `tone`, `note`
             FROM `common_entries`
             WHERE `area_id` = ? AND `id` > ?
             ORDER BY `id`
             LIMIT {$limit}"
        );
        $stmt->execute([$areaId, $after]);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $next = null;
        foreach ($entries as &$entry) {
            $entry['id'] = (int)$entry['id'];
            $next = $entry['id'];
        }
        unset($entry);
        if (count($entries) < $limit) {
            $next = null;
        }
        outputJson(['area' => $area, 'entries' => $entries, 'next_after' => $next]);
    }

    if ($method !== 'PUT') {
        throw new RuntimeException('Method not allowed');
    }
    validateCsrf();
    $releaseId = (int)($_GET['release_id'] ?? 0);
    if ($releaseId !== $area['current_release_id']) {
        throw new RuntimeException('Location release changed; rebuild from the current release');
    }
    $compressed = file_get_contents('php://input');
    if ($compressed === '' || strlen($compressed) > 16777215) {
        throw new RuntimeException('Compressed phonology payload is empty or too large');
    }
    $raw = gzdecode($compressed);
    if ($raw === false || strlen($raw) > 16777215) {
        throw new RuntimeException('Invalid or oversized phonology gzip payload');
    }
    $claimedHash = jyutdictCommonImportValidateHash(
        $_SERVER['HTTP_X_PAYLOAD_SHA256'] ?? '',
        'phonology payload hash'
    );
    $actualHash = hash('sha256', $raw);
    if (!hash_equals($claimedHash, $actualHash)) {
        throw new RuntimeException('Phonology payload hash mismatch');
    }
    $payload = json_decode($raw, true);
    [$generator, $middleChinese] = phonologyValidatePayload($payload, $areaId, $releaseId);

    $dbh->beginTransaction();
    try {
        $lock = $dbh->prepare(
            "SELECT `current_release_id` FROM `i_area_list` WHERE `id` = ? FOR UPDATE"
        );
        $lock->execute([$areaId]);
        if ((int)$lock->fetchColumn() !== $releaseId) {
            throw new RuntimeException('Location release changed during phonology publication');
        }
        $revisionStmt = $dbh->prepare(
            "SELECT COALESCE(MAX(`revision_no`), 0) + 1 FROM `phonology_reports` WHERE `area_id` = ?"
        );
        $revisionStmt->execute([$areaId]);
        $revision = (int)$revisionStmt->fetchColumn();
        $stmt = $dbh->prepare(
            "INSERT INTO `phonology_reports`
             (`area_id`, `source_release_id`, `revision_no`, `schema_version`,
              `generator_version`, `middle_chinese_version`, `payload_hash`, `payload_gzip`,
              `compressed_bytes`, `uncompressed_bytes`, `created_by`)
             VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bindValue(1, $areaId, PDO::PARAM_INT);
        $stmt->bindValue(2, $releaseId, PDO::PARAM_INT);
        $stmt->bindValue(3, $revision, PDO::PARAM_INT);
        $stmt->bindValue(4, $generator);
        $stmt->bindValue(5, $middleChinese);
        $stmt->bindValue(6, hex2bin($actualHash), PDO::PARAM_LOB);
        $stmt->bindValue(7, $compressed, PDO::PARAM_LOB);
        $stmt->bindValue(8, strlen($compressed), PDO::PARAM_INT);
        $stmt->bindValue(9, strlen($raw), PDO::PARAM_INT);
        $stmt->bindValue(10, $currentUserId, PDO::PARAM_INT);
        $stmt->execute();
        $reportId = (int)$dbh->lastInsertId();
        $dbh->prepare(
            "UPDATE `i_area_list` SET `current_phonology_id` = ? WHERE `id` = ?"
        )->execute([$reportId, $areaId]);
        $dbh->commit();
        outputJson([
            'success' => true,
            'report' => [
                'id' => $reportId,
                'area_id' => $areaId,
                'source_release_id' => $releaseId,
                'revision_no' => $revision,
                'payload_hash' => $actualHash,
                'compressed_bytes' => strlen($compressed),
                'uncompressed_bytes' => strlen($raw),
            ],
        ], 201);
    } catch (Throwable $error) {
        if ($dbh->inTransaction()) {
            $dbh->rollBack();
        }
        throw $error;
    }
} catch (Throwable $error) {
    $message = $error->getMessage();
    $status = stripos($message, 'not found') !== false ? 404 :
        (stripos($message, 'changed') !== false ? 409 : 400);
    outputJson(['error' => $message], $status);
}
