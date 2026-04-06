<?php
/**
 * Created by PhpStorm.
 * User: EcRalt
 * Date: 2020/05/27
 * Time: 22:38
 */

include("../../const.php");
include_once("../../connectDB.php");
include("../../Lookup.class.php");
include_once("../../Jyutping.class.php");
require_once("../../dict_data/DictInfo.class.php");
require_once("../../dict_data/DictData.class.php");

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
    Info::printApiJson();
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

// 不請求raw則返回json，請求raw則直接print_r()以便debug
$isReturnRaw = isset($_REQUEST['raw']);

// 不請求ascii則返回中文鍵值對，請求ascii則鍵以英文寫、值以unicode轉寫
$isReturnAscii = isset($_REQUEST['ascii']);
if (!$isReturnAscii) { // 好惡心，有沒有其它更elegent的方法
    $key = ["name" => "書名", "initial" => "聲母", "nuclei" => "韻核", "coda" => "韻尾", "tone" => "聲調", "initial_ch" => "聲字", "final_ch" => "韻字", "tone_ch" => "調類", "rime_class" => "攝", "rime" => "韻", "rounding" => "呼", "transliteration" => "轉寫", "meaning" => "義", "siuwan" => "小韻", "yunbu" => "韻部", "page" => "頁", "order" => "序", "pronunciation" => "音", "radical" => "部首", "radical_stroke" => "部首筆畫", "extra_stroke" => "部外筆畫", "state" => "狀態", "division_adm" => "片區", "division_cha" => "等", "city" => "市", "district" => "管區", "color" => "色", "latitude" => "緯", "longitude" => "經", "ipa" => "IPA", "jpp" => "粵拼", "note" => "註", "chara" => "字", "ancient" => "韻書", "location" => "各地"];
} else {
    $key = ["name" => "name", "initial" => "initial", "nuclei" => "nuclei", "coda" => "coda", "tone" => "tone", "initial_ch" => "initial_ch", "final_ch" => "final_ch", "tone_ch" => "tone_ch", "rime_class" => "rime_class", "rime" => "rime", "rounding" => "rounding", "transliteration" => "transliteration", "meaning" => "meaning", "siuwan" => "siuwan", "yunbu" => "yunbu", "page" => "page", "order" => "order", "pronunciation" => "pronunciation", "radical" => "radical", "radical_stroke" => "radical_stroke", "extra_stroke" => "extra_stroke", "state" => "state", "division_adm" => "division", "division_cha" => "division", "city" => "city", "district" => "district", "color" => "color", "latitude" => "latitude", "longitude" => "longitude", "ipa" => "IPA", "jpp" => "jpp", "note" => "note", "chara" => "chara", "ancient" => "ancient", "location" => "location"];
}

