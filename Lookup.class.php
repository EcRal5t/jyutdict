<?php

abstract class Lookup {
    abstract protected function query($character, $dbh);
    abstract protected function show($charArray);
}

class Sim2TradLookup extends Lookup {
    private function _construct() {
        self::$instance = null;
    }

    private static $instance;
    private function _clone() {}

    public static function getInstance() {
        if (!self::$instance instanceof Sim2TradLookup) {
            self::$instance = new Sim2TradLookup();
        }
        return self::$instance;
    }

    public function query($character, $dbh) {   ///到時候會大改一遍的…
        $charaArray = array($character);		//改的时候请好好规划
        $sim2Trad_getCharaId_sql = "
            SELECT chara_id
            FROM `Character_simtrad_list`
            WHERE`chara` = :chara";  #从字表中查询列出符合输入的字
        $sim2Trad_getCharaId_stmt = $dbh->prepare($sim2Trad_getCharaId_sql);
        $sim2Trad_getCharaId_stmt->execute(array(':chara'=>$character));
        $sim2Trad_getCharaId_result = $sim2Trad_getCharaId_stmt->fetchAll(PDO::FETCH_ASSOC);

        #执行SQL语句返回结果集数列
        if ($sim2Trad_getCharaId_result!=[]) {#如果结果集存在
            $sim2Trad_SimMap_sql = "
                SELECT `chara_id_trad`
                FROM `Character_simtrad_map`
                WHERE `chara_id_sim` =" . $sim2Trad_getCharaId_result[0]['chara_id'];#查找简繁映射表
            $sim2Trad_SimMap_stmt = $dbh->prepare($sim2Trad_SimMap_sql);
            $sim2Trad_SimMap_stmt->execute();
            $sim2Trad_SimMap_result = $sim2Trad_SimMap_stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($sim2Trad_SimMap_result as $items) {
                $sim2Trad_getTradChara_sql = "
                    SELECT `chara`
                    FROM `Character_simtrad_list`
                    WHERE `chara_id` =" . $items['chara_id_trad'];
                $sim2Trad_getTradChara_stmt = $dbh->prepare($sim2Trad_getTradChara_sql);
                $sim2Trad_getTradChara_stmt->execute();

                $result = $sim2Trad_getTradChara_stmt->fetchAll(PDO::FETCH_ASSOC);
                if ($character != $result[0]['chara']) array_push($charaArray, $result[0]['chara']);
            }#end foreach
        }#end if(!=[])
        return $charaArray;
    }#end function query

    public function show($charaArray) {
        if (is_array($charaArray)) {
            $count = count($charaArray);
            if ($count > 2) {
                ?>
                <div class="general-bg-deeper" id="charaSimToTrad">
                    <span id="charaSimToTradHead">简转繁</span>
                    <span id="charaSimToTradMain">
                        <?PHP
                        unset($charaArray[0]);
                        foreach($charaArray as $chara) {
                            echo " <a href=\"index.php?character=" . $chara . "\">" . $chara . "</a>";
                        } //end foreach
                        ?>
                    </span>
                </div>
                <?PHP
            }#end if
        }
    }
}

class FanWanDict extends Lookup {
    private function _clone() {}

    final private function __construct() {
        self::$instance = null;
    }

    protected static $instance;
    static public function getInstance() {
        if (self::$instance instanceof self) {
            return self::$instance;
        } else {
            self::$instance = new self();
            return self::$instance;
        }
    }

    function query($character, $dbh) {
        $inFanwan_sql = "
            SELECT `id`, `chara`, `initial`, `nuclei`, `coda`, `tone`, `siuwan`, `meaning`, `initial_ch`, `final_ch`, `yunbu`, `tone_ch`
            FROM `YFanwan`
            WHERE `chara`=:chara";
        $inFanwan_stmt = $dbh->prepare($inFanwan_sql);
        $inFanwan_stmt->execute(array(':chara'=>$character));
        return $inFanwan_stmt->fetchAll(PDO::FETCH_ASSOC);
    }//end function query

