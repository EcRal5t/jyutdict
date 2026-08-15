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

/**
 * Cascade location rename to location_name_redirects, articles, and editor_locations.
 *
 * @param PDO $dbh
 * @param string $oldName Old source location name (e.g. second + third)
 * @param string $newName New source location name (e.g. second + third)
 * @param int|null $userId User ID performing the action
 */
function jyutdictCascadeLocationRename(PDO $dbh, $oldName, $newName, $userId = null) {
    $oldName = trim((string)$oldName);
    $newName = trim((string)$newName);
    if ($oldName === '' || $newName === '' || $oldName === $newName) {
        return;
    }

    $validUserId = null;
    if ($userId !== null && (int)$userId > 0) {
        $userCheck = $dbh->prepare("SELECT COUNT(*) FROM `users` WHERE `id` = ?");
        $userCheck->execute([(int)$userId]);
        if ((int)$userCheck->fetchColumn() > 0) {
            $validUserId = (int)$userId;
        }
    }

    $redirectTableExists = jyutdictLocationRedirectTableExists($dbh);

    if ($redirectTableExists) {
        // 1. If oldName was an alias_name in location_name_redirects
        $stmt = $dbh->prepare("SELECT `canonical_name` FROM `location_name_redirects` WHERE `alias_name` = ?");
        $stmt->execute([$oldName]);
        $targetCanonical = $stmt->fetchColumn();

        if ($targetCanonical !== false) {
            $targetCanonical = (string)$targetCanonical;
            if ($newName === $targetCanonical) {
                // If the new name is identical to the canonical name, self-redirect is unnecessary
                $del = $dbh->prepare("DELETE FROM `location_name_redirects` WHERE `alias_name` = ?");
                $del->execute([$oldName]);
            } else {
                // Check if newName already exists in location_name_redirects
                $stmtCheck = $dbh->prepare("SELECT `canonical_name` FROM `location_name_redirects` WHERE `alias_name` = ?");
                $stmtCheck->execute([$newName]);
                $existingNewTarget = $stmtCheck->fetchColumn();

                if ($existingNewTarget !== false) {
                    // newName already exists as an alias; remove the oldName alias row
                    $del = $dbh->prepare("DELETE FROM `location_name_redirects` WHERE `alias_name` = ?");
                    $del->execute([$oldName]);
                } else {
                    // Update the alias_name to newName
                    $upd = $dbh->prepare(
                        "UPDATE `location_name_redirects`
                         SET `alias_name` = ?, `updated_by` = ?
                         WHERE `alias_name` = ?"
                    );
                    $upd->execute([$newName, $validUserId, $oldName]);
                }
            }
        }

        // 2. If oldName was a canonical_name in location_name_redirects
        $stmtCanonical = $dbh->prepare(
            "SELECT COUNT(*) FROM `location_name_redirects` WHERE `canonical_name` = ?"
        );
        $stmtCanonical->execute([$oldName]);
        if ((int)$stmtCanonical->fetchColumn() > 0) {
            $updCanonical = $dbh->prepare(
                "UPDATE `location_name_redirects`
                 SET `canonical_name` = ?, `updated_by` = ?
                 WHERE `canonical_name` = ?"
            );
            $updCanonical->execute([$newName, $validUserId, $oldName]);

            // If there's an alias matching newName now pointing to newName (self-loop), clean it up
            $delLoop = $dbh->prepare(
                "DELETE FROM `location_name_redirects` WHERE `alias_name` = ? AND `canonical_name` = ?"
            );
            $delLoop->execute([$newName, $newName]);
        }
    }

    // 3. Migrate articles table (where location_name is oldName and article_type = 'location')
    $stmtArticleNew = $dbh->prepare(
        "SELECT `id` FROM `articles` WHERE `location_name` = ? AND `article_type` = 'location' LIMIT 1"
    );
    $stmtArticleNew->execute([$newName]);
    $newArticleId = $stmtArticleNew->fetchColumn();

    if ($newArticleId === false) {
        $updArticle = $dbh->prepare(
            "UPDATE `articles` SET `location_name` = ?
             WHERE `location_name` = ? AND `article_type` = 'location'"
        );
        $updArticle->execute([$newName, $oldName]);
    }

    // 4. Migrate editor_locations table (from oldName to newName)
    $stmtEditors = $dbh->prepare(
        "SELECT `editor_id`, `assigned_by`, `assigned_at`
         FROM `editor_locations` WHERE `location_name` = ?"
    );
    $stmtEditors->execute([$oldName]);
    $editorRows = $stmtEditors->fetchAll(PDO::FETCH_ASSOC);

    if ($editorRows) {
        $insertEditor = $dbh->prepare(
            "INSERT IGNORE INTO `editor_locations`
             (`editor_id`, `location_name`, `assigned_by`, `assigned_at`)
             VALUES (?, ?, ?, ?)"
        );
        foreach ($editorRows as $row) {
            $insertEditor->execute([
                (int)$row['editor_id'],
                $newName,
                (int)$row['assigned_by'],
                $row['assigned_at'],
            ]);
        }
        $deleteOldEditor = $dbh->prepare("DELETE FROM `editor_locations` WHERE `location_name` = ?");
        $deleteOldEditor->execute([$oldName]);
    }
}

