<?PHP
include ("const.php");
include_once ("connectDB.php");
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
    <link rel="icon" href="./img/favicon.ico">
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
    <?PHP Info::showSidenav(); ?>
    
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
            $sim2trad = Sim2TradLookup::getInstance();      #获取简繁转换对象
            $charaArray = $sim2trad -> query($submitChara, $dbh);
            $charaCount = count($charaArray);
            if ($charaCount > 2) {
                $sim2trad -> show($charaArray);
                $charaCount = 1;
            }
            
            if ($options["wanshyu"]) {                      #prepared statement
                $inKuangyon_sql = "
                    SELECT `initial`,`rimeclass`,`rime`,`division`,`rounding`,`tone`,`transliteration`
                    FROM `YKuangyon`
                    WHERE `chara`=:chara";
                $inKuangyon_stmt = $dbh->prepare($inKuangyon_sql);
            }
            
            for ($i = 0; $i < $charaCount; $i++) {
                if ($options["wanshyu"]) {
                    ?>
                    <div id="wanshyuResult">
                        <div class="general-bg-deeper" id="charaHead">
                            <div id="charaHeadSqu"><?PHP echo "$charaArray[$i]" ?></div>
                            <?PHP
                            $inKuangyon_stmt->execute(array(':chara'=>$charaArray[$i]));
                            $inKuangyon_result = $inKuangyon_stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($inKuangyon_result as $inKuangyon_items) {
                                echo '<div id="oldPronounce"><span>';
                                echo $inKuangyon_items['initial'] . $inKuangyon_items['rimeclass'] . $inKuangyon_items['rime'];
                                echo $inKuangyon_items['division'] . $inKuangyon_items['rounding'] . $inKuangyon_items['tone'];
                                echo ' (' . $inKuangyon_items['transliteration'] . ')';
                                echo "</span></div>";
                            }
                            ?>
                        </div>
                        <div id="wanshyuResultForm" class="general-bg-deeper">
                            <?PHP
                            if (!empty($_GET['character'])) {
                                $pronFanwan = FanWanDict ::getInstance();
                                $pronFanwan -> show($pronFanwan -> query($charaArray[$i], $dbh));
                                ?>
                                <div style="margin-top: 13px;"></div>
                                <?PHP
                                $pronJingwaa = JingWaaDict ::getInstance();
                                $pronJingwaa -> show($pronJingwaa -> query($charaArray[$i], $dbh));
                            }
                            ?>
                        </div>
                    </div>
                    <?PHP
                }
                if ($options["area"]) {
                    $pronArea = LocalDictionary ::getInstance();
                    $pronArea -> show($pronArea->query($charaArray[$i], $dbh), $options["map"]);
                }
            }#END for ($i = 0; $i < $charaCount; $i++)
        }//end if !empty get
        ?>
    </div>
</div>
</body>

</html>