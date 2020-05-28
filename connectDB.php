<?PHP
$dbms='mysql';     //数据库类型
$host = "localhost";
$dbName = "jyutdict";
$user = 'jyut';
$pwd = '***REMOVED***';
$dsn="$dbms:host=$host;dbname=$dbName";
try {
  //code...
  $dbh = new PDO($dsn,$user,$pwd);
  $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $dbh->exec('SET NAMES UTF8MB4');
} catch (\Throwable $th) {
    print_r($th);
  die("<h1> Bad Request on connecting database </h1> <br /> 數據庫登入失敗");
}
?>