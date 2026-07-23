<?php
/**
 * Batched lookup helpers for the legacy per-location dictionary tables.
 *
 * Physical table names cannot be parameter-bound.  Every identifier used by
 * this file is therefore loaded from the database and checked against a
 * deliberately small identifier grammar before it is interpolated into SQL.
 */

function jyutdictAssertTableName($tableName) {
    if (!is_string($tableName) || !preg_match('/\A[a-zA-Z][a-zA-Z0-9_]*\z/', $tableName)) {
        throw new RuntimeException('Invalid dictionary table name');
    }
    return $tableName;
}

function jyutdictFormatSheetMetadata(array $area) {
    $statistics = [];
    if (!empty($area['source_date'])) {
        $statistics[] = '版本：' . $area['source_date'];
    }
    foreach ([
        'character_count' => '字數',
        'syllable_count' => '音節數',
        'toneless_syllable_count' => '不帶調音節數',
        'skipped_row_count' => '略過行數',
    ] as $field => $label) {
        if ($area[$field] !== null && ($field !== 'skipped_row_count' || (int)$area[$field] > 0)) {
            $statistics[] = $label . '：' . (int)$area[$field];
        }
    }
    $area['sheet_statistic'] = implode("\n", $statistics);
    $lines = [];
    if (!empty($area['detailed_name'])) {
        $lines[] = '地點：' . $area['detailed_name'];
    }
    if (!empty($area['sheet_author'])) {
        $lines[] = '字表作者：' . $area['sheet_author'];
    }
    if ($area['sheet_statistic'] !== '') {
        $lines[] = $area['sheet_statistic'];
    }
    $area['sheet_info'] = $lines
        ? implode("\n", $lines)
        : (string)($area['sheet_info_legacy'] ?? '');
    unset($area['sheet_info_legacy']);
    return $area;
}

function jyutdictLoadAreas(PDO $dbh) {
    $stmt = $dbh->query(
        "SELECT a.`id`, a.`longitude`, a.`latitude`, a.`first`, a.`second`, a.`third`,
                a.`detailed_name`, a.`sheet_author`, a.`sheet_info` AS `sheet_info_legacy`,
                a.`sheetname`, a.`color`, a.`current_phonology_id`,
                r.`source_date`, r.`character_count`, r.`syllable_count`,
                r.`toneless_syllable_count`, r.`skipped_row_count`,
                IF(p.`id` IS NOT NULL AND p.`source_release_id` = a.`current_release_id`, 1, 0)
                  AS `has_phonology`
         FROM `i_area_list` AS a
         LEFT JOIN `common_releases` AS r ON r.`id` = a.`current_release_id`
         LEFT JOIN `phonology_reports` AS p ON p.`id` = a.`current_phonology_id`
         WHERE a.`is_visible` = 1 AND a.`current_release_id` IS NOT NULL AND a.`archived_at` IS NULL
         ORDER BY a.`sort_order`, a.`id`"
    );
    $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($areas as &$area) {
        jyutdictAssertTableName($area['sheetname']);
        $area['id'] = (int)$area['id'];
        $area['current_phonology_id'] = $area['current_phonology_id'] === null
            ? null : (int)$area['current_phonology_id'];
        $area['has_phonology'] = (int)$area['has_phonology'];
        $area = jyutdictFormatSheetMetadata($area);
    }
    unset($area);
    return $areas;
}

/** Load hidden and visible areas for maintenance scripts. */
function jyutdictLoadAllAreas(PDO $dbh) {
    $stmt = $dbh->query(
        "SELECT a.`id`, a.`longitude`, a.`latitude`, a.`first`, a.`second`, a.`third`,
                a.`detailed_name`, a.`sheet_author`, a.`sheet_info` AS `sheet_info_legacy`,
                a.`sheetname`, a.`color`, a.`current_phonology_id`,
                a.`is_visible`, a.`sort_order`,
                r.`source_date`, r.`character_count`, r.`syllable_count`,
                r.`toneless_syllable_count`, r.`skipped_row_count`,
                IF(p.`id` IS NOT NULL AND p.`source_release_id` = a.`current_release_id`, 1, 0)
                  AS `has_phonology`
         FROM `i_area_list` AS a
         LEFT JOIN `common_releases` AS r ON r.`id` = a.`current_release_id`
         LEFT JOIN `phonology_reports` AS p ON p.`id` = a.`current_phonology_id`
         ORDER BY a.`sort_order`, a.`id`"
    );
    $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($areas as &$area) {
        jyutdictAssertTableName($area['sheetname']);
        $area['id'] = (int)$area['id'];
        $area['is_visible'] = (int)$area['is_visible'];
        $area['sort_order'] = (int)$area['sort_order'];
        $area['current_phonology_id'] = $area['current_phonology_id'] === null
            ? null : (int)$area['current_phonology_id'];
        $area['has_phonology'] = (int)$area['has_phonology'];
        $area = jyutdictFormatSheetMetadata($area);
    }
    unset($area);
    return $areas;
}

