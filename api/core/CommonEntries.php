<?php
/** Unified current-state lookup and canonical hashing helpers. */

function jyutdictCommonReadMode() {
    $configFile = __DIR__ . '/../config/common.php';
    if (is_file($configFile)) {
        require_once $configFile;
    }

    $mode = defined('COMMON_ENTRIES_READ_MODE')
        ? COMMON_ENTRIES_READ_MODE
        : getenv('COMMON_ENTRIES_READ_MODE');
    if ($mode === false || $mode === '') {
        return 'legacy';
    }
    if ($mode !== 'legacy' && $mode !== 'common') {
        throw new RuntimeException('Invalid COMMON_ENTRIES_READ_MODE');
    }
    return $mode;
}

function jyutdictCommonSemanticFields() {
    return ['chara', 'initial', 'nuclei', 'coda', 'tone', 'ipa', 'note', 'alt_group'];
}

function jyutdictCommonNormalizeRow(array $row) {
    $normalized = [];
    foreach (jyutdictCommonSemanticFields() as $field) {
        if ($field === 'alt_group') {
            $normalized[$field] = $row[$field] === null ? null : (int)$row[$field];
        } else {
            $normalized[$field] = (string)$row[$field];
        }
    }
    return $normalized;
}

function jyutdictCommonEncodeValue($value) {
    if ($value === null) {
        return "N";
    }
    $bytes = (string)$value;
    return "V" . pack('N', strlen($bytes)) . $bytes;
}

function jyutdictCommonRowHash(array $row) {
    $payload = "jyutdict-common-row-v1\0";
    $normalized = jyutdictCommonNormalizeRow($row);
    foreach (jyutdictCommonSemanticFields() as $field) {
        $payload .= jyutdictCommonEncodeValue($normalized[$field]);
    }
    return hash('sha256', $payload, true);
}

function jyutdictCommonRowsEqual(array $left, array $right) {
    return jyutdictCommonNormalizeRow($left) === jyutdictCommonNormalizeRow($right);
}

/** Hash the visible ordered multiset. Database ids and legacy provenance are excluded. */
function jyutdictCommonContentHash(array $orderedRows) {
    $context = hash_init('sha256');
    hash_update($context, "jyutdict-common-content-v1\0");
    hash_update($context, pack('N', count($orderedRows)));
    foreach ($orderedRows as $row) {
        hash_update($context, jyutdictCommonRowHash($row));
    }
    return hash_final($context, true);
}

function jyutdictCommonAreaIds(array $areas) {
    $ids = [];
    $seen = [];
    foreach ($areas as $area) {
        $id = (int)$area['id'];
        if (!isset($seen[$id])) {
            $seen[$id] = true;
            $ids[] = $id;
        }
    }
    return $ids;
}

/** Return shape: [area_id => [character => [row, ...]]]. */
function jyutdictLookupCommonLocationCharacters(PDO $dbh, array $areas, array $characters) {
    $areaIds = jyutdictCommonAreaIds($areas);
    $characters = jyutdictUniqueStrings($characters);
    if (!$areaIds || !$characters) {
        return [];
    }

    $characterPlaceholders = implode(',', array_fill(0, count($characters), '?'));
    $areaPlaceholders = implode(',', array_fill(0, count($areaIds), '?'));
    $sql =
        "SELECT `area_id`, `chara`, `initial`, `nuclei`, `coda`, `tone`, `ipa`, `note`, `alt_group`
         FROM `common_entries`
         WHERE `chara` IN ({$characterPlaceholders})
           AND `area_id` IN ({$areaPlaceholders})
         ORDER BY `area_id`, `display_order`, `id`";
    $stmt = $dbh->prepare($sql);
    $stmt->execute(array_merge($characters, $areaIds));

    $grouped = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $areaId = (int)$row['area_id'];
        $character = $row['chara'];
        unset($row['area_id'], $row['chara']);
        $grouped[$areaId][$character][] = $row;
    }
    return $grouped;
}

/** Return shape: [area_id => [row, ...]]. */
function jyutdictLookupCommonPronunciations(PDO $dbh, array $areas, array $parts) {
    $areaIds = jyutdictCommonAreaIds($areas);
    if (!$areaIds) {
        return [];
    }

    $conditions = [];
    $params = [];
    foreach (['initial', 'nuclei', 'coda', 'tone'] as $column) {
        $value = $parts[$column];
        if ($value !== '%') {
            $conditions[] = "`{$column}` = ?";
            $params[] = $value;
        }
    }
    $areaPlaceholders = implode(',', array_fill(0, count($areaIds), '?'));
    $conditions[] = "`area_id` IN ({$areaPlaceholders})";
    $params = array_merge($params, $areaIds);

    $sql =
        "SELECT `area_id`, `chara`, `initial`, `nuclei`, `coda`, `tone`
         FROM `common_entries`
         WHERE " . implode(' AND ', $conditions) . "
         ORDER BY `area_id`, `display_order`, `id`";
    $stmt = $dbh->prepare($sql);
    $stmt->execute($params);

    $grouped = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $areaId = (int)$row['area_id'];
        unset($row['area_id']);
        $grouped[$areaId][] = $row;
    }
    return $grouped;
}

function jyutdictLookupConfiguredLocationCharacters(PDO $dbh, array $areas, array $characters) {
    if (jyutdictCommonReadMode() === 'common') {
        return jyutdictLookupCommonLocationCharacters($dbh, $areas, $characters);
    }
    return jyutdictLookupLocationCharacters($dbh, $areas, $characters);
}

function jyutdictLookupConfiguredLocationPronunciations(PDO $dbh, array $areas, array $parts) {
    if (jyutdictCommonReadMode() === 'common') {
        return jyutdictLookupCommonPronunciations($dbh, $areas, $parts);
    }
    return jyutdictLookupSourcePronunciations($dbh, $areas, $parts);
}