    function show($charArray) {
        if(empty($charArray)) {
            echo "<span style='font-size: 20px;'>分韻冇見有</span>";
        } else {
            $jyutping = new Jyutping();
            ?>

            <table class="general-form annex-form">
                <?PHP
                foreach ($charArray as $resultItem) { #将数组一个个输出
                    $jyutping->set($resultItem['initial'], $resultItem['nuclei'], $resultItem['coda'], $resultItem['tone'])
                    ?>
                    <tr>
                        <td class="column2-20 font-22">分韻</td>
                        <td class="column8-20">韻部 - 小韻</td>
                        <td class="column6-20">聲 - 韻 - 調</td>
                        <td rowspan="2" class="" style="border-bottom: none;">
                            <?PHP $jyutping->printWithColor("red", "green", "green"); ?>
                        </td>
                    </tr>
                    <tr>
                        <td>分韻</td>
                        <td><?PHP echo $resultItem['yunbu'] . "-" . $resultItem['siuwan']; ?></td>
                        <td><?PHP echo $resultItem['initial_ch'].'-'.$resultItem['final_ch'].'-'.$resultItem['tone_ch']; ?></td>
                    </tr>
                    <tr>
                        <td>分韻</td>
                        <td colspan="4" style="border-top: none;"><?PHP echo $resultItem['meaning'] ?></td>
                    </tr>

                    <?PHP
                }//end foreach
                ?>
            </table>

            <?PHP
        }//end if
    }//end function show
}

class JingWaaDict extends Lookup {

    private function _clone() {}

    final private function __construct() {
        self::$instance = null;
    }

    static protected $instance;

    static public function getInstance() {
        if (self::$instance instanceof self) {
            return self::$instance;
        } else {
            self::$instance = new self();
            return self::$instance;
        }
    }

    function query($character, $dbh) {
        $inJingwaa_sql = "
            SELECT `id`, `page`, `initial`, `nuclei`, `coda`, `tone`, `pron`, `radical`, `radical_stroke`, `extra_stroke`, `page`, `state`, `order`
            FROM `YJingwaa`
            WHERE `chara`=:chara";
        $inJingwaa_stmt = $dbh->prepare($inJingwaa_sql);
        $inJingwaa_stmt->execute(array(':chara'=>$character));
        return $inJingwaa_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    function show($charArray) {
        if (empty($charArray)) {
            echo "<span style='font-size: 20px;'>英華冇見有</span>";
        } else {
            $jyutping = new Jyutping();
            ?>
            <table class="annex-form general-form">
                <tr>
                    <td class="column2-20 font-22">英華</td>
                    <td class="column4-20">葉碼</td>
                    <td class="column5-20">筆畫</td>
                    <td class="column5-20">原標音</td>
                    <td></td>
                </tr>
                <?PHP
                $lastOrder = 0;
                foreach ($charArray as $resultItem) {
                    $jyutping->set($resultItem['initial'], $resultItem['nuclei'], $resultItem['coda'], $resultItem['tone']);
                    ?>
                    <tr>
                        <td>英華</td>
                        <td><?PHP echo $resultItem['page']; ?></td>
                        <td><?PHP echo $resultItem['radical_stroke'].'('.$resultItem['radical'].')+'.$resultItem['extra_stroke']; ?></td>
                        <td class="<?PHP echo ($lastOrder==$resultItem['order']?"hl-font-gray":"") ?> alphabet"><?PHP echo $resultItem['pron'] ?></td>
                        <td><?PHP $jyutping->printWithColor("red", "green", "green"); ?></td>
                    </tr>
                    <?PHP
                    $lastOrder = $resultItem['order'];
                }
                ?>

            </table>
            <?PHP
        }//end if
    }//end function show
}//end class JingWaaDict

interface displayInMap {
    public function display($charArray);
}

class LocalDictionary extends Lookup implements displayInMap {
    private static $count = 0;

    private function _construct() {}

    private static $instance;

    private function _clone() {}

