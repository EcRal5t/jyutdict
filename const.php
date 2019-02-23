<?php
/**
 * Created by PhpStorm.
 * User: EcRalt
 * Date: 2019/02/08
 * Time: 2:01
 */

final class Info {
    private static $version = "v0.8.29.11";
    
    public static function showVersion() {
        echo "<span style=\"float:right;color:#444444;font-size:0.6em\">".self::$version."</span>";
    }
    
    
}



?>