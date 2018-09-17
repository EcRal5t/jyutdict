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



<br><br><br>
<div class="container">
    <div style="flex: 1;">　　　　</div>
    <div style="flex: 5;">
        <div class="box" style="padding-bottom: 20px">
            <?PHP
            $validMessage = true;
            $validNickname = true;
            if (!empty($_POST['submit'])) {//判断提交able与提交
                if ($_POST['message']==NULL) {
                    $validMessage = false;
                }
                if ($_POST['author']==NULL) {
                    $validNickname = false;
                }

                if ($validMessage AND $validNickname) {
                    if (isset($_COOKIE["login"])) $id = $_COOKIE['login'];
                    else $id = "_ANONYM";
                    $author = $_POST['author'];
                    $message = $_POST['message'];
                    if (preg_match('/&/', $message)) {
                        $message = preg_replace('/&/', '&amp;', $message);
                    }
                    if (preg_match('/</', $message)) {
                        $message = preg_replace('/</', '&lt;', $message);
                    }
                    if (preg_match('/"/', $message)) {
                        $message = preg_replace('/"/', '&quot;', $message);
                    }
                    if (preg_match('/ /', $message)) {
                        $message = preg_replace('/ /', '&nbsp;', $message);
                    }
                    $sql = "INSERT INTO `message`(`ident`, `id`, `author`, `posttime`, `title`, `omessage`) VALUES (NULL,'$id','$author',now(),' ','$message')";
                    mysqli_query($con, $sql);
                    echo '<div style="color:#30C030">已發出</div>';
                    echo "<script>location.href='./discuss.php'</script>";
                    unset($id, $author, $message, $sql);
                }
            }
            ?>


            <form action="" method="post">
                <table >
                    <tr>
                        <td class="infoColumn">內容</td>
                        <td colspan="3">
                            <?PHP
                            if ($validMessage) {
                                echo '<textarea rows="7" name="message" class="inputText"></textarea>';
                            } else {
                                echo '<textarea rows="7" name="message" class="inputTextWrong"></textarea>';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="infoColumn">暱稱</td>
                        <td>
                            <input type="text" name="author"
                                <?PHP
                                if (isset($_COOKIE["login"])) echo ' value="'.$_COOKIE['login'].'" ';
                                if ($validNickname) echo 'class="inputText"';
                                else echo  'class="inputTextWrong"';
                                ?>
                                  >
                        </td>
                        <td width="5%"></td>
                        <td><input type="submit" name="submit" value="發出" class="inputButton"></td>
                    </tr>
                </table>
            </form>
        </div>

        <br>

        <div class="box">
            <form>

                <?PHP
                $sql = "Select * from `message` order by `ident` DESC";				//统计数量
                $query = mysqli_query($con, $sql);
                $count = 0;
                while ($rs = mysqli_fetch_row($query)) $count++;

                echo '<div><b>之前的留言</b><sup>共 '.$count.'條，已顯示 '.$count.'条</sup></div><br><hr>';
                $sql = "Select * from `message` order by `ident` DESC";
                $query = mysqli_query($con, $sql);
                while ($rs = mysqli_fetch_row($query)) {
                    echo $rs[0].':　　<small>by '.$rs[2].'　　at '.$rs[3].'</small><br>';
                    echo '<br>'.$rs[5].'<br><hr>';
                }
                ?>

            </form>
        </div>




    </div>
    <div style="flex: 1;">　　　　</div>
</div>
</body>
<?php
unset($sql, $rs, $count, $query, $validMessage, $validNickname, $author, $message);
?>
</html>