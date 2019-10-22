<?PHP
const HOST = "***REMOVED***";
const DBNAME = "jyutdict";
const DB_USER = 'jyut';
const DB_PWD = '***REMOVED***';
$dbh = new PDO('mysql:host=' . HOST . ';dbname=' . DBNAME, DB_USER, DB_PWD);
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$dbh->exec('SET NAMES UTF8MB4');
?>