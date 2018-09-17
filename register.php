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
        echo '<a href="./discuss.php">討論串</a>';
    } else echo '<a>cc</a>';
    ?>

    <a class="about" href="./about.php">關於</a>
</nav>


<br><br><br>
<?PHP
include("connectDB.php");
$validPwEqual = true;
$validIDChar = true;
$validIDLength = true;
$validPwLength = true;
$validNickLength = true;
$validMail = true;
$validIDUsable = true;
$validNickUsable = true;
//var_dump($validPwEqual);
if (!empty($_POST['submit'])) {
    if ($_POST['pw_st']<>$_POST['pw_nd']) {
        $validPwEqual = false;
    }
    if (preg_match('/_/',$_POST['id'])) {
        $validIDChar = false;
    }
    if (mb_strlen($_POST['id'],"UTF8")>16 or mb_strlen($_POST['id'],"UTF8")<5) {
        $validIDLength = false;
    }
    if (mb_strlen($_POST['pw_st'],"UTF8")>16 or mb_strlen($_POST['pw_st'],"UTF8")<5) {
        $validPwLength = false;
    }
    if (mb_strlen($_POST['nickname'],"UTF8")>16 or mb_strlen($_POST['nickname'],"UTF8")<5) {
        $validNickLength = false;
    }
    if (!preg_match('/[0-9a-zA-Z]+@[0-9a-zA-Z]+\.[0-9a-zA-Z]+/',$_POST['email'])) {
        $validMail = false;
    }

    $sql = "Select count(*) from `register` where `id`='".$_POST['id']."'";
    $query = mysqli_query($con, $sql);
    $rs = mysqli_fetch_row($query);
    if ($rs[0]) {
        $validIDUsable = false;
    }

    $sql = "Select count(*) from `register` where `nickname`='".$_POST['nickname']."'";
    $query = mysqli_query($con, $sql);
    $rs = mysqli_fetch_row($query);
    if ($rs[0]) {
        $validNickUsable = false;
    }
    if ($validPwEqual && $validIDChar && $validIDLength && $validPwLength && //注册有效时
        $validNickLength && $validMail && $validIDUsable && $validNickUsable) {
        $id = $_POST['id'];
        $pw = md5($_POST['pw_st']);
        $nickname = $_POST['nickname'];
        $email = $_POST['email'];
        $sql = "INSERT INTO `register`(`nickname`, `mail`, `password`, `id`, `privilage`, `regtime`) VALUES ('$nickname', '$email', '$pw', '$id', 'Normal',now())";
        mysqli_query($con, $sql);
        echo "<script>alert('注册成功，请登录');location.href='login.php'</script>";
    }
    unset($id, $pw, $nickname, $email, $sql, $_POST['submit']);
}

?>

<div class="container">
    <div class="content" >
        <br><br><br>
        <div class="box">
            居中不管了（/微笑）
            <form action="" method="post">
                <table>
                    <tr>
                        <td colspan="2" class="infoColumn"><hr><h2>註冊</h2></td>
                    </tr>
                    <tr>
                        <td class="infoColumn">賬號</td><td>
                            <?PHP
                            //var_dump($validIDChar);
                            //var_dump($validIDLength);
                            //var_dump($validIDUsable);
                            if ($validIDChar && $validIDLength && $validIDUsable) {
                                echo '<input type="text" name="id" class="inputText">';
                            } else {
                                echo '<input type="text" name="id" class="inputTextWrong">';
                                if (!$validIDChar) {
                                    echo '<br>　賬號應無下劃線　';
                                }
                                if (!$validIDLength) {
                                    echo '<br>　賬號過長或過短　';
                                }
                                if (!$validIDUsable) {
                                    echo '<br>　該賬號已被註冊　';
                                }
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="infoColumn">密碼</td><td>
                            <?PHP
                            //var_dump($validPwLength);
                            if ($validPwLength) {
                                echo '<input type="password" name="pw_st" class="inputText">';
                            } else {
                                echo '<input type="password" name="pw_st" class="inputTextWrong">';
                                echo '<br>　密碼過長或過短　';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="infoColumn">密碼重複</td><td>
                            <?PHP
                            //var_dump($validPwEqual);
                            if ($validPwEqual) {
                                echo '<input type="password" name="pw_nd" class="inputText">';
                            } else {
                                echo '<input type="password" name="pw_nd" class="inputTextWrong">';
                                echo '<br>　密碼輸入不一致　';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="infoColumn">暱稱</td><td>
                            <?PHP
                            if ($validNickLength) {
                                echo '<input type="text" name="nickname" class="inputText">';
                            } else {
                                echo '<input type="text" name="nickname" class="inputTextWrong">';
                                echo '<br>　暱稱過長或過短　';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="infoColumn">郵箱</td>
                        <td>
                            <?PHP
                            if ($validMail) {
                                echo '<input type="text" name="email" class="inputText">';
                            } else {
                                echo '<input type="text" name="email" class="inputTextWrong">';
                                echo '<br>　該郵箱地址無效　';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td><br><input type="submit" name="submit" value="提交" class="inputButton" id="registerSubmitButton"></td>
                    </tr>
                </table>
            </form>
        </div>



        　　　　　　　　　　　　　　　　　　　　　　　　　　　　

    </div>
</div>
<?PHP
unset($validPwEqual, $validIDChar, $validIDLength, $validPwLength,
      $validNickLength, $validMail, $validIDUsable, $validNickUsable);
?>
</body>
</html>