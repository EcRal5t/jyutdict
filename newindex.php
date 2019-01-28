<!DOCTYPE html>
<?PHP
  include ("connectDB.php");
  include ("fun/writeLog.inc.php");
?>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-16">
    <meta http-equiv="Cache-control" content="no-cache">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TITLE</title>
    <link rel="stylesheet" type="text/css" href="./css/newcss.css">
    <link rel="icon" href="./img/favicon.png">
    <style type="text/css"></style>
    <script src="./js/index.js?<?PHP echo rand(); ?>"></script>
    <script src="./js/navBarToggle.js"></script>
</head>

<body onload="initial(0);judgeHiddenNavBar();annexForm('locOneth');annexForm('locThird');" onresize="">

<div id="wrapper" class="wrapper" >
    <div id="leftNavBar" class="leftNavBar">
        <div id="highlightRectangle"></div>
        <ul style="height: 100%;">
            <li id="topLOGO" class="topLOGO">
                <span style="font-size: 16px; line-height: 24px;">
                    <br><br><br><span style="font-size: 50px;">粵</span> Dict<br><br><br>
                </span>
            </li>
            <li style="background: #3E6CBE; z-index: 6;">
                <a href=""><span>字</span></a>
            </li>
            <li style="background: #3B67B5; z-index: 5;">
                <a href=""><span>韻</span></a>
            </li>
            <li style="background: #3861AC; z-index: 4;">
                <a><span>丙</span></a>
            </li>
            <li style="background: #355CA3; z-index: 3;">
                <a href=""><span>丁</span></a>
            </li>
            <li style="background: #32579A; z-index: 2;">
                <a><span>關於</span></a>
            </li>
            <li class="bottom bottom1">
                <a onclick=""><span>壬</span></a>
            </li>
            <li class="bottom bottom0">
                <a><span>v0.8.26</span></a>
            </li>
        </ul>
    </div>

    <div id="container" class="container">
      <input type="button" value="≡" id="toggleLeftNavBar" onclick="toggleLeftNavBar()">
      <?PHP //设置变量 submitChara 为 提交的字符
        //        writeLog("Locate: 1, open: ".var_export($_GET, true), ".");
        $editMode = isset($_GET['editmode']);
        if (!empty($_GET['character'])) {
            $submitChara = $_GET['character'];
            $submitChara = mb_substr($submitChara, 0, 1, 'utf8');
        } else {
            $submitChara = "粵";
        }
      ?>

      <div id="searching">
          <form class="inputForm">
              <input type="text" class="inputText generalBgDeeper" name="character" <?PHP echo "value=\"$submitChara\""; ?>>
              <input type="submit" class="inputButton generalBg" value="耖">
              <?PHP
                if ($editMode)
                  echo '<input style="display: none;" name="editmode">';
 ?>

          </form>
      </div>
      <?PHP
        include ("Lookup.class.php");
        if (!empty($_GET['character'])) {
            $sim2trad = Sim2TradLookup::getInstance();          #获取简繁转换对象
            $charaArray = $sim2trad -> query($submitChara, $con);
            #var_dump($charaArray);
            $charaCount = count($charaArray);
            if ($charaCount > 2) $sim2trad -> show($charaArray);
        }
      ?>
      <?PHP
      if ($charaCount > 2) $charaCount = 1;
          for ($i = 0; $i < $charaCount; $i++) {
      ?>
          <div id="wanshyuResult">
              <div class="generalBgDeeper" id="charaHead">
                  <div class="generalBg" id="charaHeadSqu"><span style="top: -10px;"><?PHP echo "$charaArray[$i]" ?></span></div>

                  <?PHP
                  $query_inKuangyon_sql = "SELECT initial,rimeclass,
                                          rime,division,rouding,tone,
                                          transliteration
                                          FROM `YKuangyon` WHERE `chara`='" . $charaArray[$i] . "'";

                    $query_inKuangyon_query = mysqli_query($con, $query_inKuangyon_sql);
                    while (is_array($query_inKuangyon_result = mysqli_fetch_row($query_inKuangyon_query))) {
                    echo '<div id="oldPronounce"><span>';
                    echo '<span style="background: gray; color: white;">中</span>'
                    . $query_inKuangyon_result[0] . $query_inKuangyon_result[1]
                    . $query_inKuangyon_result[2] . $query_inKuangyon_result[3]
                    . $query_inKuangyon_result[4] . $query_inKuangyon_result[5]
                    . ' (' . $query_inKuangyon_result[6] . ')';
                    echo "</span></div>";
                  }
                  ?>

              </div>
              <div id="wanshyuResultForm">
                  <div class="wanshyuResultBlock generalBgDeeper" style="margin-bottom: 3px;">
                      <div class="wanshyuResultFormHead" style="height: 81px;"><span>分<br>韻</span></div>
                      <?PHP
                      $fanWan = FanWanDict::getInstance();
                      $fanWan->show($fanWan->query($charaArray[$i], $con));
                      ?>
                  </div>
                  <div class="wanshyuResultBlock generalBgDeeper" style="margin-top: 12px;">
                      <div class="wanshyuResultFormHead" style="height: 54px;"><span>英<br>華</span></div>
                      <?PHP
                      $jing = JingWaaDict::getInstance();
                      $jing->show($jing->query($charaArray[$i], $con));
                        ?>
                  </div>
              </div>
          </div>
          <?PHP
            }#END for ($i = 0; $i < $charaCount; $i++)
          ?>
          <div id="regionalResult">
              <div class="generalBgDeeper" id="regionalResultForm">
                  <table class="generalForm">
                      <?PHP
                      if ($charaCount <= 2) {
                          for ($i = 0; $i < $charaCount; $i++) {
                              $query_inCityList_sql   = "SELECT * FROM `IAreaList`";
                              $query_inCityList_query = mysqli_query($con, $query_inCityList_sql);
                              ?>

                              <tr>
                                  <td style='font-size: 22px; height: 36px;' colspan='4'><?PHP echo $charaArray[$i]; ?></td>
                              </tr>
                              <?PHP
                              while (is_array($cityList = mysqli_fetch_row($query_inCityList_query))) {
                                  $query_inCity_sql   = "SELECT * FROM `" . $cityList[6] . "` WHERE `chara`='" . $charaArray[$i] . "'";
                                  $query_inCity_query = mysqli_query($con, $query_inCity_sql);
                                  while (is_array($inCityPron = mysqli_fetch_row($query_inCity_query))) {
                                      ?>

                                      <tr>
                                          <td class='locOneth' style="width: 20%"><?PHP echo $cityList[3]; ?></td>
                                          <td class='locThird' style="width: 15%"><?PHP echo $cityList[5]; ?></td>
                                          <td>
                                              <?PHP
                                            echo '<span class="hlFontRed">' . $inCityPron[2] . '</span>';
                                            echo '<span class="hlFontGreen">' . $inCityPron[3] . '</span>';
                                            echo '<span class="hlFontBlue">' . $inCityPron[4] . '</span>';
                                            echo '<span class="hlFontPurple">' . $inCityPron[5] . '</span>';
                                            echo '<span class="hlFontCyan" style="font-size: 0.9em;"> /' . $inCityPron[6] . '/</span>';
                                              ?>
                                          </td>
                                          <td class="tips"> <?PHP
                                            if ($editMode) {
                                              echo "<span style='color: gray; font-size: 9px; float:left;' onclick='editPron([";
                                              echo "\"$inCityPron[2]\", \"$inCityPron[3]\", \"$inCityPron[4]\", \"$inCityPron[5]\", ";
                                              echo "\"" . substr($cityList[6], 1) . "\", \"$inCityPron[0]\", \"$inCityPron[6]\"";
                                              echo "])'>改音</span>";
                                            }

                                            if (mb_strlen($inCityPron[7], 'UTF8') > 4) {
                                              echo mb_substr($inCityPron[7], 0, 4, 'utf8') . "…";
                                              echo "<span class='tipsMain'>$inCityPron[7]</span>";
                                            } else
                                              echo $inCityPron[7];

//                                                if ($editMode) {
//                                                    echo "<span style='color: gray; font-size: 9px; float:left;' onclick='editPronNote([";
//                                                    echo "\"" . substr($cityList[6], 1) . "\", \"$inCityPron[0]\", \"$inCityPron[7]\"";
//                                                    echo "])'>改註</span>";
//                                                }
                                              ?>

                                          </td>
                                      </tr>
                                      <?PHP
                                        }
                                        }
                                        echo "<tr><td colspan='4' class='locOneth locThird'>&nbsp;</td></tr>";
                                        }
                                        }
                      ?>

                  </table>
              </div>
              <div class="generalBgDeeper" id="regionalResultMap">
                  <span style="margin: auto;">地圖</span>
              </div>
          </div>
    </div>
</div>
</body>

</html>