<?PHP
include ("const.php");
include ("connectDB.php");
include ("Lookup.class.php");
include_once ("Jyutping.class.php");
?>

<!doctype html>
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
    <link rel="stylesheet" type="text/css" href="./css/pron.css?<?PHP echo rand(); ?>">
    <link rel="icon" href="./img/favicon.ico">
    
    <script src="./js/general.js?<?PHP echo rand(); ?>"></script>
</head>

<?PHP

//$options     为 勾选框选项
//        writeLog("Locate: 1, open: ".var_export($_REQUEST, true), ".");
$options = ["wanshyu"=>0,"area"=>0];
if (isset($_REQUEST['option'])) {
    foreach ($_REQUEST['option'] as $value) {
        $options[$value] = 1;
    }
} else $options = ["wanshyu"=>1,"area"=>1];

$submitPron =  (isset($_REQUEST["pron"]) ? $_REQUEST["pron"] : "");

//$countTime_begin = microtime(TRUE);
$jyutping = new Jyutping();
$valid = (isset($_REQUEST["in"], $_REQUEST["nu"], $_REQUEST["co"], $_REQUEST["to"]) &&
    $jyutping->set($_REQUEST["in"], $_REQUEST["nu"], $_REQUEST["co"], $_REQUEST["to"]));
if ($valid) {
    $initial = $_REQUEST["in"];
    $nuclei  = $_REQUEST["nu"];
    $coda    = $_REQUEST["co"];
    $tone    = ($_REQUEST["to"]==="" ? "%" : $_REQUEST["to"]);
}
?>

<body>