    public static function getInstance() {
        if (!self::$instance instanceof LocalDictionary) {
            self::$instance = new LocalDictionary();
			#当第一次显示地图的时候 初始化
            echo "<script>var count = ".self::$count.";</script>";
			?>
			<script>var mapList = new Array();/*用于存储地图列表*/</script>	
			<!-- leaflet 的前置CSS和JS -->
			 <link rel="stylesheet" href="https://unpkg.com/leaflet@1.5.1/dist/leaflet.css"
			  integrity="sha512-xwE/Az9zrjBIphAcBb3F6JVqxf46+CDLwfLMHloNu6KEQCAWi6HcDUbeOfBIptF7tcCzusKFjFw2yuvEpDL9wQ=="
			  crossorigin=""/>
			   <!-- Make sure you put this AFTER Leaflet's CSS -->
			<script src="https://unpkg.com/leaflet@1.5.1/dist/leaflet.js"
			  integrity="sha512-GffPMF3RvMeYyc1LWMHtK8EbPv0iNZ8/oTtHPx9/cc2ILxQ+u905qIwdpULaqDkyBKgOaB57QTMg7ztg8Jm2Og=="
			  crossorigin=""></script>
			  <?PHP
        }else{
            self::$count++;
			?>
			<script>++count//js中地图编号+1</script>
			<?PHP
        }
        return self::$instance;
    }
	#从DB中获取城市列表 (包括了各地音表表名字)
    private function getCityList($dbh) {
        $inCityList_sql = "
            SELECT `longitude`, `latitude`, `first`, `second`, `third`, `sheetname`
            FROM `IAreaList`"; #獲取城市信息
        $inCityList_stmt = $dbh->prepare($inCityList_sql);
        $inCityList_stmt->execute();
        return $inCityList_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
	#从DB中获取数据的操作
    public function query($character, $dbh) {
        $cityListArray = $this->getCityList($dbh);
        $resultArray = [];
        foreach ($cityListArray as $eachCity) {
            $result = $eachCity;
            $pronArray = $this->charaQueryInSheet($character, $eachCity['sheetname'], $dbh);
            if(!empty($pronArray)) {
                $resultArray[] = array_merge($result,$pronArray);
            }
        }
        return $resultArray;
    }
	#传入查询的字 表名 即可在各地字表中查字并返回结果
    private function charaQueryInSheet($character, $sheetName, $dbh) {
        $inSheet_sql = "
            SELECT `chara`, `initial`, `nuclei`, `coda`, `tone`, `ipa`, `note`
            FROM `$sheetName`
            WHERE `chara`=:chara";
        $inSheet_stmt = $dbh->prepare($inSheet_sql);
        $inSheet_stmt->execute(array(':chara'=>$character));
        return $inSheet_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function show($charaArray, $showMap=TRUE) {
        if(!empty($charaArray)){
            $character = $charaArray[0][0]['chara']; #从第一个结果里面取得字
            ?>
            <div id="regionalResult">
                <div class="general-bg-deeper" id="regionalResultForm">
                    <table id="regionalResultTable" class="general-form annex-form">
                        <tr>
                            <td class="font-22" style='height: 36px;' colspan='5'><?PHP echo $character; ?></td>
                        </tr>
                        <?PHP
                        $jyutping = new Jyutping();
                        for ($num = 0;$num < count($charaArray);$num++) {
                            for ($charaNum = 0; $charaNum < (count($charaArray[$num]) - 6); $charaNum++) {   #多音字
<<<<<<< Updated upstream
                                $locFirst  = $charaArray[$num]['first'];   #片
                                $locSecond = $charaArray[$num]['second'];  #市
                                $locThird  = $charaArray[$num]['third'];   #點
                                $jyutping->set(
                                        $charaArray[$num][$charaNum]['initial'],
                                        $charaArray[$num][$charaNum]['nuclei'],
                                        $charaArray[$num][$charaNum]['coda'],
                                        $charaArray[$num][$charaNum]['tone']
                                        );
                                $jyutping->setIpa($charaArray[$num][$charaNum]['ipa']);
                                $note   = $charaArray[$num][$charaNum]['note'];  #note
                                ?>
                                <tr>
                                    <td class="column4-20 min-width60 "><?PHP echo $locFirst ?></td>
                                    <td class="column3-20 min-width45 <?PHP if (!empty($locThird)) echo 'tips'; ?>">
                                        <?PHP
                                        echo $locSecond;
                                        if (!empty($locThird)) echo "<span class='hl-font-cyan font-0p9em tipsMain' style='width: 50px;'>$locThird</span>";
                                        ?>
                                    </td>
                                    <td class="alphabet">
                                        <?PHP $jyutping->printWithColor(); ?>
                                    </td>
                                    <td class="column4-20 min-width45">
                                        <?PHP $jyutping->printIpaWithColor(); ?>
                                    </td>
                                    <?PHP
                                        if (mb_strlen($note,'UTF8') > 5) {
                                            
                                            echo "<td class='tips font-0p9em'>".mb_substr($note, 0, 4, 'utf8')."…";
                                            echo "<span class='tipsMain'>$note</span></td>";
                                        } else {
                                            echo "<td class='font-0p9em'>$note</td>";
                                        }
=======
                            $pin  = $charaArray[$num]['first'];   #片
                            $shi = $charaArray[$num]['second'];  #市
                            $dim  = $charaArray[$num]['third'];   #點
                            $jyutping->set(
                                    $charaArray[$num][$charaNum]['initial'],
                                    $charaArray[$num][$charaNum]['nuclei'],
                                    $charaArray[$num][$charaNum]['coda'],
                                    $charaArray[$num][$charaNum]['tone']
                                    );
                            $jyutping->setIpa($charaArray[$num][$charaNum]['ipa']);
                            $note   = $charaArray[$num][$charaNum]['note'];  #note
                            ?>
                            <tr>
                                <td class="column4-20 min-width60 "><?PHP echo $pin ?></td>
                                <td class="column3-20 min-width45">
                                    <?PHP
                                    echo $shi;
                                    if (!empty($dim)) echo "<br><span class='hl-font-cyan font-0p9em'>$dim</span>";
>>>>>>> Stashed changes
                                    ?>
                                </tr>
                            <?PHP
                            }#end for(charaNum)
                        }#end for(num)
                        ?>
                    </table>
                </div>
                <?PHP
                    if ($showMap) $this->display($charaArray);
            echo "</div>";
        }
    }
    function display($charaArray) {
        if(!empty($charaArray)) {
            //print_r($charaArray);
            echo "<div class=\"generalBgDeeper\" id=\"mapContainer".self::$count."\"></div>";
            ?>
            <script type="text/javascript">
<<<<<<< Updated upstream
                var container = "mapContainer" + count;
                window["amap" + count];

                    window["amap" + count] = new AMap.Map(container, {
                        zoom : 6,
                        resizeEnable: true,
                        center: [111.08,22.63],                         //中心点坐标
                        mapStyle:'amap://styles/16da0aa02241a5059605e5e35e40e2fd'
                    });
=======
				mapList['m' + count] =  L.map('mapContainer' + count).setView([23.43,111.08], 6.5);
				L.tileLayer('https://api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token={accessToken}', {
					attribution: 'Performed By Lingnaam Jyutjam',
					maxZoom: 18,
					id: 'mapbox.streets',
					accessToken: 'pk.eyJ1IjoiemVuYW0iLCJhIjoiY2p4bjh5MjFxMGM4aTNobGF0dXNoejlseiJ9.BPrObTer-_5w5L3oEaEWfQ'
				}).addTo(mapList['m' + count]);
>>>>>>> Stashed changes
                    <?PHP
                    #此处插入要显示的标签
                    for($num = 0; $num < count($charaArray); $num++) 
					{
                        $longitude = $charaArray[$num]['longitude'];
                        $latitude = $charaArray[$num]['latitude'];
						$pron = $charaArray[$num]['first'].$charaArray[$num]['second'].$charaArray[$num]['third'].'<br/>';
						for($charaNum = 0; $charaNum < (count($charaArray[$num]) - 6); $charaNum++) 
						{
							$initial = $charaArray[$num][$charaNum]['initial'];
							$nuclei  = $charaArray[$num][$charaNum]['nuclei'];
							$coda    = $charaArray[$num][$charaNum]['coda'];
							$tone    = $charaArray[$num][$charaNum]['tone'];
							$pron .= $initial.$nuclei.$coda.$tone.'<br/>';	//将要显示的合并
							#下面这个显示中 color和Fillcolor可考虑 在表中添加一个color列 此处用 $color捕获 并塞入 下面的属性中 可以产生不同颜色
							echo <<<CIRCLE
							
L.circle([$latitude, $longitude], {
	color: '#00eeff', 
	fillColor: '#a34',
	fillOpacity: 0.5,
	radius: 10000
}).addTo(mapList['m' + count]).bindPopup("$pron");

CIRCLE;
						} #end for charaNum
                    }#end for num
                    ?>
            </script>
            <?PHP
        }
    }
}
?>