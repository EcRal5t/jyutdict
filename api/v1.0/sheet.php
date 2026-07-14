<?php
/**
 * API v1.0 sheet.php
 * 泛粵字表查詢接口
 *
 * 改進點：
 * - 元信息與搜索分離
 * - 參數語義化（q, random, key, meaning 等）
 * - 返回格式統一
 * - 支持 key 精確查詢
 */

include_once(__DIR__ . '/../core/db.php');
include_once(__DIR__ . '/../core/helpers.php');

header('Content-type: application/json');



// Helper: 獲取表頭信息
function getHeaderInfo($dbh) {
    $sql = "SELECT `id`, `col`, `kind`, `fullname`, `fullname_note`, `color` FROM `i_faamjyut` ORDER BY `id`";
    $stmt = $dbh->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $columns = [];
    foreach ($result as $row) {
        $col = [
            'index' => (int)$row['id'],
            'col' => $row['col'],
            'fullname' => $row['fullname'],
            'kind' => (int)$row['kind']
        ];
        if (!empty($row['fullname_note'])) {
            $col['sub'] = $row['fullname_note'];
        }
        if (!empty($row['color']) && $row['color'] !== '#') {
            $col['color'] = $row['color'];
        }
        $columns[] = $col;
    }
    return $columns;
}

// Helper: 獲取有效列名列表
function getValidColumns($dbh) {
    $sql = "SELECT `col` FROM `i_faamjyut`";
    $stmt = $dbh->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
}

// =====================================================
// 幫助信息
// =====================================================
if (isset($_REQUEST['help'])) {
    outputJson([
        "name" => "泛粵字表 API",
        "version" => "v1.0",
        "description" => "查詢泛粵字表中的字頭及其各地讀音",
        "endpoints" => [
            "GET /api/v1.0/sheet" => [
                "description" => "獲取元信息（表頭列定義）",
                "response" => ["columns" => "列信息數組"]
            ],
            "GET /api/v1.0/sheet?q={query}" => [
                "description" => "搜索字表",
                "params" => ["q", "col", "mode", "limit"]
            ],
            "GET /api/v1.0/sheet?random={count}" => [
                "description" => "隨機返回條目",
                "params" => ["random"]
            ],
            "GET /api/v1.0/sheet?key={key}" => [
                "description" => "按鍵精確查詢（用於锚定特定條目）",
                "params" => ["key"]
            ]
        ],
        "params" => [
            "q" => [
                "type" => "string",
                "description" => "查詢內容。純數字時自動查詢 id 列",
                "example" => "mat1, 粵, 123"
            ],
            "col" => [
                "type" => "string",
                "default" => "自動判斷（字母數字→綜, 漢字→字頭）",
                "description" => "查詢列名，從元信息接口獲取可用值"
            ],
            "mode" => [
                "type" => "string",
                "default" => "auto",
                "options" => ["exact", "fuzzy", "regex", "trim", "meaning", "auto"],
                "description" => [
                    "exact" => "精確匹配",
                    "fuzzy" => "模糊匹配（前後可接其他字符）",
                    "regex" => "正則表達式",
                    "trim" => "忽略標記，匹配音節整體",
                    "meaning" => "查詢釋義",
                    "auto" => "自動選擇（字母數字用 trim，漢字用 fuzzy）"
                ]
            ],
            "random" => [
                "type" => "integer",
                "range" => "1-30",
                "description" => "隨機返回條數"
            ],
            "key" => [
                "type" => "string",
                "format" => ">{數字}",
                "description" => "按鍵精確查詢，用於锚定條目（id 會變，key 不變）"
            ],
            "limit" => [
                "type" => "integer",
                "default" => 50,
                "max" => 150,
                "description" => "返回條數上限"
            ]
        ],
        "examples" => [
            "獲取表頭" => "/api/v1.0/sheet",
            "搜索讀音" => "/api/v1.0/sheet?q=mat1",
            "搜索漢字" => "/api/v1.0/sheet?q=粵",
            "搜索釋義" => "/api/v1.0/sheet?q=什麼&mode=meaning",
            "隨機返回" => "/api/v1.0/sheet?random=10",
            "按 id 查詢" => "/api/v1.0/sheet?q=1",
            "按 key 查詢" => "/api/v1.0/sheet?key=>123456789012345"
        ]
    ]);
}

