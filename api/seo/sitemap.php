<?php
require_once __DIR__ . '/../core/Seo.php';
header('Content-Type: application/xml; charset=utf-8');
try {
    $manifest = seoManifest();
    $catalog = seoPublicCatalog();
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/sitemap.xml', PHP_URL_PATH);
    $origin = $manifest['origin'];
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $count = (int)ceil(count($catalog['characters']) / 10000);
    if ($path === '/sitemap.xml') {
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach (array_merge(['/sitemaps/pages.xml'], array_map(function ($n) { return '/sitemaps/characters-' . $n . '.xml'; }, $count ? range(1, $count) : [])) as $part) {
            $xml .= '<sitemap><loc>' . seoEscape($origin . $part) . '</loc></sitemap>';
        }
        $xml .= '</sitemapindex>';
    } else {
        $urls = [];
        if ($path === '/sitemaps/pages.xml') {
            foreach ($manifest['pages'] as $key => $page) {
                if ($key === $page['canonical']) $urls[] = $key;
            }
            foreach ($catalog['articles'] as $article) $urls[] = '/location-article/' . rawurlencode($article['location_name']);
            foreach ($catalog['areas'] as $area) {
                if ($area['has_phonology']) $urls[] = '/location-phonology/' . (int)$area['id'];
            }
        } elseif (preg_match('~\A/sitemaps/characters-([1-9][0-9]*)\.xml\z~', $path, $match) && (int)$match[1] <= $count) {
            foreach (array_slice($catalog['characters'], ((int)$match[1] - 1) * 10000, 10000) as $character) $urls[] = '/detail?chara=' . rawurlencode($character);
        } else {
            http_response_code(404);
            header('Cache-Control: no-store');
            exit;
        }
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach (array_unique($urls) as $url) $xml .= '<url><loc>' . seoEscape($origin . $url) . '</loc></url>';
        $xml .= '</urlset>';
    }
    header('Cache-Control: public, max-age=3600, stale-while-revalidate=300');
    $etag = '"' . hash('sha256', $xml) . '"';
    header('ETag: ' . $etag);
    if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);
    } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
        echo $xml;
    }
} catch (Throwable $e) {
    error_log('SEO sitemap failed: ' . get_class($e));
    http_response_code(503);
    header('Cache-Control: no-store');
    header('Retry-After: 60');
}
