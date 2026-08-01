<?php
/** Resolve editor article-location assignments to common-sheet area ids. */

require_once __DIR__ . '/LocationArticleIdentity.php';

function jyutdictRoleHasAllAreaAccess($role) {
    return in_array((string)$role, ['admin', 'owner'], true);
}

function jyutdictEditorAssignedCanonicalNames(PDO $dbh, $userId) {
    $stmt = $dbh->prepare(
        "SELECT `location_name` FROM `editor_locations` WHERE `editor_id` = ?"
    );
    $stmt->execute([(int)$userId]);
    $redirects = jyutdictLoadLocationRedirectMap($dbh);
    $names = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $name) {
        $name = (string)$name;
        $names[$redirects[$name] ?? $name] = true;
    }
    return $names;
}

function jyutdictEditableAreaRows(PDO $dbh, $userId, $role, $includeArchived = false) {
    $where = $includeArchived ? '' : ' WHERE `archived_at` IS NULL';
    $rows = $dbh->query(
        "SELECT `id`, `first`, `second`, `third`, `detailed_name`, `sheetname`, `archived_at`
         FROM `i_area_list`{$where} ORDER BY `sort_order`, `id`"
    )->fetchAll(PDO::FETCH_ASSOC);
    if (jyutdictRoleHasAllAreaAccess($role)) {
        return $rows;
    }
    if ((string)$role !== 'editor') {
        return [];
    }

    $assigned = jyutdictEditorAssignedCanonicalNames($dbh, $userId);
    if (!$assigned) {
        return [];
    }
    $redirects = jyutdictLoadLocationRedirectMap($dbh);
    return array_values(array_filter($rows, function ($row) use ($assigned, $redirects) {
        $displayName = (string)$row['second'] . (string)($row['third'] ?? '');
        if ($displayName === '') {
            $displayName = (string)$row['first'];
        }
        $canonical = $redirects[$displayName] ?? $displayName;
        return isset($assigned[$canonical]);
    }));
}

function jyutdictEditableAreaIds(PDO $dbh, $userId, $role, $includeArchived = false) {
    if (jyutdictRoleHasAllAreaAccess($role)) {
        return null;
    }
    $ids = [];
    foreach (jyutdictEditableAreaRows($dbh, $userId, $role, $includeArchived) as $row) {
        $ids[(int)$row['id']] = true;
    }
    return $ids;
}

function jyutdictCanEditArea(PDO $dbh, $userId, $role, $areaId, $includeArchived = false) {
    $ids = jyutdictEditableAreaIds($dbh, $userId, $role, $includeArchived);
    return $ids === null || isset($ids[(int)$areaId]);
}

function jyutdictRequireEditableArea(PDO $dbh, $userId, $role, $areaId, $includeArchived = false) {
    if (jyutdictCanEditArea($dbh, $userId, $role, $areaId, $includeArchived)) {
        return;
    }
    outputJson([
        'error' => 'Forbidden: This location is not assigned to the current editor',
        'area_id' => (int)$areaId,
    ], 403);
}

function jyutdictEditableRuleProfiles(PDO $dbh, $userId, $role) {
    if (jyutdictRoleHasAllAreaAccess($role)) {
        return null;
    }
    $profiles = [];
    foreach (jyutdictEditableAreaRows($dbh, $userId, $role) as $row) {
        $profile = (string)$row['second'] . (string)($row['third'] ?? '');
        if ($profile === '') {
            $profile = (string)$row['first'];
        }
        if ($profile !== '') {
            $profiles[$profile] = true;
        }
    }
    return array_keys($profiles);
}
