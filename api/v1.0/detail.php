<?php
/**
 * API v1.0 detail.php
 * 字元詳情（查字、檢音）
 */

include_once(__DIR__ . '/../core/db.php');
include_once(__DIR__ . '/../core/helpers.php');
include_once(__DIR__ . '/../core/Sim2Trad.php');
include_once(__DIR__ . '/../core/Jyutping.php');
include_once(__DIR__ . '/../core/LocationLookup.php');
include_once(__DIR__ . '/../core/CommonEntries.php');

header('Content-Type: application/json; charset=utf-8');

// =====================================================
// 幫助信息
// =====================================================
if (isset($_REQUEST['help'])) {
    outputJson([
        "name" => "字元詳情 API",
        "version" => "v1.0",
        "description" => "查詢字元在各地讀音及韻書記錄",
        "endpoints" => [
            "GET /api/v1.0/detail?chara=" => [
                "description" => "獲取地點列表及地點元信息（chara 參數必須存在且為空）"
            ],
            "GET /api/v1.0/detail?chara={query}" => [
                "description" => "查詢字元詳情",
                "params" => ["chara", "areas（可選，多個 id 以逗號分隔）"]
            ],
            "GET /api/v1.0/detail?pron={jyutping}" => [
                "description" => "按粵拼查詢（檢音）",
                "params" => ["pron"]
            ],
            "GET /api/v1.0/detail?in={...}&nu={...}&co={...}&to={...}" => [
                "description" => "按聲母/韻核/韻尾/聲調分別查詢",
                "params" => ["in", "nu", "co", "to"]
            ]
        ],
        "params" => [
            "chara" => [
                "type" => "string",
                "description" => "查詢字元，可輸入多字（自動簡轉繁/異）",
                "example" => "粵, 粤, 我们"
            ],
            "pron" => [
                "type" => "string",
                "description" => "粵拼字符串",
                "example" => "jyut6"
            ],
            "in" => ["type" => "string", "description" => "聲母（initial）"],
            "nu" => ["type" => "string", "description" => "韻核（nucleus）"],
            "co" => ["type" => "string", "description" => "韻尾（coda）"],
            "to" => ["type" => "string", "description" => "聲調（tone）"],
            "locations" => [
                "type" => "string",
                "description" => "篩選地點，逗號分隔的 id 列表",
                "example" => "1,2,3"
            ],
            "areas" => [
                "type" => "string",
                "description" => "查字時只返回指定 i_area_list.id 的方音；多個 id 以逗號分隔，同時省略韻書資料",
                "example" => "10,11"
            ],
            "wanshyu" => [
                "type" => "string",
                "description" => "篩選韻書，可選 fanwan, jingwaa"
            ]
        ],
        "response_structure" => [
            "字" => "查詢的字元",
            "韻書" => "韻書記錄數組（廣韻、分韻等）",
            "各地" => [
                "id" => "地點 id（需用地點列表映射）",
                "粵拼" => "粵拼數組（可能有多音）",
                "IPA" => "國際音標數組",
                "注釋" => "注釋數組"
            ]
        ],
        "examples" => [
            "獲取地點列表" => "/api/v1.0/detail?chara=",
            "查詢單字" => "/api/v1.0/detail?chara=粵",
            "查詢指定地點" => "/api/v1.0/detail?chara=粵&areas=10,11",
            "查詢多字" => "/api/v1.0/detail?chara=我們",
            "按粵拼查詢" => "/api/v1.0/detail?pron=jyut6",
            "按音素查詢" => "/api/v1.0/detail?in=j&nu=yu&co=t"
        ]
    ]);
}

