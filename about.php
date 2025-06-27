<?php
// about.php

// 1. 包含必要的依赖文件
include("const.php");
include("Parsedown.php"); // 确保 Parsedown.php 文件在你的项目中

// 2. 创建 Parsedown 实例
$parsedown = new Parsedown();

// 3. 读取 Markdown 文件内容
$markdownContent = file_get_contents('about.md');

// 4. 将 Markdown 转换为 HTML
$htmlContent = $parsedown->text($markdownContent);

?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta http-equiv="Cache-control" content="no-cache">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="description" content="泛粵大典旨在收集現時各地讀音與歷史韻書讀音，並提供向大眾一些基礎的查詢功能，同時通過泛粵表展示泛粵各地的特色字及其讀音">
    <meta name="keywords" content="粵語 泛粵 嶺南 Cantonese 廣州 粵語查詢 泛粵大典 粤语 粤典 Yue">
    <title>泛粵大典</title>
    <link rel="stylesheet" type="text/css" href="./css/about.css?<?php echo rand(); ?>">
    <link rel="icon" href="./img/favicon.ico">
    <script src="./js/general.js"></script>
</head>
<style>
    b, strong { color: #D32913 }
    .info>p { margin-top: 0.5em; }
</style>
<body>

<div id="wrapper" class="wrapper">
    <?php Info::showSidenav(); ?>
    <div id="container" class="container">
        <button class="sidenav-show-btn" onclick="showSidenav()"></button>
        
        <div class="info">
            <?php echo $htmlContent; ?>
        </div>

        <?php Info::showFooter(); ?>
    </div>
</div>
</body>

</html>