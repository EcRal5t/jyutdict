<?php
/** Shared, PHP 7.4-compatible helpers for location catalogue maintenance. */

require_once __DIR__ . '/LocationLookup.php';

function jyutdictMaintenanceTableExists(PDO $dbh, $tableName) {
    $stmt = $dbh->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND TABLE_TYPE = 'BASE TABLE'"
    );
    $stmt->execute([$tableName]);
    return (int)$stmt->fetchColumn() === 1;
}

function jyutdictMaintenanceRequireSchema(PDO $dbh) {
    foreach (['admin_maintenance_events', 'maintenance_worker_state'] as $table) {
        if (!jyutdictMaintenanceTableExists($dbh, $table)) {
            throw new RuntimeException('Maintenance schema is not installed');
        }
    }
    $stmt = $dbh->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'i_area_list'
           AND COLUMN_NAME IN ('archived_at', 'archived_by')"
    );
    if ((int)$stmt->fetchColumn() !== 2) {
        throw new RuntimeException('Maintenance schema is not installed');
    }
}

function jyutdictMaintenanceRequestId() {
    return bin2hex(random_bytes(16));
}

function jyutdictMaintenanceJson($value) {
    if ($value === null) {
        return null;
    }
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Unable to encode maintenance audit data');
    }
    return $json;
}

function jyutdictMaintenanceAudit(
    PDO $dbh,
    $userId,
    $requestId,
    $action,
    $status,
    $areaId = null,
    $sheetname = null,
    $before = null,
    $after = null,
    $errorMessage = null
) {
    $stmt = $dbh->prepare(
        "INSERT INTO `admin_maintenance_events`
         (`user_id`, `request_id`, `action`, `area_id`, `sheetname`, `status`,
          `before_json`, `after_json`, `error_message`)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $userId === null ? null : (int)$userId,
        (string)$requestId,
        (string)$action,
        $areaId === null ? null : (int)$areaId,
        $sheetname === null ? null : (string)$sheetname,
        (string)$status,
        jyutdictMaintenanceJson($before),
        jyutdictMaintenanceJson($after),
        $errorMessage === null ? null : mb_substr((string)$errorMessage, 0, 4000, 'UTF-8'),
    ]);
}

function jyutdictMaintenanceGetArea(PDO $dbh, $areaId, $forUpdate = false) {
    $sql = "SELECT `id`, `longitude`, `latitude`, `first`, `second`, `third`,
                   `detailed_name`, `sheet_author`, `sheetname`,
                   `current_release_id`, `current_phonology_id`, `color`, `is_visible`, `sort_order`,
                   `archived_at`, `archived_by`
            FROM `i_area_list` WHERE `id` = ?" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $dbh->prepare($sql);
    $stmt->execute([(int)$areaId]);
    $area = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$area) {
        throw new RuntimeException('Location not found');
    }
    $area['id'] = (int)$area['id'];
    $area['longitude'] = (float)$area['longitude'];
    $area['latitude'] = (float)$area['latitude'];
    $area['is_visible'] = (int)$area['is_visible'];
    $area['sort_order'] = (int)$area['sort_order'];
    $area['current_release_id'] = $area['current_release_id'] === null
        ? null : (int)$area['current_release_id'];
    $area['current_phonology_id'] = $area['current_phonology_id'] === null
        ? null : (int)$area['current_phonology_id'];
    $area['archived_by'] = $area['archived_by'] === null ? null : (int)$area['archived_by'];
    return $area;
}

function jyutdictMaintenanceValidateSheetname($sheetname) {
    $sheetname = trim((string)$sheetname);
    jyutdictAssertTableName($sheetname);
    if (strlen($sheetname) > 64) {
        throw new RuntimeException('Location table name may not exceed 64 ASCII characters');
    }
    return $sheetname;
}

function jyutdictMaintenanceValidateMetadata(array $input, array $base = []) {
    $result = $base;
    foreach (['first', 'second', 'third'] as $field) {
        if (array_key_exists($field, $input)) {
            $value = trim((string)$input[$field]);
            if (strlen($value) > 255 || mb_strlen($value, 'UTF-8') > 80) {
                throw new RuntimeException("{$field} is too long");
            }
            $result[$field] = $value;
        }
    }
    if (array_key_exists('detailed_name', $input)) {
        $value = trim((string)$input['detailed_name']);
        if (mb_strlen($value, 'UTF-8') > 255) {
            throw new RuntimeException('detailed_name is too long');
        }
        $result['detailed_name'] = $value;
    }
    if (array_key_exists('sheet_author', $input)) {
        $value = trim((string)$input['sheet_author']);
        if (mb_strlen($value, 'UTF-8') > 2000) {
            throw new RuntimeException('sheet_author is too long');
        }
        $result['sheet_author'] = $value;
    }
    foreach (['longitude', 'latitude'] as $field) {
        if (array_key_exists($field, $input)) {
            if (!is_numeric($input[$field])) {
                throw new RuntimeException("{$field} must be numeric");
            }
            $result[$field] = (float)$input[$field];
        }
    }
    if (isset($result['longitude']) && ($result['longitude'] < -180 || $result['longitude'] > 180)) {
        throw new RuntimeException('longitude must be between -180 and 180');
    }
    if (isset($result['latitude']) && ($result['latitude'] < -90 || $result['latitude'] > 90)) {
        throw new RuntimeException('latitude must be between -90 and 90');
    }
    if (array_key_exists('color', $input)) {
        $color = strtoupper(trim((string)$input['color']));
        if (!preg_match('/\A#[0-9A-F]{6}\z/', $color)) {
            throw new RuntimeException('color must use #RRGGBB format');
        }
        $result['color'] = $color;
    }
    return $result;
}

