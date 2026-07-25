<?php
/** Admin management for abstract location-article identities. */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/LocationArticleIdentity.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/role.php';
require_once __DIR__ . '/../../middleware/csrf.php';

requireRole('admin');

if (!jyutdictLocationRedirectTableExists($dbh)) {
    outputJson([
        'error' => 'Location article identity schema is not installed',
        'code' => 'LOCATION_IDENTITY_SCHEMA_NOT_INSTALLED',
    ], 503);
}

function locationAliasInput() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        throw new RuntimeException('Invalid JSON body');
    }
    return $input;
}

function locationAliasNormalizeAliases($aliases, $canonical) {
    if (!is_array($aliases) || !$aliases) {
        throw new RuntimeException('At least one source alias is required');
    }
    $result = [];
    foreach ($aliases as $alias) {
        $alias = jyutdictValidateLocationIdentityName($alias, 'alias_name');
        if ($alias === $canonical) {
            throw new RuntimeException('An alias may not equal the abstract location name');
        }
        $result[$alias] = true;
    }
    return array_keys($result);
}

function locationAliasAssertKnownAliases(PDO $dbh, array $aliases) {
    $known = [];
    $areaRows = $dbh->query(
        "SELECT CONCAT(`second`, COALESCE(`third`, ''))
         FROM `i_area_list` WHERE `archived_at` IS NULL"
    )->fetchAll(PDO::FETCH_COLUMN);
    $faamjyutRows = $dbh->query(
        "SELECT CONCAT(`fullname`, COALESCE(`fullname_note`, ''))
         FROM `i_faamjyut` WHERE `kind` = 1"
    )->fetchAll(PDO::FETCH_COLUMN);
    $articleRows = $dbh->query(
        "SELECT `location_name` FROM `articles` WHERE `article_type` = 'location'"
    )->fetchAll(PDO::FETCH_COLUMN);
    $redirectRows = $dbh->query(
        "SELECT `alias_name` FROM `location_name_redirects`"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach (array_merge($areaRows, $faamjyutRows, $articleRows, $redirectRows) as $name) {
        $known[(string)$name] = true;
    }
    foreach ($aliases as $alias) {
        if (!isset($known[$alias])) {
            throw new RuntimeException("Alias {$alias} is not a known source location");
        }
    }
}

function locationAliasAssertFlat(PDO $dbh, $canonical, array $aliases, $editingCanonical = null) {
    $placeholders = implode(',', array_fill(0, count($aliases), '?'));
    $stmt = $dbh->prepare(
        "SELECT `alias_name`, `canonical_name` FROM `location_name_redirects`
         WHERE `alias_name` IN ({$placeholders})"
    );
    $stmt->execute($aliases);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($editingCanonical === null || $row['canonical_name'] !== $editingCanonical) {
            throw new RuntimeException("Alias {$row['alias_name']} already belongs to {$row['canonical_name']}");
        }
    }

    $params = array_merge([$canonical], $aliases);
    $canonicalPlaceholders = implode(',', array_fill(0, count($params), '?'));
    $stmt = $dbh->prepare(
        "SELECT DISTINCT `canonical_name` FROM `location_name_redirects`
         WHERE `canonical_name` IN ({$canonicalPlaceholders})"
    );
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $usedCanonical) {
        if ($editingCanonical === null || $usedCanonical !== $editingCanonical) {
            if (in_array($usedCanonical, $aliases, true)) {
                throw new RuntimeException("Alias {$usedCanonical} is already an abstract location name");
            }
        }
    }

    $stmt = $dbh->prepare(
        "SELECT `canonical_name` FROM `location_name_redirects`
         WHERE `alias_name` = ? LIMIT 1"
    );
    $stmt->execute([$canonical]);
    $target = $stmt->fetchColumn();
    if ($target !== false && ($editingCanonical === null || $target !== $editingCanonical)) {
        throw new RuntimeException('Abstract location name may not itself be an alias');
    }
}

function locationAliasArticleRows(PDO $dbh, array $names) {
    $names = array_values(array_unique($names));
    $placeholders = implode(',', array_fill(0, count($names), '?'));
    $stmt = $dbh->prepare(
        "SELECT `id`, `location_name` FROM `articles`
         WHERE `article_type` = 'location' AND `location_name` IN ({$placeholders})
         ORDER BY `id`"
    );
    $stmt->execute($names);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function locationAliasMoveAssignments(PDO $dbh, array $fromNames, $canonical) {
    $fromNames = array_values(array_unique(array_filter($fromNames, function ($name) use ($canonical) {
        return $name !== '' && $name !== $canonical;
    })));
    if (!$fromNames) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($fromNames), '?'));
    $stmt = $dbh->prepare(
        "SELECT `editor_id`, `assigned_by`, `assigned_at`
         FROM `editor_locations` WHERE `location_name` IN ({$placeholders})"
    );
    $stmt->execute($fromNames);
    $insert = $dbh->prepare(
        "INSERT IGNORE INTO `editor_locations`
         (`editor_id`, `location_name`, `assigned_by`, `assigned_at`)
         VALUES (?, ?, ?, ?)"
    );
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $insert->execute([
            (int)$row['editor_id'],
            $canonical,
            (int)$row['assigned_by'],
            $row['assigned_at'],
        ]);
    }
    $delete = $dbh->prepare(
        "DELETE FROM `editor_locations` WHERE `location_name` IN ({$placeholders})"
    );
    $delete->execute($fromNames);
}

