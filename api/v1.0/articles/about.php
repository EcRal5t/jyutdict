<?php
/**
 * 説明文章 API
 *
 * GET /api/v1.0/articles/about
 *     → 返回所有説明文章列表（about_pages.json）
 *
 * GET /api/v1.0/articles/about?id={id}
 *     → 返回指定文章的 Markdown 內容
 *
 * 圖片路徑：Markdown 中的 img/ 路徑需拼接 images_base 成完整 URL
 */

header('Content-Type: application/json; charset=utf-8');

// 配置
define('ABOUT_PAGES_JSON', __DIR__ . '/../../../frontend/src/data/about_pages.json');
define('MARKDOWN_DIR', __DIR__ . '/../../../frontend/src/data/markdown/');

function outputJson($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 只允許 GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    outputJson(['error' => 'Method not allowed'], 405);
}

// 構建圖片基礎 URL
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$imagesBase = $protocol . '://' . $host . '/img/';

// 讀取 about_pages.json
if (!file_exists(ABOUT_PAGES_JSON)) {
    outputJson(['error' => 'About pages config not found'], 500);
}

$pagesJson = file_get_contents(ABOUT_PAGES_JSON);
$pages = json_decode($pagesJson, true);

if ($pages === null) {
    outputJson(['error' => 'Invalid about_pages.json'], 500);
}

// 無 id 參數：返回文章列表
$articleId = $_GET['id'] ?? null;

if (!$articleId) {
    outputJson([
        'pages' => $pages,
        'images_base' => $imagesBase
    ]);
}

// 有 id 參數：返回指定文章
$page = null;
foreach ($pages as $p) {
    if ($p['id'] === $articleId) {
        $page = $p;
        break;
    }
}

if (!$page) {
    outputJson(['error' => 'Article not found'], 404);
}

// 讀取 Markdown 文件
$markdownFile = MARKDOWN_DIR . $page['file'];

if (!file_exists($markdownFile)) {
    outputJson(['error' => 'Markdown file not found'], 404);
}

$markdownContent = file_get_contents($markdownFile);

// 處理圖片路徑：將 ./img/ 或 img/ 替換為說明
// Android 端可根據 images_base 自行拼接完整 URL
$markdownContent = preg_replace('/\]\((?:\.\/)?img\//', '](_IMG_BASE_/', $markdownContent);
$markdownContent = preg_replace('/src=["\'](?:\.\/)?img\//', 'src="_IMG_BASE_/', $markdownContent);

outputJson([
    'id' => $page['id'],
    'title' => $page['title'],
    'file' => $page['file'],
    'content' => $markdownContent,
    'images_base' => $imagesBase
]);
