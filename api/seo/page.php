<?php
require_once __DIR__ . '/../core/Seo.php';

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit;
}
header('Content-Type: text/html; charset=utf-8');
try {
    $manifest = seoManifest();
    $rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $path = rawurldecode($rawPath);
    if (!mb_check_encoding($path, 'UTF-8') || strlen($path) > 1024) {
        http_response_code(400);
        exit;
    }
    // Normalize the public origin and static-file aliases, preserving meaningful query values.
    if (in_array($path, ['/index.html', '/index.php'], true) || $path !== '/' && substr($path, -1) === '/' || ($_SERVER['HTTP_HOST'] ?? '') === 'www.jyutdict.org') {
        $target = in_array($path, ['/index.html', '/index.php'], true) ? '/' : (rtrim($rawPath, '/') ?: '/');
        $query = $_SERVER['QUERY_STRING'] ?? '';
        header('Location: ' . $manifest['origin'] . $target . ($query ? '?' . $query : ''), true, 301);
        exit;
    }
    $page = seoPageModel($path, $_GET, $manifest);
    http_response_code($page['status']);
    // Revalidate the shell on navigation so a deployment cannot leave cached HTML
    // pointing at hashed assets removed by the next dist-branch update.
    header('Cache-Control: ' . ($page['noindex'] ? 'no-store' : 'public, max-age=0, must-revalidate'));
    if ($page['noindex']) header('X-Robots-Tag: noindex, follow');
    $shell = file_get_contents(seoRoot() . '/index.html');
    $shell = preg_replace('~<title>.*?</title>|<meta\b[^>]*\bname=["\x27](?:description|robots)["\x27][^>]*>~si', '', $shell);
    $shell = str_replace('</head>', seoRenderHead($page, $manifest) . '</head>', $shell);
    $links = [];
    foreach ($manifest['pages'] as $target => $entry) {
        if ($entry['canonical'] !== $target) continue;
        $links[] = '<a href="' . seoEscape($target) . '">' . seoEscape(explode('｜', $entry['title'])[0]) . '</a>';
    }
    $content = '<main style="max-width:72rem;margin:4rem auto;padding:1rem;font-family:serif;line-height:1.8">' . $page['html'] . '<p>引用資料時，請保留地點、字表作者及來源資訊；資料說明見<a href="/about/intro">本站與資料來源</a>。</p><footer>' . implode(' · ', $links) . '</footer></main>';
    $shell = str_replace('<div id="app"></div>', '<div id="app">' . $content . '</div>', $shell);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') echo $shell;
} catch (Throwable $e) {
    error_log('SEO renderer failed: ' . get_class($e));
    http_response_code(503);
    header('Cache-Control: no-store');
    header('Retry-After: 60');
    // A temporary database/cache failure must not turn every valid URL into a permanent 404.
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') readfile(seoRoot() . '/index.html');
}
