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
    <a class="home" href="oldindex.php">粵典</a>
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
<?PHP
include("connectDB.php");

$validIDChar = true;
$validIDExist = true;
$validPwCorrect = true;

if (!empty($_POST['login'])) {
    $sql = "Select * from `register` where `id`='".$_POST['id']."'";
    $query = mysqli_query($con, $sql);
    $rs = mysqli_fetch_row($query);
    if (is_array($rs)) {
        if (md5($_POST['pw']) == $rs[2]) {
            $nickname = $rs[0];
            setcookie("login", "$nickname");
            echo "<script>alert('登錄成功');location.href = 'oldoldindex.php'</script>";
        } else $validPwCorrect = false;
    } else $validIDExist = false;
    unset($rs, $_POST['pw']);
}

?>

<div class="container">
    <div class="content" >
        <br><br><br>
        <div class="box">
            居中不管了（/微笑/微笑）
            <form action="" method="post" >
                <table>
                    <tr>
                        <td colspan="2" class="infoColumn"><hr><h2>登錄</h2></td>
                    </tr>

                    <tr>
                        <td class="infoColumn">账号</td><td>
                            <?PHP
                            if ($validIDExist) {
                                echo '<input type="text" name="id" class="inputText">';
                            } else {
                                echo '<input type="text" name="id" class="inputTextWrong">';
                                echo '<br>　賬號不存在　';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="infoColumn">密码</td><td>
                            <?PHP
                            if ($validPwCorrect) {
                                echo '<input type="password" name="pw" class="inputText">';
                            } else {
                                echo '<input type="password" name="pw" class="inputTextWrong">';
                                echo '<br>　密碼輸入錯誤　';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr></tr>
                    <tr>
                        <td></td>
                        <td><br><input type="submit" name="login" value="登錄" class="inputButton" id="register"></td>
                    </tr>
                </table>
            </form>
        </div>



        　　　　　　　　　　　　　　　　　　　　　　　　　　　　

    </div>
</div>

<?PHP
unset($validIDExist, $validPwCorrect);
?>
</body>
</html>