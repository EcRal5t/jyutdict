<!doctype html>
<?PHP
include("connectDB.php");
?>
<html lang="zh-cn">
<head>
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-16">
    <meta http-equiv="Cache-control" content="no-cache">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>泛粵大典</title>
    <link rel="stylesheet" type="text/css" href="./css/newindex.css?<?PHP echo rand(); ?>">
    <script src="./js/general.js?<?PHP echo rand(); ?>"></script>
<!--    <script src="./js/newindex.js?--><?PHP //echo rand(); ?><!--"></script>-->
<!--    <link rel="icon" href="./img/favicon.png">-->
</head>
<body <?PHP if (!empty($_REQUEST['pron'])) echo "onload=\"annexForm('#regionalResultTable', 1)\""; ?>>

<script>
    //[不安全]暂时先不在后端做检测了…
    var format = /^[a-z]{1,10}\d{0,2}$/;
    var initialFormat = /^(n[jg]?|bb?|dd?|[zcs][hrjl]?|[ptg]h?|[gk][wv]?|[hmqfvwjl])(?=[aeoiuy])/;
    var codaFormat = /[^^](n[ng]?|[mptkh])\d{0,2}$/;
    var toneFormat = /\d{1,2}$/;
    var vowelFormat = /^(ng|m|ii|uu|[iu][rw]?|[aeo][aorew]?|yw|yu$|y)/;
    
    
    function inputAnalyse(input) {                                  //划分粤拼音节
        var pron = input.value;
        for (let i=0; i<13; i++) {
            document.querySelector('#temp'+i).innerHTML = "";
        }

        if (format.test(pron)) {
            let tone = (pron.match(toneFormat) ? pron.match(toneFormat)[0] : "");
            let initial = (pron.match(initialFormat) ? pron.match(initialFormat)[1] : "");
            let coda = (pron.match(codaFormat) ? pron.match(codaFormat)[1] : "");
            let nuclei = pron.substr(initial.length, pron.length-initial.length-coda.length-tone.length);

            let vowels = [];                                        //用于存放划分得出的各个元音

            for (let count=0, pos=0; pos<nuclei.length; count++) {  //划分韵母
                if (nuclei.substr(pos).match(vowelFormat)) {
                    vowels[count] = nuclei.substr(pos).match(vowelFormat)[0];   //从前到后用正则检测元音
                } else {
                    document.querySelector('#inputText').setAttribute("class", "general-bg-deeper text-invalid");
                    document.querySelector('#inputButton').disabled = true;           //虽然[不安全]…放一下再改好点
                    return;                                                     //元音输入有误，直接退出并改输入框背景色
                }
                document.querySelector('#temp'+(count+4)).innerHTML = vowels[count];
                pos += vowels[count].length;                                    //划分出几个字母，就向后几个字母继续划分
            }

            document.querySelector('#temp0').innerHTML = initial;
            document.querySelector('#temp1').innerHTML = nuclei;
            document.querySelector('#temp2').innerHTML = coda;
            document.querySelector('#temp3').innerHTML = tone;
            document.querySelector('#inputInitial').value = initial;
            document.querySelector('#inputNuclei').value = nuclei;
            document.querySelector('#inputCoda').value = coda;
            document.querySelector('#inputTone').value = tone;

            document.querySelector('#inputText').setAttribute("class", "general-bg-deeper");///为了兼容IE9…
            document.querySelector('#inputButton').disabled = false;
            return;                                                 //划分成功，改背景色为正常，退出
        }
        //如果运行到此，说明输入框为空，或输入粤拼结构有误（此时才改输入框背景色）
        if (pron) document.querySelector('#inputText').setAttribute("class", "general-bg-deeper text-invalid");
        else document.querySelector('#inputText').setAttribute("class", "general-bg-deeper");
        document.querySelector('#inputButton').disabled = true;
    }
</script>

<div id="wrapper" class="wrapper">
    
    <div class="sidenav-overlay" onclick="hideSidenav()"></div>
    
    <div id="sidenav">
        <div id="sidenav-head"><span class="font-64">粤</span>dict</div>
        <ul id="sidenav-list">
            <li class="sidenav-link"><a href="./newindex.php">字</a></li>
            <li class="sidenav-link"><a href="./pron.php">韻</a></li>
            <li class="sidenav-link"><a href="newindex.php">放著先</a></li>
            <li class="divider"></li>
            <li class="sidenav-link"><a ><!--href="./about.php">-->關於</a></li>
        </ul>
    </div>
    
    <div id="container">
        <button class="sidenav-show-btn" onclick="showSidenav()"></button>
        
        <div id="searching">
            <form id="inputForm" class="clearfix" method="get">
                <input type="text" id="inputText" class="general-bg-deeper" name="pron" oninput="inputAnalyse(this);">
                <input type="hidden" id="inputInitial" name="in">
                <input type="hidden" id="inputNuclei" name="nu">
                <input type="hidden" id="inputCoda" name="co">
                <input type="hidden" id="inputTone" name="to">
                <input type="submit" id="inputButton" class="general-bg" value="耖" disabled>
            </form>
        </div>
        
        <div>
            <div style="background-color: #f11;display: inline;" id="temp0"></div>
            <div style="background-color: #fa1;display: inline;" id="temp1"></div>
            <div style="background-color: #af1;display: inline;" id="temp2"></div>
            <div style="background-color: #1f1;display: inline;" id="temp3"></div>
            <hr>
            <div style="background-color: #1fa;display: inline;" id="temp4"></div>
            <div style="background-color: #1af;display: inline;" id="temp5"></div>
            <div style="background-color: #11f;display: inline;" id="temp6"></div>
            <div style="background-color: #a1f;display: inline;" id="temp7"></div>
            <div style="background-color: #f1a;display: inline;" id="temp8"></div>
            <div style="background-color: #faa;display: inline;" id="temp9"></div>
            <div style="background-color: #ffa;display: inline;" id="temp10"></div>
            <div style="background-color: #afa;display: inline;" id="temp11"></div>
            <div style="background-color: #aff;display: inline;" id="temp12"></div>
            <div style="background-color: #aaf;display: inline;" id="temp13"></div>
        </div>
        <?PHP $countTime_begin = microtime(TRUE); ?>
        <div id="result">
            <div class="wanshyu-result">
