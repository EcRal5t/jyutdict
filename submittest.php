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
<nav>
    <a class="home" href="index.php">粵典</a>
    <a href="search.php">詞彙</a>
    <a>aa</a>
    <a>bb</a>
    <?PHP
    if (isset($_COOKIE["login"])) {
        echo '<a href="discuss.php">討論串</a>';
    } else echo '<a>cc</a>';
    ?>

    <a class="about" href="about.php">關於</a>
</nav>



<br><br><br>
<div class="container">
    <div style="flex: 1;">　　　　</div>
    <div style="flex: 5;">
        <s><h1>要先修改&lt;a>爲絕對路徑或當前目錄的上一級！！！！！！</h1></s>
        <h2>不要覆盖掉原来的文件就行了</h2>
        <form action="" method="post" enctype="multipart/form-data">
            <input type="file" name="phpFile" id="file" class="aline"/>
            <input type="submit" name="subFile" value="上传" />
        </form>

        <?PHP
        if (!empty($_POST['subFile'])) {
            $fileName = $_FILES["phpFile"]["name"];
            echo "<hr>";
            echo "Upload: " . $fileName . "<br>";
            echo "Type: " . $_FILES["phpFile"]["type"] . "<br>";
            echo "Size: " . ($_FILES["phpFile"]["size"] / 1024.0) . " KB<br>";

            if (!file_exists($fileName)) {
                $fileNameWithFix = "sandbox/" . $fileName;
                move_uploaded_file($_FILES["phpFile"]["tmp_name"], $fileNameWithFix);
                echo "成功<br>";
                echo "<a href='sandbox/" . $_FILES["phpFile"]["name"] . "'>打开</a>";
            } else {
                echo "覆盖了原文件";
            }
        }
        ?>
    </div>
    <div style="flex: 1;">　　　　</div>
</div>
</body>
<?php
//unset();
?>
</html>