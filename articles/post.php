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
    <title>泛粵典 — 聲母 “n” 與 “l”</title>
    <link rel="stylesheet" type="text/css" href="../css/about.css?<?PHP echo rand(); ?>">
    <link rel="icon" href="../img/favicon.ico">
    <script src="../js/general.js"></script>

    <style>
        body {font-family:"Garamond","Adobe Ming Std","Adobe Song Std","澹雅明体A","Han Sans TC","Hiragino Sans GB","Microsoft JhengHei UI","Microsoft YaHei UI",sans-serif;font-size: 16px}
        p {text-indent: 2em}
        p > b {color: #d32913;}
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
            <h1>紀文</h1>
            <h3>2023·08</h3>
            <p><a href="fjb_n-l">主流粵語聲母 “n” 與 “l” 轄字表</a></p>
            <p><a href="fjb_ng-0">主流粵語聲母 “ng” 與 “0”（零聲母）轄字表</a></p>
            <?PHP Info::showFooter(); ?>
        </div>
    </div>
</body>
</html>