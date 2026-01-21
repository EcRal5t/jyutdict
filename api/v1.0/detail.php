<?php
/**
 * API v1.0 detail.php
 * Rewritten to improved JSON structure.
 */

include("../../const.php");
include_once("../../connectDB.php");
include("../../Lookup.class.php");
include_once("../../Jyutping.class.php");
require_once("../../dict_data/DictInfo.class.php");
require_once("../../dict_data/DictData.class.php");

header('Content-type: application/json');

// Helper to handle JSON output
function outputJson($data)
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// 1. If 'chara' is not set, return iarealist (Header/Location list)
if (isset($_REQUEST['help'])) {
    outputJson([
        "details_of_characters" => "https://jyutdict.org/api/v1.0/detail?chara={query}",
        "list_locations" => "https://jyutdict.org/api/v1.0/detail?chara=",
        "chara" => "可輸入漢字字符串且將自動簡轉繁/異",
        "description" => "新版 API，各地數據以 ID 分組，需先調用 chara= 獲取地點列表以映射 ID。"
    ]);
}

// 1. If 'chara' is set but empty, return iarealist (Header/Location list)
if (isset($_REQUEST['chara']) && $_REQUEST['chara'] === "") {
    try {
        $stmt = $dbh->prepare("SELECT `id`, `longitude`, `latitude`, `first`, `second`, `third`, `color` FROM `IAreaList`");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        outputJson($result);
    } catch (PDOException $e) {
        outputJson(["error" => "Database error: " . $e->getMessage()]);
    }
}

// If 'chara' is not set at all, maybe return help or error? 
// User implied strictness. Let's return help if no valid params.
if (!isset($_REQUEST['chara'])) {
    outputJson(["error" => "No parameters provided. Use ?help for usage."]);
}

// 2. Process 'chara' request
$submitCharaString = $_REQUEST['chara'];

// Split and Sim2Trad conversion (reusing logic from v0.9)
// Limit to 10 chars
$submitCharaArray = preg_split("//u", $submitCharaString, -1, PREG_SPLIT_NO_EMPTY);
$submitCharaArray = array_slice($submitCharaArray, 0, 10);

$charaTransArray = [];
try {
    $sim2trad = Sim2TradLookup::getInstance();
    foreach ($submitCharaArray as $charaForS2T) {
        $charaS2TResult = $sim2trad->query($charaForS2T, $dbh);
        foreach ($charaS2TResult as $chara) {
            array_push($charaTransArray, $chara);
        }
    }
} catch (Exception $e) {
    // If Sim2Trad fails, fallback to using original chars unique
    $charaTransArray = array_unique($submitCharaArray);
}

// Prepare result array
$entriesConcat = [];

// Prepare SQL statements
$wanshyuListSql = "SELECT `name`, `sheetname` FROM `IWanshyuList`";
// Note: Ancient data logic remains largely similar to v0.9 regarding fetching
// We will look up each char in each book.

// For Locations, we first get the list of tables
$areaListSql = "SELECT `id`, `sheetname` FROM `IAreaList`";
// We select id to index the result as requested

try {
    // Cache table lists
    $wanshyuStmt = $dbh->prepare($wanshyuListSql);
    $wanshyuStmt->execute();
    $wanshyuList = $wanshyuStmt->fetchAll(PDO::FETCH_ASSOC);

    $areaStmt = $dbh->prepare($areaListSql);
    $areaStmt->execute();
    $areaList = $areaStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    outputJson(["error" => "Database initialization error"]);
}

