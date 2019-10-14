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
    '主版本:{$version} \A 廣州190811 \A 北海190219 | 亭子190222 \A 貴港190127 | 欽州190819 \A 廉州190219 | 南寧190802 \A 梧州190117 | 蒼梧190118 \A 鬱林190803 \A 廣韻 140930';}
    </style>
    
    <div class="sidenav-overlay" onclick="hideSidenav()"></div>
    
    <div id="sidenav">
    
        <div id="sidenav-head">
            <embed src="img/LOGO.svg" width="160" type="image/svg+xml" pluginspage="https://www.jyutdict.org/" />
        </div>
        
        <ul id="sidenav-list">
            <li class="sidenav-link"><a href="./">字</a></li>
            <li class="sidenav-link"><a href="./pron">音</a></li>
            <li class="sidenav-link" style="text-decoration:line-through;"><a>　韻　</a></li>
            <li class="sidenav-link" style="text-decoration:line-through;"><a>　詞　</a></li>
            <li class="sidenav-link"><a href="https://docs.google.com/spreadsheets/d/1jwpB2pKBM0OfONJXHCRIvAhEl4cTEobRdbw6z1PajCQ/edit?usp=sharing" target="_blank">泛粵表<span style="font-size: 0.5em;">　需梯及電腦</span></a></li>
            <li class="divider"></li>
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
}



?>
