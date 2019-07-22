<?PHP
include ("const.php");
include_once ("connectDB.php");
include ("Lookup.class.php");
include_once ("Jyutping.class.php")
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>

</head>
<body>
	<?PHP
#ZGwongzau
	echo("Hello");

		$inSheet_sql = "
			SELECT ZNgzjau.chara 
			FROM `ZNgzjau`
			where
			exists
			(select chara from Character_simtrad_list)
";
		$inSheet_stmt = $dbh->prepare($inSheet_sql);
		$inSheet_stmt->execute();
		var_dump($inSheet_stmt->fetchAll(PDO::FETCH_ASSOC));
	?>
</body>
</html>