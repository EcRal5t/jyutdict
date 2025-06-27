<?php
// index.php

// 1. 环境初始化与依赖加载
include("const.php");
include_once("connectDB.php");
include("Lookup.class.php");
include_once("Jyutping.class.php");
require_once("dict_data/DictInfo.class.php");
require_once("dict_data/DictData.class.php");
require_once("dict_view/view.class.php");
require_once("dict_presenter/IndexPresenter.class.php");

// 2. 输入处理与变量准备
$editMode = isset($_REQUEST['editmode']);
$submitChara = !empty($_REQUEST['character']) ? mb_substr($_REQUEST['character'], 0, 1, 'utf8') : "粵";
$isSearchPerformed = !empty($_REQUEST['character']);

// 处理查询选项，默认全选
$options = [
    "wanshyu" => isset($_REQUEST['op']) ? in_array('wanshyu', $_REQUEST['op']) : true,
    "area" => isset($_REQUEST['op']) ? in_array('area', $_REQUEST['op']) : true,
    "map" => isset($_REQUEST['op']) ? in_array('map', $_REQUEST['op']) : true,
];

// 3. 核心业务逻辑
$resultsHtml = '';
$mapDependencyHtml = '';
$mapScriptHtml = '';
$charaArray = [];

if ($isSearchPerformed) {
    // 使用输出缓冲来捕获所有由 Presenter 生成的HTML
    ob_start();

    $sim2trad = Sim2TradLookup::getInstance();
    $charaArray = $sim2trad->query($submitChara, $dbh);
    $charaCount = count($charaArray);

    if ($charaCount > 2) {
        $sim2trad->show($charaArray);
    }

    $areaPresenter = null;
    $printTimes = 0;

    foreach ($charaArray as $chara) {
        $presenterFactory = new ShowViewFactory($dbh, $chara);

        if ($options["wanshyu"]) {
            $wanshyuPresenter = $presenterFactory->getDictPresenter('wanshyu');
            $wanshyuPresenter->show();
        }

        if ($options["area"]) {
            // 只实例化一次 AreaPresenter
            if ($areaPresenter === null) {
                 $areaPresenter = $presenterFactory->getDictPresenter('area');
            }
            ViewArea::printAreaFramework(BEGIN);
            $areaPresenter->show();
            
            // 地图依赖只需要输出一次
            if ($options["map"] && $printTimes++ === 0) {
                 ob_start();
                 $areaPresenter->printMapDependency();
                 $mapDependencyHtml = ob_get_clean();
            }
            
            // 为每个字准备地图数据
            if ($options["map"]) {
                 $areaPresenter->prepareMap("map" . $printTimes);
            }
             $areaPresenter->printRelativeLink();
        }
    }

    // 处理地图显示
    if ($options["area"] && $options["map"] && $areaPresenter) {
        ob_start();
        $areaPresenter->showMap();
        $mapScriptHtml = ob_get_clean();
        ViewArea::printAreaFramework(END);
    } else {
        ViewArea::printAreaFramework(END);
    }

    // 获取所有缓冲的输出
    $resultsHtml = ob_get_clean();
}

// 4. 渲染视图
include("index.template.php");