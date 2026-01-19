<?php
/**
 * Created by PhpStorm.
 * User: EcRalt
 * Date: 2020/05/28
 * Time: 18:52
 */

include ("../../const.php");
include_once("../../connectDB.php");
include("../../Lookup.class.php");
include_once("../../Jyutping.class.php");

header('Content-type: application/json');

if (isset($_REQUEST['help'])) {
    print_r(json_encode([
        "format"=>"https://jyutdict.org/api/v0.9/sheet?query={query}{&fuzzy, regex, trim, ascii, b, col={location}, limit={count}}",
        "query"=>"可如'aa3'、'啊'等，爲半角歎號'!'時表示隨機返回，若不指定 limit 參數則默認返回一項",
        "regex"=>"使用正則",
        "fuzzy"=>"模糊查詢，即前後可接其它字符串",
        "trim"=>"推薦使用，可以無視標記而檢索音節整體，從而支持 !!foo1/{query}⑩？/bar2 一類格式",
        "col"=>"選擇某一列查詢，參數可用 'query=' (無參數)獲取",
        "ascii"=>"將非 ASCII 字符轉爲 UNICODE 表示形式返回",
        "b"=>"查詢釋義，此時會開啟模糊查詢開關",
        "limit"=>"僅適用於隨機返回模式，用於控制返回條數，不超過 30"
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    return;
}
if (!isset($_REQUEST['query'])) {
    Info::printApiJson();
    return;
}

$queryString = $_REQUEST['query'];
$isAlphaNum = preg_match("/[!?a-zA-Z0-9]/", $queryString);
$isReturnRaw = isset($_REQUEST['raw']);
$isReturnAscii = isset($_REQUEST['ascii']);
$queryCol = (!isset($_REQUEST['col'])) ?
            $isAlphaNum ? "綜" : "字頭" :
            $_REQUEST['col'];
$isUsingRegex = isset($_REQUEST['regex']);
$isFuzzyQuery = isset($_REQUEST['fuzzy']);
$isIgnoreMark = isset($_REQUEST['trim']);
$isAskingFullHeader = isset($_REQUEST['header']);
if ($isAlphaNum) {
    $queryString = strtolower($queryString);
}
if (isset($_REQUEST['b'])) {
    $queryCol = "釋義";
    $isFuzzyQuery = true;
}

$isRandomReturn = ($queryString=="!");
$randomReturnCount = (isset($_REQUEST['limit']) and is_numeric($_REQUEST['limit'])) ?
            intval($_REQUEST['limit']) : 1;
$randomReturnCount = $randomReturnCount<=30 ? $randomReturnCount : 30;


$sheetHeader_sql  = "SELECT * FROM `IFaamjyut`"; // 偷個懶，直接獲取表頭
$sheetHeader_stmt = $dbh->prepare($sheetHeader_sql);
$sheetHeader_stmt -> execute();
$sheetHeaderArray = $sheetHeader_stmt->fetchAll(PDO::FETCH_ASSOC);
$sheetHeaderList  = [];
for ($i = 0; $i<count($sheetHeaderArray); $i++) { // 僅放表頭
    $sheetHeaderList[$sheetHeaderArray[$i]["col"]] = $i;
}
if ($queryString==="") { // 返回表頭相關的所有信息
    if ($isAskingFullHeader) {
        $sheetHeaderList  = [];
        for ($i = 0; $i<count($sheetHeaderArray); $i++) {
            $sheetHeaderList[$i]["id"] = $i;
            $sheetHeaderList[$i]["col"] = $sheetHeaderArray[$i]["col"];
            if ($sheetHeaderArray[$i]["kind"] == 1) { // 地方
                $sheetHeaderList[$i]["is_city"] = 1;
                $sheetHeaderList[$i]["city"] = $sheetHeaderArray[$i]["fullname"];
                $sheetHeaderList[$i]["sub"] = $sheetHeaderArray[$i]["fullname_note"];
                $sheetHeaderList[$i]["color"] = $sheetHeaderArray[$i]["color"];
            } elseif ($sheetHeaderArray[$i]["kind"]==0) {
                $sheetHeaderList[$i]["is_city"] = 0;
                $sheetHeaderList[$i]["fullname"] = $sheetHeaderArray[$i]["fullname"];
            } else {
                $sheetHeaderList[$i]["is_city"] = 2;
                $sheetHeaderList[$i]["fullname"] = $sheetHeaderArray[$i]["fullname"];
                $sheetHeaderList[$i]["color"] = $sheetHeaderArray[$i]["color"];  
            }
        }
    }
    print_r(json_encode([
        "__valid_options"=>$sheetHeaderList,
    ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    return;
}
for ($i = 0; $i<count($sheetHeaderArray); $i++) { // 僅放表頭
    $sheetHeaderList[$sheetHeaderArray[$i]["col"]] = $i;
}

if (!$isRandomReturn) { // 常規檢索
    if (!array_key_exists($queryCol, $sheetHeaderList)) { // 地名參數錯誤
        print_r(json_encode([
            "error"=>"Invalid parameters: Col=$queryCol",
            "__valid_options"=>$sheetHeaderList,
        ], JSON_UNESCAPED_UNICODE));
        return;
    }
    if ($isIgnoreMark) {
        $isUsingRegex = true;
        $isFuzzyQuery = true;
        $queryString = str_ireplace("*", "\*", $queryString);
        $queryString = str_ireplace("?", "\？", $queryString);
        if (!is_numeric(substr($queryString, -1))) {
            $queryString = $queryString."[^a-z]";
        }
        $queryString = "(^$queryString)|([!?/]$queryString)";
    //    $queryString = "!?!?(\w+/)*".$queryString."\d?[?？①-⑩]*(/\w+)*";
    }

    if ($isUsingRegex) {
        if ($isFuzzyQuery) {
            $inCharaSheet_sql = "SELECT * FROM `JFaamjyut` WHERE `$queryCol` REGEXP :str LIMIT 50";
        } else {
            $inCharaSheet_sql = "SELECT * FROM `JFaamjyut` WHERE `$queryCol` REGEXP :str LIMIT 50";
            $queryString = "^$queryString$";
        }
    } else {
        if ($isFuzzyQuery) {
            $inCharaSheet_sql = "SELECT * FROM `JFaamjyut` WHERE `$queryCol` LIKE :str LIMIT 50";
            $queryString = "%$queryString%";
        } else {
            $inCharaSheet_sql = "SELECT * FROM `JFaamjyut` WHERE `$queryCol` = :str LIMIT 50";
        }
    }
} else { // 隨機返回
    $inCharaSheet_sql = "
    SELECT t1.*
    FROM `JFaamjyut` AS t1
    JOIN (
        SELECT id
        FROM `JFaamjyut`
        ORDER BY RAND()
        LIMIT $randomReturnCount
    ) AS t2 ON t1.id = t2.id;
    ";
}
//print_r($inCharaSheet_sql);
//echo "<br>";
//print_r($queryString);
//echo "<br>";

$inCharaSheet_stmt = $dbh->prepare($inCharaSheet_sql);
$inCharaSheet_stmt -> execute([':str'=>$queryString]);
$inCharaSheetArray = $inCharaSheet_stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($inCharaSheetArray) > 150) { // 檢索結果過長
    print_r(json_encode([
        "error"=>"Too many search results, consider adding search criteria.",
        "__return_length"=>count($inCharaSheetArray),
        "__allowable_max_length"=>150
    ]));
    return;
}
$inCharaSheetArray = array_merge(array($sheetHeaderList), $inCharaSheetArray);

if ($isReturnRaw) {
    exit(($inCharaSheetArray));
} else {
    exit(json_encode($inCharaSheetArray, (!$isReturnAscii * JSON_UNESCAPED_UNICODE) | JSON_UNESCAPED_SLASHES));
}