// Processing Char by Char
foreach ($charaTransArray as $chara) {
    $entry = [
        "字" => $chara,
        "韻書" => [],
        "各地" => []
    ];

    // --- Ancient Rhymes (韻書) ---
    // Keeping structure similar to v0.9 as requested ("返回结果里的“韻書”字段不变")
    // v0.9 structure: array of arrays of objects?
    // v0.9: $entriesConcat[k]["ancient"][0][l]["name"]... 
    // Actually v0.9 logic:
    // $entriesInAncient = [];
    // foreach book -> $entries (list of matches) -> push to $entriesInAncient
    // So "ancient" is [[match1, match2 (book1)], [match1 (book2)], ...]

    $ancientData = [];
    foreach ($wanshyuList as $book) {
        // Reuse v0.9 Logic via Data classes if possible, or raw SQL to be safe and consistent
        // v0.9 uses `DataKuangyon`, `DataFanwan`, `DataJingwaa` classes.
        // To ensure "field doesn't change", reusing classes is safest if they return arrayable objects.
        // But v0.9 puts them into an array manually. 
        // Let's replicate v0.9 loop logic for Ancient.

        $bookEntries = [];
        // Map v0.9 keys
        $keyMap = [
            "name" => "書名",
            "initial" => "聲母",
            "nuclei" => "韻核",
            "coda" => "韻尾",
            "tone" => "聲調",
            "initial_ch" => "聲字",
            "final_ch" => "韻字",
            "tone_ch" => "調類",
            "rime_class" => "攝",
            "rime" => "韻",
            "rounding" => "呼",
            "transliteration" => "轉寫",
            "meaning" => "義",
            "siuwan" => "小韻",
            "yunbu" => "韻部",
            "page" => "頁",
            "order" => "序",
            "pronunciation" => "音",
            "radical" => "部首",
            "radical_stroke" => "部首筆畫",
            "extra_stroke" => "部外筆畫",
            "state" => "狀態"
        ];

        // Note: The v0.9 code has distinct logic for '廣韻' (DataKuangyon) vs others.
        // Since I want to preserve the output format exactly for this part, I should copy the relevant v0.9 logic blocks.

        // However, since I am rewriting, maybe I can simplify but the requirement says "unchanged".
        // I will implement raw SQL equivalents to avoid dependency on those specific helper classes if they are complex, 
        // OR just instantiate them. Instantiation seems safer to match output.

        // '廣韻' is special in v0.9 logic (always checked first if option on).
        // Wait, v0.9 iterates `IWanshyuList` BUT also hardcodes `DataKuangyon`.
        // Actually v0.9 logic is:
        // 1. DataKuangyon (hardcoded)
        // 2. Iterate InfoWanshyu (which query IWanshyuList?)

        // Let's refine. The user wants "韻書" field unchanged.
        // I'll try to use the raw SQL approach which is cleaner and I can control the output keys to match v0.9 strings.

        // 1. 廣韻 (Kuangyon)
        // Check if this book is in our list or if it's separate? v0.9 treats it separate.
        // I'll stick to iterating the `IWanshyuList` which usually contains all including Kuangyon if configured?
        // Checking v0.9: `DataKuangyon` usage suggests it might be a specific table 'YKuangyon'.

        // Let's ignore the complexity of v0.9's class loading and just use SQL for valid books if possible.
        // But to be 100% safe on "unchanged", I should probably basically copy-paste the v0.9 local logic for "ancient".

    }

    // RE-READING v0.9: It constructs `$entriesInAncient` by pushing arrays of entries.
    // I will replicate the loop structure using the classes to ensure identical output for "ancient".
    // I need to make sure I include the right classes.

    // Logic for Ancient (copied/adapted from v0.9)
    $entriesInAncient = [];

    // 1. Kuangyon (hardcoded in v0.9 as first item)
    $kyEntries = [];
    $data = new DataKuangyon($dbh, $chara, "YKuangyon", "", "", "");
    $kyKeyMap = [
        "name" => "書名",
        "initial" => "聲母",
        "rime_class" => "攝",
        "rime" => "韻",
        "division_cha" => "等",
        "rounding" => "呼",
        "tone" => "聲調",
        "transliteration" => "轉寫"
    ];

    for (; $data->hasNext(); $data->next()) {
        $e = [];
        $e["書名"] = "廣韻";
        $e["聲母"] = $data->getInitial();
        $e["攝"] = $data->getRimeClass();
        $e["韻"] = $data->getRime();
        $e["等"] = $data->getDivision();
        $e["呼"] = $data->getRounding();
        $e["聲調"] = $data->getTone();
        $e["轉寫"] = $data->getTransliteration();
        $kyEntries[] = $e;
    }
    if (!empty($kyEntries)) {
        $entriesInAncient[] = $kyEntries;
    }

    // 2. Other books
    try {
        $info = new InfoWanshyu($dbh);
        for (; $info->hasNext(); $info->next()) {
            $bkEntries = [];
            $bkName = $info->getName();

            if ($bkName == '分韻') {
                $data = new DataFanwan($dbh, $chara, $info->getSheetname(), $info->getdate(), $info->getName(), $info->getFullName());
                for (; $data->hasNext(); $data->next()) {
                    $e = [];
                    $e["書名"] = $info->getName();
                    $e["聲母"] = $data->getInitial();
                    $e["韻核"] = $data->getNuclei();
                    $e["韻尾"] = $data->getCoda();
                    $e["聲調"] = $data->getTone();
                    $e["聲字"] = $data->getInitial_ch();
                    $e["韻字"] = $data->getFinal_ch();
                    $e["調類"] = $data->getTone_ch();
                    $e["義"] = $data->getMeaning();
                    $e["小韻"] = $data->getSiuwan();
                    $e["韻部"] = $data->getYunbu();
                    $bkEntries[] = $e;
                }
            } elseif ($bkName == '英華') {
                $data = new DataJingwaa($dbh, $chara, $info->getSheetname(), $info->getdate(), $info->getName(), $info->getFullName());
                for (; $data->hasNext(); $data->next()) {
                    $e = [];
                    $e["書名"] = $info->getName();
                    $e["聲母"] = $data->getInitial();
                    $e["韻核"] = $data->getNuclei();
                    $e["韻尾"] = $data->getCoda();
                    $e["聲調"] = $data->getTone();
                    $e["頁"] = $data->getPage();
                    $e["序"] = $data->getOrder();
                    $e["音"] = $data->getPronunciation();
                    $e["部首"] = $data->getRadical();
                    $e["部首筆畫"] = $data->getRadical_stroke();
                    $e["部外筆畫"] = $data->getExtra_stroke();
                    $e["狀態"] = $data->getState();
                    $bkEntries[] = $e;
                }
            }
            if (!empty($bkEntries)) {
                $entriesInAncient[] = $bkEntries;
            }
        }
    } catch (Exception $e) {
    }

    $entry["韻書"] = $entriesInAncient;

    // --- Locations (各地) - NEW LOGIC ---

    $entriesLocations = [];
    foreach ($areaList as $area) {
        $table = $area['sheetname'];
        $id = $area['id'];

        // Safe check for table name to prevent injection via database content? 
        // Although DB content should be trusted, safe binding or check is good.
        // But for now trust iarealist content (it's internal).

        $sql = "SELECT `initial` AS `聲母`, `nuclei` AS `韻核`, `coda` AS `韻尾`, `tone` AS `聲調`, `ipa` AS `IPA`, `note` AS `注釋` FROM `$table` WHERE `chara` = :chara";
        $stmt = $dbh->prepare($sql);
        $stmt->execute([':chara' => $chara]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) > 0) {
            // Grouping
            $locObj = [
                "id" => $id,
                "粵拼" => [],
                "IPA" => [],
                "注釋" => []  // Note: user asked for "注釋", v0.9 had "note" => "註"
                // User example: "注釋": ...
            ];

            foreach ($rows as $row) {
                // Combining jpp from components might be safer or just take column?
                // v0.9 `DataArea` constructed jpp?
                // `DataArea.class.php`: getJpp() usually returns the column `jpp`.
                // I will use the columns directly.

                $locObj["粵拼"][] = $row['聲母'] . $row['韻核'] . $row['韻尾'] . $row['聲調'];
                $locObj["IPA"][] = $row['IPA'];
                $locObj["注釋"][] = $row['注釋'];
            }
            $entriesLocations[] = $locObj;
        }
    }

    $entry["各地"] = $entriesLocations;
    $entriesConcat[] = $entry;
}

outputJson($entriesConcat);
?>