function jyutdictLoadWanshyu(PDO $dbh) {
    $stmt = $dbh->query(
        "SELECT `id`, `name`, `fullname`, `date`, `sheetname`
         FROM `i_wanshyu_list` ORDER BY `id`, `sheetname`"
    );
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($books as &$book) {
        jyutdictAssertTableName($book['sheetname']);
        $book['id'] = (int)$book['id'];
    }
    unset($book);
    return $books;
}

function jyutdictUniqueStrings(array $values) {
    $result = [];
    $seen = [];
    foreach ($values as $value) {
        $value = (string)$value;
        if (!isset($seen[$value])) {
            $seen[$value] = true;
            $result[] = $value;
        }
    }
    return $result;
}

/**
 * Fetch all location rows for all requested characters with one SQL statement.
 * Return shape: [area_id => [character => [row, ...]]].
 */
function jyutdictLookupLocationCharacters(PDO $dbh, array $areas, array $characters) {
    $characters = jyutdictUniqueStrings($characters);
    if (!$areas || !$characters) {
        return [];
    }

    $placeholderList = implode(',', array_fill(0, count($characters), '?'));
    $branches = [];
    $params = [];

    foreach (array_values($areas) as $sourceOrder => $area) {
        $table = jyutdictAssertTableName($area['sheetname']);
        $areaId = (int)$area['id'];
        $branches[] =
            "SELECT {$sourceOrder} AS `source_order`, {$areaId} AS `source_id`, `id` AS `row_id`,
                    `chara`, `initial`, `nuclei`, `coda`, `tone`, `ipa`, `note`, `alt_group`
             FROM `{$table}` WHERE `chara` IN ({$placeholderList})";
        foreach ($characters as $character) {
            $params[] = $character;
        }
    }

    $sql = implode(" UNION ALL ", $branches) . " ORDER BY `source_order`, `row_id`";
    $stmt = $dbh->prepare($sql);
    $stmt->execute($params);

    $grouped = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sourceId = (int)$row['source_id'];
        $character = $row['chara'];
        unset($row['source_order'], $row['source_id'], $row['row_id'], $row['chara']);
        $grouped[$sourceId][$character][] = $row;
    }
    return $grouped;
}

/**
 * Fetch pronunciation matches from all supplied sources with one statement.
 * A percent sign means "do not constrain this component", matching the old API.
 * Return shape: [source_id => [row, ...]].
 */
function jyutdictLookupSourcePronunciations(PDO $dbh, array $sources, array $parts) {
    if (!$sources) {
        return [];
    }

    $componentMap = [
        'initial' => $parts['initial'],
        'nuclei' => $parts['nuclei'],
        'coda' => $parts['coda'],
        'tone' => $parts['tone'],
    ];
    $conditions = [];
    $conditionValues = [];
    foreach ($componentMap as $column => $value) {
        if ($value !== '%') {
            $conditions[] = "`{$column}` = ?";
            $conditionValues[] = $value;
        }
    }
    $where = $conditions ? implode(' AND ', $conditions) : '1 = 1';

    $branches = [];
    $params = [];
    foreach (array_values($sources) as $sourceOrder => $source) {
        $table = jyutdictAssertTableName($source['sheetname']);
        $sourceId = (int)$source['id'];
        $branches[] =
            "SELECT {$sourceOrder} AS `source_order`, {$sourceId} AS `source_id`, `id` AS `row_id`,
                    `chara`, `initial`, `nuclei`, `coda`, `tone`
             FROM `{$table}` WHERE {$where}";
        foreach ($conditionValues as $value) {
            $params[] = $value;
        }
    }

    $sql = implode(" UNION ALL ", $branches) . " ORDER BY `source_order`, `row_id`";
    $stmt = $dbh->prepare($sql);
    $stmt->execute($params);

    $grouped = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sourceId = (int)$row['source_id'];
        unset($row['source_order'], $row['source_id'], $row['row_id']);
        $grouped[$sourceId][] = $row;
    }
    return $grouped;
}

/** Fetch one fixed table for a set of characters, grouped by character. */
function jyutdictLookupTableCharacters(PDO $dbh, $tableName, array $characters, array $columns) {
    $characters = jyutdictUniqueStrings($characters);
    if (!$characters) {
        return [];
    }
    $tableName = jyutdictAssertTableName($tableName);
    foreach ($columns as $column) {
        if (!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]*\z/', $column)) {
            throw new RuntimeException('Invalid dictionary column name');
        }
    }
    $selectColumns = implode(', ', array_map(function ($column) {
        return "`{$column}`";
    }, $columns));
    $placeholders = implode(',', array_fill(0, count($characters), '?'));
    $sql = "SELECT `chara`, {$selectColumns} FROM `{$tableName}` " .
           "WHERE `chara` IN ({$placeholders})";
    $stmt = $dbh->prepare($sql);
    $stmt->execute($characters);

    $grouped = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $character = $row['chara'];
        unset($row['chara']);
        $grouped[$character][] = $row;
    }
    return $grouped;
}
