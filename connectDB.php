<?PHP

$dbh = new PDO('mysql:host=localhost;dbname=jyutdict', 'jyut', '615v9qjVs1k8siMp');
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$dbh->exec('SET NAMES UTF8MB4');
?>