<!--                <div class="fanwan-result ">-->
<!--                    <table class="general-form">-->
<!--                        <tr>-->
<!--                            <td class="column1-20" rowspan="3">分<br>韻</td>-->
<!--                            <td class="column2-20 font-14">njyut</td>-->
<!--                            <td class="column1-20 font-14">6</td>-->
<!--                            <td class="text-align-left">月</td>-->
<!--                        </tr>-->
<!--                -->
<!--                        <tr>-->
<!--                            <td class="font-14" rowspan="2">jyut</td>-->
<!--                            <td class="font-14">1</td>-->
<!--                            <td class="text-align-left">乙曰</td>-->
<!--                        </tr>-->
<!--                        <tr>-->
<!--                            <td class="font-14">6</td>-->
<!--                            <td class="text-align-left">粤越</td>-->
<!--                        </tr>-->
<!--                    </table>-->
<!--                </div>-->
<!--                <div class="jingwaa-result">-->
<!--                    <table class="general-form">-->
<!--                        <tr>-->
<!--                            <td class="column1-20" rowspan="3">英<br>華</td>-->
<!--                            <td class="column2-20 font-14" rowspan="2">jyut</td>-->
<!--                            <td class="column1-20 font-14">1</td>-->
<!--                            <td class="text-align-left">乙曰</td>-->
<!--                        </tr>-->
<!--                        <tr>-->
<!--                            <td class="column1-20 font-14" rowspan="2">6</td>-->
<!--                            <td class="text-align-left">粤越月</td>-->
<!--                        </tr>-->
<!--                    </table>-->
<!--                </div>-->
            </div>
            <div class="regional-result">
                <table id="regionalResultTable" class="general-form">
                    <?PHP
                    
                    if (!empty($_REQUEST["pron"])) {
                        $initial = $_REQUEST["in"];     //[不安全]…到时再改吧…
                        $nuclei  = $_REQUEST["nu"];
                        $coda    = $_REQUEST["co"];
                        $tone    = $_REQUEST["to"];
                        $query_inCityList_sql   = "SELECT * FROM `IAreaList`";                      //获取城市（地点）列表
                        $query_inCityList_query = mysqli_query($con, $query_inCityList_sql);
                        
                        
                        while (is_array($cityList = mysqli_fetch_row($query_inCityList_query))) {   //对每个地点：
                            $query_inCity_sql   = "SELECT * FROM `" . $cityList[6] . "` WHERE `nuclei`='" . $nuclei . "'";
                            $query_inCity_sql  .= " AND `initial`='" . $initial . "' AND `coda`='" . $coda . "' ";
                            if (!empty($tone) AND $tone!="*") {
                                $query_inCity_sql .= " AND `tone`='" . $tone . "'";
                            }
        
                            $allPron = [];
        
                            $query_inCity_query = mysqli_query($con, $query_inCity_sql);
                            while (is_array($inCityPron = mysqli_fetch_row($query_inCity_query))) {    //对每个符合条件的字：以jyut6为例
                                $pron = $inCityPron[2].$inCityPron[3].$inCityPron[4];                  //$pron==="jyut", $allPron["jyut"][6]==="月粤越…"
                                $allPron[$pron][$inCityPron[5]] = empty($allPron[$pron][$inCityPron[5]]) ? $inCityPron[1] : $allPron[$pron][$inCityPron[5]].$inCityPron[1];
                            }
        
                            foreach ($allPron as $pron => $toneArray) {
                                foreach ($toneArray as $toneInPron => $chara) {
                                    ?>
                                    <tr>
                                        <td class="column2-20 min-width60"><?PHP echo $cityList[5]; ?></td>
                                        <td class="column2-20 min-width60 font-14"><?PHP echo $pron; ?></td>
                                        <td class="column1-20 max-width2-20 font-14"><?PHP echo $toneInPron; ?></td>
                                        <td class="text-align-left"><?PHP echo $chara; ?></td>
                                    </tr>
                                    <?PHP
                                }
                            }
                        }
                    }
                    
                    ?>
                </table>
            </div>
        </div>
        <?PHP
        $countTime_end   = microtime(TRUE);
        print_r(round(($countTime_end - $countTime_begin)*1000, 3));
        ?>
        ms
    </div>
</div>
</body>
</html>