<?php
/**
 * Created by PhpStorm.
 * User: EcRalt
 * Date: 2019/02/08
 * Time: 2:01
 */

final class Info {
    private static $version = "v0.8.34.2b/170731/230810";
    
    public static function showVersion() {
        echo "<span style=\"float:right;color:#444444;font-size:0.6em\">".self::$version."</span>";
    }
    
    public static function showSidenav() {
        $version = self::$version;
        echo <<< SIDENAV
    <style>.sidenav-info:after{content:
        '主版本:{$version} \A 鶴山沙坪-220601 \A 開平護龍-221020 \A 台山斗山墟-240420 \A 江門水南-230910 \A 江門白沙-230910 \A 江門紫萊-230910 \A 江門沙仔尾-230910 \A 開平沙塘-220529 \A 開平羅坑-240408 \A 新會會城-220623 \A 江門墟頂-230910 \A 江門白話-230910 \A 廣州-190811 \A 順德大良-211021 \A 中山石岐-230909 \A 肇慶-240420 \A 梧州-240420 \A 梧州戎墟-240420 \A 桂平-240420 \A 南寧-240420 \A 高州-230308 \A 高州石鼓-230308 \A 茂名-200709 \A 防城-190910 \A 欽州-190820 \A 北海-190219 \A 廉州-240423 \A 湛江坡头-221013 \A 吳川吳陽-221013 \A 化州上江-220404 \A 化州下江-220404 \A 貴港街裏-190720 \A 桂平尋旺-200126 \A 梧州扶典-230129 \A 蒼梧石橋-240420 \A 開建-240421 \A 容縣-2102? \A 北流大旺-220608 \A 鬱林大塘-240420 \A 蒙山-240420 \A 廣韻 140930';}
    </style>
    
    <div class="sidenav-overlay" onclick="hideSidenav()"></div>
    
    <div id="sidenav">
    
        <div id="sidenav-head">
            <embed src="https://jyutdict.org/img/LOGO.svg" width="160" type="image/svg+xml" pluginspage="https://www.jyutdict.org/" />
        </div>
        
        <ul id="sidenav-list">
            <li class="sidenav-link"><a href="https://jyutdict.org/">檢字</a></li>
            <li class="sidenav-link"><a href="https://jyutdict.org/pron">檢音</a></li>
            <li class="sidenav-link"><a href="https://docs.google.com/spreadsheets/d/1M6_0DWB1CgFBSEPivOoJjc8XOFFZB-jkxLknXDpPPQ8" target="_blank">泛粵表<span style="font-size: 0.5em;">(v2403. 於谷歌)</span></a></li>
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
        /*
            <li class="sidenav-link">
                <a href="./about.php">說明
                    <span style="float:right;color:#444444;font-size:0.6em">
                        {$version}
                    </span>
                </a>
            </li>
         */
    }
    public static function showFooter() {
        echo <<< FOOTER
        <hr style="margin: 50px 0;">
        <div class="copyright" style="text-align: center;margin-bottom: 2em;">
            <p>© 2019-2024 <a href="https://jyutjam.org">嶺南粵音</a> <a href="https://jyutdict.org">泛粵大典</a> 開發組 版權所有</p>
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
