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
    <link rel="stylesheet" type="text/css" href="./css/general.css?<?PHP echo rand(); ?>">
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

    <div id="container">
        
        <button class="sidenav-show-btn" onclick="showSidenav()"></button>

        <div id="searching">
            <form id="inputForm" class="clearfix" method="get">
                <input type="text" id="inputText" class="general-bg-deeper" name="character"  <?PHP echo "value=\"$submitChara\""; ?>>
                <input type="submit" id="inputButton" class="general-bg" value="耖">
                <?PHP if ($editMode) echo '<input type="hidden" name="editmode">'; ?>
            </form>
        </div>
        
        <?PHP
        if (!empty($_REQUEST['character'])) {
            $charaArray = array($submitChara);
            
            $sim2Trad_countTime_begin   = microtime(TRUE);
            $sim2Trad_getCharaId_sql    = "SELECT * FROM `Character_simtrad_list` WHERE `chara`='" . $submitChara . "'";
            $sim2Trad_getCharaId_result = mysqli_fetch_row(mysqli_query($con, $sim2Trad_getCharaId_sql));
            
            if (is_array($sim2Trad_getCharaId_result)) {
                $sim2Trad_SimMap_sql    = "SELECT * FROM `Character_simtrad_map` WHERE `chara_id_sim`=" . $sim2Trad_getCharaId_result[0];
                $sim2Trad_SimMap_query  = mysqli_query($con, $sim2Trad_SimMap_sql);
                $sim2Trad_SimMap_result = mysqli_fetch_row($sim2Trad_SimMap_query);
                
                while (is_array($sim2Trad_SimMap_result)) {
                    $sim2Trad_getTradChara_sql    = "SELECT * FROM `Character_simtrad_list` WHERE `chara_id`=" . $sim2Trad_SimMap_result[1];
                    $sim2Trad_getTradChara_result = mysqli_fetch_row(mysqli_query($con, $sim2Trad_getTradChara_sql));
                    if ($charaArray[0] <> $sim2Trad_getTradChara_result[1]) {
                        array_push($charaArray, $sim2Trad_getTradChara_result[1]);
                    }
                    $sim2Trad_SimMap_result = mysqli_fetch_row($sim2Trad_SimMap_query);
                }
            }
            
            $charaCount = count($charaArray);
            if ($charaCount > 2) { ?>
                <div class="generalBgDeeper" id="charaSimToTrad">
                    <span id="charaSimToTradHead">简转繁</span>
                    <span id="charaSimToTradMain">
                    <?PHP
                    for ($i = 1; $i < $charaCount; $i++) {
                        echo " <a href=\"newindex.php?character=" . $charaArray[$i] . "\">" . $charaArray[$i] . "</a>";
                    } ?>
                    </span>
                </div>
                <?PHP
            }
            ?>
            
            <?PHP
            if ($charaCount > 2) $charaCount = 1;
            if ($charaCount <= 2) {
                for ($i = 0; $i < $charaCount; $i++) {
                    ?>

                    <div id="wanshyuResult">
                        <div class="generalBgDeeper" id="charaHead">
                            <div class="generalBg" id="charaHeadSqu"><span style="top: -10px;"><?PHP echo "$charaArray[$i]" ?></span></div>
                            
                            <?PHP
                            $query_inKuangyon_sql   = "SELECT * FROM `YKuangyon` WHERE `chara`='" . $charaArray[$i] . "'";
                            $query_inKuangyon_query = mysqli_query($con, $query_inKuangyon_sql);
                            while (is_array($query_inKuangyon_result = mysqli_fetch_row($query_inKuangyon_query))) {
                                echo '<div id="oldPronounce"><span>';
                                echo '<span style="background: gray; color: white;">中</span>'
                                    .$query_inKuangyon_result[2].$query_inKuangyon_result[3].$query_inKuangyon_result[4]
                                    .$query_inKuangyon_result[5].$query_inKuangyon_result[6].$query_inKuangyon_result[7]
                                    .' ('.$query_inKuangyon_result[8].')';
                                echo "</span></div>";
                            }
                            ?>

                        </div>
                        <div id="wanshyuResultForm">
                            <div class="wanshyuResultBlock generalBgDeeper" style="margin-bottom: 3px;">
                                <div class="wanshyuResultFormHead" style="height: 81px;"><span>分<br>韻</span></div>
                                <?PHP
                                $query_inFanwan_sql    = "SELECT * FROM `Fanwan` WHERE `chara`='" . $charaArray[$i] . "'";
                                $query_inFanwan_query  = mysqli_query($con, $query_inFanwan_sql);
                                $query_inFanwan_result = mysqli_fetch_row($query_inFanwan_query);
                                if (is_array($query_inFanwan_result)) {
                                    do {
                                        ?>

                                        <table class="general-form" id="wanshyuResultFanwan">
                                            <tr>
                                                <td class="column2-20">序號</td>
                                                <td class="column10-20">韻部 - 小韻</td>
                                                <td class="column5-20">聲-韻-調</td>
                                                <td rowspan="2" class="hlFontRed">
                                                    <?PHP
                                                    if ($query_inFanwan_result[9] <> '0') echo $query_inFanwan_result[9];
                                                    echo $query_inFanwan_result[10] . $query_inFanwan_result[11];
                                                    ?>

                                                </td>
                                            </tr>
                                            <tr>
                                                <td><?PHP echo $query_inFanwan_result[0]; ?></td>
                                                <td><?PHP echo $query_inFanwan_result[1].'-'.$query_inFanwan_result[4]; ?></td>
                                                <td><?PHP echo $query_inFanwan_result[6]
                                                        . '-' . $query_inFanwan_result[7]
                                                        . '-' . $query_inFanwan_result[8]; ?>

                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="4"><?PHP echo $query_inFanwan_result[5]; ?></td>
                                            </tr>
                                        </table>
                                        <?PHP
                                    } while (is_array($query_inFanwan_result = mysqli_fetch_row($query_inFanwan_query)));
                                } else echo "<span style='font-size: 20px;'>冇見有</span>";
                                ?>
                            </div>
                            <div class="wanshyuResultBlock generalBgDeeper" style="margin-top: 12px;">
                                <div class="wanshyuResultFormHead" style="height: 54px;"><span>英<br>華</span></div>
                                <?PHP
                                $query_inJingwaa_sql   = "SELECT * FROM `Jingwaa` WHERE `chara`='" . $charaArray[$i] . "'";
                                $query_inJingwaa_query = mysqli_query($con, $query_inJingwaa_sql);
                                $JingwaaArray          = [];
                                for ($j = 0; is_array($query_inJingwaa_result = mysqli_fetch_row($query_inJingwaa_query)); $j++) {
                                    $JingwaaArray[$j] = $query_inJingwaa_result;
                                }
                                $JingwaaPronounCount = count($JingwaaArray);
                                if ($JingwaaPronounCount > 0) {
                                    ?>

                                    <table class="general-form" id="wanshyuResultYingwaa">
                                        <tr>
                                            <td width="12.5%">序號</td>
                                            <td width="12.5%">葉碼</td>
                                            <td width="25%">筆畫</td>
                                            <td width="25%">原標音</td>
                                            <td rowspan="2" class="hlFontRed">
                                                <?PHP
                                                for ($j=0; $j<$JingwaaPronounCount; $j++) {
                                                    if ($j>0) echo '<br>';
                                                    echo ($JingwaaArray[$j][12] ? "<i>$JingwaaArray[$j][8]</i>" : $JingwaaArray[$j][8]);
                                                    echo ( ($JingwaaArray[$j][10]<>'_NULL') ? ' ('.$JingwaaArray[$j][10].')' : '');
                                                }
                                                ?>

                                            </td>
                                        </tr>
                                        <tr>
                                            <td><?PHP
                                                for ($j=0; $j<$JingwaaPronounCount; $j++) {
                                                    if ($j>0) echo '<br>';
                                                    echo $JingwaaArray[$j][0];
                                                }
                                                ?>

                                            </td>
                                            <td><?PHP echo $JingwaaArray[0][1]; ?></td>
                                            <td><?PHP echo $JingwaaArray[0][2]
                                                    . '(' .$JingwaaArray[0][6]
                                                    . ')+'.$JingwaaArray[0][5]; ?></td>
                                            <td>
                                                <?PHP
                                                for ($j=0; $j<$JingwaaPronounCount; $j++) {
                                                    if ($j>0) echo '<br>';
                                                    echo ($JingwaaArray[$j][12] ? "<i> $JingwaaArray[$j][9] </i>" : $JingwaaArray[$j][9]);
                                                    echo ( ($JingwaaArray[$j][11]<>'_NULL') ? ' ('.$JingwaaArray[$j][11].')' : '');
                                                }
                                                ?>

                                            </td>
                                        </tr>
                                    </table>
                                    <?PHP
                                } else {
                                    ?>
                                    <span style='font-size: 20px;'>冇見有</span>
                                    <?PHP
                                }
                                ?>

                            </div>
                        </div>
                    </div>
                    <?PHP
                }
            }
            ?>
            <div id="regionalResult">
                <div class="generalBgDeeper" id="regionalResultForm">
                    <table id="regionalResultTable" class="general-form">
                        <?PHP
                        if ($charaCount <= 2) {
                            for ($i = 0; $i < $charaCount; $i++) {
                                $query_inCityList_sql   = "SELECT * FROM `IAreaList`";
                                $query_inCityList_query = mysqli_query($con, $query_inCityList_sql);
                                ?>

                                <tr>
                                    <td style='font-size: 22px; height: 36px;' colspan='4'><?PHP echo $charaArray[$i]; ?></td>
                                </tr>
                                <?PHP
                                while (is_array($cityList = mysqli_fetch_row($query_inCityList_query))) {
                                    $query_inCity_sql   = "SELECT * FROM `" . $cityList[6] . "` WHERE `chara`='" . $charaArray[$i] . "'";
                                    $query_inCity_query = mysqli_query($con, $query_inCity_sql);
                                    while (is_array($inCityPron = mysqli_fetch_row($query_inCity_query))) {
                                        ?>

                                        <tr>
                                            <td class="locOneth column4-20 min-width60"><?PHP echo $cityList[3]; ?></td>
                                            <td class="locThird column3-20 min-width45" style="width: 15%"><?PHP echo $cityList[5]; ?></td>
                                            <td class="column6-20">
                                                <?PHP
                                                echo '<span class="hlFontRed">' . $inCityPron[2] . '</span>';
                                                echo '<span class="hlFontGreen">' . $inCityPron[3] . '</span>';
                                                echo '<span class="hlFontBlue">' . $inCityPron[4] . '</span>';
                                                echo '<span class="hlFontYellow">' . $inCityPron[5] . '</span>';
                                                echo '<span class="hlFontCyan ipa">  /' . $inCityPron[6] . '/</span>';
                                                ?>
                                            </td>
                                            <td class="tips"> <?PHP
                                                if ($editMode) {
                                                    echo "<span style='color: gray; font-size: 9px; float:left;' onclick='editPron([";
                                                    echo "\"$inCityPron[2]\", \"$inCityPron[3]\", \"$inCityPron[4]\", \"$inCityPron[5]\", ";
                                                    echo "\"" . substr($cityList[6], 1) . "\", \"$inCityPron[0]\", \"$inCityPron[6]\"";
                                                    echo "])'>改音</span>";
                                                }
                                                
                                                if (mb_strlen($inCityPron[7],'UTF8') > 5) {
                                                    echo mb_substr($inCityPron[7], 0, 4, 'utf8') . "…";
                                                    echo "<span class='tipsMain'>$inCityPron[7]</span>";
                                                } else
                                                    echo $inCityPron[7];
    
//                                                if ($editMode) {
//                                                    echo "<span style='color: gray; font-size: 9px; float:left;' onclick='editPronNote([";
//                                                    echo "\"" . substr($cityList[6], 1) . "\", \"$inCityPron[0]\", \"$inCityPron[7]\"";
//                                                    echo "])'>改註</span>";
//                                                }
                                                ?>

                                            </td>
                                        </tr>
                                        <?PHP
                                    }
                                }
                                echo "<tr><td colspan='4' class='locOneth locThird'>&nbsp;</td></tr>";
                            }
                        }
                        ?>

                    </table>
                </div>
                <div class="generalBgDeeper" id="regionalResultMap">
                    <span style="margin: auto;">地圖</span>
                </div>
            </div>
            <?PHP
        }
        ?>
    </div>
</div>
</body>

</html>