function locationAliasSaveGroup(
    PDO $dbh,
    $userId,
    $canonical,
    array $aliases,
    $editingCanonical = null
) {
    $canonical = jyutdictValidateLocationIdentityName($canonical, 'canonical_name');
    $aliases = locationAliasNormalizeAliases($aliases, $canonical);
    locationAliasAssertKnownAliases($dbh, $aliases);
    locationAliasAssertFlat($dbh, $canonical, $aliases, $editingCanonical);

    $oldAliases = [];
    if ($editingCanonical !== null) {
        $stmt = $dbh->prepare(
            "SELECT `alias_name` FROM `location_name_redirects`
             WHERE `canonical_name` = ?"
        );
        $stmt->execute([$editingCanonical]);
        $oldAliases = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!$oldAliases) {
            throw new RuntimeException('Abstract location identity not found');
        }
    }

    $articleNames = array_values(array_unique(array_merge(
        [$canonical],
        $editingCanonical === null ? [] : [$editingCanonical],
        $aliases,
        $oldAliases
    )));
    $articles = locationAliasArticleRows($dbh, $articleNames);
    if (count($articles) > 1) {
        $labels = array_map(function ($row) {
            return "#{$row['id']} {$row['location_name']}";
        }, $articles);
        throw new RuntimeException('Multiple articles conflict: ' . implode(', ', $labels));
    }

    if (count($articles) === 1 && $articles[0]['location_name'] !== $canonical) {
        $stmt = $dbh->prepare("UPDATE `articles` SET `location_name` = ? WHERE `id` = ?");
        $stmt->execute([$canonical, (int)$articles[0]['id']]);
    }

    locationAliasMoveAssignments(
        $dbh,
        array_values(array_unique(array_merge(
            $editingCanonical === null ? [] : [$editingCanonical],
            $oldAliases,
            $aliases
        ))),
        $canonical
    );

    if ($editingCanonical !== null) {
        $stmt = $dbh->prepare(
            "DELETE FROM `location_name_redirects` WHERE `canonical_name` = ?"
        );
        $stmt->execute([$editingCanonical]);
    }
    $insert = $dbh->prepare(
        "INSERT INTO `location_name_redirects`
         (`alias_name`, `canonical_name`, `created_by`, `updated_by`)
         VALUES (?, ?, ?, ?)"
    );
    foreach ($aliases as $alias) {
        $insert->execute([$alias, $canonical, $userId, $userId]);
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $identities = jyutdictLoadLocationArticleIdentities($dbh);
        $identityByName = [];
        $sourceNames = [];
        foreach ($identities as $identity) {
            $identityByName[$identity['name']] = $identity;
            foreach ($identity['sources'] as $source) {
                $sourceNames[$source['display_name']][] = $source;
            }
        }

        $counts = [];
        $countRows = $dbh->query(
            "SELECT `location_name`, COUNT(*) AS `editor_count`
             FROM `editor_locations` GROUP BY `location_name`"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($countRows as $row) {
            $canonical = jyutdictResolveLocationName($dbh, $row['location_name']);
            $counts[$canonical] = ($counts[$canonical] ?? 0) + (int)$row['editor_count'];
        }

        $groups = [];
        foreach (jyutdictLocationAliasesByCanonical($dbh) as $canonical => $aliases) {
            $identity = $identityByName[$canonical] ?? [
                'name' => $canonical,
                'aliases' => $aliases,
                'sources' => [],
                'has_article' => false,
            ];
            $identity['editor_count'] = $counts[$canonical] ?? 0;
            $groups[] = $identity;
        }
        usort($groups, function ($left, $right) {
            return strcmp($left['name'], $right['name']);
        });

        $candidates = [];
        foreach ($sourceNames as $name => $sources) {
            $candidates[] = ['name' => $name, 'sources' => $sources];
        }
        usort($candidates, function ($left, $right) {
            return strcmp($left['name'], $right['name']);
        });
        outputJson(['groups' => $groups, 'candidates' => $candidates]);
    }

    validateCsrf();
    $input = locationAliasInput();

    if ($method === 'POST') {
        $dbh->beginTransaction();
        locationAliasSaveGroup(
            $dbh,
            $currentUserId,
            $input['canonical_name'] ?? '',
            $input['aliases'] ?? []
        );
        $dbh->commit();
        outputJson(['success' => true], 201);
    }

    if ($method === 'PATCH') {
        $currentCanonical = jyutdictValidateLocationIdentityName(
            $input['current_canonical_name'] ?? '',
            'current_canonical_name'
        );
        $dbh->beginTransaction();
        locationAliasSaveGroup(
            $dbh,
            $currentUserId,
            $input['canonical_name'] ?? '',
            $input['aliases'] ?? [],
            $currentCanonical
        );
        $dbh->commit();
        outputJson(['success' => true]);
    }

    if ($method === 'DELETE') {
        $canonical = jyutdictValidateLocationIdentityName(
            $input['canonical_name'] ?? '',
            'canonical_name'
        );
        if (($input['confirm_canonical_name'] ?? '') !== $canonical) {
            throw new RuntimeException('Type the abstract location name to confirm deletion');
        }
        $stmt = $dbh->prepare(
            "DELETE FROM `location_name_redirects` WHERE `canonical_name` = ?"
        );
        $stmt->execute([$canonical]);
        if ($stmt->rowCount() === 0) {
            outputJson(['error' => 'Abstract location identity not found'], 404);
        }
        outputJson(['success' => true]);
    }

    outputJson(['error' => 'Method not allowed'], 405);
} catch (Throwable $error) {
    if ($dbh->inTransaction()) {
        $dbh->rollBack();
    }
    $message = $error->getMessage();
    $status = strpos($message, 'not found') !== false ? 404
        : (strpos($message, 'conflict') !== false || strpos($message, 'already') !== false ? 409 : 400);
    outputJson(['error' => $message], $status);
}
