<!DOCTYPE html>
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
    <?PHP
    include("connectDB.php");
    ?>
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
        

        
        
        
        <br><br><br><br>
        其實已經有<a href="./login.php">login.php</a>同<a href="./register.php">register.php</a>同<a href="./discuss.php">討論串</a>頁面<?php
        if (isset($_COOKIE["login"])) echo '同<a href="./logout.php">logout.php</a>了';
        else echo '了';
        ?>
        <br>未做入口，因为显示有问题（不过其实不重要（
        <form class="search" action="" method="get">
            <input type="text" id="searchingInputText" class="inputText" name="character" maxlength="2" style="font-size: 24px"
            <?PHP
            if (!empty($_GET['character'])) {
                $val = $_GET['character'];
                echo "value=\"$val\"";
            } else {
                echo "value=\"測\"";
            }
            ?>>
            <input type="submit" class="inputButton" name="submit" value="耖！">


        </form>
        <br>

                <?PHP

                if (!empty($_GET['character'])) {
                    ?>
                    <div class="box">
                    <form style=" text-align: center;">
                    <?php
                    $chara = $_GET['character'];

                    $sql = "Select * from `Jingwaa` where `chara`='".$chara."'";
                    $query = mysqli_query($con, $sql);
                    if (!$query) {
                        printf("Error: %s\n", mysqli_error($con));
                        exit();
                    }
                    $rs = mysqli_fetch_row($query);
                    if (is_array($rs)) {
                        echo '<span style="font-size: 5em;">'.$rs[7].'</span><br>';
                        echo '序號: '.$rs[0].'　　　葉碼: '.$rs[1].'<br>筆畫: '.$rs[2].'　　　部首外筆畫: '.$rs[5];
                        echo '<br><br>原文標音（粵拼）: '.$rs[9].' ( '.$rs[8].' )';
                        if ($rs[10]<>'_NULL')
                            echo '<br>正文又讀: '.$rs[11].' ( '.$rs[10].' )';

                        if ($rs = mysqli_fetch_row($query)) {
                            echo '<br><br>又音: '.$rs[9].' ( '.$rs[8].' )';
                            if ($rs[10]<>'_NULL')
                                echo '<br>正文又讀: '.$rs[11].' ( '.$rs[10].' )';
                        }

                    } else echo '耖毋到: '.$chara.'。';
                    unset($rs, $_POST['pw']);
                    echo '</form></div>';


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
</html>