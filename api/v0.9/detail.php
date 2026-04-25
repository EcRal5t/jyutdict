<?php
/**
 * API v0.9 detail.php (deprecated)
 */

include_once(__DIR__ . '/../core/db.php');
include_once(__DIR__ . '/../core/helpers.php');
include_once(__DIR__ . '/../core/Sim2Trad.php');
include_once(__DIR__ . '/../core/Jyutping.php');

header('Content-type: application/json');

if (isset($_REQUEST['help'])) {
    print_r(json_encode([
        "details_of_characters" => "https://jyutdict.org/api/v0.9/detail?chara={query}{&ascii}",
        "details_of_pronunciations" => "https://jyutdict.org/api/v0.9/detail?pron={query}{&ascii}",
        "chara" => "可輸入漢字字符串且將自動簡轉繁/異",
        "pron" => "使用粵拼作爲輸入",
        "ascii" => "將非ASCII字符轉爲UNICODE表示形式返回",
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    return;
}

// 若不請求chara=*或pron=*，則返回空
if (!isset($_REQUEST['chara']) && !isset($_REQUEST['pron'])) {
    printApiRootJson();
    return;
}
if (isset($_REQUEST['chara']) && isset($_REQUEST['pron'])) {
    print_r(json_encode([
        "error" => "Querying chara= and pron= concurrently is not allowed.",
    ]));
    return;
}

// $options["wanshyu"]==1 時檢索韻書
// $options["area"]==1 時檢索各地
$options = ["wanshyu" => 0, "area" => 0];
if (isset($_REQUEST['option'])) {
    foreach ($_REQUEST['option'] as $value) {
        $options[$value] = 1;
    }
} else
    $options = ["wanshyu" => 1, "area" => 1];

$isReturnRaw = isset($_REQUEST['raw']);

$isReturnAscii = isset($_REQUEST['ascii']);
if (!$isReturnAscii) {
    $key = ["name" => "書名", "initial" => "聲母", "nuclei" => "韻核", "coda" => "韻尾", "tone" => "聲調", "initial_ch" => "聲字", "final_ch" => "韻字", "tone_ch" => "調類", "rime_class" => "攝", "rime" => "韻", "rounding" => "呼", "transliteration" => "轉寫", "meaning" => "義", "siuwan" => "小韻", "yunbu" => "韻部", "page" => "頁", "order" => "序", "pronunciation" => "音", "radical" => "部首", "radical_stroke" => "部首筆畫", "extra_stroke" => "部外筆畫", "state" => "狀態", "division_adm" => "片區", "division_cha" => "等", "city" => "市", "district" => "管區", "color" => "色", "latitude" => "緯", "longitude" => "經", "ipa" => "IPA", "jpp" => "粵拼", "note" => "註", "chara" => "字", "ancient" => "韻書", "location" => "各地"];
} else {
    $key = ["name" => "name", "initial" => "initial", "nuclei" => "nuclei", "coda" => "coda", "tone" => "tone", "initial_ch" => "initial_ch", "final_ch" => "final_ch", "tone_ch" => "tone_ch", "rime_class" => "rime_class", "rime" => "rime", "rounding" => "rounding", "transliteration" => "transliteration", "meaning" => "meaning", "siuwan" => "siuwan", "yunbu" => "yunbu", "page" => "page", "order" => "order", "pronunciation" => "pronunciation", "radical" => "radical", "radical_stroke" => "radical_stroke", "extra_stroke" => "extra_stroke", "state" => "state", "division_adm" => "division", "division_cha" => "division", "city" => "city", "district" => "district", "color" => "color", "latitude" => "latitude", "longitude" => "longitude", "ipa" => "IPA", "jpp" => "jpp", "note" => "note", "chara" => "chara", "ancient" => "ancient", "location" => "location"];
}

if (isset($_REQUEST['chara'])) {
    $submitCharaString = $_REQUEST['chara'];

    $submitCharaArray = preg_split("//u", $submitCharaString, -1, PREG_SPLIT_NO_EMPTY);
    $submitCharaArray = array_slice($submitCharaArray, 0, 10);

    $charaTransArray = [];
    foreach ($submitCharaArray as $charaForS2T) {
        $charaS2TResult = querySim2Trad($charaForS2T, $dbh);
        foreach ($charaS2TResult as $chara) {
            $charaTransArray[] = $chara;
        }
    }

    $entriesConcat = [];
    foreach ($charaTransArray as $chara) {
        $entry = [];
        $entries = [];
        $entriesInAncient = [];
        $entriesInLocations = [];

        if ($options["wanshyu"]) {
            // 廣韻
            $kyStmt = $dbh->prepare("SELECT `initial`,`rimeclass`,`rime`,`division`,`rounding`,`tone`,`transliteration` FROM `y_kuangyon` WHERE `chara` = :chara");
            $kyStmt->execute([':chara' => $chara]);
            $kyRows = $kyStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($kyRows as $row) {
                $entry[$key["name"]] = "廣韻";
                $entry[$key["initial"]] = $row['initial'];
                $entry[$key["rime_class"]] = $row['rimeclass'];
                $entry[$key["rime"]] = $row['rime'];
                $entry[$key["division_cha"]] = $row['division'];
                $entry[$key["rounding"]] = $row['rounding'];
                $entry[$key["tone"]] = $row['tone'];
                $entry[$key["transliteration"]] = $row['transliteration'];
                $entries[] = $entry;
                $entry = [];
            }
            $entriesInAncient[] = $entries;
            $entries = [];

            // 其他韻書
            $wsStmt = $dbh->prepare("SELECT `name`,`fullname`,`date`,`sheetname` FROM `i_wanshyu_list`");
            $wsStmt->execute();
            $wanshyuList = $wsStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($wanshyuList as $book) {
                $bkName = $book['name'];
                $tableName = $book['sheetname'];

                if ($bkName === '分韻') {
                    $stmt = $dbh->prepare("SELECT `initial`,`nuclei`,`coda`,`tone`,`siuwan`,`meaning`,`initial_ch`,`final_ch`,`yunbu`,`tone_ch` FROM `$tableName` WHERE `chara` = :chara");
                    $stmt->execute([':chara' => $chara]);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as $row) {
                        $entry[$key["name"]] = $bkName;
                        $entry[$key["initial"]] = $row['initial'];
                        $entry[$key["nuclei"]] = $row['nuclei'];
                        $entry[$key["coda"]] = $row['coda'];
                        $entry[$key["tone"]] = $row['tone'];
                        $entry[$key["initial_ch"]] = $row['initial_ch'];
                        $entry[$key["final_ch"]] = $row['final_ch'];
                        $entry[$key["tone_ch"]] = $row['tone_ch'];
                        $entry[$key["meaning"]] = $row['meaning'];
                        $entry[$key["siuwan"]] = $row['siuwan'];
                        $entry[$key["yunbu"]] = $row['yunbu'];
                        $entries[] = $entry;
                        $entry = [];
                    }
                } elseif ($bkName === '英華') {
                    $stmt = $dbh->prepare("SELECT `initial`,`nuclei`,`coda`,`tone`,`pron`,`radical`,`radical_stroke`,`extra_stroke`,`page`,`state`,`order` FROM `$tableName` WHERE `chara` = :chara");
                    $stmt->execute([':chara' => $chara]);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as $row) {
                        $entry[$key["name"]] = $bkName;
                        $entry[$key["initial"]] = $row['initial'];
                        $entry[$key["nuclei"]] = $row['nuclei'];
                        $entry[$key["coda"]] = $row['coda'];
                        $entry[$key["tone"]] = $row['tone'];
                        $entry[$key["page"]] = $row['page'];
                        $entry[$key["order"]] = $row['order'];
                        $entry[$key["pronunciation"]] = $row['pron'];
                        $entry[$key["radical"]] = $row['radical'];
                        $entry[$key["radical_stroke"]] = $row['radical_stroke'];
                        $entry[$key["extra_stroke"]] = $row['extra_stroke'];
                        $entry[$key["state"]] = $row['state'];
                        $entries[] = $entry;
                        $entry = [];
                    }
                }
                $entriesInAncient[] = $entries;
                $entries = [];
            }
        }

        if ($options["area"]) {
            $areaStmt = $dbh->prepare("SELECT `id`,`longitude`,`latitude`,`first`,`second`,`third`,`sheetname`,`color` FROM `i_area_list`");
            $areaStmt->execute();
            $areaList = $areaStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($areaList as $area) {
                $stmt = $dbh->prepare("SELECT `initial`,`nuclei`,`coda`,`tone`,`ipa`,`note` FROM `{$area['sheetname']}` WHERE `chara` = :chara");
                $stmt->execute([':chara' => $chara]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($rows) > 0) {
                    $color = $area['color'];
                    if (strpos($color, ",") !== false) {
                        $colors = explode(",", $color);
                        $color = array_pop($colors);
                    }

                    foreach ($rows as $row) {
                        $entry[$key["division_adm"]] = $area['first'];
                        $entry[$key["city"]] = $area['second'];
                        $entry[$key["district"]] = $area['third'];
                        $entry[$key["color"]] = $color;
                        $entry[$key["latitude"]] = $area['latitude'];
                        $entry[$key["longitude"]] = $area['longitude'];
                        $entry[$key["initial"]] = $row['initial'];
                        $entry[$key["nuclei"]] = $row['nuclei'];
                        $entry[$key["coda"]] = $row['coda'];
                        $entry[$key["tone"]] = $row['tone'];
                        $entry[$key["ipa"]] = $row['ipa'];
                        $entry[$key["jpp"]] = $row['initial'] . $row['nuclei'] . $row['coda'] . $row['tone'];
                        $entry[$key["note"]] = $row['note'];
                        $entries[] = $entry;
                        $entry = [];
                    }
                    $entriesInLocations[] = $entries;
                    $entries = [];
                }
            }
        }

        $entriesConcat[] = [
            $key["chara"] => $chara,
            $key["ancient"] => $entriesInAncient,
            $key["location"] => $entriesInLocations
        ];
    }

    if ($isReturnRaw) {
        print_r(($entriesConcat));
    } else {
        print_r(json_encode($entriesConcat, !$isReturnAscii * JSON_UNESCAPED_UNICODE));
    }
}


// 檢音部分
$queryInitial = null;
$queryNuclei = null;
$queryCoda = null;
$queryTone = null;

if (isset($_REQUEST['in']) || isset($_REQUEST['nu']) || isset($_REQUEST['co']) || isset($_REQUEST['to'])) {
    $queryInitial = isset($_REQUEST['in']) ? ($_REQUEST['in'] === '' ? '' : $_REQUEST['in']) : '%';
    $queryNuclei = isset($_REQUEST['nu']) ? ($_REQUEST['nu'] === '' ? '' : $_REQUEST['nu']) : '%';
    $queryCoda = isset($_REQUEST['co']) ? ($_REQUEST['co'] === '' ? '' : $_REQUEST['co']) : '%';
    $queryTone = isset($_REQUEST['to']) ? ($_REQUEST['to'] === '' ? '' : $_REQUEST['to']) : '%';
} else if (isset($_REQUEST['pron'])) {
    $submitPronString = $_REQUEST['pron'];
    $jyutping = new Jyutping();
    if ($jyutping->setWithRaw($submitPronString)) {
        $queryInitial = $jyutping->getInitial();
        $queryNuclei = $jyutping->getNuclei();
        $queryCoda = $jyutping->getCoda();
        $queryTone = $jyutping->getTone();
    } else {
        print_r(json_encode([
            "error" => "Invalid Jyutping",
            "__ref" => "http://jyutdict.org/about"
        ], JSON_UNESCAPED_SLASHES));
        return;
    }
}

if ($queryInitial !== null) {
    $entriesConcat = [];
    $entriesInAncient = [];
    $entriesInLocations = [];

    // 構建查詢條件（使用參數綁定，修復原版 SQL 注入）
    $whereConditions = [];
    $whereParams = [];

    if ($queryInitial === '%') {
        $whereConditions[] = "`initial` LIKE '%'";
    } else {
        $whereConditions[] = "`initial` = :initial";
        $whereParams[':initial'] = $queryInitial;
    }
    if ($queryNuclei === '%') {
        $whereConditions[] = "`nuclei` LIKE '%'";
    } else {
        $whereConditions[] = "`nuclei` = :nuclei";
        $whereParams[':nuclei'] = $queryNuclei;
    }
    if ($queryCoda === '%') {
        $whereConditions[] = "`coda` LIKE '%'";
    } else {
        $whereConditions[] = "`coda` = :coda";
        $whereParams[':coda'] = $queryCoda;
    }
    if ($queryTone === '%') {
        $whereConditions[] = "`tone` LIKE '%'";
    } else {
        $whereConditions[] = "`tone` = :tone";
        $whereParams[':tone'] = $queryTone;
    }

    $whereClause = implode(' AND ', $whereConditions);

    // 韻書
    $wsStmt = $dbh->prepare("SELECT `name`, `sheetname` FROM `i_wanshyu_list`");
    $wsStmt->execute();
    $wanshyuList = $wsStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($wanshyuList as $eachWanshyu) {
        $tableName = $eachWanshyu['sheetname'];
        $sql = "SELECT `id`, `chara`, `initial`, `nuclei`, `coda`, `tone` FROM `$tableName` WHERE $whereClause";
        $stmt = $dbh->prepare($sql);
        $stmt->execute($whereParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $allPron = ["__name" => $eachWanshyu['name']];
        foreach ($rows as $row) {
            $pron = $row['initial'] . $row['nuclei'] . $row['coda'];
            $allPron[$pron][$row['tone']] =
                empty($allPron[$pron][$row['tone']]) ?
                $row['chara'] :
                $allPron[$pron][$row['tone']] . $row['chara'];
        }
        $entriesInAncient[] = $allPron;
    }

    // 地方
    $cityStmt = $dbh->prepare("SELECT `longitude`, `latitude`, `first`, `second`, `third`, `sheetname` FROM `i_area_list`");
    $cityStmt->execute();
    $cityList = $cityStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cityList as $eachCity) {
        $tableName = $eachCity['sheetname'];
        $sql = "SELECT `id`, `chara`, `initial`, `nuclei`, `coda`, `tone` FROM `$tableName` WHERE $whereClause";
        $stmt = $dbh->prepare($sql);
        $stmt->execute($whereParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $allPron = [
            "__city" => $eachCity['second'],
            "__district" => empty($eachCity['third']) ? "" : $eachCity['third']
        ];
        foreach ($rows as $row) {
            $pron = $row['initial'] . $row['nuclei'] . $row['coda'];
            $allPron[$pron][$row['tone']] =
                empty($allPron[$pron][$row['tone']]) ?
                $row['chara'] :
                $allPron[$pron][$row['tone']] . $row['chara'];
        }
        $entriesInLocations[] = $allPron;
    }

    $entriesConcat = [
        $key["ancient"] => $entriesInAncient,
        $key["location"] => $entriesInLocations
    ];

    if ($isReturnRaw) {
        print_r(($entriesConcat));
    } else {
        print_r(json_encode($entriesConcat, !$isReturnAscii * JSON_UNESCAPED_UNICODE));
    }
} // 查音部分