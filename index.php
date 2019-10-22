<?PHP
include("const.php");
include_once("connectDB.php");
include("Lookup.class.php");
include_once("Jyutping.class.php");
require_once("dict_data/Dictinfo.class.php");
require_once("dict_data/DictData.class.php");
require_once("dict_view/view.class.php");
require_once("dict_presenter/IndexPresenter.class.php");
?>

<!DOCTYPE html>
<html lang="zh-cn">

<head>
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-16">
    <meta http-equiv="Cache-control" content="no-cache">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="description" content="泛粵大典旨在收集現時各地讀音與歷史韻書讀音，並提供向大眾一些基礎的查詢功能，同時通過泛粵表展示泛粵各地的特色字及其讀音">
    <meta name="keywords" content="粵語 泛粵 嶺南 Cantonese 廣州 粵語查詢 泛粵大典">
    <title>泛粵大典</title>
    <link rel="stylesheet" type="text/css" href="./css/index.css">
    <link rel="icon" href="./img/favicon.ico">
    <script src="./js/index.js"></script>
    <script src="./js/general.js"></script>
</head>

<?PHP

//$submitChara 为 提交的字符
//$options     为 勾选框选项
//        writeLog("Locate: 1, open: ".var_export($_REQUEST, true), ".");
$editMode = isset($_REQUEST['editmode']);
$options = ["wanshyu" => 0, "area" => 0, "map" => 0];
if (isset($_REQUEST['option'])) {
	foreach ($_REQUEST['option'] as $value) {
		$options[$value] = 1;
	}
} else $options = ["wanshyu" => 1, "area" => 1, "map" => 1];
if (!empty($_REQUEST['character'])) {
	$submitChara = $_REQUEST['character'];
	$submitChara = mb_substr($submitChara, 0, 1, 'utf8');
} else {
	$submitChara = "粵";
}

?>

<body>

<div id="wrapper" class="wrapper">
        <?PHP Info::showSidenav(); ?>

        <div id="container" class="container">

            <button id="sidenav-show-btn" class="sidenav-show-btn"></button>

            <div id="searching" style="<?PHP if (empty($_REQUEST['character'])) echo "margin-top: 220px;" ?>">
                <form id="inputForm" class="clearfix" method="post">
                    <label><input type="text" id="inputText" class="general-bg-deeper" name="character" <?PHP echo "value=\"$submitChara\""; ?>></label>
                    <input type="submit" id="inputButton" class="general-bg" value="耖">
                    <?PHP if ($editMode) echo '<input type="text" name="editmode">'; ?>
                    <div id="inputCheck">
                        <label><input name="option[]" type="checkbox" value="wanshyu" <?PHP if ($options["wanshyu"]) echo "checked"; ?>>韻書音</label>
                        <label><input name="option[]" type="checkbox" value="area" <?PHP if ($options["area"]) echo "checked"; ?> id="check-area">地方音</label>
                        <label><input name="option[]" type="checkbox" value="map" <?PHP if ($options["map"]) echo "checked"; ?> id="check-map">地方音地圖</label>
                    </div>
                </form>
            </div>

			<?PHP
			if (!empty($_REQUEST['character'])) 
			{
				$sim2trad = Sim2TradLookup::getInstance();      #获取简繁转换对象
				$charaArray = $sim2trad->query($submitChara, $dbh);
				$charaCount = count($charaArray);
				if ($charaCount > 2) {
					$sim2trad->show($charaArray);
				}
				$printTimes = 0;
				foreach ($charaArray as $chara) {
					$presenter = new ShowViewFactory($dbh, $chara);
					if ($options["wanshyu"]) {
						$presenter->getDictPresenter('wanshyu')->show();
					}
					if ($options["area"]) {
						$area = $presenter->getDictPresenter('area');
						$area->printAreaFramework(BEGIN);
						$area->show();
						if ($options["map"]) {
							if ($printTimes++ == 0) $area->printMapDependency();
							$area->prepareMap("map" . $printTimes);
						}
					}
					if ($charaCount > 2) break;
				} #end foreach($charaArray as $chara)
				if ($options["area"] && $options["map"]) {
					$area->showMap();
					$area->printAreaFramework(END);
				}
			} //end if !empty get
			?>
		</div>
	</div>

	<script type="text/javascript" src="//js.users.51.la/20205743.js"></script>
	<script>
		document.querySelector('#sidenav-show-btn').onclick = function() {
			showSidenav();
		};
		window.onload = function() {
			annexTableShell('.annex-form', 2);
			document.querySelector('#check-map').disabled = !(document.querySelector('#check-area').checked);
		};
		document.querySelector('#check-area').onclick = function() {
			var chackmap = document.querySelector('#check-map');
			if (this.checked === false) {
				chackmap.checked = false;
			}
			chackmap.disabled = !this.checked;
		};
	</script>
</body>

</html>
