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
                    <span style="font-size: 50px;">粤</span> DicK
                </span>
            </li>
            <li style="background: #3E6CBE; z-index: 6;">
                <a href="#topLOGO"><span>字</span></a>
            </li>
            <li style="background: #3B67B5; z-index: 5;">
                <a href="./index.php"><span>旧主页</span></a>
            </li>
            <li style="background: #3861AC; z-index: 4;">
                <a href="#topLOGO"><span>丙</span></a>
            </li>
            <li style="background: #355CA3; z-index: 3;">
                <a href="#topLOGO"><span>丁</span></a>
            </li>
            <li style="background: #32579A; z-index: 2;">
                <a href="#topLOGO"><span>戊</span></a>
            </li>
            <li class="bottom bottom1">
                <a href="#topLOGO"><span>癸</span></a>
            </li>
            <li class="bottom bottom0">
                <a href="#topLOGO"><span>癸</span></a>
            </li>
        </ul>
    </div>

    <div id="container" class="container">
        <hr style="height: 2px; border: none;">
        <input type="button" value="Ξ" id="toggleLeftNavBar" onclick="toggleLeftNavBar()">
        <?PHP //设置变量 submitChara 为 提交的字符
        if (!empty($_GET['character'])) {
            $submitChara = $_GET['character'];
        } else {
            $submitChara = "粵";
        }
        ?>
        <div class="searching">
            <form>
                <input type="text" id="searchingInput" class="searchingInput" name="character" maxlength="2" <?PHP echo "value=\"$submitChara\""; ?>>
                <input type="submit" class="searchingButton" value="耖！">
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
                <div id="charaSimToTrad">
                    <span id="charaSimToTradHead">简转繁</span>
                    <span id="charaSimToTradMain">
                    <?PHP
                    for ($i = 2; $i < $charaCount; $i++) {
                        echo " <a href=\"index.php?character=" . $charaArray[$i] . "\">" . $charaArray[$i] . "</a>";
                    } ?>
                    </span>
                </div>
                <?PHP
            }
            ?>

            <?PHP
            if ($charaCount <= 2) {
                for ($i = 0; $i < $charaCount; $i++) { ?>
                    <div id="wanshyuResult">
                        <div id="charaHead">
                            <div id="charaHeadSqu"><span style="top: -25px;"><?PHP echo "$charaArray[$i]" ?></span>
                            </div>
                            <div id="oldPronounce">
                                <span>上上上上上上上上</span>
                            </div>
                            <div id="oldPronounce">
                                <span>中中中中中中中中</span>
                            </div>
                        </div>
                        <div id="wanshyuResultForm">
                            <div class="wanshyuResultBlock">
                                <div class="wanshyuResultFormHead"><span>分<br/>韻</span></div>
                                <?PHP
                                $query_inFanwan_sql = "SELECT * FROM `Fanwan` WHERE `chara`='" . $charaArray[$i] . "'";
                                $query_inFanwan_query = mysqli_query($con, $query_inFanwan_sql);
                                $query_inFanwan_result = mysqli_fetch_row($query_inFanwan_query);
                                if (is_array($query_inFanwan_result)) {
                                    do {
                                        ?>
                                        <table id="wanshyuResultFanwan">
                                            <tr>
                                                <td bgcolor="green" width="12.5%">序號</td>
                                                <td bgcolor="red" width="12.5%">小韻</td>
                                                <td bgcolor="blue" width="37.5%">韻部</td>
                                                <td bgcolor="cyan" width="25%">聲-韻-調</td>
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
                            <div class="wanshyuResultBlock" style="margin-top: 15px;">
                                <div class="wanshyuResultFormHead"><span>英<br/>華</span></div>
                                <?PHP
                                $query_inJingwaa_sql = "SELECT * FROM `Jingwaa` WHERE `chara`='" . $charaArray[$i] . "'";
                                $query_inJingwaa_query = mysqli_query($con, $query_inJingwaa_sql);
                                $query_inJingwaa_result = mysqli_fetch_row($query_inJingwaa_query);
                                if (is_array($query_inJingwaa_result)) {
                                    $JingwaaOne = $query_inJingwaa_result;
                                    if (is_array($query_inJingwaa_result = mysqli_fetch_row($query_inJingwaa_query))) {
                                        $JingwaaTwo = $query_inJingwaa_result;
                                    }
                                }
                                if (isset($JingwaaOne)) {
                                    ?>
                                    <table id="wanshyuResultYingwaa">
                                        <tr>
                                            <td bgcolor="green" width="12.5%">序號</td>
                                            <td bgcolor="red" width="12.5%">葉碼</td>
                                            <td bgcolor="blue" width="12.5%">筆畫</td>
                                            <td bgcolor="cyan" width="37.5%">原文標音</td>
                                            <td bgcolor="purple" rowspan="2">
                                                <?PHP
                                                if ($JingwaaOne[12]) echo '<i>';
                                                echo $JingwaaOne[8];
                                                if ($JingwaaOne[12]) echo '</i>';
                                                if ($JingwaaOne[10] <> '_NULL') {
                                                    echo ' (' . $JingwaaOne[10] . ')';
                                                }
                                                if (isset($JingwaaTwo)) {
                                                    echo "<br>";
                                                    if ($JingwaaTwo[12]) echo '<i>';
                                                    echo $JingwaaTwo[8];
                                                    if ($JingwaaTwo[12]) echo '</i>';
                                                    if ($JingwaaTwo[10] <> '_NULL') {
                                                        echo ' (' . $JingwaaTwo[10] . ')';
                                                    }
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <?PHP
                                                echo $JingwaaOne[0];
                                                if (isset($JingwaaTwo)) {
                                                    echo '<br>'.$JingwaaTwo[0];
                                                }
                                                ?>
                                            </td>
                                            <td><?PHP echo $JingwaaOne[1]; ?></td>
                                            <td><?PHP echo $JingwaaOne[2]
                                                    . '(' . $JingwaaOne[6]
                                                    . ')+' . $JingwaaOne[5]; ?></td>
                                            <td>
                                                <?PHP
                                                if ($JingwaaOne[12]) echo '<i>';
                                                echo $JingwaaOne[9];
                                                if ($JingwaaOne[12]) echo '</i>';
                                                if ($JingwaaOne[11] <> '_NULL') {
                                                    echo ' (' . $JingwaaOne[11] . ')';
                                                }
                                                if (isset($JingwaaTwo)) {
                                                    echo "<br>";
                                                    if ($JingwaaTwo[12]) echo '<i>';
                                                    echo $JingwaaTwo[9];
                                                    if ($JingwaaTwo[12]) echo '</i>';
                                                    if ($JingwaaTwo[11] <> '_NULL') {
                                                        echo ' (' . $JingwaaTwo[11] . ')';
                                                    }
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
                <div id="regionalResultForm">
                    <table>
                        <tr>
                            <td></td><td></td><td></td><td></td>
                        </tr>
                        <tr>
                            <td>廣東</td><td>廣州</td><td> - </td><td>chiaang4</td>
                        </tr>
                        <tr>
                            <td>未</td><td>加</td><td>數</td><td>據</td>
                        </tr>
                        <tr>
                            <td>衹</td><td>是</td><td>測</td><td>試</td>
                        </tr>
                        <tr style="font-size: 25px; color: fuchsia;">
                            <td>極</td><td>致</td><td>色</td><td>彩</td>
                        </tr>
                        <tr style="font-size: 25px; color: chartreuse;">
                            <td>全</td><td>新</td><td>體</td><td>驗</td>
                        </tr>
                    </table>
                </div>
                <div id="regionalResultMap">
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