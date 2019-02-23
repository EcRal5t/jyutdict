<?php
include_once ("Jyutping.class.php");

abstract class Lookup {
    abstract protected function query($character, $con);
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
    
    public function query($character, $con) {
        $charaArray = array($character);
        $sim2Trad_getCharaId_sql = "
            SELECT chara_id
            FROM `Character_simtrad_list`
            WHERE`chara` = '$character'";  #从字表中查询列出符合输入的字
        $sim2Trad_getCharaId_result = mysqli_fetch_row(mysqli_query($con, $sim2Trad_getCharaId_sql));
        #执行SQL语句返回结果集数列
        if (is_array($sim2Trad_getCharaId_result)) {#如果结果集存在
            $sim2Trad_SimMap_sql = "
                SELECT chara_id_trad
                FROM `Character_simtrad_map`
                WHERE `chara_id_sim` = $sim2Trad_getCharaId_result[0]";
            $sim2Trad_SimMap_query = mysqli_query($con, $sim2Trad_SimMap_sql);
            #查找简繁映射表
            $sim2Trad_SimMap_result = mysqli_fetch_row($sim2Trad_SimMap_query);
            while (is_array($sim2Trad_SimMap_result)) {
                $sim2Trad_getTradChara_sql = "
                    SELECT `chara`
                    FROM `Character_simtrad_list`
                    WHERE `chara_id` = $sim2Trad_SimMap_result[0]";
                $sim2Trad_getTradChara_result = mysqli_fetch_row(mysqli_query($con, $sim2Trad_getTradChara_sql));
                if ($character != $sim2Trad_getTradChara_result[0]) array_push($charaArray, $sim2Trad_getTradChara_result[0]);
                $sim2Trad_SimMap_result = mysqli_fetch_row($sim2Trad_SimMap_query);
            }#end while(is_array)
            return $charaArray;
        } else {
            return $charaArray;
        }#end if(is_array)
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
    
    function query($character, $con) {
        $query_inFanwan_sql = "
            SELECT `id`, `chara`, `initial`, `nuclei`, `coda`, `tone`, `siuwan`, `meaning`, `initial_ch`, `final_ch`, `yunbu`, `tone_ch`
            FROM `YFanwan`
            WHERE `chara`='" . $character. "'";
        $resultArray = array();         #多维数组每个维用来存储结果数列
        $resultSet   = mysqli_query($con, $query_inFanwan_sql);#结果集
        for($result = mysqli_fetch_row($resultSet); is_array($result); $result = mysqli_fetch_row($resultSet)) {
            $resultArray[] = $result;#插入结果数列到下一维度
        }//end for
        return $resultArray;#返回结果数列，会可能返回一个空的数列
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
                    $jyutping->set($resultItem[2], $resultItem[3], $resultItem[4], $resultItem[5])
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
                        <td><?PHP echo "$resultItem[10] - $resultItem[6]"; ?></td>
                        <td><?PHP echo "$resultItem[8] - $resultItem[9] - $resultItem[11]"; ?></td>
                    </tr>
                    <tr>
                        <td>分韻</td>
                        <td colspan="4" style="border-top: none;"><?PHP echo $resultItem[7] ?></td>
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
    
    function query($character, $con) {
       $query_inJingwaa_sql = "
            SELECT `id`, `page`, `initial`, `nuclei`, `coda`, `tone`, `pron`, `radical`, `radical_stroke`, `extra_stroke`, `page`, `state`, `order`
            FROM `YJingwaa`
            WHERE `chara`='" . $character . "'";
        $query_inJingwaa_query = mysqli_query($con, $query_inJingwaa_sql);
        $JingwaaArray          = [];
        for($result = mysqli_fetch_row($query_inJingwaa_query);
            is_array($result);
            $result = mysqli_fetch_row($query_inJingwaa_query)) {
            $JingwaaArray[] = $result; #插入结果数列到下一维度
        }//end for
        return $JingwaaArray;
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
                foreach ($charArray as $key => $JingwaaArray) {
                    $jyutping->set($JingwaaArray[2],$JingwaaArray[3],$JingwaaArray[4],$JingwaaArray[5]);
                    ?>
                    <tr>
                        <td>英華</td>
                        <td><?PHP echo $JingwaaArray[10] ?></td>
                        <td><?PHP echo "$JingwaaArray[8]($JingwaaArray[7])+$JingwaaArray[9]" ?></td>
                        <td class="<?PHP echo ($lastOrder==$JingwaaArray[12]?"hl-font-gray":"") ?> alphabet"><?PHP echo $JingwaaArray[6] ?></td>
                        <td><?PHP $jyutping->printWithColor("red", "green", "green"); ?></td>
                    </tr>
                    <?PHP
                    $lastOrder = $JingwaaArray[12];
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
    private function _construct() {}
    
    private static $instance;
    
    private function _clone() {}
    
    public static function getInstance() {
        if (!self::$instance instanceof LocalDictionary) {
            self::$instance = new LocalDictionary();
        }
        return self::$instance;
    }
    
    private function getCityList($con) {
        $query_inCityList_sql = "
            SELECT `longitude`, `latitude`, `first`, `second`, `third`, `sheetname`
            FROM `IAreaList`"; #獲取城市信息
        $query_inCityList_query = mysqli_query($con, $query_inCityList_sql);
        $cityListArray = [];
        for($cityList = mysqli_fetch_row($query_inCityList_query); is_array($cityList); $cityList = mysqli_fetch_row($query_inCityList_query)) {
            $cityListArray[] = $cityList;
        }
        //var_dump($cityListArray);
        return $cityListArray;
    }
    
    public function query($character,$con) {
        $cityListArray = $this->getCityList($con);
        $resultArray = [];
        foreach ($cityListArray as $eachCity) {
            $result = [
                "longitude" => $eachCity[0],
                "latitude"  => $eachCity[1],
                "first"     => $eachCity[2],
                "second"    => $eachCity[3],
                "third"     => $eachCity[4]
            ];#一个数组五个键名
            $pronArray = $this->charaQueryInSheet($character, $eachCity[5], $con);
            if(!empty($pronArray)) {
                $resultArray[] = array_merge($result,$pronArray);
            }
        }
        return $resultArray;
    }
    
    private function charaQueryInSheet($character, $sheetName, $con) {
        $resultArray = [];
        $query_sql = "
            SELECT `chara`, `initial`, `nuclei`, `coda`, `tone`, `ipa`, `note`
            FROM ".$sheetName."
            WHERE `chara` = '".$character."'";
        $resultSet = mysqli_query($con, $query_sql);
        for($result = mysqli_fetch_row($resultSet);
            is_array($result);
            $result = mysqli_fetch_row($resultSet)) {
            $resultArray[] = $result;
        }
        return $resultArray;
    }
    
    public function show($charaArray, $showMap=TRUE) {
        if(!empty($charaArray)){
            $character = $charaArray[0][0][0]; #从第一个结果里面取得字
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
                            for ($charaNum = 0; $charaNum < (count($charaArray[$num]) - 5); $charaNum++) {   #多音字
                                $locFirst  = $charaArray[$num]["first"];   #片
                                $locSecond = $charaArray[$num]["second"];  #市
                                $locThird  = $charaArray[$num]['third'];   #點
                                $jyutping->set(
                                        $charaArray[$num][$charaNum][1],
                                        $charaArray[$num][$charaNum][2],
                                        $charaArray[$num][$charaNum][3],
                                        $charaArray[$num][$charaNum][4]
                                        );
                                $jyutping->setIpa($charaArray[$num][$charaNum][5]);
                                $note   = $charaArray[$num][$charaNum][6];  #note
                                ?>
                                <tr>
                                    <td class="column4-20 min-width60 "><?PHP echo $locFirst ?></td>
                                    <td class="column3-20 min-width45">
                                        <?PHP
                                        echo $locSecond;
                                        if (!empty($locThird)) echo "<br><span class='hl-font-cyan font-0p9em'>$locThird</span>";
                                        ?>
                                    </td>
                                    <td class="alphabet">
                                        <?PHP $jyutping->printWithColor(); ?>
                                    </td>
                                    <td class="column4-20 min-width45">
                                        <?PHP $jyutping->printIpaWithColor(); ?>
                                    </td>
                                    <td class="tips">
                                        <?PHP
                                        if (mb_strlen($note,'UTF8') > 5) {
                                            echo mb_substr($note, 0, 4, 'utf8')."…";
                                            echo "<span class='tipsMain'>$note</span>";
                                        } else {
                                            echo $note;
                                        }
                                        ?>
                                    </td>
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
            ?>
            <div class="generalBgDeeper" id="mapContainer"></div>
            <script type="text/javascript">
                var amap;
                window.init = function() {
                    amap = new AMap.Map('mapContainer', {
                        zoom : 6,
                        resizeEnable: true,
                        center: [111.08,23.43],                         //中心点坐标
                        mapStyle:'amap://styles/16da0aa02241a5059605e5e35e40e2fd'
                    });
                    <?PHP
                    #此处插入要显示的标签
                    for($num = 0; $num < count($charaArray); $num++) {
                        $longitude = $charaArray[$num]['longitude'];
                        $latitude = $charaArray[$num]['latitude'];
                        #此处CONTENT是MAKER标记位置的内容 去掉默认CONTENT是一个小大头针
                        
                        echo "var marker$num = new AMap.Marker({";
                            $pron = "";
                            for($charaNum = 0; $charaNum < (count($charaArray[$num]) - 5); $charaNum++) {
                                $initial = $charaArray[$num][$charaNum][1];
                                $nuclei  = $charaArray[$num][$charaNum][2];
                                $coda    = $charaArray[$num][$charaNum][3];
                                $tone    = $charaArray[$num][$charaNum][4];
                                $pron .= $initial.$nuclei.$coda.$tone.'<br>';
                            } #end for charaNum
                            echo "position: [$longitude,$latitude],";
                            echo "content: \"<div class='locale-label'>$pron<div class='label-triangle'></div></div>\"";
                        echo "});";
                        
                        echo "marker$num.setMap(amap);";
                    }#end for num
                    ?>
                }
            </script>
            <script src="https://webapi.amap.com/maps?v=1.4.13&key=160f3ffdbe10ec13c75edac2fae17e3c&callback=init"></script>
            
            <?PHP
        }
    }
}
?>