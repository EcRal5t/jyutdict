<!DOCTYPE html>
<html>
<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">
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

        <br><br><br><br>
        <a href="./login.php">login.php</a> - <a href="./register.php">register.php</a> - <a href="./discuss.php">討論串</a>
        <?php
        if (isset($_COOKIE["login"])) echo ' - <a href="./logout.php">logout.php</a>';
        ?>
        <br><br>
        <form class="search" action="" method="get">
            <input type="text" id="searchingInputText" class="inputText" name="character" maxlength="2" style="font-size: 24px"
            <?PHP
            if (!empty($_GET['character'])) {
                $chara = $_GET['character'];
                echo "value=\"$chara\"";
            } else {
                echo "value=\"粵\"";
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
                $sqlJingwaa = "Select * from `Jingwaa` where `chara`='".$chara."'";
                $sqlFanwan = "Select * from `Fanwan` where `chara`='".$chara."'";
                $queryJingwaa = mysqli_query($con, $sqlJingwaa);
                $queryFanwan = mysqli_query($con, $sqlFanwan);
                $resultJingwaa = mysqli_fetch_row($queryJingwaa);
                $resultFanwan = mysqli_fetch_row($queryFanwan);
                echo '<span style="font-size: 5em;">'.$chara.'</span><br>';
                ?>

                <table style="width: 100%;">
                    <tr>
                        <td width="50%">
                            <span style="font-size: 2em;">分韻</span><br>
                            <?PHP
                            if (is_array($resultFanwan)) {
                                do {
                                    echo '序號: '.$resultFanwan[0].'　　小韻: '.$resultFanwan[4].'<br>';
                                    echo '韻部: '.$resultFanwan[1].' - '.$resultFanwan[2].'<br>';
                                    echo '原文註解:“ '.$resultFanwan[5].' ”<br>';
                                    echo '聲-韻-調: '.$resultFanwan[6].'-'.$resultFanwan[7].'-'.$resultFanwan[8].' ( ';
                                    if ($resultFanwan[9]<>'0') echo $resultFanwan[9];
                                    echo $resultFanwan[10].$resultFanwan[11].' )<br><br>';
                                } while (is_array($resultFanwan = mysqli_fetch_row($queryFanwan)));
                            } else {
                                echo '耖毋到';
                            }
                            ?>
                        </td>
                        <td width="50%">
                            <span style="font-size: 2em;">英華</span><br>
                            <?PHP
                            if (is_array($resultJingwaa)) {
                                echo '序號: '.$resultJingwaa[0].'　　葉碼: '.$resultJingwaa[1].'<br>';
                                echo '部首: '.$resultJingwaa[6].'　　筆畫: '.$resultJingwaa[2].'+'.$resultJingwaa[5].'<br>';
                                if ($resultJingwaa[12]==0)
								{
                                    echo '<br>原文標音（粵拼）: '.$resultJingwaa[9].' ( '.$resultJingwaa[8].' )<br>';
									$JingwaaText=$resultJingwaa[9];
								}
                                else
                                    echo '<br>原文標音（粵拼）: <i>'.$resultJingwaa[9].'</i> ( '.$resultJingwaa[8].' )<br>';
                                if ($resultJingwaa[10]<>'_NULL')
                                    echo '正文又讀: '.$resultJingwaa[11].' ( '.$resultJingwaa[10].' )<br><br>';
                                else echo '<br>';
                                if ($resultJingwaa = mysqli_fetch_row($queryJingwaa)) {
                                    if ($resultJingwaa[9]<>'_NULL')
                                        echo '又音: '.$resultJingwaa[9].' ( '.$resultJingwaa[8].' )';
                                    if ($resultJingwaa[10]<>'_NULL')
                                        echo '<br>正文又讀: '.$resultJingwaa[11].' ( '.$resultJingwaa[10].' )';
                                }
                            } else {
                                echo '耖毋到';
                            }
                            ?>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
        <?PHP
        } else {
            echo "<br><br>";
        }
        ?>
<br><br><br>
<div class="container">
    <div style="flex: 1;">　　　　</div>
    <div style="flex: 5;">
        <div class="box">
			<!--
            	作者：ZenamLeong
            	时间：2018-09-26
            	描述：一個細細嘅MAP DEMO
         -->
			<div id="Mapcontainer" style="margin:auto;height: 600px;width: 500px;"></div> 
			<script type="text/javascript">
				var amap;
			    window.init = function()
			    {
			     	amap = new AMap.Map('Mapcontainer', {
			        zoom : 8,
			        position: [114.057822,22.534797],
			        mapStyle:'amap://styles/aec282517396368ba079797e8c90a25d'
			    });
			            	<?php
    echo '// 创建纯文本标记'."\n";
    echo 'var text = new AMap.Text({'."\n";
    echo 'text:\''.$JingwaaText.'\','."\n";
    echo 'textAlign:\'center\','."\n";
    echo 'verticalAlign:\'middle\','."\n";
    echo 'cursor:\'pointer\','."\n";
    echo 'angle:0,'."\n";
    echo 'style:{'."\n";
    echo '\'padding\': \'.1rem .1rem\','."\n";
    echo '\'margin-bottom\': \'。1rem\','."\n";
    echo '\'border-radius\': \'.1rem\','."\n";
    echo '\'background-color\': \'white\','."\n";
    echo '\'width\': \'2rem\','."\n";
    echo '\'border-width\': 0,'."\n";
    echo '\'box-shadow\': \'0 2px 6px 0 rgba(114, 124, 245, .5)\','."\n";
    echo '\'text-align\': \'center\','."\n";
    echo '\'font-size\': \'16px\','."\n";
    echo '\'color\': \'blue\''."\n";
    echo '},'."\n";
    echo 'position: [114.057822,22.534797]'."\n";
    echo '});'."\n";
    echo 'text.setMap(amap);'."\n"

        	?>
			    }
			</script>
			<script src="https://webapi.amap.com/maps?v=1.4.8&key=ee088abc17a02cbebe4786e06dbd11f9&callback=init"></script>
        </div>
        </div>
    </div>
    <div style="flex: 1;">　　　　</div>
</div>
</body>
<?php
//unset();
?>
</html>