// 1. If 'chara' is set but empty, return area list (Header/Location list)
if (isset($_REQUEST['chara']) && $_REQUEST['chara'] === "") {
    try {
        $areas = jyutdictLoadAreas($dbh);
        $result = array_map(function ($area) {
            return [
                'id' => $area['id'],
                'longitude' => $area['longitude'],
                'latitude' => $area['latitude'],
                'first' => $area['first'],
                'second' => $area['second'],
                'third' => $area['third'],
                'detailed_name' => $area['detailed_name'],
                'sheet_author' => $area['sheet_author'],
                'sheet_statistic' => $area['sheet_statistic'],
                'sheet_info' => $area['sheet_info'],
                'has_phonology' => $area['has_phonology'],
                'color' => $area['color'],
            ];
        }, $areas);
        outputPublicJson($result);
    } catch (PDOException $e) {
        outputJson(["error" => "Database error: " . $e->getMessage()]);
    }
}

// No valid params → error
if (!isset($_REQUEST['chara']) && !isset($_REQUEST['in']) && !isset($_REQUEST['nu']) && !isset($_REQUEST['co']) && !isset($_REQUEST['to']) && !isset($_REQUEST['pron'])) {
    outputJson(["error" => "No parameters provided. Use ?help for usage."]);
}

// =====================================================
// 檢音邏輯 (Pronunciation Search)
// =====================================================
$queryInitial = null;
$queryNuclei = null;
$queryCoda = null;
$queryTone = null;

if (isset($_REQUEST['in']) || isset($_REQUEST['nu']) || isset($_REQUEST['co']) || isset($_REQUEST['to'])) {
    $queryInitial = isset($_REQUEST['in']) ? $_REQUEST['in'] : '%';
    $queryNuclei = isset($_REQUEST['nu']) ? $_REQUEST['nu'] : '%';
    $queryCoda = isset($_REQUEST['co']) ? $_REQUEST['co'] : '%';
    $queryTone = isset($_REQUEST['to']) ? $_REQUEST['to'] : '%';
} else if (isset($_REQUEST['pron'])) {
    $jyutping = new Jyutping();
    if ($jyutping->setWithRaw($_REQUEST['pron'])) {
        $queryInitial = $jyutping->getInitial();
        $queryNuclei = $jyutping->getNuclei();
        $queryCoda = $jyutping->getCoda();
        $queryTone = $jyutping->getTone();
        if ($queryInitial === '*') $queryInitial = '%';
        if ($queryNuclei === '*') $queryNuclei = '%';
        if ($queryCoda === '*') $queryCoda = '%';
        if ($queryTone === '*') $queryTone = '%';
    } else {
        outputJson(["error" => "Invalid Jyutping"]);
    }
}

if ($queryInitial !== null) {
    $entriesInAncient = [];
    $entriesInLocations = [];

    $pronunciationParts = [
        'initial' => $queryInitial,
        'nuclei' => $queryNuclei,
        'coda' => $queryCoda,
        'tone' => $queryTone,
    ];

    // 篩選地點
    $selectedLocationIds = null;
    if (isset($_REQUEST['locations']) && $_REQUEST['locations'] !== '') {
        $selectedLocationIds = array_values(array_unique(array_map('intval', explode(',', $_REQUEST['locations']))));
    }

    // 篩選韻書
    $selectedWanshyu = null;
    if (isset($_REQUEST['wanshyu'])) {
        if (is_array($_REQUEST['wanshyu'])) {
            $selectedWanshyu = $_REQUEST['wanshyu'];
        } else if ($_REQUEST['wanshyu'] === 'none') {
            $selectedWanshyu = [];
        } else {
            $selectedWanshyu = [$_REQUEST['wanshyu']];
        }
    }

    // 韻書
    $wanshyuList = jyutdictLoadWanshyu($dbh);
    $selectedBooks = [];
    foreach ($wanshyuList as $eachWanshyu) {
        $wanshyuKey = preg_replace('/^[yY]_/', '', $eachWanshyu['sheetname']);
        if ($selectedWanshyu !== null && !in_array($wanshyuKey, $selectedWanshyu)) {
            continue;
        }
        $selectedBooks[] = $eachWanshyu;
    }
    $bookRows = jyutdictLookupSourcePronunciations($dbh, $selectedBooks, $pronunciationParts);

    foreach ($selectedBooks as $eachWanshyu) {
        $allPron = ["__name" => $eachWanshyu['name']];
        foreach ($bookRows[$eachWanshyu['id']] ?? [] as $row) {
            $pron = $row['initial'] . $row['nuclei'] . $row['coda'];
            $allPron[$pron][$row['tone']] =
                empty($allPron[$pron][$row['tone']]) ?
                $row['chara'] :
                $allPron[$pron][$row['tone']] . $row['chara'];
        }
        $entriesInAncient[] = $allPron;
    }

    // 地方
    $cityList = jyutdictLoadAreas($dbh);
    $selectedCities = [];
    foreach ($cityList as $eachCity) {
        if ($selectedLocationIds !== null && !in_array($eachCity['id'], $selectedLocationIds, true)) {
            continue;
        }
        $selectedCities[] = $eachCity;
    }
    $cityRows = jyutdictLookupConfiguredLocationPronunciations($dbh, $selectedCities, $pronunciationParts);

    foreach ($selectedCities as $eachCity) {
        $allPron = ["__id" => $eachCity['id']];
        foreach ($cityRows[$eachCity['id']] ?? [] as $row) {
            $pron = $row['initial'] . $row['nuclei'] . $row['coda'];
            $allPron[$pron][$row['tone']] =
                empty($allPron[$pron][$row['tone']]) ?
                $row['chara'] :
                $allPron[$pron][$row['tone']] . $row['chara'];
        }
        if (count($allPron) > 1) {
            $entriesInLocations[] = $allPron;
        }
    }

    outputPublicJson([
        "韻書" => $entriesInAncient,
        "各地" => $entriesInLocations
    ]);
}

