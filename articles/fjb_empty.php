<?PHP
include ("../const.php");
?>

<!DOCTYPE html>
<html lang='zh-hant'>
<head>
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=0.8, maximum-scale=1, minimum-scale=0.5">
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html" charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="description" content="泛粵大典旨在收集現時各地讀音與歷史韻書讀音，並提供向大眾一些基礎的查詢功能，同時通過泛粵表展示泛粵各地的特色字及其讀音">
    <meta name="keywords" content="粵語 泛粵 嶺南 Cantonese 廣州 粵語查詢 泛粵大典 粤语 粤典 Yue">
    <title>泛粵典 — 聲母 “” 與 “”</title>
    <link rel="stylesheet" type="text/css" href="../css/about.css?<?PHP echo rand(); ?>">
    <link rel="icon" href="../img/favicon.ico">
    <script src="../js/general.js"></script>

    <style>
        body {font-family:"Garamond","Adobe Ming Std","Adobe Song Std","澹雅明体A","Han Sans TC","Hiragino Sans GB","Microsoft JhengHei UI","Microsoft YaHei UI",sans-serif;font-size: 16px}
        p {text-indent: 2em}
        p > b {color: #d32913;}
        td > b {color: inherit;font-size: inherit;}
        table {border-collapse:collapse; text-align: center; border-color:#908471;border-width: 2px;}
        th {border-bottom-width: 2px;border-color:#908471;font-size: 1.2em}
        p, th {line-height: 2em}}
        i {color: darkslategrey;}
        i > .head {}
        :not(i) > .head {font-weight: bold;font-size: 1.5em;color: #d32913;line-height:1.5em}
        .sub { }
        .mean { text-align: left;padding: 1ex;line-height:1.2em}
        .A {background-color:rgb(193 227 255)}
        .B {background-color:rgb(173 251 158)}
        #container {padding-top: 100px;}
    </style>
</head>
<body>
    <div id="wrapper" class="wrapper">
    <?PHP Info::showSidenav(); ?>
        <div id="container" class="container" style="">
            <button class="sidenav-show-btn" onclick="showSidenav()"></button>

            <p>本文用於記錄主流粵語聲母“”和“”對立時各所轄者，但不包括容易寫出漢字的，如「」「」等，此類字聲母大多可由普通話簡單推出。因此，本文主要關注一般不出現在書面語的詞彙。</p>
            <p>下表中，<b>記音</b>列可能有兩列，此時前一列是綜合音，可忽略；<br>
            有些詞可能二聲母皆可，本文不置可否。</p>
            <p><b>字頭</b>列高亮者<b>不</b>代表本字或正字，而只是基於音、義來選出的相對較爲合理的字；併隨後的小號漢字可分別理解爲「優先字形」及「候選字形」；<br>
            <i>斜體者</i>代表該詞可能不出現在廣州話中，本文保留作爲參考；<br>
                以「Ｘ」表記的字頭表示本文未對該詞指定優先字形。</p>


<table border="1">
<caption>聲母 “” 與 “” 轄字表</caption>
<tr><th style="width: 2.5em;">韻母</th>
    <th style="width: 2.5em;">聲調</th>
    <th style="width: 2.5em;">聲母</th>
    <th colspan="2">記音</th>
    <th style="width: 4em;">字頭</th>
    <th>釋義</th></tr>









</table>

            <p>本表所有數據出自<a href="https://jyutdict.org/about#fjb">泛粵字表</a>，其是由民間各地人士協作而成。有誤漏煩請聯繫我們。</p>
            <?PHP Info::showFooter(); ?>
        </div>
    </div>
</body>
</html>