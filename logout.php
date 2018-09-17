<?PHP
setcookie("login", "", time()-1);
echo "<script>alert('注销成功');location.href='./index.php'</script>";
?>