// =====================================================
// 按 key 查詢（優先級最高）
// =====================================================
if (isset($_REQUEST['key'])) {
    $key = $_REQUEST['key'];

    // 驗證 key 格式：>{數字}
    if (!preg_match('/^>\d+$/', $key)) {
        outputJson([
            "error" => "Invalid key format. Expected: >{number}"
        ]);
    }

    $sql = "SELECT * FROM `j_faamjyut` WHERE `鍵` = :key ORDER BY `id` ASC LIMIT 1";
    $stmt = $dbh->prepare($sql);
    $stmt->execute([':key' => $key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        // 移除 null 和空字符串，保持數據整潔
        $result = array_filter($row, function($v) { return $v !== null && $v !== ''; });
        outputPublicJson([$result]);
    } else {
        outputPublicJson([]);
    }
}

// =====================================================
// 隨機返回
// =====================================================
if (isset($_REQUEST['random'])) {
    $count = (int)$_REQUEST['random'];
    $count = max(1, min(30, $count)); // 限制 1-30

    $sql = "
        SELECT t1.*
        FROM `j_faamjyut` AS t1
        JOIN (
            SELECT id
            FROM `j_faamjyut`
            ORDER BY RAND()
            LIMIT $count
        ) AS t2 ON t1.id = t2.id
    ";
    $stmt = $dbh->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 移除 null 值
    $results = array_map(function($row) {
        return array_filter($row, function($v) { return $v !== null && $v !== ''; });
    }, $results);

    header('Cache-Control: no-store');
    outputJson($results);
}

// =====================================================
// 元信息（無參數時返回表頭）
// =====================================================
if (!isset($_REQUEST['q']) && !isset($_REQUEST['query'])) {
    $columns = getHeaderInfo($dbh);
    outputPublicJson(["columns" => $columns]);
}

// =====================================================
// 搜索
// =====================================================
$queryString = isset($_REQUEST['q']) ? $_REQUEST['q'] : (isset($_REQUEST['query']) ? $_REQUEST['query'] : null);

// 查詢列
$col = isset($_REQUEST['col']) ? $_REQUEST['col'] : null;

// 查詢模式
$mode = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : 'auto';

// 如果是釋義查詢
if ($mode === 'meaning' || isset($_REQUEST['meaning'])) {
    $col = '釋義';
    $mode = 'fuzzy';
}

// 如果是純數字，直接查詢 ID 列
if (ctype_digit($queryString) && $col === null) {
    $sql = "SELECT * FROM `j_faamjyut` WHERE `id` = :id LIMIT 1";
    $stmt = $dbh->prepare($sql);
    $stmt->execute([':id' => $queryString]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = array_map(function($row) {
        return array_filter($row, function($v) { return $v !== null && $v !== ''; });
    }, $results);

    outputPublicJson($results);
}

// 自動判斷查詢列和模式
if ($col === null) {
    $isAlphaNum = preg_match("/[a-zA-Z0-9]/", $queryString);
    $col = $isAlphaNum ? '綜' : '字頭';

    if ($mode === 'auto') {
        $mode = $isAlphaNum ? 'trim' : 'fuzzy';
    }
} else {
    // 驗證列名
    $validColumns = getValidColumns($dbh);
    if (!in_array($col, $validColumns)) {
        outputJson([
            "error" => "Invalid column: $col",
            "valid_columns" => $validColumns
        ]);
    }
}

// 構建查詢
$limit = isset($_REQUEST['limit']) ? min(150, (int)$_REQUEST['limit']) : 50;

switch ($mode) {
    case 'exact':
        $sql = "SELECT * FROM `j_faamjyut` WHERE `$col` = :str LIMIT $limit";
        break;

    case 'fuzzy':
        $queryString = "%$queryString%";
        $sql = "SELECT * FROM `j_faamjyut` WHERE `$col` LIKE :str LIMIT $limit";
        break;

    case 'regex':
        $sql = "SELECT * FROM `j_faamjyut` WHERE `$col` REGEXP :str LIMIT $limit";
        break;

    case 'trim':
        // 忽略標記，匹配音節整體
        $queryString = str_ireplace("*", "\*", $queryString);
        $queryString = str_ireplace("?", "\？", $queryString);
        if (!is_numeric(substr($queryString, -1))) {
            // 匹配非字母字符或字符串結尾（支持缺失聲調的音節如 "bit"）
            $queryString = $queryString . "([^a-z]|$)";
        }
        $queryString = "(^$queryString)|([!?/; *]$queryString)";
        $sql = "SELECT * FROM `j_faamjyut` WHERE `$col` REGEXP :str LIMIT $limit";
        break;

    default: // auto
        $isAlphaNum = preg_match("/[a-zA-Z0-9]/", $queryString);
        if ($isAlphaNum) {
            // 默認使用 trim 模式
            $queryString = str_ireplace("*", "\*", $queryString);
            $queryString = str_ireplace("?", "\？", $queryString);
            if (!is_numeric(substr($queryString, -1))) {
                $queryString = $queryString . "([^a-z]|$)";
            }
            $queryString = "(^$queryString)|([!?/; *]$queryString)";
            $sql = "SELECT * FROM `j_faamjyut` WHERE `$col` REGEXP :str LIMIT $limit";
        } else {
            // 漢字使用模糊查詢
            $queryString = "%$queryString%";
            $sql = "SELECT * FROM `j_faamjyut` WHERE `$col` LIKE :str LIMIT $limit";
        }
}

$stmt = $dbh->prepare($sql);
$stmt->execute([':str' => $queryString]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 移除 null 值
$results = array_map(function($row) {
    return array_filter($row, function($v) { return $v !== null && $v !== ''; });
}, $results);

// 檢查結果是否過多
if (count($results) >= 150) {
    outputJson([
        "error" => "Too many results, please refine your search.",
        "count" => count($results)
    ]);
}

outputPublicJson($results);
