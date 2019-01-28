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
                echo <<<'trad2sim'
<div class="generalBgDeeper" id="charaSimToTrad">
	<span id="charaSimToTradHead">简转繁</span>
	<span id="charaSimToTradMain">
trad2sim;
                unset($charaArray[0]);
                foreach($charaArray as $chara)
                {
                    echo " <a href=\"newindex.php?character=" . $chara . "\">" . $chara . "</a>";
                }//end foreach
                echo <<<'trad2sim'
    </span>
</div>
trad2sim;
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

    function query($character, $con)
    {
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
        for($result = mysqli_fetch_row($resultSet);
        is_array($result);
        $result = mysqli_fetch_row($resultSet))
        {
            $resultArray[] = $result;#插入结果数列到下一维度
        }//end for
        return $resultArray;#返回结果数列，会可能返回一个空的数列
    }//end function query

    function show($charArray)
    {
        if(empty($charArray))
        {
            echo "<span style='font-size: 20px;'>冇見有</span>";
        }else{
            foreach ($charArray as $query_inFanwan_result) #将数组一个个输出
            {
                echo <<< DISPLAY
              <table class="generalForm" id="wanshyuResultFanwan">
                  <tr>
                      <td width="12.5%">序號</td>
                      <td width="50%">韻部 - 小韻</td>
                      <td width="25%">聲-韻-調</td>
                      <td rowspan="2" class="hlFontRed">
DISPLAY;
                if ($query_inFanwan_result[9] <> '0')
                  echo $query_inFanwan_result[9];
                echo $query_inFanwan_result[10] . $query_inFanwan_result[11];
                echo <<< DISPLAY
                      </td>
                  </tr>
                  <tr>
                      <td>$query_inFanwan_result[0]</td>
                      <td>$query_inFanwan_result[1]-$query_inFanwan_result[4]</td>
                      <td>$query_inFanwan_result[6]-$query_inFanwan_result[7]-$query_inFanwan_result[8]</td>
                  </tr>
                  <tr>
                      <td colspan="5">$query_inFanwan_result[5]</td>
                  </tr>
              </table>
DISPLAY;
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

    function query($character, $con)
    {
          $query_inJingwaa_sql   = "SELECT  `id`, `page`, `stroke`, `order_st`,
           `order_nd`, `exstroke`, `radical`, `chara`, `bopo_tr`, `bopo`, `bopo_nd_tr`,
           `bopo_nd`, `state`
          FROM `Jingwaa`
          WHERE `chara`='" . $character . "'";

          $query_inJingwaa_query = mysqli_query($con, $query_inJingwaa_sql);
          $JingwaaArray          = [];
        for($result = mysqli_fetch_row($query_inJingwaa_query);
        is_array($result);
        $result = mysqli_fetch_row($query_inJingwaa_query))
        {
            $JingwaaArray[] = $result;#插入结果数列到下一维度
        }//end for
        return $JingwaaArray;
    }

    function show($charArray)
    {
        if(empty($charArray)) echo "<span style='font-size: 20px;'>冇見有</span>";
        else{
            echo <<<DISPLAY
<table class="generalForm" id="wanshyuResultYingwaa">
    <tr>
        <td width="12.5%">序號</td>
        <td width="12.5%">葉碼</td>
        <td width="25%">筆畫</td>
        <td width="25%">原標音</td>
        <td rowspan="2" class="hlFontRed">
DISPLAY;
            foreach ($charArray as $key => $JingwaaArray)
            {
                if($key > 0) echo '<br>';
                echo($JingwaaArray[12] ? "<i>$JingwaaArray[8]</i>" : $JingwaaArray[8]);
                echo(($JingwaaArray[10] <> '_NULL') ? ' (' . $JingwaaArray[10] . ')' : '');
            }
            echo <<<DISPLAY
        </td>
    </tr>
    <tr>
        <td>
DISPLAY;
            foreach ($charArray as $key => $JingwaaArray)
            {
                if($key > 0) echo '<br>';
                echo $JingwaaArray[0];
            }
            echo "</td>";
            echo "<td>".$charArray[0][1]."</td>";
            echo "<td>".$charArray[0][2] . '(' . $charArray[0][6] . ')+' . $charArray[0][5]."</td>"; #发现要调用第0项虽然不知道用意还是把原数组给弄上来了
            echo "<td>";
            foreach ($charArray as $key => $JingwaaArray)
            {
                if($key > 0) echo '<br>';
                echo($JingwaaArray[12] ? "<i> $JingwaaArray[9] </i>" : $JingwaaArray[9]);
                echo(($JingwaaArray[11] <> '_NULL') ? ' (' . $JingwaaArray[11] . ')' : '');
            }
            echo <<< DISPLAY
      </td>
  </tr>
  </table>
DISPLAY;
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
        if (!self::$instance instanceof SingleInstance) {
            self::$instance = new SingleInstance();
        }
        return self::$instance;
    }
    private function getCityList($con)
    {
        $query_inCityList_sql   = "SELECT
        `longitude`, `latitude`,
         `oneth`, `forth`, `third`,
          `sheetname`
          FROM `IAreaList`"; #獲取城市信息
          $query_inCityList_query = mysqli_query($con, $query_inCityList_sql);
          $citylistArray = [];
          for($citylist = mysqli_fetch_row($query_inCityList_query);
          is_array($citylist);
          $citylist = mysqli_fetch_row($query_inCityList_query))
          {
            $citylistArray[] = $citylist;
          }
          #var_dump($citylistArray);
          return $citylistArray;
    }
    public function query($character,$con)
    {
        $citylist = $this->getCityList($con);
        $resultArray = [];
        foreach ($citylist as $eachCitylist)
        {
            $result = ["longitude"=>$eachCitylist[1], "latitude"=>$eachCitylist[1],
            "oneth"=>$eachCitylist[2],"forth"=>$eachCitylist[3],
            "third"=>$eachCitylist[4]];#一个数组五个键名
            $jamArray = $this->jamquery($character, $eachCitylist[5]);
            if(!empty($jamArray))
            {
                $resultArray[] = array_merge($result,$jamArray);
            }
        }
        return $resultArray;
    }
    private function jamquery($character,$sheetname)
    {
        $resultArray = [];
        $query_sql =
        "SELECT
        'chara','initial','nuclei','coda','tone','ipa','note'
        FROM"
        .$sheetname.
        "WHERE
        chara = '".$character."'";
        $resultSet = mysqli_query($con, $query_sql);
        for($result = mysqli_fetch_row($resultSet);
        is_array($resultSet);
        $result = mysqli_fetch_row($resultSet))
        {
            $resultArray[] = $result;
        }
        return $resultArray;
    }
    public function show($charaArray)
    {
        if(!empty($charaArray)){
            $character = $charaArray[0][0];
            echo <<<show
<div id="regionalResult">
    <div class="generalBgDeeper" id="regionalResultForm">
        <table class="generalForm">
        <tr>
            <td style='font-size: 22px; height: 36px;' colspan='4'>$character</td>
        </tr>
show;
            for($num = 0;$num < (count($charaArray) - 1);$num++)
            {
                $locOneth = $charaArray[$num]["oneth"];
                $locThird = $charaArray[$num]["third"];
                $red =  $charaArray[$num]
                $green
                $blue
                $purple
                $cyan
                $charaArray[$num]
            }
        }
    }
    function display($charArray) {

    }

}
?>