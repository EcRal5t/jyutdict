<?PHP
$dbh = new PDO('mysql:host=localhost;dbname=jyutdict', 'jyut', '***REMOVED***');
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$dbh->exec('SET NAMES UTF8MB4');
?>