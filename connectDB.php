<?PHP
$con = @mysqli_connect("***REMOVED***:3306","root","***REMOVED***","jyutdict")or die("Failed to connect DB.");
mysqli_set_charset($con, "UTF8MB4");
//  print_r($con);
?>