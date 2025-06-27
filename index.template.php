<?php
// index.template.php
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
    <meta name="description" content="泛粵大典旨在收集現時各地讀音與歷史韻書讀音，並提供向大眾一些基礎的查詢功能，同時通过泛粵表展示泛粵各地的特色字及其讀音">
    <meta name="keywords" content="粵語 泛粵 嶺南 Cantonese 廣州 粵語查詢 泛粵大典">
    <title>泛粵大典</title>
    <link rel="stylesheet" type="text/css" href="./css/index.css?1">
    <link rel="icon" href="./img/favicon.ico">
    <script src="./js/index.js"></script>
    <script src="./js/general.js"></script>
    <script charset="UTF-8" id="LA_COLLECT" src="//sdk.51.la/js-sdk-pro.min.js"></script>
    <script>LA.init({id:"KBntZT3wki1TtUTu",ck:"KBntZT3wki1TtUTu"})</script>
    <style> 
        .hidden { display: none; } 
        .ipa-hidden-btn { text-decoration: line-through 2px; } 
        .general-form .tbl-head { border-right: none; }
        .general-form .tbl-sub { border-left: none; } 
    </style>
    <?php echo $mapDependencyHtml; // 输出地图依赖 ?>
</head>

<body>

<div id="wrapper" class="wrapper">
    <?php Info::showSidenav(); ?>

    <div id="container" class="container">
        <button id="sidenav-show-btn" class="sidenav-show-btn"></button>

        <div id="searching" style="<?php if (!$isSearchPerformed) echo "margin-top: 220px;" ?>">
            <form id="inputForm" class="clearfix" method="get">
                <label><input type="text" id="inputText" class="general-bg-deeper" name="character" value="<?php echo htmlspecialchars($submitChara); ?>"></label>
                <input type="submit" id="inputButton" class="general-bg" value="耖">
                <?php if ($editMode) echo '<input type="hidden" name="editmode" value="1">'; ?>
                <div id="inputCheck">
                    <label><input name="op[]" type="checkbox" value="wanshyu" <?php if ($options["wanshyu"]) echo "checked"; ?>>韻書音</label>
                    <label><input name="op[]" type="checkbox" value="area" <?php if ($options["area"]) echo "checked"; ?> id="check-area">地方音</label>
                    <label><input name="op[]" type="checkbox" value="map" <?php if ($options["map"]) echo "checked"; ?> id="check-map">地方音地圖</label>
                </div>
            </form>
            <?php
            if ($isSearchPerformed) {
                $jyutOnlyCharas = "漚齙鎅抨儮涫嗍喐企跙俾涿竉呃𤠑冚㪗墊劘𠲺捋鉸諗𧟘笡撴㩄扽竭痕歪炕掬𠺘凼搇喫𢴒扤燶焫眲𦢊𢬿𢱕嬲䆟姣𡴀鐺仲嘢妗擤唥瀨竇誃㧬偈斟徛靚耖湴癐應襟䐁佊腯扻樽𨣇窿熝咩冇攰砧孭㵒泵㨃脌㫱甂漝緡柒𢲲䊆鍘譇𥐹匱蕹欮𠱓𨂾𠼮撩欱噏焗囈攴篤摱軚啽擸𧨾搥哋戇姩𢆡扂佮唞攞卅屌躉渠箍蚊笠撳獳罯蓊赧窞嚿係撈刏攀煠潷樖爇轆剢嘥擰閂晾睏熰漦摣跛騮畀趤沊屐𨂽嘈黐𧽤謦冞啱㩧羹㞓呔𧿒鵮胐撓抌腩𣼽搲盪䑜骾錔屙懵劏㗇揾梘𠝹𡚦敨罌𤸻䅺煲䠩𧬈叻瘟𪒬喎埞髀欶𧰵挽乸𢳂咁滐啖搑𨈇壅睇搏冧𢩦擳掣擁踭䟗廿寐懟燜搦褸𠽒溦𪘞逳蝨𨳒傾㥋㩒𤗈熛抵𨳍嘸𢫏淥㢥扱脷㧺攨搽沌鑊甩摷𡰪踸墟掂嘔漉𧿔杘𥌮拎揸丼慳𧦠檻𢴩腍韞欬㔶拗氼閪菢佢晏蛤呷猋仔擝鏜乜掹惗澀囝𤊒凹椏滮㨆䂿攬挃疴肶哴坎氹嗲嘅炆瞓𧶄癲㧻㧾㫰㞘褪窟潺穮沕傑孖搵𧕴揞揀";
                if (strpos($jyutOnlyCharas, $submitChara) !== false) {
                    echo "<div style='text-align:center;margin:20px;padding:10px;' class='general-bg'>是否在檢索“粵用漢字”？請移步<a href='https://jyutdict.org/sheet'>這裏</a>~！或者試試<a href='https://jyutjam.org/jyutdict-android/' target='_blank'>泛粵典安卓版</a>(´・∀・｀ ) </div>";
                }
            }
            ?>
        </div>

        <?php
        if ($isSearchPerformed) {
            echo $resultsHtml; // 输出查询结果的HTML
        }
        ?>

    </div>
    <?php Info::showFooter(); ?>
</div>

<?php echo $mapScriptHtml; // 输出地图执行脚本 ?>

<script>
    document.querySelector('#sidenav-show-btn').onclick = showSidenav;

    window.onload = function() {
        annexTableShell('.annex-form', 2);
        const checkArea = document.querySelector('#check-area');
        if (checkArea) {
            document.querySelector('#check-map').disabled = !checkArea.checked;
        }
    };

    const checkArea = document.querySelector('#check-area');
    if(checkArea) {
        checkArea.onclick = function() {
            const chackmap = document.querySelector('#check-map');
            if (this.checked === false) {
                chackmap.checked = false;
            }
            chackmap.disabled = !this.checked;
        };
    }

    const toggleButtons = document.getElementsByName('toggleButton');
    const markedSpans = document.querySelectorAll('span.ipa');
    toggleButtons.forEach(element => {
        element.addEventListener('click', function() {
            markedSpans.forEach(span => {
                span.classList.toggle('hidden');
            });
            element.classList.toggle('ipa-hidden-btn');
        })
    });
</script>
</body>
</html>