<?PHP
$con = @mysqli_connect("localhost:3306","jyut","***REMOVED***","jyutdict")or die("Failed to connect Database.");
mysqli_set_charset($con, "UTF8MB4");
//  print_r($con);
?>