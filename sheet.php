<?PHP
include ("const.php");



?>

<!DOCTYPE html>
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
    <link rel="stylesheet" type="text/css" href="./css/index.css">
    <link rel="icon" href="./img/favicon.ico">
    
    <script src="./js/general.js"></script>
</head>

<body style="overflow: hidden;">

<script>
    function changeFrameHeight(){
        var iframe= document.getElementById("sheet");
        iframe.height=document.documentElement.clientHeight;
    }
    
    window.onresize=function(){
        changeFrameHeight();
    };
</script>


<div id="wrapper" class="wrapper">
    <?PHP Info::showSidenav(); ?>
    <button class="sidenav-show-btn" onclick="showSidenav()"></button>
    <iframe id="sheet" width="100%" height="100%" src="https://docs.google.com/spreadsheets/d/1jwpB2pKBM0OfONJXHCRIvAhEl4cTEobRdbw6z1PajCQ/edit?usp=sharing" frameborder="0" scrolling="no" onload="changeFrameHeight()" ></iframe>
</div>
</body>

</html>