// =====================================================
// 查字邏輯 (Character Search)
// =====================================================
$submitCharaString = $_REQUEST['chara'];

// 分割、簡繁轉換（上限 10 字）
$submitCharaArray = preg_split("//u", $submitCharaString, -1, PREG_SPLIT_NO_EMPTY);
$submitCharaArray = array_slice($submitCharaArray, 0, 10);

$charaTransArray = querySim2TradBatch($submitCharaArray, $dbh);

try {
    $areaList = jyutdictLoadAreas($dbh);
    $selectedAreaIds = null;
    $rawAreas = $_REQUEST['areas'] ?? ($_REQUEST['area'] ?? null); // area 保留為單選舊連結兼容
    if ($rawAreas !== null && $rawAreas !== '') {
        if (is_array($rawAreas)) {
            $rawAreas = implode(',', $rawAreas);
        }
        $areaParts = explode(',', (string)$rawAreas);
        $selectedAreaIds = [];
        foreach ($areaParts as $areaPart) {
            $areaId = filter_var(trim($areaPart), FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1]
            ]);
            if ($areaId === false) {
                outputJson(["error" => "Invalid area id list"], 400);
            }
            if (!in_array($areaId, $selectedAreaIds, true)) {
                $selectedAreaIds[] = $areaId;
            }
        }
        $areaList = array_values(array_filter($areaList, function ($area) use ($selectedAreaIds) {
            return in_array($area['id'], $selectedAreaIds, true);
        }));
        if (count($areaList) !== count($selectedAreaIds)) {
            outputJson(["error" => "One or more areas were not found or unavailable"], 404);
        }
    }
    $wanshyuList = $selectedAreaIds === null ? jyutdictLoadWanshyu($dbh) : [];
    $locationRows = jyutdictLookupConfiguredLocationCharacters($dbh, $areaList, $charaTransArray);
    $kuangyonRows = $selectedAreaIds === null ? jyutdictLookupTableCharacters(
        $dbh,
        'y_kuangyon',
        $charaTransArray,
        ['initial', 'rimeclass', 'rime', 'division', 'rounding', 'tone', 'transliteration']
    ) : [];
    $bookRowsByTable = [];
    foreach ($wanshyuList as $book) {
        if ($book['name'] === '分韻') {
            $bookRowsByTable[$book['sheetname']] = jyutdictLookupTableCharacters(
                $dbh,
                $book['sheetname'],
                $charaTransArray,
                ['initial', 'nuclei', 'coda', 'tone', 'siuwan', 'meaning', 'initial_ch', 'final_ch', 'yunbu', 'tone_ch']
            );
        } elseif ($book['name'] === '英華') {
            $bookRowsByTable[$book['sheetname']] = jyutdictLookupTableCharacters(
                $dbh,
                $book['sheetname'],
                $charaTransArray,
                ['initial', 'nuclei', 'coda', 'tone', 'pron', 'radical', 'radical_stroke', 'extra_stroke', 'page', 'state', 'order']
            );
        }
    }
} catch (PDOException $e) {
    outputJson(["error" => "Database initialization error"]);
}

