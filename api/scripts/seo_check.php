<?php
/** CLI-only contract checks; no database writes or private-content reads. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../core/Seo.php';

function checkSeo($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}

$manifest = seoManifest();
$home = seoPageModel('/', [], $manifest);
checkSeo($home['status'] === 200 && !$home['noindex'], 'Home must be indexable');
checkSeo(strpos($home['html'], '<h1>') !== false, 'Home needs a visible heading');
foreach (['/admin', '/user', '/missing-page'] as $path) {
    $page = seoPageModel($path, [], $manifest);
    checkSeo($page['noindex'], 'Private and missing pages must not be indexed');
}
checkSeo(seoPageModel('/missing-page', [], $manifest)['status'] === 404, 'Missing route must be a real 404');
checkSeo(seoPageModel('/detail', ['chara' => ['粵']], $manifest)['noindex'], 'Array queries must not crash or be indexed');
checkSeo(seoPageModel('/detail', ['chara' => '粵', 'areas' => '1,2'], $manifest)['noindex'], 'Filtered entries must not be indexed');
checkSeo(seoPageModel('/detail', ['chara' => '<script>alert(1)</script>'], $manifest)['noindex'], 'Arbitrary searches must not be indexed');
$page = $home;
$page['title'] = '</title><script>alert(1)</script>';
$head = seoRenderHead($page, $manifest);
checkSeo(strpos($head, '</title><script>') === false, 'Head metadata must be escaped');
checkSeo(strpos($head, 'https://jyutdict.org/') !== false, 'Canonical origin must be fixed');
checkSeo(substr_count($head, 'rel="canonical"') === 1, 'Exactly one canonical is required');
echo "SEO contract checks passed\n";