if (isset($_REQUEST['chara'])) {
    $submitCharaString = $_REQUEST['chara'];

    // 分割請求字符串到 單字 放在 $submitCharaArray
    $submitCharaArray = preg_split("//u", $submitCharaString, -1, PREG_SPLIT_NO_EMPTY);
    $submitCharaArray = array_slice($submitCharaArray, 0, 10);

    // 簡繁轉換所有請求單字 放在 $charaTransArray
    $charaTransArray = [];
    $sim2trad = Sim2TradLookup::getInstance();      #获取简繁转换对象
    foreach ($submitCharaArray as $charaForS2T) {
        $charaS2TResult = $sim2trad->query($charaForS2T, $dbh);
        foreach ($charaS2TResult as $chara) {
            array_push($charaTransArray, $chara);
        }
    }

    // $entriesConcat 是個深度爲4的數組
    // $entriesConcat[k] 是對 $charaTransArray 的第k個字的搜索結果，有三個元素：
    //   "字"/"chara" 請求字, "古"/"ancient" 韻書音（包括廣韻與近期的韻書） , "地"/"location" 時音

    // $entriesConcat[k]["ancient"] 爲數組，每個元素爲各韻書音，
    // $entriesConcat[k]["ancient"][0] 爲數組，每個元素代表一本不同的韻書
    // $entriesConcat[k]["ancient"][0][l] 表示某本韻書下第l個讀音
    // $entriesConcat[k]["ancient"][0][l]["name"] 放韻書的名

    // $entriesConcat[k]["location"] 爲數組，每個元素代表一處不同的地方
    // $entriesConcat[k]["location"][0] 爲數組，每個元素代表一處不同的地方
    // $entriesConcat[k]["location"][0][l] 表示某處地方下第l個讀音
    // $entriesConcat[k]["location"][0][l]["city"] 放城市的名

    $entriesConcat = [];
    foreach ($charaTransArray as $chara) {
        $entry = [];
        $entries = [];
        $entriesInAncient = [];
        $entriesInLocations = [];

        #----------------------------------------------#

        if ($options["wanshyu"]) {
            $data = new DataKuangyon($dbh, $chara, "y_kuangyon", "", "", "");
            for (; $data->hasNext(); $data->next()) {
                $entry[$key["name"]] = "廣韻"; // 不整齊，難受
                //$entry["id"]                  = $data -> getID();
                $entry[$key["initial"]] = $data->getInitial();
                $entry[$key["rime_class"]] = $data->getRimeClass();
                $entry[$key["rime"]] = $data->getRime();
                $entry[$key["division_cha"]] = $data->getDivision();
                $entry[$key["rounding"]] = $data->getRounding();
                $entry[$key["tone"]] = $data->getTone();
                $entry[$key["transliteration"]] = $data->getTransliteration();
                array_push($entries, $entry);
                $entry = [];
            }
            array_push($entriesInAncient, $entries);
            $entries = [];
            
            try {
                $info = new InfoWanshyu($dbh);
            } catch (Exception $e) {
                echo ""; return;
            }
            for (; $info->hasNext(); $info->next()) {
                switch ($info->getName()) {
                    case '分韻':
                        $data = new DataFanwan($dbh, $chara, $info->getSheetname(), $info->getdate(), $info->getName(), $info->getFullName());
                        if ($data->listCount() > 0) {
                            for (; $data->hasNext(); $data->next()) {
                                $entry[$key["name"]] = $info->getName();
                                //$entry["id"]             = $data -> getID();
                                $entry[$key["initial"]] = $data->getInitial();
                                $entry[$key["nuclei"]] = $data->getNuclei();
                                $entry[$key["coda"]] = $data->getCoda();
                                $entry[$key["tone"]] = $data->getTone();
                                $entry[$key["initial_ch"]] = $data->getInitial_ch();
                                $entry[$key["final_ch"]] = $data->getFinal_ch();
                                $entry[$key["tone_ch"]] = $data->getTone_ch();
                                $entry[$key["meaning"]] = $data->getMeaning();
                                $entry[$key["siuwan"]] = $data->getSiuwan();
                                $entry[$key["yunbu"]] = $data->getYunbu();
                                array_push($entries, $entry);
                                $entry = [];
                            }
                        }
                        break;

                    case '英華':
                        $data = new DataJingwaa($dbh, $chara, $info->getSheetname(), $info->getdate(), $info->getName(), $info->getFullName());
                        if ($data->listCount() > 0) {
                            for (; $data->hasNext(); $data->next()) {
                                $entry[$key["name"]] = $info->getName();
                                //$entry["id"]                 = $data -> getID();
                                $entry[$key["initial"]] = $data->getInitial();
                                $entry[$key["nuclei"]] = $data->getNuclei();
                                $entry[$key["coda"]] = $data->getCoda();
                                $entry[$key["tone"]] = $data->getTone();
                                $entry[$key["page"]] = $data->getPage();
                                $entry[$key["order"]] = $data->getOrder();
                                $entry[$key["pronunciation"]] = $data->getPronunciation();
                                $entry[$key["radical"]] = $data->getRadical();
                                $entry[$key["radical_stroke"]] = $data->getRadical_stroke();
                                $entry[$key["extra_stroke"]] = $data->getExtra_stroke();
                                $entry[$key["state"]] = $data->getState();
                                array_push($entries, $entry);
                                $entry = [];
                            }
                        }
                        break;

                    default:
                        break;
                }
                array_push($entriesInAncient, $entries);
                $entries = [];
            }
        } // if ($options["wanshyu"])

        #----------------------------------------------#

        if ($options["area"]) { // 這“時音”的譯名還未統一
            try {
                $info = new InfoArea($dbh);
            } catch (Exception $e) {
                echo "";
                return;
            }
            for (; $info->hasNext(); $info->next()) {
                $data = new DataArea($dbh, $chara, $info->getSheetname(), $info->getLongitude(), $info->getLatitude(), $info->getDivision(), $info->getCity(), $info->getDistrict(), $info->getColor());
                if ($data->listCount() > 0) {
                    for (; $data->hasNext(); $data->next()) {
                        // 假如 color 含有“,”，就 split，只要最后一项
                        $color = $data->getColor();
                        if (strpos($color, ",") !== false) {
                            $colors = explode(",", $color);
                            $color = array_pop($colors);
                        }

                        //$entry["id"]       = $data -> getID();
                        $entry[$key["division_adm"]] = $data->getDivision();
                        $entry[$key["city"]] = $data->getCity();
                        $entry[$key["district"]] = $data->getDistrict();
                        $entry[$key["color"]] = $color;
                        $entry[$key["latitude"]] = $data->getLatitude(); // Added for Map
                        $entry[$key["longitude"]] = $data->getLongitude(); // Added for Map
                        $entry[$key["initial"]] = $data->getInitial();
                        $entry[$key["nuclei"]] = $data->getNuclei();
                        $entry[$key["coda"]] = $data->getCoda();
                        $entry[$key["tone"]] = $data->getTone();
                        $entry[$key["ipa"]] = $data->getIPA();
                        $entry[$key["jpp"]] = $data->getJpp(); // Added for frontend parsing
                        $entry[$key["note"]] = $data->getNote();
                        array_push($entries, $entry);
                        $entry = [];
                    }
                    array_push($entriesInLocations, $entries);
                    $entries = [];
                }
            }
        } // if ($options["area"])

        #----------------------------------------------#

        array_push($entriesConcat, [
            $key["chara"] => $chara,
            $key["ancient"] => $entriesInAncient,
            $key["location"] => $entriesInLocations
        ]);
    }

    if ($isReturnRaw) {
        print_r(($entriesConcat));
    } else {
        print_r(json_encode($entriesConcat, !$isReturnAscii * JSON_UNESCAPED_UNICODE));
    }
} // 查字部分