function jyutdictMaintenanceEnqueue(PDO $dbh, array $area, $sourceRef) {
    if (!jyutdictMaintenanceTableExists($dbh, $area['sheetname'])) {
        throw new RuntimeException('Physical location table does not exist');
    }
    $stmt = $dbh->prepare(
        "INSERT INTO `common_sync_queue`
         (`area_id`, `legacy_table`, `source_ref`, `requested_generation`,
          `processed_generation`, `status`, `attempt_count`, `requested_at`,
          `started_at`, `completed_at`, `last_error`)
         VALUES (?, ?, ?, 1, 0, 'pending', 0, NOW(), NULL, NULL, NULL)
         ON DUPLICATE KEY UPDATE
           `legacy_table` = VALUES(`legacy_table`), `source_ref` = VALUES(`source_ref`),
           `requested_generation` = `requested_generation` + 1,
           `status` = 'pending', `attempt_count` = 0, `requested_at` = NOW(),
           `started_at` = NULL, `completed_at` = NULL, `last_error` = NULL"
    );
    $stmt->execute([(int)$area['id'], $area['sheetname'], (string)$sourceRef]);
}

function jyutdictMaintenanceOptionalCount(PDO $dbh, $tableName, $whereSql, array $params) {
    if (!jyutdictMaintenanceTableExists($dbh, $tableName)) {
        return 0;
    }
    $stmt = $dbh->prepare("SELECT COUNT(*) FROM `{$tableName}` WHERE {$whereSql}");
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function jyutdictMaintenanceRenamePreview(PDO $dbh, $areaId, $to) {
    $area = jyutdictMaintenanceGetArea($dbh, $areaId);
    $from = jyutdictMaintenanceValidateSheetname($area['sheetname']);
    $to = jyutdictMaintenanceValidateSheetname($to);
    if ($from === $to) {
        throw new RuntimeException('New sheetname must be different');
    }
    $stmt = $dbh->prepare("SELECT `id` FROM `i_area_list` WHERE `sheetname` = ?");
    $stmt->execute([$to]);
    if ($stmt->fetchColumn() !== false) {
        throw new RuntimeException('Target sheetname is already used');
    }
    if (!jyutdictMaintenanceTableExists($dbh, $from)) {
        throw new RuntimeException('Source physical table does not exist');
    }
    if (jyutdictMaintenanceTableExists($dbh, $to)) {
        throw new RuntimeException('Target physical table already exists');
    }
    if (jyutdictMaintenanceOptionalCount($dbh, 'common_sync_queue', '`area_id` <> ? AND `legacy_table` = ?', [$areaId, $to]) > 0) {
        throw new RuntimeException('Target sheetname is already used by another queue item');
    }
    return [
        'area_id' => (int)$areaId,
        'location' => $area['first'] . '/' . $area['second'] . '/' . $area['third'],
        'from' => $from,
        'to' => $to,
        'physical_rows' => (int)$dbh->query("SELECT COUNT(*) FROM `{$from}`")->fetchColumn(),
        'common_entries_to_update' => jyutdictMaintenanceOptionalCount(
            $dbh,
            'common_entries',
            '`area_id` = ? AND `legacy_table` = ?',
            [(int)$areaId, $from]
        ),
    ];
}

function jyutdictMaintenanceRename(PDO $dbh, $areaId, $to) {
    $preview = jyutdictMaintenanceRenamePreview($dbh, $areaId, $to);
    $from = $preview['from'];
    $to = $preview['to'];
    $lock = $dbh->query("SELECT GET_LOCK('jyutdict_location_table_rename', 0)")->fetchColumn();
    if ((int)$lock !== 1) {
        throw new RuntimeException('Another location table rename is already running');
    }
    $physicalRenamed = false;
    try {
        $preview = jyutdictMaintenanceRenamePreview($dbh, $areaId, $to);
        $dbh->exec("RENAME TABLE `{$from}` TO `{$to}`");
        $physicalRenamed = true;
        $dbh->beginTransaction();
        $area = jyutdictMaintenanceGetArea($dbh, $areaId, true);
        if ($area['sheetname'] !== $from) {
            throw new RuntimeException('Location changed after preflight');
        }
        $stmt = $dbh->prepare("UPDATE `i_area_list` SET `sheetname` = ? WHERE `id` = ?");
        $stmt->execute([$to, (int)$areaId]);
        if (jyutdictMaintenanceTableExists($dbh, 'common_entries')) {
            $stmt = $dbh->prepare(
                "UPDATE `common_entries` SET `legacy_table` = ?
                 WHERE `area_id` = ? AND `legacy_table` = ?"
            );
            $stmt->execute([$to, (int)$areaId, $from]);
        }
        if (jyutdictMaintenanceTableExists($dbh, 'common_sync_queue')) {
            $stmt = $dbh->prepare(
                "UPDATE `common_sync_queue` SET `legacy_table` = ?
                 WHERE `area_id` = ? AND `legacy_table` = ?"
            );
            $stmt->execute([$to, (int)$areaId, $from]);
        }
        $dbh->commit();
        return $preview;
    } catch (Throwable $error) {
        if ($dbh->inTransaction()) {
            $dbh->rollBack();
        }
        if ($physicalRenamed && jyutdictMaintenanceTableExists($dbh, $to) && !jyutdictMaintenanceTableExists($dbh, $from)) {
            try {
                $dbh->exec("RENAME TABLE `{$to}` TO `{$from}`");
            } catch (Throwable $rollbackError) {
                error_log('CRITICAL: unable to roll back location table rename: ' . $rollbackError->getMessage());
            }
        }
        throw $error;
    } finally {
        $dbh->query("SELECT RELEASE_LOCK('jyutdict_location_table_rename')")->fetchColumn();
    }
}
