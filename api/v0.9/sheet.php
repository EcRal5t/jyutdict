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

if (isset($_REQUEST['help'])) {
    print_r(json_encode([
        "format"=>"https://jyutdict.org/api/v0.9/sheet?query={query}{&fuzzy, regex, trim, ascii, b, col={locations}}",
        "query"=>"可如'aa3', '啊'",
        "regex"=>"使用正則",
        "fuzzy"=>"模糊查詢，即前後可接其它字符串",
        "trim"=>"<b>推薦使用</b>，可以蒐索音節整體，無視標記以檢索 !!foo1/{query}⑩？/bar2 這類格式",
        "col"=>"選擇某一列查詢，參數可用 &col=0 獲取",
        "ascii"=>"將非ASCII字符轉爲UNICODE表示形式返回",
        "b"=>"查詢釋義，此時會開啟模糊查詢開關"
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
            $isAlphaNum ? "綜" : "繁" :
            $_REQUEST['col'];
$isUsingRegex = isset($_REQUEST['regex']);
$isFuzzyQuery = isset($_REQUEST['fuzzy']);
$isIgnoreMark = isset($_REQUEST['trim']);
if ($isAlphaNum) {
    $queryString = strtolower($queryString);
}
if (isset($_REQUEST['b'])) {
    $queryCol = "釋義";
    $isFuzzyQuery = true;
}

$sheetHeader_sql  = "SHOW columns FROM `YFaanjyut`"; // 偷懶了
$sheetHeader_stmt = $dbh->prepare($sheetHeader_sql);
$sheetHeader_stmt -> execute();
$sheetHeaderArray = $sheetHeader_stmt->fetchAll(PDO::FETCH_ASSOC);
$sheetHeaderList  = [];

foreach ($sheetHeaderArray as $sheetHeaderEntry) {
    array_push($sheetHeaderList, $sheetHeaderEntry["Field"]);
}
if (!in_array($queryCol, $sheetHeaderList)) {
    print_r(json_encode([
        "error"=>"Invalid parameters: Col",
        "__valid_options"=>$sheetHeaderList,
    ], JSON_UNESCAPED_UNICODE));
    return;
}
if ($isIgnoreMark) {
    $isUsingRegex = true;
    $isFuzzyQuery = true;
    $queryString = str_ireplace("*", "\*", $queryString);
    if (!is_numeric(substr($queryString, -1))) {
        $queryString = $queryString."[^a-z]";
    }
    $queryString = "(^".$queryString.")|([!?/]".$queryString.")";
//    $queryString = "!?!?(\w+/)*".$queryString."\d?[?？①-⑩]*(/\w+)*";
}

if ($isUsingRegex) {
    if ($isFuzzyQuery) {
        $inCharaSheet_sql = " SELECT * FROM `YFaanjyut` WHERE `$queryCol` REGEXP :str";
    } else {
        $inCharaSheet_sql = " SELECT * FROM `YFaanjyut` WHERE `$queryCol` REGEXP :str";
        $queryString = "^$queryString$";
    }
} else {
    if ($isFuzzyQuery) {
        $inCharaSheet_sql = " SELECT * FROM `YFaanjyut` WHERE `$queryCol` LIKE :str";
        $queryString = "%$queryString%";
    } else {
        $inCharaSheet_sql = " SELECT * FROM `YFaanjyut` WHERE `$queryCol` = :str";
    }
}

//print_r($inCharaSheet_sql);
//echo "<br>";
//print_r($queryString);
//echo "<br>";

$inCharaSheet_stmt = $dbh->prepare($inCharaSheet_sql);
$inCharaSheet_stmt -> execute([':str'=>$queryString]);
$inCharaSheetArray = $inCharaSheet_stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($sheetHeaderArray) > 100) {
    print_r(json_encode([
        "error"=>"Too many search results, consider adding search criteria.",
    ]));
    return;
}

if ($isReturnRaw) {
    print_r(($inCharaSheetArray));
} else {
    print_r(json_encode($inCharaSheetArray, (!$isReturnAscii * JSON_UNESCAPED_UNICODE) | JSON_UNESCAPED_SLASHES));
}
