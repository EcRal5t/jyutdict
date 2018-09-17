<!DOCTYPE html>
<html>
<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta http-equiv="Cache-control" content="no-cache">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>大粵之典</title>
    <link rel="stylesheet" type="text/css" href="./css/main.css">
    <link rel="icon" href="./img/favicon.png">
    <style type="text/css">


    </style>
    <?PHP
    include("connectDB.php");
    ?>
</head>


<body>
<ul class="sidenav" id="sidenavLeft">
    <li><a>AAA</a></li>
    <li><a>BBB</a></li>
    <li><a>CCC</a></li>
    <li><a>DDD</a></li>
</ul>



<nav>
    <a class="home" href="./index.php">粵典</a>
    <a href="./search.php">詞彙</a>
    <a>aa</a>
    <a>bb</a>
    <?PHP
    if (isset($_COOKIE["login"])) {
        echo '<a href="discuss.php">討論串</a>';
    } else echo '<a>cc</a>';
    ?>

    <a class="about" href="about.php">關於</a>
</nav>


<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>
<div class="content">
    SampleText1 SampleText2 SampleText3 SampleText4 SampleText5 SampleText6
    <br>
    aa<sup>SampleText</sup>
</div>
<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>
</body>
</html>