<?php
/**
 * 评论数量批量查询 API
 *
 * GET /api/v1.0/comments/counts?type=char&targets=字1,字2,字3
 * GET /api/v1.0/comments/counts?type=sheet&targets=key1,key2
 *
 * 返回: { "counts": { "字1": 5, "字2": 0, ... } }
 */

header('Content-Type: application/json; charset=utf-8');

include_once(__DIR__ . '/../../core/db.php');
include_once(__DIR__ . '/../../core/helpers.php');



$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    outputJson(['error' => 'Method not allowed'], 405);
}

$type = $_GET['type'] ?? '';
$targetsStr = $_GET['targets'] ?? '';

if (!in_array($type, ['char', 'sheet'])) {
    outputJson(['error' => 'Invalid type. Must be "char" or "sheet"'], 400);
}

if (!$targetsStr) {
    outputJson(['error' => 'Missing targets parameter'], 400);
}

// 解析 targets（逗号分隔）
$targets = array_map('trim', explode(',', $targetsStr));
$targets = array_filter($targets, fn($t) => $t !== '');

if (empty($targets)) {
    outputJson(['counts' => []]);
}

// 限制一次最多查询 200 个
if (count($targets) > 200) {
    outputJson(['error' => 'Too many targets (max 200)'], 400);
}

try {
    $table = $type === 'char' ? 'char_comments' : 'sheet_comments';
    $column = $type === 'char' ? 'chara' : 'sheet_key';

    // 构建查询
    $placeholders = implode(',', array_fill(0, count($targets), '?'));
    $sql = "
        SELECT `{$column}` AS target, COUNT(*) AS count
        FROM `{$table}`
        WHERE `{$column}` IN ({$placeholders})
          AND `is_deleted` = 0
        GROUP BY `{$column}`
    ";

    $stmt = $dbh->prepare($sql);
    $stmt->execute($targets);
    $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // 填充未返回的（count = 0）
    $counts = [];
    foreach ($targets as $t) {
        $counts[$t] = isset($results[$t]) ? (int)$results[$t] : 0;
    }

    outputJson(['counts' => $counts]);

} catch (PDOException $e) {
    outputJson(['error' => 'Database error'], 500);
}
