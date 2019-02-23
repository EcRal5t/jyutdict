<?php
/**
 * Created by PhpStorm.
 * User: EcRalt
 * Date: 2019/02/08
 * Time: 2:01
 */

final class Info {
    private static $version = "v0.8.29.14";
    
    public static function showVersion() {
        echo "<span style=\"float:right;color:#444444;font-size:0.6em\">".self::$version."</span>";
    }
    
    public static function showSidenav() {
        $version = self::$version;
        echo <<< SIDENAV
    <div class="sidenav-overlay" onclick="hideSidenav()"></div>
    
    <div id="sidenav">
        <div id="sidenav-head"><span class="font-64">粤</span>dict</div>
        <ul id="sidenav-list">
            <li class="sidenav-link"><a href="./">字</a></li>
            <li class="sidenav-link"><a href="./pron.php">韻</a></li>
            <li class="sidenav-link"><a href="./sheet.php">泛粵表</a></li>
            <li class="divider"></li>
            <li class="sidenav-link">
                <a href="./about.php">說明<span style="float:right;color:#444444;font-size:0.6em">{$version}</span></a>
            </li>
        </ul>
    </div>
SIDENAV;
    }
}



?>