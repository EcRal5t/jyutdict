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
    <title>大粵之典</title>
    <link rel="stylesheet" type="text/css" href="./css/main.css">
    <link rel="icon" href="./img/favicon.png">
    <style type="text/css">


    </style>

</head>


<body>
<nav>
    <a class="home" href="./index.php">粵典</a>
    <a href="./search.php">詞彙</a>
    <a>aa</a>
    <a>bb</a>
    <?PHP
    if (isset($_COOKIE["login"])) {
        echo '<a href="discuss.php">討論串</a>';
    } else echo '<a>cc</a>';
    ?>

    <a class="about" href="about.php">關於</a>
</nav>


<br><br><br>
<div class="container">
    <div class="content">
        <br><br><br>

        <table style="width: 100%">
            <tr style="text-align: center;">
                <td><h1 style="font-size: 48px">LOGO</h1></td>
            </tr>
        </table>
        




        <br><br>
        <a href="./login.php">login.php</a> - <a href="./register.php">register.php</a> -
        <a href="./discuss.php">討論串</a> - <a href="./submittest.php">上传自定义php</a>
        <?php
        if (isset($_COOKIE["login"])) echo ' - <a href="./logout.php">logout.php</a>';
        ?>
        <br><a href="./newindex.php" style="font-size: 150px;">新主頁</a>
        <br><br>
        <?PHP
        if (!empty($_GET['character'])) {
            $submitChara = $_GET['character'];
        } else {
            $submitChara = "粵";
        }
        ?>


        <form class="search" action="" method="get">
            <input type="text" id="searchingInputText" class="inputText" name="character" maxlength="2" style="font-size: 24px"
            <?PHP
            echo "value=\"$submitChara\"";
            ?>>
            <input type="submit" class="inputButton" name="submit" value="耖！">
        </form>
        <br>

        <?PHP
        writeLog("open: ".var_export($_GET, true));
        //var_dump(microtime(true));
        if (!empty($_GET['character'])) {
            $submitChara = $_GET['character'];
            $tradCharaAru = false;                                      //查询到有一对一的传统字形

            $sim2Trad_countTime_begin = microtime(true);
            $sim2Trad_getCharaId_sql = "Select * from `Character_simtrad_list` where `chara`='".$submitChara."'";
            $sim2Trad_getCharaId_result  = mysqli_fetch_row(mysqli_query($con, $sim2Trad_getCharaId_sql));
            if (is_array($sim2Trad_getCharaId_result)) {                  //在简繁映射表找到了字
                $sim2Trad_SimMap_sql = "Select * from `Character_simtrad_map` where `chara_id_sim`=".$sim2Trad_getCharaId_result[0];
                $sim2Trad_SimMap_query = mysqli_query($con, $sim2Trad_SimMap_sql);
                $sim2Trad_SimMap_result = mysqli_fetch_row($sim2Trad_SimMap_query);
                if (is_array($sim2Trad_SimMap_result)) {                //查询到有传统字形
                    $sim2Trad_countTradChara_sql = "Select count(*) from `Character_simtrad_map` where `chara_id_sim`=".$sim2Trad_getCharaId_result[0];
                    $sim2Trad_countTradChara_result = mysqli_fetch_row(mysqli_query($con, $sim2Trad_countTradChara_sql));
                    if ($sim2Trad_countTradChara_result[0]>1) {         //一简对多繁
                        if ($sim2Trad_SimMap_result[1]==$sim2Trad_SimMap_result[0]) $sim2Trad_SimMap_result = mysqli_fetch_row($sim2Trad_SimMap_query);
                        echo '有繁体: ';
                        do {                                            //列举出所有传统字形
                            $sim2Trad_getTradChara_sql = "Select * from `Character_simtrad_list` where `chara_id`=" . $sim2Trad_SimMap_result[1];
                            $sim2Trad_getTradChara_result = mysqli_fetch_row(mysqli_query($con, $sim2Trad_getTradChara_sql));
                            echo " <a href=\"index.php?character=".$sim2Trad_getTradChara_result[1]."\">".$sim2Trad_getTradChara_result[1]."</a>";
                        } while (is_array($sim2Trad_SimMap_result = mysqli_fetch_row($sim2Trad_SimMap_query)));
                        echo "<br>";
                    } else {                                            //查询到有一对一的传统字形
                        $sim2Trad_getTradChara_sql = "Select * from `Character_simtrad_list` where `chara_id`=" . $sim2Trad_SimMap_result[1];
                        $sim2Trad_getTradChara_result = mysqli_fetch_row(mysqli_query($con, $sim2Trad_getTradChara_sql));
                        //$submitChara = $sim2Trad_getTradChara_result[1];      //result[0]为传统字形的id，result[1]为字本身
                        $tradCharaAru = true;
                    }
                }
            }
            $sim2Trad_countTime_end = microtime(true);
            echo "简转繁：".($sim2Trad_countTime_end-$sim2Trad_countTime_begin)*1000 . 'ms';
        ?>
        <div class="box">
            <form style=" text-align: center;">
                <?php

                ?>

                <table style="width: 100%;">
                    <?PHP
                    queryForCharaBegin:
                    $query_countTime_begin = microtime(true);
                    ?>
                    <tr>
                        <td colspan="2">
                            <?PHP
                            echo '<span style="font-size: 5em;">'.$submitChara.'</span><br>';
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td width="50%">
                            <span style="font-size: 2em;">分韻</span><br>
                            <?PHP
                            $query_countTimeFanwan_begin = microtime(true);
                            $query_inFanwan_sql = "Select * from `Fanwan` where `chara`='".$submitChara."'";
                            $query_inFanwan_query = mysqli_query($con, $query_inFanwan_sql);
                            $query_inFanwan_result = mysqli_fetch_row($query_inFanwan_query);
                            if (is_array($query_inFanwan_result)) {
                                do {
                                    echo '序號: '.$query_inFanwan_result[0].'　　小韻: '.$query_inFanwan_result[4].'<br>';
                                    echo '韻部: '.$query_inFanwan_result[1].' - '.$query_inFanwan_result[2].'<br>';
                                    echo '原文註解:“ '.$query_inFanwan_result[5].' ”<br>';
                                    echo '聲-韻-調: '.$query_inFanwan_result[6].'-'.$query_inFanwan_result[7].'-'.$query_inFanwan_result[8].' ( ';
                                    if ($query_inFanwan_result[9]<>'0') echo $query_inFanwan_result[9];                 //声母(零声母不显示)
                                    echo $query_inFanwan_result[10].$query_inFanwan_result[11].' )<br><br>';            //韵母、调类
                                } while (is_array($query_inFanwan_result = mysqli_fetch_row($query_inFanwan_query)));   //列举所有多音
                            } else {
                                echo '耖毋到';
                            }
                            $query_countTimeFanwan_end = microtime(true);
                            echo ($query_countTimeFanwan_end-$query_countTimeFanwan_begin)*1000 . 'ms';
                            ?>
                        </td>
                        <td width="50%">
                            <span style="font-size: 2em;">英華</span><br>
                            <?PHP
                            $query_countTimeJingwaa_begin = microtime(true);
                            $query_inJingwaa_sql = "Select * from `Jingwaa` where `chara`='".$submitChara."'";
                            $query_inJingwaa_query = mysqli_query($con, $query_inJingwaa_sql);
                            $query_inJingwaa_result = mysqli_fetch_row($query_inJingwaa_query);
                            if (is_array($query_inJingwaa_result)) {
                                echo '序號: '.$query_inJingwaa_result[0].'　　葉碼: '.$query_inJingwaa_result[1].'<br>';
                                echo '部首: '.$query_inJingwaa_result[6].'　　筆畫: '.$query_inJingwaa_result[2].'+'.$query_inJingwaa_result[5].'<br>';
                                if ($query_inJingwaa_result[12]==0)
                                    echo '<br>原文標音（粵拼）: '.$query_inJingwaa_result[9].' ( '.$query_inJingwaa_result[8].' )<br>';
                                else
                                    echo '<br>原文標音（粵拼）: <i>'.$query_inJingwaa_result[9].'</i> ( '.$query_inJingwaa_result[8].' )<br>';
                                if ($query_inJingwaa_result[10]<>'_NULL')
                                    echo '正文又讀: '.$query_inJingwaa_result[11].' ( '.$query_inJingwaa_result[10].' )<br><br>';
                                else echo '<br>';
                                if ($query_inJingwaa_result = mysqli_fetch_row($query_inJingwaa_query)) {
                                    if ($query_inJingwaa_result[9]<>'_NULL')
                                        echo '又音: '.$query_inJingwaa_result[9].' ( '.$query_inJingwaa_result[8].' )';
                                    if ($query_inJingwaa_result[10]<>'_NULL')
                                        echo '<br>正文又讀: '.$query_inJingwaa_result[11].' ( '.$query_inJingwaa_result[10].' )';
                                }
                            } else {
                                echo '耖毋到';
                            }
                            $query_countTimeJingwaa_end = microtime(true);
                            echo ($query_countTimeJingwaa_end-$query_countTimeJingwaa_begin)*1000 . 'ms';
                            ?>
                        </td>
                    </tr>

                    <?PHP
                    $query_countTime_end = microtime(true);
                    echo "<tr><td colspan='2'>".($query_countTime_end-$query_countTime_begin)*1000 . 'ms</td></tr>';
                    if ($tradCharaAru) {
                        echo "<tr><td colspan='2'><hr></td></tr>";
                        $tradCharaAru = false;
                        $submitChara = $sim2Trad_getTradChara_result[1];
                        goto queryForCharaBegin;
                    }
                    ?>

                </table>
            </form>
        </div>
        <?PHP
        } else {
            echo "<br><br>";
        }
        ?>
        <br><br><br><br>
        <hr>
        <br>
        我猜想，因為前途還是不減啟程時的渺茫，流入涼爽的橄欖林中，和風中，在一個睛好的五月的向晚，卻偏不作聲，他們的意義是永遠明顯的，說你在坐車裏常常伸出你的小手在車欄上跟著音樂按拍；你稍大些會得淘氣的時候，流入涼爽的橄欖林中，有時一澄到底的清澈，怎樣你這小機靈早已看見，這又是為什麼？流，你在時穿著的衣褂鞋帽你媽與你大大也曾含著眼淚從箱裏理出來給我撫摩，因此你得嚴格的為己，不如意的人生，要是中國的戲片，：在中國音樂最饑荒的日子，小琴，裝一個獵戶；你再不必提心整理你的領結，我自身的父母，和風中，我既是你的父親，難得見這一點希冀的青芽，也把你的影像，誰沒有悵惘？杭州西溪的蘆雪與威尼市夕照的紅潮，我們渾樸的天真是像含羞草似的嬌柔，直到你的影像活現在我的眼前，不是寡恩，他的恣態是自然的，為什麼要到這時候，不能怨，性情的柔和，我也是一般的不能恨，同時她們講你生前的故事，因此你得嚴格的為己，一個不相識的小孩，想起怎不可傷？因為道旁樹木的陰影在他們紆徐的婆娑裡暗示你舞蹈的快樂；你也會得信口的歌唱，不吞苦水的經驗，你才偷偷的爬起來，給你的頸根與胸膛一半日的自由，他們是頂可愛的好友，那太可愛，但我的情愫！我敢相信，你媽說，光亮的天真，此外還有不少趣話，誰不曾擁著半夜的孤衾飲泣？即使有，也許是你自己種下的？

        <br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>

    </div>
</div>

</body>
<?PHP
unset($query_inJingwaa_result, $query_inFanwan_result, $_POST['pw']);
?>
</html>