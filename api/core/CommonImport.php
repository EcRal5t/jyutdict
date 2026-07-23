<?php
/** Shared validation and publication helpers for browser-side common-sheet imports. */

require_once __DIR__ . '/LocationMaintenance.php';

const JYUTDICT_IMPORT_MAX_COMPRESSED_CHUNK = 1048576;
const JYUTDICT_IMPORT_MAX_UNCOMPRESSED_CHUNK = 4194304;
const JYUTDICT_IMPORT_MAX_ROWS = 100000;

function jyutdictCommonImportRequireSchema(PDO $dbh) {
    foreach ([
        'common_rule_bundles',
        'common_import_jobs',
        'common_import_chunks',
        'common_import_rows',
        'common_release_snapshots',
        'phonology_reports',
    ] as $table) {
        if (!jyutdictMaintenanceTableExists($dbh, $table)) {
            throw new RuntimeException('Common import schema is not installed');
        }
    }
}

function jyutdictCommonImportJsonBody() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        throw new RuntimeException('Invalid JSON body');
    }
    return $input;
}

function jyutdictCommonImportUuid() {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' .
        substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}

function jyutdictCommonImportValidateUuid($value) {
    $value = strtolower(trim((string)$value));
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $value)) {
        throw new RuntimeException('Invalid import job id');
    }
    return $value;
}

function jyutdictCommonImportValidateHash($value, $label = 'hash') {
    $value = strtolower(trim((string)$value));
    if (!preg_match('/^[0-9a-f]{64}$/', $value)) {
        throw new RuntimeException("Invalid {$label}");
    }
    return $value;
}

function jyutdictCommonImportValidateDate($value) {
    $value = trim((string)$value);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) ||
        $date->format('Y-m-d') !== $value) {
        throw new RuntimeException('source_date must be a valid YYYY-MM-DD date');
    }
    return $value;
}

function jyutdictCommonImportCleanText($value, $label, $maxLength, $allowEmpty = false) {
    $value = trim((string)$value);
    if (!$allowEmpty && $value === '') {
        throw new RuntimeException("{$label} is required");
    }
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value)) {
        throw new RuntimeException("{$label} contains control characters");
    }
    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    if ($length > $maxLength) {
        throw new RuntimeException("{$label} is too long");
    }
    return $value;
}

function jyutdictCommonImportActiveBundle(PDO $dbh) {
    $row = $dbh->query(
        "SELECT `id`, `version`, `payload_json`, HEX(`payload_hash`) AS `payload_hash`, `created_at`
         FROM `common_rule_bundles`
         WHERE `is_active` = 1
         ORDER BY `created_at` DESC, `id` DESC
         LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException('No active common conversion rule bundle');
    }
    $row['id'] = (int)$row['id'];
    $row['payload_hash'] = strtolower($row['payload_hash']);
    return $row;
}

