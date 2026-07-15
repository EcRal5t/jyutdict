<?php
/**
 * Add or remove indexes used by the public dictionary APIs.
 *
 * Usage:
 *   php api/scripts/lookup_indexes.php              # dry run
 *   php api/scripts/lookup_indexes.php --apply
 *   php api/scripts/lookup_indexes.php --rollback
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/LocationLookup.php';

$mode = 'dry-run';
if (in_array('--apply', $argv, true)) {
    $mode = 'apply';
} elseif (in_array('--rollback', $argv, true)) {
    $mode = 'rollback';
}

function migrationQuoteIdentifier($identifier) {
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function migrationIndexSignature(PDO $dbh, $tableName) {
    $stmt = $dbh->prepare(
        "SELECT `INDEX_NAME`, `SEQ_IN_INDEX`, `COLUMN_NAME`, `SUB_PART`
         FROM `information_schema`.`STATISTICS`
         WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ?
         ORDER BY `INDEX_NAME`, `SEQ_IN_INDEX`"
    );
    $stmt->execute([$tableName]);
    $indexes = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $indexes[$row['INDEX_NAME']][] = [
            'column' => $row['COLUMN_NAME'],
            'prefix' => $row['SUB_PART'] === null ? null : (int)$row['SUB_PART'],
        ];
    }
    return $indexes;
}

function migrationSameColumns(array $left, array $right) {
    return $left === $right;
}

function migrationHasEquivalentIndex(array $existing, array $columns) {
    foreach ($existing as $existingColumns) {
        if (migrationSameColumns($existingColumns, $columns)) {
            return true;
        }
    }
    return false;
}

function migrationColumnSql(array $column) {
    $sql = migrationQuoteIdentifier($column['column']);
    if ($column['prefix'] !== null) {
        $sql .= '(' . (int)$column['prefix'] . ')';
    }
    return $sql;
}

function migrationTableColumns(PDO $dbh, $tableName) {
    $stmt = $dbh->prepare(
        "SELECT `COLUMN_NAME` FROM `information_schema`.`COLUMNS`
         WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ?"
    );
    $stmt->execute([$tableName]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function migrationDefinition($name, array $columns) {
    return ['name' => $name, 'columns' => $columns];
}

function migrationColumn($name, $prefix = null) {
    return ['column' => $name, 'prefix' => $prefix];
}

$definitions = [];
$areas = jyutdictLoadAllAreas($dbh);
foreach ($areas as $area) {
    $definitions[$area['sheetname']] = [
        migrationDefinition('idx_chara_lookup', [migrationColumn('chara', 2)]),
        migrationDefinition('idx_pron_lookup', [
            migrationColumn('initial', 5),
            migrationColumn('nuclei', 5),
            migrationColumn('coda', 5),
        ]),
    ];
}

$definitions['y_kuangyon'][] = migrationDefinition(
    'idx_kuangyon_chara',
    [migrationColumn('chara', 2)]
);
$definitions['character_simtrad_list'][] = migrationDefinition(
    'idx_simtrad_chara',
    [migrationColumn('chara', 2)]
);
$definitions['j_faamjyut'][] = migrationDefinition(
    'idx_sheet_key_id',
    [migrationColumn('鍵'), migrationColumn('id')]
);

$definitions['char_comments'][] = migrationDefinition(
    'idx_char_created',
    [migrationColumn('chara', 2), migrationColumn('created_at')]
);
$definitions['char_comments'][] = migrationDefinition(
    'idx_char_user_active_created',
    [migrationColumn('user_id'), migrationColumn('is_deleted'), migrationColumn('created_at')]
);
$definitions['sheet_comments'][] = migrationDefinition(
    'idx_sheet_created',
    [migrationColumn('sheet_key'), migrationColumn('created_at')]
);
$definitions['sheet_comments'][] = migrationDefinition(
    'idx_sheet_user_active_created',
    [migrationColumn('user_id'), migrationColumn('is_deleted'), migrationColumn('created_at')]
);
$definitions['char_comment_versions'][] = migrationDefinition(
    'idx_char_version_created',
    [migrationColumn('comment_id'), migrationColumn('created_at')]
);
$definitions['sheet_comment_versions'][] = migrationDefinition(
    'idx_sheet_version_created',
    [migrationColumn('comment_id'), migrationColumn('created_at')]
);
$definitions['article_versions'][] = migrationDefinition(
    'idx_article_version_created',
    [migrationColumn('article_id'), migrationColumn('created_at')]
);
$definitions['articles'][] = migrationDefinition(
    'idx_article_updated',
    [migrationColumn('updated_at')]
);
$definitions['users'][] = migrationDefinition(
    'idx_user_role_created',
    [migrationColumn('role'), migrationColumn('created_at')]
);

echo 'Mode: ' . $mode . PHP_EOL;
echo 'Database: ' . $dbh->query('SELECT DATABASE()')->fetchColumn() . PHP_EOL;
echo 'Server: ' . $dbh->getAttribute(PDO::ATTR_SERVER_VERSION) . PHP_EOL;
echo 'Location tables: ' . count($areas) . PHP_EOL;

$changes = 0;
$changedTables = [];

foreach ($definitions as $tableName => $tableDefinitions) {
    jyutdictAssertTableName($tableName);
    $columns = migrationTableColumns($dbh, $tableName);
    if (!$columns) {
        throw new RuntimeException("Missing table: {$tableName}");
    }
    $existing = migrationIndexSignature($dbh, $tableName);

    if ($mode === 'rollback') {
        $drops = [];
        foreach ($tableDefinitions as $definition) {
            if (isset($existing[$definition['name']])) {
                $drops[] = 'DROP INDEX ' . migrationQuoteIdentifier($definition['name']);
            }
        }
        if (!$drops) {
            continue;
        }
        $sql = 'ALTER TABLE ' . migrationQuoteIdentifier($tableName) . ' ' . implode(', ', $drops);
    } else {
        $adds = [];
        foreach ($tableDefinitions as $definition) {
            foreach ($definition['columns'] as $column) {
                if (!in_array($column['column'], $columns, true)) {
                    throw new RuntimeException("Missing column {$tableName}.{$column['column']}");
                }
            }
            if (migrationHasEquivalentIndex($existing, $definition['columns'])) {
                continue;
            }
            if (isset($existing[$definition['name']])) {
                throw new RuntimeException("Index name collision: {$tableName}.{$definition['name']}");
            }
            $columnSql = implode(', ', array_map('migrationColumnSql', $definition['columns']));
            $adds[] = 'ADD INDEX ' . migrationQuoteIdentifier($definition['name']) . " ({$columnSql})";
        }
        if (!$adds) {
            continue;
        }
        $sql = 'ALTER TABLE ' . migrationQuoteIdentifier($tableName) . ' ' . implode(', ', $adds);
    }

    echo $sql . ';' . PHP_EOL;
    $changes++;
    if ($mode === 'apply' || $mode === 'rollback') {
        $dbh->exec($sql);
        $changedTables[] = $tableName;
    }
}

if ($mode === 'apply') {
    // ANALYZE TABLE returns a result set in MySQL/MariaDB; consume it before
    // issuing the next statement. Analyze all managed tables so a retry after
    // an interrupted run still completes the statistics refresh.
    foreach (array_keys($definitions) as $tableName) {
        $analyze = $dbh->query('ANALYZE TABLE ' . migrationQuoteIdentifier($tableName));
        $analyze->fetchAll(PDO::FETCH_ASSOC);
        $analyze->closeCursor();
    }
}

echo 'Statements: ' . $changes . PHP_EOL;
echo $mode === 'dry-run' ? "No changes applied.\n" : "Migration completed.\n";
