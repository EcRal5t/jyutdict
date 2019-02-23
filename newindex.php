<?PHP
include ("const.php");
include ("connectDB.php");
include ("Lookup.class.php");
include_once ("Jyutping.class.php")
?>

<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-16">
    <meta http-equiv="Cache-control" content="no-cache">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>泛粵大典</title>
    <link rel="stylesheet" type="text/css" href="./css/index.css?<?PHP echo rand(); ?>">
    <link rel="icon" href="./img/favicon.png">
    <script src="./js/index.js?<?PHP echo rand(); ?>"></script>
    <script src="./js/general.js?<?PHP echo rand(); ?>"></script>
</head>

<?PHP

//$submitChara 为 提交的字符
//$options     为 勾选框选项
//        writeLog("Locate: 1, open: ".var_export($_REQUEST, true), ".");
$editMode = isset($_REQUEST['editmode']);
$options = ["wanshyu"=>0,"area"=>0,"map"=>0];
if (isset($_REQUEST['option'])) {
    foreach ($_REQUEST['option'] as $value) {
        $options[$value] = 1;
    }
} else $options = ["wanshyu"=>1,"area"=>1,"map"=>1];

if (!empty($_REQUEST['character'])) {
    $submitChara = $_REQUEST['character'];
    $submitChara = mb_substr($submitChara, 0, 1, 'utf8');
} else {
    $submitChara = "粵";
}


?>

<body onload="annexTableShell('.annex-form', 2);">

<div id="wrapper" class="wrapper">
    <div class="sidenav-overlay" onclick="hideSidenav()"></div>
    
    <div id="sidenav">
        <div id="sidenav-head"><span class="font-64">粤</span>dict</div>
        <ul id="sidenav-list">
            <li class="sidenav-link"><a href="./newindex.php">字</a></li>
            <li class="sidenav-link"><a href="./pron.php">韻</a></li>
            <li class="sidenav-link"><a>說明</a></li>
            <li class="sidenav-link"><a>泛粵表</a></li>
            <li class="divider"></li>
            <li class="sidenav-link">
                <a ><!--href="./about.php">-->關於<?PHP Info::showVersion(); ?></a>
            </li>
        </ul>
    </div>
    
    <div id="container" class="container">
    
        <button class="sidenav-show-btn" onclick="showSidenav()"></button>
        
        <div id="searching">
            <form id="inputForm" class="clearfix" method="get">
                <label><input type="text" id="inputText" class="general-bg-deeper" name="character" <?PHP echo "value=\"$submitChara\""; ?>></label>
                <input type="submit" id="inputButton" class="general-bg" value="耖">
                <?PHP if ($editMode) echo '<input type="hidden" name="editmode">';?>
                <label><input name="option[]" type="checkbox" value="wanshyu" <?PHP if ($options["wanshyu"]) echo "checked"; ?>>韻書音 </label>
                <label><input name="option[]" type="checkbox" value="area" <?PHP if ($options["area"]) echo "checked"; ?>>地方音 </label>
                <label><input name="option[]" type="checkbox" value="map" <?PHP if ($options["map"]) echo "checked"; ?>>地方音地圖 </label>
            </form>
        </div>
        
        <?PHP
        if (!empty($_GET['character'])) {
            $sim2trad = Sim2TradLookup::getInstance();          #获取简繁转换对象
            $charaArray = $sim2trad -> query($submitChara, $con);
            //var_dump($charaArray);
            $charaCount = count($charaArray);
            if ($charaCount > 2) $sim2trad -> show($charaArray);
        }
        
        if (!empty($_GET['character']) && $options["wanshyu"]){
            if ($charaCount > 2) $charaCount = 1;
            for ($i = 0; $i < $charaCount; $i++) {
                ?>
                <div id="wanshyuResult">
                    <div class="general-bg-deeper" id="charaHead">
                        <div id="charaHeadSqu"><?PHP echo "$charaArray[$i]" ?></div>
                        
                        <?PHP
                        $query_inKuangyon_sql = "
                            SELECT `initial`,`rimeclass`,`rime`,`division`,`rouding`,`tone`,`transliteration`
                            FROM `YKuangyon`
                            WHERE `chara`='" . $charaArray[$i] . "'";
                        
                        $query_inKuangyon_query = mysqli_query($con, $query_inKuangyon_sql);
                        while (is_array($query_inKuangyon_result = mysqli_fetch_row($query_inKuangyon_query))) {
                            echo '<div id="oldPronounce"><span>';
                            echo  $query_inKuangyon_result[0] . $query_inKuangyon_result[1]
                                . $query_inKuangyon_result[2] . $query_inKuangyon_result[3]
                                . $query_inKuangyon_result[4] . $query_inKuangyon_result[5]
                                . ' (' . $query_inKuangyon_result[6] . ')';
                            echo "</span></div>";
                        }
                        ?>
                    </div>
                    <div id="wanshyuResultForm" class="general-bg-deeper">
                        <?PHP
                        if (!empty($_GET['character'])){
                            $pronFanwan = FanWanDict::getInstance();
                            $pronFanwan->show($pronFanwan->query($charaArray[$i], $con));
                            ?>
                            <div style="margin-top: 13px;"></div>
                            <?PHP
                            $pronJingwaa = JingWaaDict::getInstance();
                            $pronJingwaa->show($pronJingwaa->query($charaArray[$i], $con));
                        }
                        ?>
                    </div>
                </div>
                <?PHP
                if ($options["area"]) {
                    $pronArea = LocalDictionary ::getInstance();
                    $pronArea -> show($pronArea->query($charaArray[$i], $con), $options["map"]);
                }
            }#END for ($i = 0; $i < $charaCount; $i++)
        }//end if !empty get
        ?>
    </div>
</div>
</body>

</html>