function jyutdictCommonImportGetJob(PDO $dbh, $jobId, $ownerId, $forUpdate = false) {
    $suffix = $forUpdate ? ' FOR UPDATE' : '';
    $stmt = $dbh->prepare(
        "SELECT j.*, HEX(j.`content_hash`) AS `content_hash_hex`,
                b.`version` AS `rule_bundle_version`,
                HEX(b.`payload_hash`) AS `rule_bundle_hash`
         FROM `common_import_jobs` AS j
         JOIN `common_rule_bundles` AS b ON b.`id` = j.`rule_bundle_id`
         WHERE j.`id` = ? AND j.`created_by` = ?{$suffix}"
    );
    $stmt->execute([$jobId, $ownerId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException('Import job not found');
    }
    foreach ([
        'created_by', 'area_id', 'rule_bundle_id', 'expected_chunk_count', 'expected_row_count',
        'character_count', 'syllable_count', 'toneless_syllable_count', 'skipped_row_count',
        'received_chunk_count', 'received_row_count', 'published_release_id',
    ] as $field) {
        $row[$field] = $row[$field] === null ? null : (int)$row[$field];
    }
    $row['content_hash_hex'] = strtolower($row['content_hash_hex']);
    $row['rule_bundle_hash'] = strtolower($row['rule_bundle_hash']);
    return $row;
}

function jyutdictCommonImportPublicJob($row) {
    $config = json_decode((string)($row['config_json'] ?? ''), true);
    $stable = json_decode((string)($row['stable_metadata_json'] ?? ''), true);
    $newArea = json_decode((string)($row['new_area_json'] ?? ''), true);
    return [
        'id' => $row['id'],
        'area_id' => $row['area_id'],
        'status' => $row['status'],
        'source_filename' => $row['source_filename'],
        'source_date' => $row['source_date'],
        'source_sheet' => $row['source_sheet'],
        'converter_version' => $row['converter_version'],
        'rule_profile' => $row['rule_profile'],
        'rule_bundle_id' => $row['rule_bundle_id'],
        'rule_bundle_version' => $row['rule_bundle_version'],
        'rule_bundle_hash' => $row['rule_bundle_hash'],
        'expected_chunk_count' => $row['expected_chunk_count'],
        'expected_row_count' => $row['expected_row_count'],
        'received_chunk_count' => $row['received_chunk_count'],
        'received_row_count' => $row['received_row_count'],
        'character_count' => $row['character_count'],
        'syllable_count' => $row['syllable_count'],
        'toneless_syllable_count' => $row['toneless_syllable_count'],
        'skipped_row_count' => $row['skipped_row_count'],
        'content_hash' => $row['content_hash_hex'],
        'config' => is_array($config) ? $config : new stdClass(),
        'stable_metadata' => is_array($stable) ? $stable : new stdClass(),
        'new_area' => is_array($newArea) ? $newArea : null,
        'published_release_id' => $row['published_release_id'],
        'last_error' => $row['last_error'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
        'published_at' => $row['published_at'],
    ];
}

function jyutdictCommonImportCanonicalRow($row) {
    return [
        'row_no' => (int)$row['row_no'],
        'display_order' => (int)$row['display_order'],
        'chara' => (string)$row['chara'],
        'initial' => (string)$row['initial'],
        'nuclei' => (string)$row['nuclei'],
        'coda' => (string)$row['coda'],
        'tone' => (string)$row['tone'],
        'ipa' => (string)$row['ipa'],
        'note' => (string)$row['note'],
        'alt_group' => $row['alt_group'] === null ? null : (int)$row['alt_group'],
        'source_row' => $row['source_row'] === null ? null : (int)$row['source_row'],
    ];
}

function jyutdictCommonImportCanonicalJson($row) {
    return json_encode(
        jyutdictCommonImportCanonicalRow($row),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}

function jyutdictCommonImportValidateRow($row) {
    if (!is_array($row)) {
        throw new RuntimeException('Each import row must be an object');
    }
    $normal = [
        'row_no' => (int)($row['row_no'] ?? 0),
        'display_order' => (int)($row['display_order'] ?? 0),
        'chara' => jyutdictCommonImportCleanText($row['chara'] ?? '', 'chara', 16),
        'initial' => jyutdictCommonImportCleanText($row['initial'] ?? '', 'initial', 32, true),
        'nuclei' => jyutdictCommonImportCleanText($row['nuclei'] ?? '', 'nuclei', 32),
        'coda' => jyutdictCommonImportCleanText($row['coda'] ?? '', 'coda', 16, true),
        'tone' => jyutdictCommonImportCleanText($row['tone'] ?? '', 'tone', 8, true),
        'ipa' => jyutdictCommonImportCleanText($row['ipa'] ?? '', 'ipa', 128, true),
        'note' => jyutdictCommonImportCleanText($row['note'] ?? '', 'note', 65535, true),
        'alt_group' => array_key_exists('alt_group', $row) && $row['alt_group'] !== null
            ? (int)$row['alt_group'] : null,
        'source_row' => array_key_exists('source_row', $row) && $row['source_row'] !== null
            ? (int)$row['source_row'] : null,
    ];
    if ($normal['row_no'] < 1 || $normal['display_order'] < 1) {
        throw new RuntimeException('row_no and display_order must be positive');
    }
    if ($normal['alt_group'] !== null && ($normal['alt_group'] < 1 || $normal['alt_group'] > 65535)) {
        throw new RuntimeException('alt_group is out of range');
    }
    if ($normal['source_row'] !== null && $normal['source_row'] < 1) {
        throw new RuntimeException('source_row must be positive');
    }
    return $normal;
}

function jyutdictCommonImportDecodeChunk($payload) {
    if ($payload === '' || strlen($payload) > JYUTDICT_IMPORT_MAX_COMPRESSED_CHUNK) {
        throw new RuntimeException('Compressed chunk is empty or too large');
    }
    $decoded = gzdecode($payload);
    if ($decoded === false) {
        throw new RuntimeException('Chunk is not valid gzip data');
    }
    if (strlen($decoded) > JYUTDICT_IMPORT_MAX_UNCOMPRESSED_CHUNK) {
        throw new RuntimeException('Uncompressed chunk is too large');
    }
    $rows = [];
    foreach (preg_split('/\r?\n/', trim($decoded)) as $lineNo => $line) {
        if ($line === '') {
            continue;
        }
        $row = json_decode($line, true);
        if (!is_array($row)) {
            throw new RuntimeException('Invalid NDJSON at chunk line ' . ($lineNo + 1));
        }
        $rows[] = jyutdictCommonImportValidateRow($row);
    }
    if (!$rows) {
        throw new RuntimeException('Chunk contains no rows');
    }
    return $rows;
}

function jyutdictCommonImportBuildSnapshot(PDO $dbh, $jobId) {
    $stmt = $dbh->prepare(
        "SELECT `row_no`, `display_order`, `chara`, `initial`, `nuclei`, `coda`, `tone`,
                `ipa`, `note`, `alt_group`, `source_row`
         FROM `common_import_rows`
         WHERE `job_id` = ?
         ORDER BY `display_order`, `row_no`"
    );
    $stmt->execute([$jobId]);
    $lines = [];
    $characters = [];
    $syllables = [];
    $toneless = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lines[] = jyutdictCommonImportCanonicalJson($row);
        $characters[$row['chara']] = true;
        $syllables[$row['initial'] . "\x1f" . $row['nuclei'] . "\x1f" . $row['coda'] . "\x1f" . $row['tone']] = true;
        $toneless[$row['initial'] . "\x1f" . $row['nuclei'] . "\x1f" . $row['coda']] = true;
    }
    $raw = implode("\n", $lines);
    if ($raw !== '') {
        $raw .= "\n";
    }
    $gzip = gzencode($raw, 6, ZLIB_ENCODING_GZIP);
    if ($gzip === false) {
        throw new RuntimeException('Unable to compress release snapshot');
    }
    if (strlen($gzip) > 16777215) {
        throw new RuntimeException('Compressed release snapshot exceeds MEDIUMBLOB capacity');
    }
    return [
        'raw' => $raw,
        'gzip' => $gzip,
        'hash' => hash('sha256', $raw),
        'entry_count' => count($lines),
        'character_count' => count($characters),
        'syllable_count' => count($syllables),
        'toneless_syllable_count' => count($toneless),
    ];
}

function jyutdictCommonImportNewArea(PDO $dbh, $json, $stableJson) {
    $input = json_decode((string)$json, true);
    if (!is_array($input)) {
        throw new RuntimeException('New location metadata is missing');
    }
    $sheetname = jyutdictMaintenanceValidateSheetname($input['sheetname'] ?? '');
    $stmt = $dbh->prepare("SELECT COUNT(*) FROM `i_area_list` WHERE `sheetname` = ?");
    $stmt->execute([$sheetname]);
    if ((int)$stmt->fetchColumn() > 0 || jyutdictMaintenanceTableExists($dbh, $sheetname)) {
        throw new RuntimeException('sheetname is already in use');
    }
    $metadata = jyutdictMaintenanceValidateMetadata($input, [
        'first' => '', 'second' => '', 'third' => '',
        'longitude' => 0.0, 'latitude' => 0.0, 'color' => '#CCCCCC',
    ]);
    $stable = json_decode((string)$stableJson, true);
    $stable = is_array($stable) ? $stable : [];
    $detailedName = jyutdictCommonImportCleanText($stable['detailed_name'] ?? '', 'detailed_name', 255, true);
    $author = jyutdictCommonImportCleanText($stable['sheet_author'] ?? '', 'sheet_author', 2000, true);
    $sortOrder = (int)$dbh->query(
        "SELECT COALESCE(MAX(`sort_order`), 0) + 10 FROM `i_area_list` WHERE `archived_at` IS NULL"
    )->fetchColumn();
    $stmt = $dbh->prepare(
        "INSERT INTO `i_area_list`
         (`longitude`, `latitude`, `first`, `second`, `third`, `detailed_name`, `sheet_author`,
          `sheetname`, `color`, `is_visible`, `sort_order`, `archived_at`, `archived_by`)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, NULL, NULL)"
    );
    $stmt->execute([
        $metadata['longitude'], $metadata['latitude'], $metadata['first'], $metadata['second'],
        $metadata['third'], $detailedName ?: null, $author ?: null, $sheetname,
        $metadata['color'], $sortOrder,
    ]);
    return (int)$dbh->lastInsertId();
}

function jyutdictCommonImportPublish(PDO $dbh, $jobId, $ownerId) {
    $dbh->beginTransaction();
    try {
        $job = jyutdictCommonImportGetJob($dbh, $jobId, $ownerId, true);
        if ($job['status'] === 'published') {
            $dbh->commit();
            return [
                'already_published' => true,
                'area_id' => $job['area_id'],
                'release_id' => $job['published_release_id'],
            ];
        }
        if (!in_array($job['status'], ['ready', 'receiving', 'failed'], true)) {
            throw new RuntimeException('Import job cannot be published in its current state');
        }
        if ($job['received_chunk_count'] !== $job['expected_chunk_count'] ||
            $job['received_row_count'] !== $job['expected_row_count']) {
            throw new RuntimeException('Import job has not received every chunk');
        }
        $dbh->prepare(
            "UPDATE `common_import_jobs`
             SET `status` = 'publishing', `last_error` = NULL WHERE `id` = ?"
        )->execute([$jobId]);

        $snapshot = jyutdictCommonImportBuildSnapshot($dbh, $jobId);
        foreach (['entry_count', 'character_count', 'syllable_count', 'toneless_syllable_count'] as $field) {
            $expectedField = $field === 'entry_count' ? 'expected_row_count' : $field;
            if ($snapshot[$field] !== $job[$expectedField]) {
                throw new RuntimeException("Server statistic mismatch: {$field}");
            }
        }
        if (!hash_equals($job['content_hash_hex'], $snapshot['hash'])) {
            throw new RuntimeException('Published content hash does not match browser preview');
        }

        $areaId = $job['area_id'];
        if ($areaId === null) {
            $areaId = jyutdictCommonImportNewArea(
                $dbh,
                $job['new_area_json'],
                $job['stable_metadata_json']
            );
            $dbh->prepare("UPDATE `common_import_jobs` SET `area_id` = ? WHERE `id` = ?")
                ->execute([$areaId, $jobId]);
        }
        $areaStmt = $dbh->prepare("SELECT * FROM `i_area_list` WHERE `id` = ? FOR UPDATE");
        $areaStmt->execute([$areaId]);
        $area = $areaStmt->fetch(PDO::FETCH_ASSOC);
        if (!$area || $area['archived_at'] !== null) {
            throw new RuntimeException('Target location does not exist or is archived');
        }

        $stable = json_decode((string)$job['stable_metadata_json'], true);
        if (is_array($stable)) {
            $detailedName = array_key_exists('detailed_name', $stable)
                ? jyutdictCommonImportCleanText($stable['detailed_name'], 'detailed_name', 255, true)
                : $area['detailed_name'];
            $author = array_key_exists('sheet_author', $stable)
                ? jyutdictCommonImportCleanText($stable['sheet_author'], 'sheet_author', 2000, true)
                : $area['sheet_author'];
            $dbh->prepare(
                "UPDATE `i_area_list` SET `detailed_name` = ?, `sheet_author` = ? WHERE `id` = ?"
            )->execute([$detailedName ?: null, $author ?: null, $areaId]);
        }

        $parentReleaseId = $area['current_release_id'] === null ? null : (int)$area['current_release_id'];
        $releaseNoStmt = $dbh->prepare(
            "SELECT COALESCE(MAX(`release_no`), 0) + 1 FROM `common_releases` WHERE `area_id` = ?"
        );
        $releaseNoStmt->execute([$areaId]);
        $releaseNo = (int)$releaseNoStmt->fetchColumn();
        $oldCountStmt = $dbh->prepare("SELECT COUNT(*) FROM `common_entries` WHERE `area_id` = ?");
        $oldCountStmt->execute([$areaId]);
        $oldCount = (int)$oldCountStmt->fetchColumn();

        $releaseStmt = $dbh->prepare(
            "INSERT INTO `common_releases`
             (`area_id`, `release_no`, `parent_release_id`, `source_type`, `source_ref`,
              `source_filename`, `source_date`, `source_sheet`,
              `insert_count`, `update_count`, `delete_count`, `entry_count`, `character_count`,
              `syllable_count`, `toneless_syllable_count`, `skipped_row_count`,
              `import_job_id`, `rule_bundle_id`, `rule_profile`, `converter_version`,
              `content_hash`, `message`, `published_by`)
             VALUES (?, ?, ?, 'browser_excel', ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', ?)"
        );
        $releaseStmt->execute([
            $areaId, $releaseNo, $parentReleaseId, $job['source_filename'], $job['source_filename'],
            $job['source_date'], $job['source_sheet'], $snapshot['entry_count'], $oldCount,
            $snapshot['entry_count'], $snapshot['character_count'], $snapshot['syllable_count'],
            $snapshot['toneless_syllable_count'], $job['skipped_row_count'], $jobId,
            $job['rule_bundle_id'], $job['rule_profile'], $job['converter_version'],
            hex2bin($snapshot['hash']), $ownerId,
        ]);
        $releaseId = (int)$dbh->lastInsertId();

        $dbh->prepare("DELETE FROM `common_entries` WHERE `area_id` = ?")->execute([$areaId]);
        $insert = $dbh->prepare(
            "INSERT INTO `common_entries`
             (`area_id`, `display_order`, `chara`, `initial`, `nuclei`, `coda`, `tone`, `ipa`,
              `note`, `alt_group`, `row_revision`, `row_hash`, `created_release_id`,
              `last_changed_release_id`, `source_import_id`, `source_sheet`, `source_row`,
              `legacy_table`, `legacy_row_id`)
             SELECT ?, `display_order`, `chara`, `initial`, `nuclei`, `coda`, `tone`, `ipa`,
                    `note`, `alt_group`, 1, `row_hash`, ?, ?, NULL, ?, `source_row`, NULL, NULL
             FROM `common_import_rows`
             WHERE `job_id` = ?
             ORDER BY `display_order`, `row_no`"
        );
        $insert->execute([$areaId, $releaseId, $releaseId, $job['source_sheet'], $jobId]);

        $snapshotStmt = $dbh->prepare(
            "INSERT INTO `common_release_snapshots`
             (`release_id`, `area_id`, `schema_version`, `entry_count`, `payload_hash`,
              `payload_gzip`, `compressed_bytes`, `uncompressed_bytes`)
             VALUES (?, ?, 1, ?, ?, ?, ?, ?)"
        );
        $snapshotStmt->bindValue(1, $releaseId, PDO::PARAM_INT);
        $snapshotStmt->bindValue(2, $areaId, PDO::PARAM_INT);
        $snapshotStmt->bindValue(3, $snapshot['entry_count'], PDO::PARAM_INT);
        $snapshotStmt->bindValue(4, hex2bin($snapshot['hash']), PDO::PARAM_LOB);
        $snapshotStmt->bindValue(5, $snapshot['gzip'], PDO::PARAM_LOB);
        $snapshotStmt->bindValue(6, strlen($snapshot['gzip']), PDO::PARAM_INT);
        $snapshotStmt->bindValue(7, strlen($snapshot['raw']), PDO::PARAM_INT);
        $snapshotStmt->execute();

        $dbh->prepare("UPDATE `i_area_list` SET `current_release_id` = ? WHERE `id` = ?")
            ->execute([$releaseId, $areaId]);
        $dbh->prepare(
            "UPDATE `common_import_jobs`
             SET `status` = 'published', `area_id` = ?, `published_release_id` = ?,
                 `published_at` = NOW(), `last_error` = NULL
             WHERE `id` = ?"
        )->execute([$areaId, $releaseId, $jobId]);
        $dbh->commit();
        return [
            'already_published' => false,
            'area_id' => $areaId,
            'release_id' => $releaseId,
            'release_no' => $releaseNo,
            'entry_count' => $snapshot['entry_count'],
            'character_count' => $snapshot['character_count'],
        ];
    } catch (Throwable $error) {
        if ($dbh->inTransaction()) {
            $dbh->rollBack();
        }
        try {
            $stmt = $dbh->prepare(
                "UPDATE `common_import_jobs`
                 SET `status` = 'failed', `last_error` = ?
                 WHERE `id` = ? AND `created_by` = ? AND `status` <> 'published'"
            );
            $stmt->execute([$error->getMessage(), $jobId, $ownerId]);
        } catch (Throwable $ignored) {
        }
        throw $error;
    }
}