// 檢音部分：支持 pron 參數或 in/nu/co/to 分開參數
$queryInitial = null;
$queryNuclei = null;
$queryCoda = null;
$queryTone = null;

if (isset($_REQUEST['in']) || isset($_REQUEST['nu']) || isset($_REQUEST['co']) || isset($_REQUEST['to'])) {
    // 使用分開的參數（用於前端檢音頁面）
    // 注意：空字符串 '' 應該精確匹配空值，不是模糊匹配
    // 只有當參數未傳遞時才使用 '%'
    $queryInitial = isset($_REQUEST['in']) ? ($_REQUEST['in'] === '' ? '' : $_REQUEST['in']) : '%';
    $queryNuclei = isset($_REQUEST['nu']) ? ($_REQUEST['nu'] === '' ? '' : $_REQUEST['nu']) : '%';
    $queryCoda = isset($_REQUEST['co']) ? ($_REQUEST['co'] === '' ? '' : $_REQUEST['co']) : '%';
    $queryTone = isset($_REQUEST['to']) ? ($_REQUEST['to'] === '' ? '' : $_REQUEST['to']) : '%';
} else if (isset($_REQUEST['pron'])) {
    // 使用完整的粵拼字符串
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

        // 構建查詢條件
        // 如果參數包含 %，使用 LIKE；否則使用精確匹配
        $buildCondition = function($field, $value) {
            $field = "`" . $field . "`";
            if ($value === '%') {
                return "$field LIKE '%'";
            } elseif (strpos($value, '%') !== false) {
                return "$field LIKE '$value'";
            } else {
                return "$field = '$value'";
            }
        };

        // 構建 WHERE 條件
        $whereConditions = [];
        $whereConditions[] = $buildCondition('initial', $queryInitial);
        $whereConditions[] = $buildCondition('nuclei', $queryNuclei);
        $whereConditions[] = $buildCondition('coda', $queryCoda);
        $whereConditions[] = $buildCondition('tone', $queryTone);
        $whereClause = implode(' AND ', $whereConditions);

        //獲取韻書列表
        $inWanshyuList_sql = " SELECT `name`, `sheetname`
                                FROM `i_wanshyu_list`";
        $inWanshyuList_stmt = $dbh->prepare($inWanshyuList_sql);
        $inWanshyuList_stmt->execute();
        $wanshyuListArray = $inWanshyuList_stmt->fetchAll(PDO::FETCH_ASSOC);

        //對每本韻書：
        foreach ($wanshyuListArray as $eachWanshyu) {
            $tableName = $eachWanshyu['sheetname'];
            $inWanshyu_sql = "SELECT `id`, `chara`, `initial`, `nuclei`, `coda`, `tone`
                              FROM `$tableName`
                              WHERE $whereClause";
            $inWanshyu_stmt = $dbh->query($inWanshyu_sql);
            $inWanshyu_result = $inWanshyu_stmt->fetchAll(PDO::FETCH_ASSOC);

            $allPron = ["__name" => $eachWanshyu['name']];
            foreach ($inWanshyu_result as $inWanshyuPron) {
                //对每个符合条件的字：以jyut6为例
                $pron = $inWanshyuPron['initial'] . $inWanshyuPron['nuclei'] . $inWanshyuPron['coda'];
                //$pron==="jyut", $allPron["jyut"][6]==="月粤越…"
                $allPron[$pron][$inWanshyuPron['tone']] =
                    empty($allPron[$pron][$inWanshyuPron['tone']]) ?
                    $inWanshyuPron['chara'] :
                    $allPron[$pron][$inWanshyuPron['tone']] . $inWanshyuPron['chara'];
            }
            array_push($entriesInAncient, $allPron);
        }



        //獲取地方列表
        $inCityList_sql = "SELECT `longitude`, `latitude`, `first`, `second`, `third`, `sheetname`
                            FROM `i_area_list`";
        $inCityList_stmt = $dbh->prepare($inCityList_sql);
        $inCityList_stmt->execute();
        $cityListArray = $inCityList_stmt->fetchAll(PDO::FETCH_ASSOC);

        //对每个地点：
        foreach ($cityListArray as $eachCity) {
            $tableName = $eachCity['sheetname'];
            $inCity_sql = " SELECT `id`, `chara`, `initial`, `nuclei`, `coda`, `tone`
                            FROM `$tableName`
                            WHERE $whereClause";
            $inCity_stmt = $dbh->query($inCity_sql);
            $inCity_result = $inCity_stmt->fetchAll(PDO::FETCH_ASSOC);

            $allPron = [
                "__city" => $eachCity['second'],
                "__district" => empty($eachCity['third']) ? "" : $eachCity['third']
            ];


            foreach ($inCity_result as $inCityPron) {
                //对每个符合条件的字：以jyut6为例
                $pron = $inCityPron['initial'] . $inCityPron['nuclei'] . $inCityPron['coda'];
                //$pron==="jyut", $allPron["jyut"][6]==="月粤越…"
                $allPron[$pron][$inCityPron['tone']] =
                    empty($allPron[$pron][$inCityPron['tone']]) ?
                    $inCityPron['chara'] :
                    $allPron[$pron][$inCityPron['tone']] . $inCityPron['chara'];
            }
            array_push($entriesInLocations, $allPron);
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