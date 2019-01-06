<!DOCTYPE html>
<?PHP
include("connectDB.php");
include("fun/writeLog.inc.php");
?>
<html>
<head><meta http-equiv="Content-Type" content="text/html; charset=utf-16">
    <meta http-equiv="Cache-control" content="no-cache">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TITLE</title>
    <link rel="stylesheet" type="text/css" href="./css/newcss.css">
    <link rel="icon" href="./img/favicon.png">
    <style type="text/css"></style>
    <script src="./js/index.js"></script>
    <script src="./js/navBarToggle.js"></script>
</head>

<body onload="initial(0);judgeHiddenNavBar();" onresize="heightModify()">

<div id="wrapper" class="wrapper" >
    <div id="leftNavBar" class="leftNavBar">
        <div id="highlightRectangle"></div>
        <ul>
            <li id="topLOGO" class="topLOGO">
                <span style="font-size: 16px; line-height: 24px;">
                    <br><br><br><span style="font-size: 50px;">粵</span> DicK<br><br><br>
                </span>
            </li>
            <li style="background: #3E6CBE; z-index: 6;">
                <a href="#topLOGO"><span>字</span></a>
            </li>
            <li style="background: #3B67B5; z-index: 5;">
                <a href="#topLOGO"><span>韻</span></a>
            </li>
            <li style="background: #3861AC; z-index: 4;">
                <a onclick="alert('<h1>不給看</h1>')"><span>旧主页</span></a>
            </li>
            <li style="background: #355CA3; z-index: 3;">
                <a href="#topLOGO"><span>丁</span></a>
            </li>
            <li style="background: #32579A; z-index: 2;">
                <a href="#topLOGO"><span>戊</span></a>
            </li>
            <li class="bottom bottom1">
                <a href="#topLOGO"><span>壬</span></a>
            </li>
            <li class="bottom bottom0">
                <a href="#topLOGO"><span>癸</span></a>
            </li>
        </ul>
    </div>

    <div id="container" class="container">
        <input type="button" value="Ξ" id="toggleLeftNavBar" onclick="toggleLeftNavBar()">
        <?PHP //设置变量 submitChara 为 提交的字符
        if (!empty($_GET['character'])) {
            $submitChara = $_GET['character'];
            $submitChara = mb_substr($submitChara, 0, 1, 'utf8');
        } else {
            $submitChara = "粵";
        }

        ?>
        <div class="searching">
            <form>
                <input type="text" id="searchingInput" class="searchingInput generalBgDeeper" name="character" <?PHP echo "value=\"$submitChara\""; ?>>
                <input type="submit" class="searchingButton generalBg" value="耖">
            </form>
        </div>


        <?PHP
        if (!empty($_GET['character'])) {
            $charaArray = array($submitChara);

            $sim2Trad_countTime_begin = microtime(true);
            $sim2Trad_getCharaId_sql = "SELECT * FROM `Character_simtrad_list` WHERE `chara`='" . $submitChara . "'";
            $sim2Trad_getCharaId_result = mysqli_fetch_row(mysqli_query($con, $sim2Trad_getCharaId_sql));

            if (is_array($sim2Trad_getCharaId_result)) {
                $sim2Trad_SimMap_sql = "SELECT * FROM `Character_simtrad_map` WHERE `chara_id_sim`=" . $sim2Trad_getCharaId_result[0];
                $sim2Trad_SimMap_query = mysqli_query($con, $sim2Trad_SimMap_sql);
                $sim2Trad_SimMap_result = mysqli_fetch_row($sim2Trad_SimMap_query);

                while (is_array($sim2Trad_SimMap_result)) {
                    $sim2Trad_getTradChara_sql = "SELECT * FROM `Character_simtrad_list` WHERE `chara_id`=" . $sim2Trad_SimMap_result[1];
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
                for ($i = 0; $i < $charaCount; $i++) { ?>
                    <div id="wanshyuResult">
                        <div class="generalBgDeeper" id="charaHead">
                            <div class="generalBg" id="charaHeadSqu"><span style="top: -10px;"><?PHP echo "$charaArray[$i]" ?></span></div>

                            <?PHP
                            $query_inKuangyon_sql = "SELECT * FROM `YKuangyon` WHERE `chara`='" . $charaArray[$i] . "'";
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
                                <div class="wanshyuResultFormHead" style="height: 81px;"><span>分 韻 </span></div>
                                <?PHP
                                $query_inFanwan_sql = "SELECT * FROM `Fanwan` WHERE `chara`='" . $charaArray[$i] . "'";
                                $query_inFanwan_query = mysqli_query($con, $query_inFanwan_sql);
                                $query_inFanwan_result = mysqli_fetch_row($query_inFanwan_query);
                                if (is_array($query_inFanwan_result)) {
                                    do {
                                        ?>
                                        <table class="generalForm" id="wanshyuResultFanwan">
                                            <tr>
                                                <td width="12.5%">序號</td>
                                                <td width="12.5%">小韻</td>
                                                <td width="37.5%">韻部</td>
                                                <td width="25%">聲-韻-調</td>
                                                <td rowspan="2">
                                                    <?PHP
                                                    if ($query_inFanwan_result[9] <> '0') echo $query_inFanwan_result[9];
                                                    echo $query_inFanwan_result[10] . $query_inFanwan_result[11];
                                                    ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><?PHP echo $query_inFanwan_result[0]; ?></td>
                                                <td><?PHP echo $query_inFanwan_result[4]; ?></td>
                                                <td><?PHP echo $query_inFanwan_result[1]; ?></td>
                                                <td>
                                                    <?PHP echo $query_inFanwan_result[6]
                                                        . '-' . $query_inFanwan_result[7]
                                                        . '-' . $query_inFanwan_result[8]; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="5"><?PHP echo $query_inFanwan_result[5]; ?></td>
                                            </tr>
                                        </table>
                                        <?PHP
                                    } while (is_array($query_inFanwan_result = mysqli_fetch_row($query_inFanwan_query)));
                                } else echo "<span style='font-size: 20px;'>冇見有</span>";
                                ?>
                            </div>
                            <div class="wanshyuResultBlock generalBgDeeper" style="margin-top: 12px;">
                                <div class="wanshyuResultFormHead" style="height: 54px;"><span>英 華</span></div>
                                <?PHP
                                $query_inJingwaa_sql = "SELECT * FROM `Jingwaa` WHERE `chara`='" . $charaArray[$i] . "'";
                                $query_inJingwaa_query = mysqli_query($con, $query_inJingwaa_sql);
                                $JingwaaArray = [];
                                for ($i = 0; is_array($query_inJingwaa_result = mysqli_fetch_row($query_inJingwaa_query)); $i++) {
                                    $JingwaaArray[$i] = $query_inJingwaa_result;
                                }
                                $JingwaaPronounCount = count($JingwaaArray);
                                if ($JingwaaPronounCount>0) {
                                    ?>
                                    <table class="generalForm" id="wanshyuResultYingwaa">
                                        <tr>
                                            <td width="12.5%">序號</td>
                                            <td width="12.5%">葉碼</td>
                                            <td width="12.5%">筆畫</td>
                                            <td width="37.5%">原文標音</td>
                                            <td rowspan="2">
                                                <?PHP
                                                for ($i=0; $i<$JingwaaPronounCount; $i++) {
                                                    if ($i>0) echo '<br>';
                                                    echo ($JingwaaArray[$i][12] ? "<i>$JingwaaArray[$i][8]</i>" : $JingwaaArray[$i][8]);
                                                    echo ( ($JingwaaArray[$i][10]<>'_NULL') ? ' ('.$JingwaaArray[$i][10].')' : '');
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <?PHP
                                                for ($i=0; $i<$JingwaaPronounCount; $i++) {
                                                    if ($i>0) echo '<br>';
                                                    echo $JingwaaArray[$i][0];
                                                }
                                                ?>
                                            </td>
                                            <td><?PHP echo $JingwaaArray[0][1]; ?></td>
                                            <td><?PHP echo $JingwaaArray[0][2]
                                                    . '(' .$JingwaaArray[0][6]
                                                    . ')+'.$JingwaaArray[0][5]; ?></td>
                                            <td>
                                                <?PHP
                                                for ($i=0; $i<$JingwaaPronounCount; $i++) {
                                                    if ($i>0) echo '<br>';
                                                    echo ($JingwaaArray[$i][12] ? "<i> $JingwaaArray[$i][9] </i>" : $JingwaaArray[$i][9]);
                                                    echo ( ($JingwaaArray[$i][11]<>'_NULL') ? ' ('.$JingwaaArray[$i][11].')' : '');
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    </table>
                                    <?PHP
                                } else {
                                    echo "<span style='font-size: 20px;'>冇見有</span>";
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
                    <table class="generalForm">
                        <?PHP
                        if ($charaCount <= 2) {
                            for ($i = 0; $i < $charaCount; $i++) {
                                $query_inCityList_sql = "SELECT * FROM `IAreaList`";
                                $query_inCityList_query = mysqli_query($con, $query_inCityList_sql);

                                echo "<tr><td style='font-size: 22px; height: 36px;' colspan='4'>$charaArray[$i]</td></tr>";

                                while (is_array($cityList = mysqli_fetch_row($query_inCityList_query))) {
                                    $query_inCity_sql = "SELECT * FROM `" . $cityList[6] . "` WHERE `chara`='" . $charaArray[$i] . "'";
                                    $query_inCity_query = mysqli_query($con, $query_inCity_sql);
                                    while (is_array($query_inCity_result = mysqli_fetch_row($query_inCity_query))) {
                                        echo "<tr><td>$cityList[3]</td>";
                                        echo "<td>$cityList[4]</td><td>$cityList[5]</td>";
                                        echo "<td>";
                                        echo "<span style='color: #C00000;'>$query_inCity_result[2]</span>";
                                        echo "<span style='color: #00C000;'>$query_inCity_result[3]</span>";
                                        echo "<span style='color: #0000C0;'>$query_inCity_result[4]</span>";
                                        echo "<span style='color: #C0C000;'>$query_inCity_result[5]</span>";
                                        echo "</td></tr>";
                                    }
                                }
                                echo "<tr><td colspan='4'>&nbsp;</td></tr>";
                            }
                        }
                        ?>
                        <tr>
                            <td>顏</td><td>色</td><td>先</td><td>不管</td>
                        </tr>
                        <tr>
                            <td>僅</td><td>用</td><td>於</td><td>測試</td>
                        </tr>

                        <tr style="font-size: 25px;">
                            <td style="color: fuchsia;">極</td><td style="color: chartreuse;">致</td><td style="color: lightskyblue;">色</td><td style="color: lightpink;">彩</td>
                        </tr>
                        <tr style="font-size: 25px;">
                            <td style="color: blueviolet;">全</td><td style="color: aquamarine;">新</td><td style="color: khaki;">體</td><td style="color: crimson;">驗</td>
                        </tr>
                    </table>
                </div>
                <div class="generalBgDeeper" id="regionalResultMap">
                    <span style="margin: auto;">放地圖</span>
                </div>
            </div>
        <?PHP
        }
        ?>
    </div>
</div>
</body>

</html>