<?php
/** Shared, read-only public entry identity and directory rules. Keys remain strings. */
function jyutdictSheetEntryPath($key) {
    return preg_match('/\A>[0-9]{1,15}\z/', (string)$key) ? '/sheet/entry/' . substr($key, 1) : null;
}

function jyutdictSheetEntryIndexable(array $row) {
    return jyutdictSheetEntryPath($row['鍵'] ?? '') !== null
        && trim($row['釋義'] ?? '') !== '' && trim($row['釋'] ?? '') !== '';
}

function jyutdictSheetEntryLabel(array $row) {
    $glyph = trim($row['繁'] ?? '');
    return preg_match('/[^?？!！\s]/u', $glyph) ? $glyph : '未定字頭';
}

function jyutdictSheetDirectoryPath($page) {
    return '/sheet/entries' . ($page > 1 ? '/' . $page : '');
}

function jyutdictSheetIndexWhere() {
    return "`鍵` REGEXP '^>[0-9]{1,15}$' AND TRIM(COALESCE(`釋義`, '')) <> '' AND TRIM(COALESCE(`釋`, '')) <> ''";
}

function jyutdictSheetDirectory(PDO $dbh, $page) {
    $total = (int)$dbh->query('SELECT COUNT(*) FROM `j_faamjyut` WHERE ' . jyutdictSheetIndexWhere())->fetchColumn();
    $pages = max(1, (int)ceil($total / 100));
    $rows = [];
    if ($page >= 1 && $page <= $pages) {
        $offset = ($page - 1) * 100;
        $rows = $dbh->query('SELECT `鍵`, `繁`, `綜`, `釋`, `釋義` FROM `j_faamjyut` WHERE '
            . jyutdictSheetIndexWhere() . " ORDER BY `鍵` LIMIT 100 OFFSET {$offset}")->fetchAll(PDO::FETCH_ASSOC);
    }
    return ['page' => $page, 'pages' => $pages, 'total' => $total, 'entries' => $rows];
}
