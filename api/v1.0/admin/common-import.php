<?php
/** Owner-only resumable browser import for complete common-sheet workbooks. */

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
} catch (Throwable $error) {
    outputJson(['error' => $error->getMessage(), 'code' => 'COMMON_IMPORT_SCHEMA_NOT_INSTALLED'], 503);
}

function commonImportOutputError(Throwable $error) {
    $message = $error->getMessage();
    $status = 400;
    if (stripos($message, 'not found') !== false) {
        $status = 404;
    } elseif (stripos($message, 'already') !== false ||
              stripos($message, 'current state') !== false ||
              stripos($message, 'does not match') !== false) {
        $status = 409;
    }
    outputJson(['error' => $message], $status);
}

function commonImportCatalog(PDO $dbh) {
    $rows = $dbh->query(
        "SELECT a.`id`, a.`first`, a.`second`, a.`third`, a.`detailed_name`, a.`sheet_author`,
                a.`sheetname`, a.`color`, a.`current_release_id`, a.`current_phonology_id`,
                a.`is_visible`, a.`archived_at`,
                r.`release_no`, r.`source_filename`, r.`source_date`, r.`source_sheet`,
                r.`entry_count`, r.`character_count`, r.`syllable_count`,
                r.`toneless_syllable_count`, r.`skipped_row_count`, r.`published_at`,
                IF(p.`id` IS NOT NULL AND p.`source_release_id` = a.`current_release_id`, 1, 0)
                  AS `has_current_phonology`
         FROM `i_area_list` AS a
         LEFT JOIN `common_releases` AS r ON r.`id` = a.`current_release_id`
         LEFT JOIN `phonology_reports` AS p ON p.`id` = a.`current_phonology_id`
         ORDER BY a.`archived_at` IS NOT NULL, a.`sort_order`, a.`id`"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        foreach ([
            'id', 'current_release_id', 'current_phonology_id', 'release_no', 'entry_count',
            'character_count', 'syllable_count', 'toneless_syllable_count',
            'skipped_row_count', 'is_visible', 'has_current_phonology',
        ] as $field) {
            $row[$field] = $row[$field] === null ? null : (int)$row[$field];
        }
    }
    unset($row);
    return $rows;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $jobId = isset($_GET['job']) ? jyutdictCommonImportValidateUuid($_GET['job']) : null;
        if ($jobId) {
            $job = jyutdictCommonImportGetJob($dbh, $jobId, $currentUserId);
            $stmt = $dbh->prepare(
                "SELECT `chunk_no`, `row_count`, HEX(`payload_hash`) AS `payload_hash`, `received_at`
                 FROM `common_import_chunks` WHERE `job_id` = ? ORDER BY `chunk_no`"
            );
            $stmt->execute([$jobId]);
            $chunks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($chunks as &$chunk) {
                $chunk['chunk_no'] = (int)$chunk['chunk_no'];
                $chunk['row_count'] = (int)$chunk['row_count'];
                $chunk['payload_hash'] = strtolower($chunk['payload_hash']);
            }
            unset($chunk);
            outputJson(['job' => jyutdictCommonImportPublicJob($job), 'chunks' => $chunks]);
        }
        $bundle = jyutdictCommonImportActiveBundle($dbh);
        $jobsStmt = $dbh->prepare(
            "SELECT j.*, HEX(j.`content_hash`) AS `content_hash_hex`,
                    b.`version` AS `rule_bundle_version`,
                    HEX(b.`payload_hash`) AS `rule_bundle_hash`
             FROM `common_import_jobs` AS j
             JOIN `common_rule_bundles` AS b ON b.`id` = j.`rule_bundle_id`
             WHERE j.`created_by` = ?
             ORDER BY j.`created_at` DESC
             LIMIT 12"
        );
        $jobsStmt->execute([$currentUserId]);
        $jobs = [];
        foreach ($jobsStmt->fetchAll(PDO::FETCH_ASSOC) as $job) {
            foreach ([
                'created_by', 'area_id', 'rule_bundle_id', 'expected_chunk_count',
                'expected_row_count', 'character_count', 'syllable_count',
                'toneless_syllable_count', 'skipped_row_count', 'received_chunk_count',
                'received_row_count', 'published_release_id',
            ] as $field) {
                $job[$field] = $job[$field] === null ? null : (int)$job[$field];
            }
            $job['content_hash_hex'] = strtolower($job['content_hash_hex']);
            $job['rule_bundle_hash'] = strtolower($job['rule_bundle_hash']);
            $jobs[] = jyutdictCommonImportPublicJob($job);
        }
        outputJson([
            'locations' => commonImportCatalog($dbh),
            'rule_bundle' => [
                'id' => $bundle['id'],
                'version' => $bundle['version'],
                'payload_hash' => $bundle['payload_hash'],
            ],
            'jobs' => $jobs,
            'limits' => [
                'max_xlsx_bytes' => 20971520,
                'max_rows' => JYUTDICT_IMPORT_MAX_ROWS,
                'max_columns' => 256,
                'max_compressed_chunk_bytes' => JYUTDICT_IMPORT_MAX_COMPRESSED_CHUNK,
                'recommended_chunk_rows' => 750,
            ],
        ]);
    }

    validateCsrf();

    if ($method === 'POST') {
        $input = jyutdictCommonImportJsonBody();
        $action = (string)($input['action'] ?? 'create');
        if ($action === 'publish') {
            $jobId = jyutdictCommonImportValidateUuid($input['job_id'] ?? '');
            $result = jyutdictCommonImportPublish($dbh, $jobId, $currentUserId);
            outputJson(['success' => true, 'publication' => $result]);
        }
        if ($action !== 'create') {
            throw new RuntimeException('Unsupported import action');
        }

        $bundle = jyutdictCommonImportActiveBundle($dbh);
        $bundleId = (int)($input['rule_bundle_id'] ?? 0);
        $bundleHash = jyutdictCommonImportValidateHash($input['rule_bundle_hash'] ?? '', 'rule bundle hash');
        if ($bundleId !== $bundle['id'] || !hash_equals($bundle['payload_hash'], $bundleHash)) {
            throw new RuntimeException('Rule bundle changed after workbook conversion; parse it again');
        }
        $jobId = empty($input['job_id'])
            ? jyutdictCommonImportUuid()
            : jyutdictCommonImportValidateUuid($input['job_id']);
        $areaId = isset($input['area_id']) && $input['area_id'] !== ''
            ? (int)$input['area_id'] : null;
        $newArea = $input['new_area'] ?? null;
        if ($areaId !== null) {
            $stmt = $dbh->prepare(
                "SELECT COUNT(*) FROM `i_area_list` WHERE `id` = ? AND `archived_at` IS NULL"
            );
            $stmt->execute([$areaId]);
            if ((int)$stmt->fetchColumn() === 0) {
                throw new RuntimeException('Target location not found');
            }
            $newArea = null;
        } elseif (!is_array($newArea)) {
            throw new RuntimeException('Choose an existing location or supply new location metadata');
        } else {
            jyutdictMaintenanceValidateSheetname($newArea['sheetname'] ?? '');
            jyutdictMaintenanceValidateMetadata($newArea, [
                'first' => '', 'second' => '', 'third' => '',
                'longitude' => 0.0, 'latitude' => 0.0, 'color' => '#CCCCCC',
            ]);
        }

        $expectedChunks = (int)($input['expected_chunk_count'] ?? 0);
        $expectedRows = (int)($input['expected_row_count'] ?? 0);
        $characterCount = (int)($input['character_count'] ?? 0);
        $syllableCount = (int)($input['syllable_count'] ?? 0);
        $tonelessCount = (int)($input['toneless_syllable_count'] ?? 0);
        $skippedCount = (int)($input['skipped_row_count'] ?? 0);
        if ($expectedChunks < 1 || $expectedChunks > 1000 ||
            $expectedRows < 1 || $expectedRows > JYUTDICT_IMPORT_MAX_ROWS ||
            $characterCount < 1 || $characterCount > $expectedRows ||
            $syllableCount < 1 || $syllableCount > $expectedRows ||
            $tonelessCount < 1 || $tonelessCount > $syllableCount ||
            $skippedCount < 0) {
            throw new RuntimeException('Invalid import statistics');
        }
        $sourceFilename = jyutdictCommonImportCleanText(
            basename(str_replace('\\', '/', (string)($input['source_filename'] ?? ''))),
            'source_filename',
            255
        );
        if (!preg_match('/\.xlsx$/i', $sourceFilename)) {
            throw new RuntimeException('Only .xlsx workbooks are accepted');
        }
        $sourceDate = jyutdictCommonImportValidateDate($input['source_date'] ?? '');
        $sourceSheet = jyutdictCommonImportCleanText($input['source_sheet'] ?? '', 'source_sheet', 128);
        $converterVersion = jyutdictCommonImportCleanText(
            $input['converter_version'] ?? '',
            'converter_version',
            80
        );
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $converterVersion)) {
            throw new RuntimeException('Invalid converter_version');
        }
        $ruleProfile = jyutdictCommonImportCleanText($input['rule_profile'] ?? '', 'rule_profile', 128);
        $config = $input['config'] ?? null;
        if (!is_array($config)) {
            throw new RuntimeException('config must be an object');
        }
        $stable = $input['stable_metadata'] ?? [];
        if (!is_array($stable)) {
            throw new RuntimeException('stable_metadata must be an object');
        }
        if (array_key_exists('detailed_name', $stable)) {
            $stable['detailed_name'] = jyutdictCommonImportCleanText(
                $stable['detailed_name'],
                'detailed_name',
                255,
                true
            );
        }
        if (array_key_exists('sheet_author', $stable)) {
            $stable['sheet_author'] = jyutdictCommonImportCleanText(
                $stable['sheet_author'],
                'sheet_author',
                2000,
                true
            );
        }
        $configJson = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $newAreaJson = $newArea === null ? null :
            json_encode($newArea, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stableJson = json_encode($stable, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (strlen($configJson) > 1048576) {
            throw new RuntimeException('Import config is too large');
        }
        $contentHash = jyutdictCommonImportValidateHash($input['content_hash'] ?? '', 'content hash');

        $stmt = $dbh->prepare(
            "INSERT INTO `common_import_jobs`
             (`id`, `created_by`, `area_id`, `rule_bundle_id`, `status`,
              `source_filename`, `source_date`, `source_sheet`, `converter_version`,
              `rule_profile`, `config_json`, `new_area_json`, `stable_metadata_json`,
              `expected_chunk_count`, `expected_row_count`, `character_count`,
              `syllable_count`, `toneless_syllable_count`, `skipped_row_count`, `content_hash`)
             VALUES (?, ?, ?, ?, 'receiving', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        try {
            $stmt->execute([
                $jobId, $currentUserId, $areaId, $bundleId, $sourceFilename, $sourceDate,
                $sourceSheet, $converterVersion, $ruleProfile, $configJson, $newAreaJson,
                $stableJson, $expectedChunks, $expectedRows, $characterCount,
                $syllableCount, $tonelessCount, $skippedCount, hex2bin($contentHash),
            ]);
        } catch (PDOException $error) {
            if ((string)$error->getCode() === '23000') {
                $existing = jyutdictCommonImportGetJob($dbh, $jobId, $currentUserId);
                $matches = hash_equals($existing['content_hash_hex'], $contentHash) &&
                    $existing['area_id'] === $areaId &&
                    $existing['rule_bundle_id'] === $bundleId &&
                    $existing['source_filename'] === $sourceFilename &&
                    $existing['source_date'] === $sourceDate &&
                    $existing['source_sheet'] === $sourceSheet &&
                    $existing['converter_version'] === $converterVersion &&
                    $existing['rule_profile'] === $ruleProfile &&
                    $existing['expected_chunk_count'] === $expectedChunks &&
                    $existing['expected_row_count'] === $expectedRows &&
                    $existing['character_count'] === $characterCount &&
                    $existing['syllable_count'] === $syllableCount &&
                    $existing['toneless_syllable_count'] === $tonelessCount &&
                    $existing['skipped_row_count'] === $skippedCount &&
                    $existing['config_json'] === $configJson &&
                    (string)$existing['new_area_json'] === (string)$newAreaJson &&
                    (string)$existing['stable_metadata_json'] === (string)$stableJson;
                if (!$matches) {
                    throw new RuntimeException('Import job id already belongs to a different import manifest');
                }
                outputJson(['success' => true, 'job' => jyutdictCommonImportPublicJob($existing), 'resumed' => true]);
            }
            throw $error;
        }
        $job = jyutdictCommonImportGetJob($dbh, $jobId, $currentUserId);
        outputJson(['success' => true, 'job' => jyutdictCommonImportPublicJob($job), 'resumed' => false], 201);
    }

    if ($method === 'PUT') {
        $jobId = jyutdictCommonImportValidateUuid($_GET['job'] ?? '');
        $chunkNo = isset($_GET['chunk']) ? (int)$_GET['chunk'] : -1;
        $claimedHash = jyutdictCommonImportValidateHash(
            $_SERVER['HTTP_X_CHUNK_SHA256'] ?? '',
            'chunk hash'
        );
        $payload = file_get_contents('php://input');
        $actualHash = hash('sha256', $payload);
        if (!hash_equals($claimedHash, $actualHash)) {
            throw new RuntimeException('Chunk hash mismatch');
        }
        $rows = jyutdictCommonImportDecodeChunk($payload);
        $dbh->beginTransaction();
        try {
            $job = jyutdictCommonImportGetJob($dbh, $jobId, $currentUserId, true);
            if (!in_array($job['status'], ['receiving', 'ready', 'failed'], true)) {
                throw new RuntimeException('Import job cannot receive chunks in its current state');
            }
            if ($chunkNo < 0 || $chunkNo >= $job['expected_chunk_count']) {
                throw new RuntimeException('chunk number is out of range');
            }
            $existingStmt = $dbh->prepare(
                "SELECT HEX(`payload_hash`) FROM `common_import_chunks`
                 WHERE `job_id` = ? AND `chunk_no` = ?"
            );
            $existingStmt->execute([$jobId, $chunkNo]);
            $existingHash = $existingStmt->fetchColumn();
            if ($existingHash !== false) {
                if (!hash_equals(strtolower($existingHash), $actualHash)) {
                    throw new RuntimeException('Chunk number already contains different content');
                }
                $dbh->commit();
                outputJson(['success' => true, 'chunk_no' => $chunkNo, 'already_received' => true]);
            }

            $chunkStmt = $dbh->prepare(
                "INSERT INTO `common_import_chunks`
                 (`job_id`, `chunk_no`, `row_count`, `payload_hash`, `payload_gzip`)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $chunkStmt->bindValue(1, $jobId);
            $chunkStmt->bindValue(2, $chunkNo, PDO::PARAM_INT);
            $chunkStmt->bindValue(3, count($rows), PDO::PARAM_INT);
            $chunkStmt->bindValue(4, hex2bin($actualHash), PDO::PARAM_LOB);
            $chunkStmt->bindValue(5, $payload, PDO::PARAM_LOB);
            $chunkStmt->execute();

            $rowStmt = $dbh->prepare(
                "INSERT INTO `common_import_rows`
                 (`job_id`, `row_no`, `display_order`, `chara`, `initial`, `nuclei`, `coda`,
                  `tone`, `ipa`, `note`, `alt_group`, `source_row`, `row_hash`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            foreach ($rows as $row) {
                $canonical = jyutdictCommonImportCanonicalJson($row);
                $rowStmt->bindValue(1, $jobId);
                $rowStmt->bindValue(2, $row['row_no'], PDO::PARAM_INT);
                $rowStmt->bindValue(3, $row['display_order'], PDO::PARAM_INT);
                $rowStmt->bindValue(4, $row['chara']);
                $rowStmt->bindValue(5, $row['initial']);
                $rowStmt->bindValue(6, $row['nuclei']);
                $rowStmt->bindValue(7, $row['coda']);
                $rowStmt->bindValue(8, $row['tone']);
                $rowStmt->bindValue(9, $row['ipa']);
                $rowStmt->bindValue(10, $row['note']);
                $rowStmt->bindValue(11, $row['alt_group'], $row['alt_group'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $rowStmt->bindValue(12, $row['source_row'], $row['source_row'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $rowStmt->bindValue(13, hash('sha256', $canonical, true), PDO::PARAM_LOB);
                $rowStmt->execute();
            }
            $totalsStmt = $dbh->prepare(
                "SELECT COUNT(*), COALESCE(SUM(`row_count`), 0)
                 FROM `common_import_chunks` WHERE `job_id` = ?"
            );
            $totalsStmt->execute([$jobId]);
            [$chunkCount, $rowCount] = array_map('intval', $totalsStmt->fetch(PDO::FETCH_NUM));
            if ($chunkCount > $job['expected_chunk_count'] || $rowCount > $job['expected_row_count']) {
                throw new RuntimeException('Received content exceeds declared import size');
            }
            $status = ($chunkCount === $job['expected_chunk_count'] &&
                       $rowCount === $job['expected_row_count']) ? 'ready' : 'receiving';
            $dbh->prepare(
                "UPDATE `common_import_jobs`
                 SET `received_chunk_count` = ?, `received_row_count` = ?,
                     `status` = ?, `last_error` = NULL
                 WHERE `id` = ?"
            )->execute([$chunkCount, $rowCount, $status, $jobId]);
            $dbh->commit();
            outputJson([
                'success' => true,
                'chunk_no' => $chunkNo,
                'already_received' => false,
                'received_chunk_count' => $chunkCount,
                'received_row_count' => $rowCount,
                'status' => $status,
            ]);
        } catch (Throwable $error) {
            if ($dbh->inTransaction()) {
                $dbh->rollBack();
            }
            throw $error;
        }
    }

    if ($method === 'DELETE') {
        $input = jyutdictCommonImportJsonBody();
        $jobId = jyutdictCommonImportValidateUuid($input['job_id'] ?? '');
        $job = jyutdictCommonImportGetJob($dbh, $jobId, $currentUserId);
        if ($job['status'] === 'published') {
            throw new RuntimeException('Published import jobs cannot be aborted');
        }
        $dbh->prepare(
            "UPDATE `common_import_jobs` SET `status` = 'aborted' WHERE `id` = ?"
        )->execute([$jobId]);
        outputJson(['success' => true]);
    }

    throw new RuntimeException('Method not allowed');
} catch (Throwable $error) {
    commonImportOutputError($error);
}
