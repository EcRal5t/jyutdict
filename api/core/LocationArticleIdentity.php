<?php
/**
 * Lightweight location-article identity helpers.
 *
 * Source dictionaries keep their own display names. Only article-related
 * operations resolve those names through location_name_redirects.
 * Compatible with PHP 7.4 and MySQL 5.7.
 */

function jyutdictLocationRedirectTableExists(PDO $dbh) {
    static $cache = [];
    $key = spl_object_hash($dbh);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $dbh->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'location_name_redirects'"
    );
    $stmt->execute();
    $cache[$key] = (int)$stmt->fetchColumn() === 1;
    return $cache[$key];
}

function jyutdictValidateLocationIdentityName($name, $field = 'location_name') {
    $name = trim((string)$name);
    if ($name === '') {
        throw new RuntimeException("{$field} is required");
    }
    if (mb_strlen($name, 'UTF-8') > 100) {
        throw new RuntimeException("{$field} may not exceed 100 characters");
    }
    return $name;
}

function jyutdictResolveLocationName(PDO $dbh, $name) {
    $name = trim((string)$name);
    if ($name === '' || !jyutdictLocationRedirectTableExists($dbh)) {
        return $name;
    }
    $stmt = $dbh->prepare(
        "SELECT `canonical_name` FROM `location_name_redirects`
         WHERE `alias_name` = ? LIMIT 1"
    );
    $stmt->execute([$name]);
    $canonical = $stmt->fetchColumn();
    return $canonical === false ? $name : (string)$canonical;
}

function jyutdictLoadLocationRedirectMap(PDO $dbh) {
    if (!jyutdictLocationRedirectTableExists($dbh)) {
        return [];
    }
    $rows = $dbh->query(
        "SELECT `alias_name`, `canonical_name`
         FROM `location_name_redirects` ORDER BY `canonical_name`, `alias_name`"
    )->fetchAll(PDO::FETCH_ASSOC);
    $map = [];
    foreach ($rows as $row) {
        $map[(string)$row['alias_name']] = (string)$row['canonical_name'];
    }
    return $map;
}

function jyutdictLocationAliasesByCanonical(PDO $dbh) {
    $result = [];
    foreach (jyutdictLoadLocationRedirectMap($dbh) as $alias => $canonical) {
        $result[$canonical][] = $alias;
    }
    return $result;
}

function jyutdictCanonicalizeAssignedLocationRows(PDO $dbh, array $rows) {
    $redirects = jyutdictLoadLocationRedirectMap($dbh);
    $aliasesByCanonical = [];
    foreach ($redirects as $alias => $canonical) {
        $aliasesByCanonical[$canonical][] = $alias;
    }
    $result = [];
    $seen = [];
    foreach ($rows as $row) {
        $requested = (string)($row['location_name'] ?? '');
        $canonical = $redirects[$requested] ?? $requested;
        if ($canonical === '' || isset($seen[$canonical])) {
            continue;
        }
        $seen[$canonical] = true;
        $row['location_name'] = $canonical;
        $row['aliases'] = $aliasesByCanonical[$canonical] ?? [];
        $result[] = $row;
    }
    return $result;
}

/**
 * Merge both source catalogues into article identities without per-row SQL.
 */
function jyutdictLoadLocationArticleIdentities(PDO $dbh) {
    $redirects = jyutdictLoadLocationRedirectMap($dbh);
    $identities = [];

    $ensure = function ($canonical) use (&$identities) {
        if (!isset($identities[$canonical])) {
            $identities[$canonical] = [
                'name' => $canonical,
                'aliases' => [],
                'sources' => [],
                'has_article' => false,
            ];
        }
    };
    $addAlias = function ($canonical, $alias) use (&$identities, $ensure) {
        $ensure($canonical);
        if ($alias !== $canonical && !in_array($alias, $identities[$canonical]['aliases'], true)) {
            $identities[$canonical]['aliases'][] = $alias;
        }
    };
    $addSource = function ($type, $id, $displayName, $first = null) use (&$identities, $redirects, $ensure, $addAlias) {
        if ($displayName === '') {
            return;
        }
        $canonical = $redirects[$displayName] ?? $displayName;
        $ensure($canonical);
        $addAlias($canonical, $displayName);
        $source = [
            'type' => $type,
            'id' => (int)$id,
            'display_name' => $displayName,
        ];
        if ($first !== null && $first !== '') {
            $source['first'] = $first;
        }
        $identities[$canonical]['sources'][] = $source;
    };

    $areaRows = $dbh->query(
        "SELECT `id`, `first`, `second`, `third`
         FROM `i_area_list`
         WHERE `is_visible` = 1 AND `archived_at` IS NULL
         ORDER BY `sort_order`, `id`"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($areaRows as $row) {
        $addSource(
            'common',
            $row['id'],
            (string)$row['second'] . (string)($row['third'] ?? ''),
            (string)$row['first']
        );
    }

    $faamjyutRows = $dbh->query(
        "SELECT `id`, `fullname`, `fullname_note`
         FROM `i_faamjyut` WHERE `kind` = 1 ORDER BY `id`"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($faamjyutRows as $row) {
        $addSource(
            'faamjyut',
            $row['id'],
            (string)$row['fullname'] . (string)($row['fullname_note'] ?? '')
        );
    }

    foreach ($redirects as $alias => $canonical) {
        $addAlias($canonical, $alias);
    }

    $articleRows = $dbh->query(
        "SELECT `location_name` FROM `articles` WHERE `article_type` = 'location'"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach ($articleRows as $articleName) {
        $canonical = $redirects[$articleName] ?? (string)$articleName;
        $ensure($canonical);
        $identities[$canonical]['has_article'] = true;
    }

    $assignmentNames = $dbh->query(
        "SELECT DISTINCT `location_name` FROM `editor_locations`"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach ($assignmentNames as $assignmentName) {
        $canonical = $redirects[$assignmentName] ?? (string)$assignmentName;
        $ensure($canonical);
    }

    foreach ($identities as &$identity) {
        sort($identity['aliases'], SORT_STRING);
    }
    unset($identity);
    uasort($identities, function ($left, $right) {
        return strcmp($left['name'], $right['name']);
    });
    return array_values($identities);
}

function jyutdictLocationIdentityMeta(PDO $dbh, $requestedName) {
    $resolved = jyutdictResolveLocationName($dbh, $requestedName);
    return [
        'requested_location_name' => (string)$requestedName,
        'resolved_location_name' => $resolved,
        'redirected' => $resolved !== (string)$requestedName,
    ];
}
