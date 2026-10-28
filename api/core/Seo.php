<?php
/** Public HTML and crawl-discovery helpers. Never reads sessions or private content. */
require_once __DIR__ . '/LocationLookup.php';
require_once __DIR__ . '/CommonEntries.php';
require_once __DIR__ . '/LocationArticleIdentity.php';
require_once __DIR__ . '/SheetEntries.php';

function seoEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function seoJson($value) {
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
}

function seoRoot() {
    return dirname(__DIR__, 2);
}

function seoManifest() {
    static $manifest;
    if ($manifest === null) {
        $manifest = json_decode(file_get_contents(seoRoot() . '/data/seo-pages.json'), true, 512, JSON_THROW_ON_ERROR);
    }
    return $manifest;
}

function seoDatabase() {
    static $dbh;
    if (!$dbh) {
        require_once __DIR__ . '/../config/db.php';
        $dbh = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    }
    return $dbh;
}

/** Bounded shared catalog cache outside the document root; a single refresher prevents stampedes. */
function seoCachedCatalog(callable $load) {
    $directory = sys_get_temp_dir() . '/jyutdict-seo-' . substr(hash('sha256', seoRoot()), 0, 16);
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('SEO cache directory unavailable');
    }
    $file = $directory . '/catalog-v2.json';
    $read = function () use ($file) {
        if (!is_file($file)) return null;
        return json_decode(file_get_contents($file), true);
    };
    $cached = $read();
    if ($cached && $cached['expires'] > time()) return $cached['data'];
    $lock = fopen($directory . '/catalog.lock', 'c');
    if (!$lock) throw new RuntimeException('SEO cache lock unavailable');
    try {
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            if ($cached) return $cached['data'];
            if (!flock($lock, LOCK_EX)) throw new RuntimeException('SEO catalog busy');
        }
        clearstatcache(true, $file);
        $cached = $read();
        if ($cached && $cached['expires'] > time()) return $cached['data'];
        $data = $load();
        $temporary = $file . '.new';
        if (file_put_contents($temporary, seoJson(['expires' => time() + 3600, 'data' => $data])) === false || !rename($temporary, $file)) {
            throw new RuntimeException('SEO catalog write failed');
        }
        return $data;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function seoPublicCatalog() {
    return seoCachedCatalog(function () {
        $dbh = seoDatabase();
        $areas = jyutdictLoadAreas($dbh);
        $characters = [];
        if ($areas) {
            if (jyutdictCommonReadMode() === 'common') {
                $sql = "SELECT DISTINCT e.`chara` FROM `common_entries` e
                        JOIN `i_area_list` a ON a.`id` = e.`area_id`
                        WHERE a.`is_visible` = 1 AND a.`current_release_id` IS NOT NULL
                          AND a.`archived_at` IS NULL";
            } else {
                $parts = [];
                foreach ($areas as $area) {
                    $table = jyutdictAssertTableName($area['sheetname']);
                    $parts[] = "SELECT `chara` FROM `{$table}`";
                }
                $sql = implode(' UNION ', $parts);
            }
            $stmt = $dbh->query($sql);
            while (($character = $stmt->fetchColumn()) !== false) {
                // Only single Han entries are crawl targets, not arbitrary search combinations.
                if (preg_match('/\A[\p{Han}〇]\z/u', $character)) $characters[] = $character;
            }
            sort($characters, SORT_STRING);
        }
        $articles = $dbh->query("SELECT `location_name`, `updated_at` FROM `articles`
                                 WHERE `article_type` = 'location' ORDER BY `location_name`")->fetchAll();
        $sheetKeys = $dbh->query('SELECT `鍵` FROM `j_faamjyut` WHERE ' . jyutdictSheetIndexWhere() . ' ORDER BY `鍵`')->fetchAll(PDO::FETCH_COLUMN);
        return ['areas' => $areas, 'characters' => array_values(array_unique($characters)), 'articles' => $articles, 'sheet_keys' => $sheetKeys];
    });
}

function seoPlainText($value) {
    $value = preg_replace('/\[([^\]]+)\]\([^\)]+\)/u', '$1', (string)$value);
    $value = preg_replace('/^[\s#>*]+/mu', '', $value);
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8')));
}

function seoPageModel($path, array $query, array $manifest) {
    $contentKeys = array_values(array_filter(array_keys($query), function ($key) {
        return strpos($key, 'utm_') !== 0 && !in_array($key, ['gclid', 'fbclid'], true);
    }));
    $page = $manifest['pages'][$path] ?? [
        'title' => '找不到頁面｜' . $manifest['name'], 'description' => '此頁面不存在，請使用檢字或地點目錄查詢。',
        'canonical' => $path, 'html' => '<h1>找不到頁面</h1><p>此頁面不存在。</p>', 'status' => 404,
    ];
    $page += ['status' => 200, 'noindex' => false];
    if (in_array($path, ['/admin', '/user'], true)) {
        $page = ['title' => '帳戶與管理｜' . $manifest['name'], 'description' => '泛粵大典帳戶頁面。', 'canonical' => $path,
                 'html' => '<h1>帳戶與管理</h1><p>此功能需要登入。</p>', 'status' => 200, 'noindex' => true];
    }
    if (preg_match('~\A/sheet/entry/([0-9]{1,15})\z~', $path, $match)) {
        $page = seoSheetEntryPage(seoDatabase(), '>' . $match[1], $manifest);
        if ($contentKeys) $page['noindex'] = true;
    } elseif (preg_match('~\A/sheet/entries(?:/([1-9][0-9]{0,6}))?\z~', $path, $match)) {
        $page = seoSheetDirectoryPage(seoDatabase(), isset($match[1]) ? (int)$match[1] : 1, $manifest);
        if ($contentKeys) $page['noindex'] = true;
    } elseif ($path === '/detail' && isset($query['chara']) && (!is_string($query['chara']) || trim($query['chara']) !== '')) {
        $character = is_string($query['chara']) ? trim($query['chara']) : '';
        $page['title'] = $character . '的粵語讀音與各地方音｜' . $manifest['name'];
        $page['description'] = '查詢「' . $character . '」在各地的粵語讀音、擴展粵拼與 IPA，對照歷史韻書記錄。';
        $page['canonical'] = '/detail?chara=' . rawurlencode($character);
        $page['html'] = '<h1>' . seoEscape($character ?: '粵語檢字') . '</h1>';
        $page['noindex'] = !preg_match('/\A[\p{Han}〇]\z/u', $character) || count(array_diff($contentKeys, ['chara'])) > 0;
        if (!$page['noindex']) {
            $dbh = seoDatabase();
            // Use the same simplified/traditional mapping and read mode as the public detail API.
            require_once __DIR__ . '/Sim2Trad.php';
            $mapped = querySim2TradBatch([$character], $dbh);
            $areas = jyutdictLoadAreas($dbh);
            $rows = jyutdictLookupConfiguredLocationCharacters($dbh, $areas, $mapped);
            $body = '';
            $count = 0;
            foreach ($mapped as $variant) {
                foreach ($areas as $area) {
                    foreach ($rows[$area['id']][$variant] ?? [] as $row) {
                        if (++$count > 500) break 3;
                        $name = ($area['second'] ?? '') . ($area['third'] ?? '');
                        $pronunciation = $row['initial'] . $row['nuclei'] . $row['coda'] . $row['tone'];
                        $body .= '<tr><th scope="row">' . seoEscape($variant) . '</th><td>' . seoEscape($name ?: $area['first']) . '</td><td>' . seoEscape($pronunciation) . '</td><td>' . seoEscape($row['ipa']) . '</td><td>' . seoEscape($row['note']) . '</td><td>' . seoEscape($area['sheet_author'] ?? '') . '</td></tr>';
                    }
                }
            }
            if ($count) {
                $page['html'] = '<h1>' . seoEscape($character) . '的粵語讀音與各地方音</h1><table><thead><tr><th>字</th><th>地點</th><th>擴展粵拼</th><th>IPA</th><th>備註</th><th>字表作者</th></tr></thead><tbody>' . $body . '</tbody></table>';
                $page['term'] = $character;
            } else {
                // A character absent from location tables may still occur in a historical book.
                // Keep the query usable, but do not index an empty initial entry.
                $page['noindex'] = true;
                $page['html'] .= '<p>暫無各地方音記錄。</p>';
            }
        }
    } elseif ($path === '/locations' || $path === '/location-phonology') {
        $catalog = seoPublicCatalog();
        $links = [];
        if ($path === '/locations') {
            foreach ($catalog['articles'] as $article) {
                $links[] = '<li><a href="/location-article/' . rawurlencode($article['location_name']) . '">' . seoEscape($article['location_name']) . '</a></li>';
            }
        } else {
            foreach ($catalog['areas'] as $area) {
                if ($area['has_phonology']) $links[] = '<li><a href="/location-phonology/' . (int)$area['id'] . '">' . seoEscape(($area['second'] ?? '') . ($area['third'] ?? '') ?: $area['first']) . '</a></li>';
            }
        }
        $page['html'] .= '<ul>' . implode('', $links) . '</ul>';
    } elseif (preg_match('~\A/location-article/([^/]+)\z~u', $path, $match) && mb_strlen($match[1]) <= 100) {
        $dbh = seoDatabase();
        $name = jyutdictResolveLocationName($dbh, $match[1]);
        $stmt = $dbh->prepare("SELECT `content`, `updated_at` FROM `articles` WHERE `location_name` = ? AND `article_type` = 'location' LIMIT 1");
        $stmt->execute([$name]);
        if ($article = $stmt->fetch()) {
            $page = ['title' => $name . '粵語介紹｜' . $manifest['name'], 'description' => mb_substr(seoPlainText($article['content']), 0, 150),
                     'canonical' => '/location-article/' . rawurlencode($name), 'html' => '<h1>' . seoEscape($name) . '粵語介紹</h1><div style="white-space:pre-wrap">' . seoEscape($article['content']) . '</div>',
                     'status' => 200, 'noindex' => false];
        }
    } elseif (preg_match('~\A/location-phonology/([1-9][0-9]*)\z~', $path, $match)) {
        foreach (jyutdictLoadAreas(seoDatabase()) as $area) {
            if ((int)$match[1] !== $area['id'] || !$area['has_phonology']) continue;
            $name = ($area['second'] ?? '') . ($area['third'] ?? '') ?: $area['first'];
            $page = ['title' => $name . '粵語音系｜' . $manifest['name'], 'description' => '查閱' . $name . '目前發布字表所生成的音系四表及例字。',
                     'canonical' => '/location-phonology/' . (int)$area['id'], 'html' => '<h1>' . seoEscape($name) . '粵語音系</h1>', 'status' => 200, 'noindex' => false];
            break;
        }
    }
    if (in_array($path, ['/sheet', '/pronunciation', '/locations', '/phonology'], true) && $contentKeys) $page['noindex'] = true;
    if ($page['status'] === 404) $page['noindex'] = true;
    return $page;
}

function seoSheetEntryPage(PDO $dbh, $key, array $manifest) {
    $path = jyutdictSheetEntryPath($key);
    $stmt = $dbh->prepare('SELECT * FROM `j_faamjyut` WHERE `鍵` = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    if (!$row) return ['title' => '找不到詞條｜' . $manifest['name'], 'description' => '此泛粵字表記錄不存在。', 'canonical' => $path, 'html' => '<h1>找不到詞條</h1>', 'status' => 404, 'noindex' => true];
    $label = jyutdictSheetEntryLabel($row);
    $pron = trim($row['綜'] ?? '');
    $meaning = seoPlainText($row['釋'] ?: $row['釋義']);
    $description = mb_substr($label . ($pron ? '〔' . $pron . '〕' : '') . '：' . $meaning . '。泛粵字表的完整釋義、例詞與各地讀音記錄。', 0, 160);
    $html = '<nav><a href="/sheet">泛粵字表檢索</a></nav><h1>' . seoEscape($label) . '</h1>';
    if (preg_match('/\A[\p{Han}〇]\z/u', $row['繁'] ?? '')) $html .= '<p><a href="/detail?chara=' . rawurlencode($row['繁']) . '">查閱「' . seoEscape($row['繁']) . '」的方音與韻書對照</a></p>';
    if ($pron) $html .= '<p>綜合音：' . seoEscape($pron) . '</p>';
    $html .= '<h2>完整釋義與例詞</h2><p style="white-space:pre-wrap">' . seoEscape($row['釋義'] ?: '暫無釋義') . '</p>';
    if ($row['釋']) $html .= '<h2>反查關鍵詞</h2><p>' . seoEscape($row['釋']) . '</p>';
    foreach (['IDS' => '字形描述', '俗/常' => '常用或俗字', '字頭' => '字頭歸併', '錔' => '錔字字源', '註(少用)' => '註'] as $column => $name) {
        if (!empty($row[$column])) $html .= '<p>' . $name . '：' . seoEscape($row[$column]) . '</p>';
    }
    $body = '';
    foreach ($dbh->query('SELECT `col`, `kind`, `fullname`, `fullname_note` FROM `i_faamjyut` WHERE `kind` IN (1, 2) ORDER BY `id`') as $column) {
        $value = $row[$column['col']] ?? '';
        if ($value === '' || $value === null) continue;
        $name = $column['fullname'] . ($column['fullname_note'] ? ' · ' . $column['fullname_note'] : '');
        $body .= '<tr><th scope="row">' . seoEscape($name ?: $column['col']) . '</th><td>' . ($column['kind'] == 1 ? '域內' : '域外對照') . '</td><td>' . seoEscape($value) . '</td></tr>';
    }
    if ($body) $html .= '<h2>各地讀音與對照</h2><table><thead><tr><th>地點或語言</th><th>分類</th><th>字表記錄</th></tr></thead><tbody>' . $body . '</tbody></table>';
    return ['title' => $label . ($pron ? '（' . $pron . '）' : '') . '的意思與各地讀音｜泛粵字表｜' . $manifest['name'], 'description' => $description, 'canonical' => $path, 'html' => $html, 'status' => 200, 'noindex' => !jyutdictSheetEntryIndexable($row), 'term' => $label, 'term_set' => '/sheet/entries'];
}

function seoSheetDirectoryPage(PDO $dbh, $number, array $manifest) {
    $directory = jyutdictSheetDirectory($dbh, $number);
    $path = jyutdictSheetDirectoryPath($number);
    if ($number > $directory['pages']) return ['title' => '找不到目錄頁｜' . $manifest['name'], 'description' => '此詞條目錄頁不存在。', 'canonical' => $path, 'html' => '<h1>找不到目錄頁</h1>', 'status' => 404, 'noindex' => true];
    $html = '<h1>泛粵詞條目錄 · 第 ' . $number . ' 頁</h1><ul>';
    foreach ($directory['entries'] as $row) {
        $html .= '<li><a href="' . jyutdictSheetEntryPath($row['鍵']) . '">' . seoEscape(jyutdictSheetEntryLabel($row) . '〔' . $row['綜'] . '〕') . '</a> — ' . seoEscape($row['釋']) . '</li>';
    }
    $html .= '</ul><nav aria-label="詞條目錄分頁">';
    if ($number > 1) $html .= '<a href="' . jyutdictSheetDirectoryPath($number - 1) . '">上一頁</a> · ';
    $html .= $number . ' / ' . $directory['pages'];
    if ($number < $directory['pages']) $html .= ' · <a href="' . jyutdictSheetDirectoryPath($number + 1) . '">下一頁</a>';
    return ['title' => '泛粵詞條目錄 · 第 ' . $number . ' 頁｜' . $manifest['name'], 'description' => '瀏覽泛粵特色字、釋義與方音的固定詞條頁，第 ' . $number . ' 頁。', 'canonical' => $path, 'html' => $html . '</nav>', 'status' => 200, 'noindex' => false];
}

function seoRenderHead(array $page, array $manifest) {
    $url = $manifest['origin'] . $page['canonical'];
    $head = '<title>' . seoEscape($page['title']) . '</title>' . "\n";
    foreach (['description' => $page['description'], 'robots' => $page['noindex'] ? 'noindex,follow' : 'index,follow,max-image-preview:large', 'twitter:card' => 'summary', 'twitter:title' => $page['title'], 'twitter:description' => $page['description']] as $name => $content) {
        $head .= '<meta name="' . $name . '" content="' . seoEscape($content) . '">' . "\n";
    }
    foreach (['og:title' => $page['title'], 'og:description' => $page['description'], 'og:url' => $url, 'og:type' => 'website', 'og:locale' => 'zh_HK', 'og:site_name' => $manifest['name']] as $name => $content) {
        $head .= '<meta property="' . $name . '" content="' . seoEscape($content) . '">' . "\n";
    }
    $head .= '<link rel="canonical" href="' . seoEscape($url) . '">' . "\n";
    $graph = [['@type' => 'WebSite', '@id' => $manifest['origin'] . '/#website', 'name' => $manifest['name'], 'alternateName' => 'Jyutdict', 'url' => $manifest['origin'] . '/', 'inLanguage' => 'zh-Hant', 'description' => $manifest['description']]];
    if (!empty($page['term'])) $graph[] = ['@type' => 'DefinedTerm', '@id' => $url, 'url' => $url, 'name' => $page['term'], 'description' => $page['description'], 'inDefinedTermSet' => ['@type' => 'DefinedTermSet', 'name' => isset($page['term_set']) ? '泛粵字表' : '泛粵大典各地方音', 'url' => $manifest['origin'] . ($page['term_set'] ?? '/detail')]];
    if (!$page['noindex']) $head .= '<script id="jyutdict-structured-data" type="application/ld+json" data-url="' . seoEscape($url) . '">' . seoJson(['@context' => 'https://schema.org', '@graph' => $graph]) . '</script>';
    return $head;
}
