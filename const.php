<?php
/**
 * Created by PhpStorm.
 * User: EcRalt
 * Date: 2019/02/08
 * Time: 2:01
 */

final class Info {
    private static $version = "v0.8.34.2/170731";
    
    public static function showVersion() {
        echo "<span style=\"float:right;color:#444444;font-size:0.6em\">".self::$version."</span>";
    }
    
    public static function showSidenav() {
        $version = self::$version;
        echo <<< SIDENAV
    <style>.sidenav-info:after{content:
    '主版本:{$version} \A 鶴山沙坪-220601 \A 江門水南-220623 \A 江門白沙-220623 \A 江門沙仔尾-220623 \A 台山斗山墟-221024 \A 新會會城-220623 \A 開平赤磡-221020 \A 開平沙塘-211011 \A 江門墟頂-220623 \A 江門白話-221017 \A 廣州-190811 \A 順德大良-211021 \A 肇慶-201029 \A 梧州-181228 \A 桂平-210515 \A 南寧-230803 \A 高州-230308 \A 高州石鼓-230308 \A 茂名-200709 \A 防城-190910 \A 欽州-190820 \A 北海-190219 \A 廉州-190114 \A 湛江坡头-221013 \A 吳川吳陽-221013 \A 化州上江-220404 \A 化州下江-220404 \A 貴港街裏-190720 \A 桂平尋旺-200126 \A 梧州扶典-230129 \A 蒼梧石橋-181228 \A 容縣容州2102? \A 北流大旺-220608 \A 鬱林大塘-忘了 \A 蒙山-220321 \A 廣韻 140930';}
    </style>
    
    <div class="sidenav-overlay" onclick="hideSidenav()"></div>
    
    <div id="sidenav">
    
        <div id="sidenav-head">
            <embed src="img/LOGO.svg" width="160" type="image/svg+xml" pluginspage="https://www.jyutdict.org/" />
        </div>
        
        <ul id="sidenav-list">
            <li class="sidenav-link"><a href="./">檢字</a></li>
            <li class="sidenav-link"><a href="./pron">檢音</a></li>
            <li class="sidenav-link"><a href="https://docs.google.com/spreadsheets/d/1jwpB2pKBM0OfONJXHCRIvAhEl4cTEobRdbw6z1PajCQ/edit?usp=sharing" target="_blank">泛粵表<span style="font-size: 0.5em;">(v1907. 於谷歌)</span></a></li>
            <li class="divider"></li>
            <li class="sidenav-link"><a href="https://jyutjam.org/" target="_blank">關於</a></li>
            <li class="sidenav-link sidenav-info"><a href="./about">說明</a></li>
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
