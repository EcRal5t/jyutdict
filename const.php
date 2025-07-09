<?php
/**
 * Created by PhpStorm.
 * User: EcRalt
 * Date: 2019/02/08
 * Time: 2:01
 */

final class Info {
    private static $version = "α0.9.0/250627/250629";
    
    public static function showVersion() {
        echo "<span style=\"float:right;color:#444444;font-size:0.6em\">".self::$version."</span>";
    }
    
    public static function showSidenav() {
        $version = self::$version;
        echo <<< SIDENAV
    <style>.sidenav-info:after{content:
        '主版本:{$version} \A 鶴山沙坪 - 220601 \A 開平護龍 - 221020 \A 台山大江 - 250609 \A 台山斗山墟 - 240420 \A 江門荷塘上邊 - 241215 \A 江門荷塘下邊 - 241215 \A 江門水南 - 250602 \A 江門白沙 - 250602 \A 江門紫萊 - 250602 \A 江門沙仔尾 - 250602 \A 開平蒼城 - 240724 \A 開平沙塘 - 240724 \A 新會天湖 - 240713 \A 新會羅坑 - 240408 \A 新會會城 - 220623 \A 江門墟頂 - 250602 \A 東莞塘角 - 250624 \A 東莞黃麻嶺 - 250610 \A 東莞莞城 - 250703 \A 江門白話 - 230910 \A 廣州 - 250706 \A 順德大良 - 211021 \A 中山石岐 - 241101 \A 中山小欖 - 250709 \A 肇慶 - 240420 \A 陽春松柏 - 250603 \A 新興 - 250624 \A 梧州 - 240420 \A 南寧 - 250706 \A 橫州 - 250627 \A 百色 - 240420 \A 桂平 - 240420 \A 梧州戎墟 - 240420 \A 梧州扶典 - 241214 \A 封川 - 250414 \A 開建 - 250709 \A 蒼梧石橋 - 240420 \A 蒙山 - 240420 \A 桂平尋旺 - 250611 \A 陸川馬坡 - 250624 \A 北流大旺 - 220608 \A 容縣 - 250608 \A 鬱林 - 250704 \A 貴港街裏 - 190720 \A 靈山太平 - 250527 \A 廉州 - 240423 \A 湛江坡頭 - 221013 \A 吳川吳陽 - 221013 \A 化州下江 - 220404 \A 化州上江 - 220404 \A 欽州 - 190820 \A 防城 - 190910 \A 北海 - 190219 \A 遂溪草潭 - 250607 \A 遂溪 - 250609 \A 湛江赤坎 - 250531 \A 高州 - 230308 \A 高州石鼓 - 230308 \A 茂名 - 200709 \A 陽春合水 - 250602 \A 陽春河口 - 240723 \A 陽江 - 250706 \A 柳州 - 240618 \A 宜章一六 - 250706 \A\A 廣韻 140930';}
    </style>
    
    <div class="sidenav-overlay" onclick="hideSidenav()"></div>
    
    <div id="sidenav">
    
        <div id="sidenav-head">
            <embed src="https://jyutdict.org/img/LOGO.svg" width="160" type="image/svg+xml" pluginspage="https://www.jyutdict.org/" />
        </div>
        
        <ul id="sidenav-list">
            <li class="sidenav-link"><a href="https://jyutdict.org/">檢字</a></li>
            <li class="sidenav-link"><a href="https://jyutdict.org/pron">檢音</a></li>
            <li class="sidenav-link"><a href="https://jyutdict.org/sheet">泛粵字表</a></li>
            <li class="divider"></li>
            <li class="sidenav-link"><a href="https://jyutdict.org/articles/post" target="_blank">紀文</a></li>
            <li class="divider"></li>
            <li class="sidenav-link"><a href="https://got.jyutdict.org" target="_blank">GoT</a></li>
            <li class="divider"></li>
            <li class="sidenav-link"><a href="https://jyutjam.org/" target="_blank">關於</a></li>
            <li class="sidenav-link sidenav-info"><a href="https://jyutdict.org/about">說明</a></li>
        </ul>
    </div>
SIDENAV;
    }
    public static function showFooter() {
        echo <<< FOOTER
        <hr style="margin: 50px 0;">
        <div class="copyright" style="text-align: center;margin-bottom: 2em;">
            <p>© 2019-2025 <a href="https://jyutjam.org">嶺南粵音</a> <a href="https://jyutdict.org">泛粵大典</a> 開發組 版權所有</p>
        </div>
        FOOTER;
    }

    public static function printApiJson() {
        print_r(json_encode([
            "app_version"=>[0,6,7],
            "details_of_characters"=>"https://jyutdict.org/api/v0.9/detail?chara={query}{&ascii}",
            "details_of_pronunciations"=>"https://jyutdict.org/api/v0.9/detail?pron={query}{&ascii}",
            "details_in_characters_sheet"=>"https://jyutdict.org/api/v0.9/sheet?query={query}{&fuzzy, regex, trim, ascii, b, col={locations}}",
            "help"=>"Apply parameter '&help' at corresponding API for detailed info."
        ], JSON_UNESCAPED_SLASHES));
    }
}
?>