$entriesConcat = [];

foreach ($charaTransArray as $chara) {
    $entry = [
        "字" => $chara,
        "韻書" => [],
        "各地" => []
    ];

    // --- 韻書 (Ancient Rhymes) ---
    $entriesInAncient = [];

    // 1. 廣韻
    $kyEntries = [];
    foreach ($kuangyonRows[$chara] ?? [] as $row) {
        $kyEntries[] = [
            "書名" => "廣韻",
            "聲母" => $row['initial'],
            "攝" => $row['rimeclass'],
            "韻" => $row['rime'],
            "等" => $row['division'],
            "呼" => $row['rounding'],
            "聲調" => $row['tone'],
            "轉寫" => $row['transliteration'],
        ];
    }
    if (!empty($kyEntries)) {
        $entriesInAncient[] = $kyEntries;
    }

    // 2. 其他韻書（分韻、英華等）
    foreach ($wanshyuList as $book) {
        $bkEntries = [];
        $bkName = $book['name'];
        $rows = $bookRowsByTable[$book['sheetname']][$chara] ?? [];

        if ($bkName === '分韻') {
            foreach ($rows as $row) {
                $bkEntries[] = [
                    "書名" => $bkName, "聲母" => $row['initial'],
                    "韻核" => $row['nuclei'], "韻尾" => $row['coda'], "聲調" => $row['tone'],
                    "聲字" => $row['initial_ch'], "韻字" => $row['final_ch'], "調類" => $row['tone_ch'],
                    "義" => $row['meaning'], "小韻" => $row['siuwan'], "韻部" => $row['yunbu'],
                ];
            }
        } elseif ($bkName === '英華') {
            foreach ($rows as $row) {
                $bkEntries[] = [
                    "書名" => $bkName, "聲母" => $row['initial'],
                    "韻核" => $row['nuclei'], "韻尾" => $row['coda'], "聲調" => $row['tone'],
                    "頁" => $row['page'], "序" => $row['order'], "音" => $row['pron'],
                    "部首" => $row['radical'], "部首筆畫" => $row['radical_stroke'],
                    "部外筆畫" => $row['extra_stroke'], "狀態" => $row['state'],
                ];
            }
        }
        if (!empty($bkEntries)) {
            $entriesInAncient[] = $bkEntries;
        }
    }

    $entry["韻書"] = $entriesInAncient;

    // --- 各地 (Locations) ---
    $entriesLocations = [];
    foreach ($areaList as $area) {
        $id = $area['id'];
        $rows = $locationRows[$id][$chara] ?? [];

        if (count($rows) > 0) {
            $locObj = [
                "id" => $id,
                "粵拼" => [],
                "IPA" => [],
                "注釋" => [],
                "又音組" => []
            ];
            foreach ($rows as $row) {
                $locObj["粵拼"][] = $row['initial'] . $row['nuclei'] . $row['coda'] . $row['tone'];
                $locObj["IPA"][] = $row['ipa'];
                $locObj["注釋"][] = $row['note'];
                $locObj["又音組"][] = $row['alt_group'];
            }
            $entriesLocations[] = $locObj;
        }
    }

    $entry["各地"] = $entriesLocations;
    $entriesConcat[] = $entry;
}

outputPublicJson($entriesConcat);
?>