<script>
    var format = /^[a-z%]{1,10}\d{0,2}$/;
    var initialFormat = /^(n[jg]?|bb?|dd?|[zcs][hrjl]?|[ptg]h?|[gk][wv]?|[hmqfvwjl]|%)(?=[aeoiuy%])/;
    var codaFormat    = /[aoreiwu%](n[ng]?|[mptkh]|%)(\d{0,2})$/;
    var toneFormat    = /\d{1,2}$/;
    var vowelFormat   = /^(ng$|m$|ii|uu|[iu][rw]?|[aeo][aorew]?|yw|yu$|y|%$)/;
    
    
    function inputAnalyse(input) {                                  //划分粤拼音节
        var pron = input.value;
        for (var i=0; i<13; i++) {
            document.querySelector('#temp'+i).innerHTML = "";
        }
        
        if (pron.split("%").length<4 && format.test(pron)) {
            var tone    = (pron.match(toneFormat) ? pron.match(toneFormat)[0] : "");
            var initial = (pron.match(initialFormat) ? pron.match(initialFormat)[1] : "");
            var coda    = (pron.match(codaFormat) ? pron.match(codaFormat)[1] : "");
            var nuclei  = pron.substr(initial.length, pron.length-initial.length-coda.length-tone.length);
            
            var vowels = [];                                        //用于存放划分得出的各个元音
            
            for (var count=0, pos=0; pos<nuclei.length; count++) {  //划分韵母
                if (nuclei.substr(pos).match(vowelFormat)) {
                    vowels[count] = nuclei.substr(pos).match(vowelFormat)[0];   //从前到后用正则检测元音
                } else {
                    document.querySelector('#inputText').setAttribute("class", "general-bg-deeper text-invalid");
                    document.querySelector('#inputButton').disabled = true;
                    return;                                                     //元音输入有误，改输入框背景色并直接退出
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
    <?PHP Info::showSidenav(); ?>
    
    <div id="container">
        <button id="sidenav-show-btn" class="sidenav-show-btn"></button>
        
        <div id="searching">
            <form id="inputForm" class="clearfix" method="post">
                <label><input type="text" id="inputText" autocomplete="off" class="general-bg-deeper alphabet" name="pron"></label>
                <input type="hidden" id="inputInitial" name="in">
                <input type="hidden" id="inputNuclei" name="nu">
                <input type="hidden" id="inputCoda" name="co">
                <input type="hidden" id="inputTone" name="to">
                <input type="submit" id="inputButton" class="general-bg" value="耖" disabled>
                <div id="inputCheck" style="">
                    <label><input name="option[]" type="checkbox" value="wanshyu" <?PHP if ($options["wanshyu"]) echo "checked"; ?>>韻書音 </label>
                    <label><input name="option[]" type="checkbox" value="area" <?PHP if ($options["area"]) echo "checked"; ?>>地方音 </label>
                </div>
            </form>
        </div>
        
        <div style="text-align: center;font-size: 0.5em;">
            <div style="background-color: #ff111177;display: inline;" id="temp0"></div>&nbsp;
            <div style="background-color: #ffaa1177;display: inline;" id="temp1"></div>&nbsp;
            <div style="background-color: #aaff1177;display: inline;" id="temp2"></div>&nbsp;
            <div style="background-color: #11ff1177;display: inline;" id="temp3"></div>&nbsp;
            <div style="background-color: #11ffaa77;display: inline;" id="temp4"></div>
            <div style="background-color: #11aaff77;display: inline;" id="temp5"></div>
            <div style="background-color: #1111ff77;display: inline;" id="temp6"></div>
            <div style="background-color: #aa11ff77;display: inline;" id="temp7"></div>
            <div style="background-color: #ff11aa77;display: inline;" id="temp8"></div>
            <div style="background-color: #ffaaaa77;display: inline;" id="temp9"></div>
            <div style="background-color: #ffffaa77;display: inline;" id="temp10"></div>
            <div style="background-color: #aaffaa77;display: inline;" id="temp11"></div>
            <div style="background-color: #aaffff77;display: inline;" id="temp12"></div>
            <div style="background-color: #aaaaff77;display: inline;" id="temp13"></div>
            <br>
        </div>
        
        <div id="result">
            <?PHP
            if ($valid && $options["wanshyu"]) {
                ?>
                <div class="wanshyu-result">
                    <table id="wanshyuResultTable" class="general-form annex-form">
                        <?PHP
                        $inWanshyuList_sql   = "
                            SELECT `name`, `sheetname`
                            FROM `IWanshyuList`";                      //獲取韻書列表
                        $inWanshyuList_stmt = $dbh->prepare($inWanshyuList_sql);
                        $inWanshyuList_stmt->execute();
                        $wanshyuListArray = $inWanshyuList_stmt->fetchAll(PDO::FETCH_ASSOC);
                        $inWanshyu_sql = "
                            SELECT `id`, `chara`, `initial`, `nuclei`, `coda`, `tone`
                            FROM `%s`
                            WHERE `nuclei` LIKE :nuclei
                              AND `initial` LIKE :initial
                              AND `coda` LIKE :coda
                              AND `tone` LIKE :tone";
                        foreach ($wanshyuListArray as $eachWanshyu)  {   //对每个韻書：
                            $inWanshyu_stmt = $dbh->prepare(sprintf($inWanshyu_sql, $eachWanshyu['sheetname']));
                            $inWanshyu_stmt->execute(array(
                                ':initial'=>$initial,
                                ':nuclei'=>$nuclei,
                                ':coda'=>$coda,
                                ':tone'=>$tone,
                            ));
                            $inWanshyu_result = $inWanshyu_stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            $allPron = [];
                            
                            foreach ($inWanshyu_result as $inWanshyuPron) {//对每个符合条件的字：以jyut6为例
                                $pron = $inWanshyuPron['initial'].$inWanshyuPron['nuclei'].$inWanshyuPron['coda'];                  //$pron==="jyut", $allPron["jyut"][6]==="月粤越…"
                                $allPron[$pron][$inWanshyuPron['tone']] = empty($allPron[$pron][$inWanshyuPron['tone']]) ? $inWanshyuPron['chara'] : $allPron[$pron][$inWanshyuPron['tone']].$inWanshyuPron['chara'];
                            }
                
                            foreach ($allPron as $pron => $toneArray) {
                                foreach ($toneArray as $toneInPron => $chara) {
                                    ?>
                                    <tr>
                                        <td class="column2-20 min-width45 line-height1em">
                                            <?PHP
                                            echo $eachWanshyu['name'];
                                            ?>
                                        </td>
                                        <td class="column2-20 min-width60 font-14 alphabet"><?PHP echo $pron; ?></td>
                                        <td class="column1-20 max-width2-20 font-14"><?PHP echo $toneInPron; ?></td>
                                        <td class="text-align-left"><?PHP echo $chara; ?></td>
                                    </tr>
                                    <?PHP
                                }
                            }
                        }
                        ?>
                    </table>
                </div>
                <?PHP
            }
            
            if ($valid && $options["wanshyu"] && $options["area"]) echo '<div style="margin-top: 13px;"></div>';
            
            if ($valid && $options["area"]) {
                ?>
                <div class="regional-result">
                    <table id="regionalResultTable" class="general-form annex-form">
                        <?PHP
                        $inCityList_sql   = "
                            SELECT `longitude`, `latitude`, `first`, `second`, `third`, `sheetname`
                            FROM `IAreaList`";                      //獲取韻書列表
                        $inCityList_stmt = $dbh->prepare($inCityList_sql);
                        $inCityList_stmt->execute();
                        $cityListArray = $inCityList_stmt->fetchAll(PDO::FETCH_ASSOC);
                        $inCity_sql = "
                            SELECT `id`, `chara`, `initial`, `nuclei`, `coda`, `tone`
                            FROM `%s`
                            WHERE `nuclei` LIKE :nuclei
                              AND `initial` LIKE :initial
                              AND `coda` LIKE :coda
                              AND `tone` LIKE :tone";
                        foreach ($cityListArray as $eachCity)  {   //对每个地点：
                            $inCity_stmt = $dbh->prepare(sprintf($inCity_sql, $eachCity['sheetname']));
                            $inCity_stmt->execute(array(
                                ':initial'=>$initial,
                                ':nuclei'=>$nuclei,
                                ':coda'=>$coda,
                                ':tone'=>$tone,
                            ));
                            $inCity_result = $inCity_stmt->fetchAll(PDO::FETCH_ASSOC);
    
                            $allPron = [];
    
                            foreach ($inCity_result as $inCityPron) {//对每个符合条件的字：以jyut6为例
                                $pron = $inCityPron['initial'].$inCityPron['nuclei'].$inCityPron['coda'];                  //$pron==="jyut", $allPron["jyut"][6]==="月粤越…"
                                $allPron[$pron][$inCityPron['tone']] = empty($allPron[$pron][$inCityPron['tone']]) ? $inCityPron['chara'] : $allPron[$pron][$inCityPron['tone']].$inCityPron['chara'];
                            }
        
                            foreach ($allPron as $pron => $toneArray) {
                                foreach ($toneArray as $toneInPron => $chara) {
                                    ?>
                                    <tr>
                                        <td class="column2-20 min-width45 line-height1em">
                                            <?PHP
                                            echo $eachCity['second'];
                                            if (!empty($eachCity['third'])) echo "<span class='hl-font-cyan font-0p9em'>".$eachCity['third']."</span>";
                                            ?>
                                        </td>
                                        <td class="column2-20 min-width60 font-14 alphabet"><?PHP echo $pron; ?></td>
                                        <td class="column1-20 max-width2-20 font-14"><?PHP echo $toneInPron; ?></td>
                                        <td class="text-align-left"><?PHP echo $chara; ?></td>
                                    </tr>
                                    <?PHP
                                }
                            }
                        }
                        ?>
                    </table>
                </div>
                <?PHP
            }
            ?>
        </div>
        <?PHP //print_r(round((microtime(TRUE) - $countTime_begin)*1000, 3)); ?>
    </div>
</div>

<script type="text/javascript" src="//js.users.51.la/20205743.js"></script>
<script>
    document.querySelector('#inputText').oninput = function () {
        inputAnalyse(this);
    };
    document.querySelector('#sidenav-show-btn').onclick = function() {
        showSidenav();
    };
    window.onload = function () {
        annexTableShell('.annex-form', 1);
    }
</script>
</body>
</html>