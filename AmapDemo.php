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
    <?PHP
	class Mapdata		#一个Mapdata类
	{
		protected $location;
		protected $pronunciation= array(); #存储发音的数组
		protected $n;
		function name($name)		#一定要有的名字
		{
			$this->n=$name;
		}
		function Locate($location)  #地理坐标用的是高德坐标传入格式是字符串即("[经度,纬度]")
		{
			$this->location = $location;
		}
		function Faatjam($jam)		#直接把发音传进
		{
			array_push($this->pronunciation,$jam);//发音压入数组
		}
		function Mapshow()
		{
			echo 'var marker'.$this->n.' = new AMap.Marker({'."\n";
			echo 'content : "<div></div>",'."\n";
			echo 'position:'.$this->location."\n";
			echo '});'."\n";
			echo 'marker'.$this->n.'.setMap(amap);'."\n";
			echo 'marker'.$this->n.'.setLabel({'."\n";
			echo 'offset: new AMap.Pixel(2,2),'."\n";
			echo 'content:"<div class=\'container\'>';	#此处遍历输出读音 可以改里面那个DIV改标签样式
			foreach($this->pronunciation as $value)
			{
				echo $value."<br>";
			}
			echo '</div>"'."\n".'});'."\n";
		}
	}
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
        

        
        
        
        <br><br>
        <a href="./login.php">login.php</a> - <a href="./register.php">register.php</a> - <a href="./discuss.php">討論串</a>
        <?php
        if (isset($_COOKIE["login"])) echo ' - <a href="./logout.php">logout.php</a>';
        ?>
        <br><br>
        <form class="search" action="" method="get">
            <input type="text" id="searchingInputText" class="inputText" name="character" maxlength="2" style="font-size: 24px"
            <?PHP
            if (!empty($_GET['character'])) {
                $chara = $_GET['character'];		//此处POST请求
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
                            <?PHP	#2018-10-24-Zenam-添加地图方法
                            if (is_array($resultFanwan)) {
                            	$mapFanwan = new Mapdata;
								$mapFanwan->name("fanwan");
								$mapFanwan->Locate("[114.287836,22.483897]");
                                do {
                                    echo '序號: '.$resultFanwan[0].'　　小韻: '.$resultFanwan[4].'<br>';
                                    echo '韻部: '.$resultFanwan[1].' - '.$resultFanwan[2].'<br>';
                                    echo '原文註解:“ '.$resultFanwan[5].' ”<br>';
                                    echo '聲-韻-調: '.$resultFanwan[6].'-'.$resultFanwan[7].'-'.$resultFanwan[8].' ( ';
                                    if ($resultFanwan[9]<>'0') #应该是0声母？
                                    {
                                    	echo $resultFanwan[9];
                                    	$mapFanwan->Faatjam($resultFanwan[9].$resultFanwan[10].$resultFanwan[11]); #存储发音一次压入
									}else
										{
											$mapFanwan->Faatjam($resultFanwan[10].$resultFanwan[11]); #存储发音一次压入 此处是不压入零声母的0
										}
                                    echo $resultFanwan[10].$resultFanwan[11].' )<br><br>';
									
                                } while (is_array($resultFanwan = mysqli_fetch_row($queryFanwan)));
                            } else {
                                echo '耖毋到';
                            }
                            ?>
                        </td>
                        <td width="50%">
                            <span style="font-size: 2em;">英華</span><br>
                            <?PHP	#2018-10-24-Zenam-添加地图方法
                            if (is_array($resultJingwaa)) {
                            	$mapJingwaa = new Mapdata;							#实例化数组真的要有数指才实例化啊
								$mapJingwaa->name("Jingwaa");						#名字必须用来输出JS用			
                            	$mapJingwaa->Locate("[114.019186,22.583787]");		#地图坐标必须
                                echo '序號: '.$resultJingwaa[0].'　　葉碼: '.$resultJingwaa[1].'<br>';
                                echo '部首: '.$resultJingwaa[6].'　　筆畫: '.$resultJingwaa[2].'+'.$resultJingwaa[5].'<br>';
                                if ($resultJingwaa[12]==0)
                                {
                                    echo '<br>原文標音（粵拼）: '.$resultJingwaa[9].' ( '.$resultJingwaa[8].' )<br>';
	                                $mapJingwaa->Faatjam($resultJingwaa[8]);		#存储发音
								}else
									{
                                    	echo '<br>原文標音（粵拼）: <i>'.$resultJingwaa[9].'</i> ( '.$resultJingwaa[8].' )<br>';
										$mapJingwaa->Faatjam($resultJingwaa[8]);		#存储发音
									}
                                if ($resultJingwaa[10]<>'_NULL')
                                {
                                    echo '正文又讀: '.$resultJingwaa[11].' ( '.$resultJingwaa[10].' )<br><br>';
									$mapJingwaa->Faatjam($resultJingwaa[10]);		#存储发音
								}else
									{
										echo '<br>';
									}
                                if (($resultJingwaa = mysqli_fetch_row($queryJingwaa)) != FALSE) {
                                    if ($resultJingwaa[9]<>'_NULL')
                                    {
                                        echo '又音: '.$resultJingwaa[9].' ( '.$resultJingwaa[8].' )';
										$mapJingwaa->Faatjam($resultJingwaa[8]);		#存储发音
									}
                                    if ($resultJingwaa[10]<>'_NULL')
                                    {
                                        echo '<br>正文又讀: '.$resultJingwaa[11].' ( '.$resultJingwaa[10].' )';
										$mapJingwaa->Faatjam($resultJingwaa[10]);		#存储发音
									}
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
        <br><br><br><br>
			<!--
            	作者：ZenamLeong
            	时间：2018-09-26	
            	描述：一個細細嘅MAP DEMO
            	2018-10-19
            	修改了TextMarker的Echo方法 实现遍历输出文本标记
            	2018-10-24 用MARKER方法实现了加点 并处理了多音字显示问题加入了默认未查询的时候的状态
         -->
		<div id="Mapcontainer" style="height: 600px;width: 500px;margin: 0 auto;box-shadow: 10px 10px 5px #888888;"></div> 
		<script type="text/javascript">
			var amap;
		    window.init = function()
		    {
		     	amap = new AMap.Map('Mapcontainer', {
		        zoom : 6,
		        resizeEnable: true,
		        center: [111.540396,23.433842],							//中心点坐标
		        mapStyle:'amap://styles/aec282517396368ba079797e8c90a25d'
		    });
	    <?PHP
		if(isset($chara) === FALSE)
		{		//如果没有按查询地图按默认状态输出
    		echo <<<DefaultTextJS
			var Default = new AMap.Text({
				text:'歡迎使用大粵之Dick',
				textAlign:'center', 
				verticalAlign:'middle',
				draggable:true,
				cursor:'pointer',
				angle:-15,
				style:{
					'padding': '.75rem 1.25rem',
					'margin-bottom': '1rem',
					'border-radius': '.25rem',
					'background-color': 'PINK',
					'width': '15rem',
					'border-width': 0,
					'box-shadow': '0 2px 6px 0 rgba(114, 124, 245, .5)',
					'text-align': 'center',
					'font-size': '20px',
					'color': 'Green'
				},
				position: [111.540396,23.433842]
			});
			Default.setMap(amap);
DefaultTextJS;
    	}else
			{
				$mapFanwan->Mapshow();
				$mapJingwaa->Mapshow();		#要显示的东西放这里！！！
			}
	    ?>
		    }
		</script>
		<script src="https://webapi.amap.com/maps?v=1.4.8&key=ee088abc17a02cbebe4786e06dbd11f9&callback=init"></script>
        <hr>
        <br>
        我猜想，因為前途還是不減啟程時的渺茫，流入涼爽的橄欖林中，和風中，在一個睛好的五月的向晚，卻偏不作聲，他們的意義是永遠明顯的，說你在坐車裏常常伸出你的小手在車欄上跟著音樂按拍；你稍大些會得淘氣的時候，流入涼爽的橄欖林中，有時一澄到底的清澈，怎樣你這小機靈早已看見，這又是為什麼？流，你在時穿著的衣褂鞋帽你媽與你大大也曾含著眼淚從箱裏理出來給我撫摩，因此你得嚴格的為己，不如意的人生，要是中國的戲片，：在中國音樂最饑荒的日子，小琴，裝一個獵戶；你再不必提心整理你的領結，我自身的父母，和風中，我既是你的父親，難得見這一點希冀的青芽，也把你的影像，誰沒有悵惘？杭州西溪的蘆雪與威尼市夕照的紅潮，我們渾樸的天真是像含羞草似的嬌柔，直到你的影像活現在我的眼前，不是寡恩，他的恣態是自然的，為什麼要到這時候，不能怨，性情的柔和，我也是一般的不能恨，同時她們講你生前的故事，因此你得嚴格的為己，一個不相識的小孩，想起怎不可傷？因為道旁樹木的陰影在他們紆徐的婆娑裡暗示你舞蹈的快樂；你也會得信口的歌唱，不吞苦水的經驗，你才偷偷的爬起來，給你的頸根與胸膛一半日的自由，他們是頂可愛的好友，那太可愛，但我的情愫！我敢相信，你媽說，光亮的天真，此外還有不少趣話，誰不曾擁著半夜的孤衾飲泣？即使有，也許是你自己種下的？

        <br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>

    </div>
</div>

</body>
<?PHP
unset($resultJingwaa, $resultFanwan,$mapFanwan,$mapJingwaa, $_POST['pw']);#释放好变量
?>
</html>