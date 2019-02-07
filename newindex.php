<!DOCTYPE html>
<?PHP
include("connectDB.php");
include("fun/writeLog.inc.php");

//设置变量 submitChara 为 提交的字符
//        writeLog("Locate: 1, open: ".var_export($_REQUEST, true), ".");
$editMode = isset($_REQUEST['editmode']);
if (!empty($_REQUEST['character'])) {
    $submitChara = $_REQUEST['character'];
    $submitChara = mb_substr($submitChara, 0, 1, 'utf8');
} else {
    $submitChara = "粵";
}
?>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-16">
    <meta http-equiv="Cache-control" content="no-cache">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TITLE</title>
    <link rel="stylesheet" type="text/css" href="./css/index.css?<?PHP echo rand(); ?>">
    <link rel="icon" href="./img/favicon.png">
    <script src="./js/index.js?<?PHP echo rand(); ?>"></script>
    <script src="./js/general.js"></script>
</head>

<body <?PHP if (!empty($_REQUEST['character'])) echo "onload=\"annexForm('#regionalResultTable', 1)\""; ?>>

<div id="wrapper" class="wrapper">
    <div class="sidenav-overlay" onclick="hideSidenav()"></div>
    
    <div id="sidenav">
        <div id="sidenav-head"><span class="font-64">粤</span>dict</div>
        <ul id="sidenav-list">
            <li class="sidenav-link"><a href="./newindex.php">字</a></li>
            <li class="sidenav-link"><a href="./pron.php">韻</a></li>
            <li class="sidenav-link"><a href="newindex.php">放著先</a></li>
            <li class="divider"></li>
            <li class="sidenav-link"><a ><!--href="./about.php">-->關於</a></li>
        </ul>
    </div>
    
    <div id="container" class="container">
    
        <button class="sidenav-show-btn" onclick="showSidenav()"></button>
    
        <div id="searching">
            <form id="inputForm" class="clearfix" method="get">
                <input type="text" id="inputText" class="general-bg-deeper" name="character"  <?PHP echo "value=\"$submitChara\""; ?>>
                <input type="submit" id="inputButton" class="general-bg" value="耖">
                <?PHP if ($editMode) echo '<input type="hidden" name="editmode">'; ?>
            </form>
        </div>
        
        <?PHP
        include ("Lookup.class.php");
        if (!empty($_GET['character'])) {
            $sim2trad = Sim2TradLookup::getInstance();          #获取简繁转换对象
            $charaArray = $sim2trad -> query($submitChara, $con);
            #var_dump($charaArray);
            $charaCount = count($charaArray);
            if ($charaCount > 2) $sim2trad -> show($charaArray);
        }
        
        if (!empty($_GET['character'])){
            if ($charaCount > 2) $charaCount = 1;
            for ($i = 0; $i < $charaCount; $i++) {
                ?>
                <div id="wanshyuResult">
                    <div class="generalBgDeeper" id="charaHead">
                        <div class="generalBg" id="charaHeadSqu"><span style="top: -10px;"><?PHP echo "$charaArray[$i]" ?></span></div>
                        
                        <?PHP
                        $query_inKuangyon_sql = "SELECT initial,rimeclass,
                                          rime,division,rouding,tone,
                                          transliteration
                                          FROM `YKuangyon` WHERE `chara`='" . $charaArray[$i] . "'";
                        
                        $query_inKuangyon_query = mysqli_query($con, $query_inKuangyon_sql);
                        while (is_array($query_inKuangyon_result = mysqli_fetch_row($query_inKuangyon_query))) {
                            echo '<div id="oldPronounce"><span>';
                            echo '<span style="background: gray; color: white;">中</span>'
                                . $query_inKuangyon_result[0] . $query_inKuangyon_result[1]
                                . $query_inKuangyon_result[2] . $query_inKuangyon_result[3]
                                . $query_inKuangyon_result[4] . $query_inKuangyon_result[5]
                                . ' (' . $query_inKuangyon_result[6] . ')';
                            echo "</span></div>";
                        }
                        ?>
                    </div>
                    <div id="wanshyuResultForm">
                        <div class="wanshyuResultBlock generalBgDeeper" style="margin-bottom: 3px;">
                            <div class="wanshyuResultFormHead" style="height: 81px;"><span>分韻</span></div>
                            <?PHP
                            $fanWan = FanWanDict::getInstance();
                            $fanWan->show($fanWan->query($charaArray[$i], $con));
                            ?>
                        </div>
                        <div class="wanshyuResultBlock generalBgDeeper" style="margin-top: 12px;">
                            <div class="wanshyuResultFormHead" style="height: 54px;"><span>英<br>華</span></div>
                            <?PHP
                            if (!empty($_GET['character'])){
                                $jing = JingWaaDict::getInstance();
                                $jing->show($jing->query($charaArray[$i], $con));
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <?PHP
                $test = LocalDictionary::getInstance();
                $test->show($test->query($charaArray[$i], $con));
            }#END for ($i = 0; $i < $charaCount; $i++)
        }//end if !empty get
        ?>
    </div>
</div>
</body>

</html>