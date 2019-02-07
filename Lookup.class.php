<?php
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
        $sim2Trad_getCharaId_sql = "SELECT chara_id FROM
        `Character_simtrad_list`
        WHERE
        `chara`='" . $character . "'";  #从字表中查询列出符合输入的字
        $sim2Trad_getCharaId_result = mysqli_fetch_row(mysqli_query($con, $sim2Trad_getCharaId_sql));
        #执行SQL语句返回结果集数列
        if (is_array($sim2Trad_getCharaId_result)) {#如果结果集存在
            $sim2Trad_SimMap_sql = "SELECT chara_id_trad FROM
            `Character_simtrad_map`
            WHERE `chara_id_sim`=" . $sim2Trad_getCharaId_result[0];
            
            $sim2Trad_SimMap_query = mysqli_query($con, $sim2Trad_SimMap_sql);
            #查找简繁映射表
            $sim2Trad_SimMap_result = mysqli_fetch_row($sim2Trad_SimMap_query);
            while (is_array($sim2Trad_SimMap_result)) {
                $sim2Trad_getTradChara_sql = "SELECT chara FROM `Character_simtrad_list` WHERE `chara_id`=" . $sim2Trad_SimMap_result[0];
                $sim2Trad_getTradChara_result = mysqli_fetch_row(mysqli_query($con, $sim2Trad_getTradChara_sql));
                array_push($charaArray, $sim2Trad_getTradChara_result[0]);
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
                <div class="generalBgDeeper" id="charaSimToTrad">
                    <span id="charaSimToTradHead">简转繁</span>
                    <span id="charaSimToTradMain">
                        <?PHP
                        unset($charaArray[0]);
                        foreach($charaArray as $chara) {
                            echo " <a href=\"newindex.php?character=" . $chara . "\">" . $chara . "</a>";
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
        $query_inFanwan_sql    =
            "SELECT `id`, `yunbu`, `tone`, `chara`, `xiaoyun`, `meaning`,
        `initial_chara`, `final_chara`, `tone_chara`, `initial_latin`,
        `final_latin`, `tone_latin`
        FROM
        `Fanwan`
        WHERE
        `chara`='" . $character. "'";
        $resultArray = array();         #多维数组每个维用来存储结果数列
        $resultSet = mysqli_query($con, $query_inFanwan_sql);#结果集
        for($result = mysqli_fetch_row($resultSet); is_array($result); $result = mysqli_fetch_row($resultSet)) {
            $resultArray[] = $result;#插入结果数列到下一维度
        }//end for
        return $resultArray;#返回结果数列，会可能返回一个空的数列
    }//end function query
    
    function show($charArray) {
        if(empty($charArray)) {
            echo "<span style='font-size: 20px;'>冇見有</span>";
        } else {
            foreach ($charArray as $query_inFanwan_result) { #将数组一个个输出
                ?>
                
                <table class="general-form" id="wanshyuResultFanwan">
                    <tr>
                        <td class="column2-20">序號</td>
                        <td class="column10-20">韻部 - 小韻</td>
                        <td class="column5-20">聲-韻-調</td>
                        <td rowspan="2" class="hlFontRed">
                            <?PHP
                            if ($query_inFanwan_result[9] <> '0')
                                echo $query_inFanwan_result[9];
                            echo $query_inFanwan_result[10] . $query_inFanwan_result[11];
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><?PHP echo "$query_inFanwan_result[0]"; ?></td>
                        <td><?PHP echo "$query_inFanwan_result[1]-$query_inFanwan_result[4]"; ?></td>
                        <td><?PHP echo "$query_inFanwan_result[6]-$query_inFanwan_result[7]-$query_inFanwan_result[8]"; ?></td>
                    </tr>
                    <tr>
                        <td colspan="4"><?PHP echo $query_inFanwan_result[5] ?></td>
                    </tr>
                </table>
                
                <?PHP
            }//end foreach
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
        $query_inJingwaa_sql   = "SELECT  `id`, `page`, `stroke`, `order_st`,
           `order_nd`, `exstroke`, `radical`, `chara`, `bopo_tr`, `bopo`, `bopo_nd_tr`,
           `bopo_nd`, `state`
          FROM `Jingwaa`
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
            echo "<span style='font-size: 20px;'>冇見有</span>";
        } else {
            ?>
            <table class="general-form" id="wanshyuResultYingwaa">
                <tr>
                    <td width="12.5%">序號</td>
                    <td width="12.5%">葉碼</td>
                    <td width="25%">筆畫</td>
                    <td width="25%">原標音</td>
                    <td rowspan="2" class="hlFontRed">
                        <?PHP
                        foreach ($charArray as $key => $JingwaaArray) {
                            if($key > 0) echo '<br>';
                            echo($JingwaaArray[12] ? "<i>$JingwaaArray[8]</i>" : $JingwaaArray[8]);
                            echo(($JingwaaArray[10] <> '_NULL') ? ' (' . $JingwaaArray[10] . ')' : '');
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <?PHP
                        foreach ($charArray as $key => $JingwaaArray) {
                            if($key > 0) echo '<br>';
                            echo $JingwaaArray[0];
                        }
                        echo "</td>";
                        echo "<td>".$charArray[0][1]."</td>";
                        echo "<td>".$charArray[0][2] . '(' . $charArray[0][6] . ')+' . $charArray[0][5]."</td>"; #发现要调用第0项虽然不知道用意还是把原数组给弄上来了
                        echo "<td>";
                        foreach ($charArray as $key => $JingwaaArray) {
                            if($key > 0) echo '<br>';
                            echo($JingwaaArray[12] ? "<i> $JingwaaArray[9] </i>" : $JingwaaArray[9]);
                            echo(($JingwaaArray[11] <> '_NULL') ? ' (' . $JingwaaArray[11] . ')' : '');
                        }
                        ?>
                    </td>
                </tr>
            </table>
            <?PHP
        }//end if
    }//end function show
}//end class JingWaaDict

interface displayInMap {
    public function display($charArray);
}

class LocalDictionary extends Lookup implements displayInMap {
    private function _construct() {
    }
    
    private static $instance;
    
    private function _clone() {}
    
    public static function getInstance() {
        if (!self::$instance instanceof LocalDictionary) {
            self::$instance = new LocalDictionary();
        }
        return self::$instance;
    }
    
    private function getCityList($con) {
        $query_inCityList_sql   = "SELECT
        `longitude`, `latitude`,
         `oneth`, `forth`, `third`,
          `sheetname`
          FROM `IAreaList`"; #獲取城市信息
        $query_inCityList_query = mysqli_query($con, $query_inCityList_sql);
        $cityListArray = [];
        for($cityList = mysqli_fetch_row($query_inCityList_query);
            is_array($cityList);
            $cityList = mysqli_fetch_row($query_inCityList_query))
        {
            $cityListArray[] = $cityList;
        }
        #var_dump($cityListArray);
        return $cityListArray;
    }
    
    public function query($character,$con) {
        $cityList = $this->getCityList($con);
        $resultArray = [];
        foreach ($cityList as $eachCityList) {
            $result = [
                "longitude"=>$eachCityList[0],
                "latitude"=>$eachCityList[1],
                "oneth"=>$eachCityList[2],
                "forth"=>$eachCityList[3],
                "third"=>$eachCityList[4]
            ];#一个数组五个键名
            $jamArray = $this->jamquery($character, $eachCityList[5],$con);
            if(!empty($jamArray)) {
                $resultArray[] = array_merge($result,$jamArray);
            }
        }
        return $resultArray;
    }
    
    private function jamquery($character,$sheetName,$con) {
        $resultArray = [];
        $query_sql =
            "SELECT
        `chara`, `initial`, `nuclei`, `coda`, `tone`, `ipa`, `note`
        FROM "
            .$sheetName.
            "
        WHERE
        chara = '".$character."'";
        $resultSet = mysqli_query($con, $query_sql);
        for($result = mysqli_fetch_row($resultSet);
            is_array($result);
            $result = mysqli_fetch_row($resultSet)) {
            $resultArray[] = $result;
        }
        return $resultArray;
    }
    
    public function show($charaArray) {
        if(!empty($charaArray)){
            $character = $charaArray[0][0][0]; #从第一个结果里面取得字
            ?>
            <div id="regionalResult">
            <div class="generalBgDeeper" id="regionalResultForm">
                <table id="regionalResultTable" class="general-form">
                    <tr>
                        <td style='font-size: 22px; height: 36px;' colspan='5'><?PHP echo $character; ?></td>
                    </tr>
                    <?PHP
                    for ($num = 0;$num < count($charaArray);$num++) {
                        for ($charaNum = 0; $charaNum < (count($charaArray[$num]) - 5); $charaNum++) {   #多音字
                            $locFirst = $charaArray[$num]["oneth"]; #大片
                            $locThird = $charaArray[$num]["third"]; #小片
                            $red =  $charaArray[$num][$charaNum][1];    #声母
                            $green = $charaArray[$num][$charaNum][2];  #韵腹
                            $blue = $charaArray[$num][$charaNum][3];   #韵尾
                            $purple = $charaArray[$num][$charaNum][4]; #声调
                            $cyan = $charaArray[$num][$charaNum][5];   #IPA
                            $note = $charaArray[$num][$charaNum][6];   #note
                            $forth = $charaArray[$num]['forth'];
                            ?>
                            <tr>
                                <td class="column4-20 min-width60"><?PHP echo $locFirst ?></td>
                                <td class="column3-20 min-width45"><?PHP echo $locThird ?></td>
                                <td>
                                    <?PHP
                                    echo '<span class="hlFontRed">' . $red . '</span>';
                                    echo '<span class="hlFontGreen">' . $green . '</span>';
                                    echo '<span class="hlFontBlue">' . $blue . '</span>';
                                    echo '<span class="hlFontYellow">' . $purple . '</span>';
                                    
                                    ?>
                                </td>
                                <td class="column3-20 min-width45"><span class="hlFontCyan ipa">/<?PHP echo $cyan; ?>/</span></td>
                                <td class = "tips">
                                    <?PHP
                                    if (mb_strlen($note,'UTF8') > 4) {
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
            $this->display($charaArray);
            echo "</div>";
        }
    }
    function display($charaArray) {
        if(!empty($charaArray)) {
            echo '<div class="generalBgDeeper" id ="regionalResultMap">';
            ?>
            <div id="mapContainer"></div>
            <script type="text/javascript">
                var amap;
                window.init = function() {
                    amap = new AMap.Map('mapContainer', {
                        zoom : 6,
                        resizeEnable: true,
                        center: [111.040396,23.433842],                         //中心点坐标
                        mapStyle:'amap://styles/aec282517396368ba079797e8c90a25d'
                    });
                    <?PHP
                    #此处插入要显示的标签
                    for($num = 0; $num < count($charaArray); $num++) {
                    $longitude = $charaArray[$num]['longitude'];
                    $latitude = $charaArray[$num]['latitude'];
                    #此处CONTENT是MAKER标记位置的内容 去掉默认CONTENT是一个小大头针
                    ?>
                    var marker<?PHP echo $num; ?> = new AMap.Marker({
                            content: "<div></div>" ,
                        <?PHP
                        echo "position: [$longitude,$latitude],";
                        echo "content: \"<div class='locale-label'>";
                        for($charaNum = 0; $charaNum < (count($charaArray[$num]) - 5); $charaNum++) {
                            $initial = $charaArray[$num][$charaNum][1];
                            $nuclei = $charaArray[$num][$charaNum][2];
                            $coda = $charaArray[$num][$charaNum][3];
                            $tone = $charaArray[$num][$charaNum][4];
                            echo $initial.$nuclei.$coda.$tone.'<br>';
                        } #end for charanum
                        
                        ?>
                        </div>"
                })
                    marker<?PHP echo $num; ?>.setMap(amap);
                    
                    <?PHP
                    }#end for num
                    ?>
                }
            </script>
            <script src="https://webapi.amap.com/maps?v=1.4.8&key=ee088abc17a02cbebe4786e06dbd11f9&callback=init"></script>
            </div>
            <?PHP
        }
    }
}
?>