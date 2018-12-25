<?php
/**
 * Created by PhpStorm.
 * User: EcRalt
 * Date: 2018/10/27
 * Time: 1:41
 */

function getIP() {
    static $realIP;
    if (isset($_SERVER)) {
        if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])){
            $realIP = $_SERVER["HTTP_X_FORWARDED_FOR"];
        } else if (isset($_SERVER["HTTP_CLIENT_IP"])) {
            $realIP = $_SERVER["HTTP_CLIENT_IP"];
        } else {
            $realIP = $_SERVER["REMOTE_ADDR"];
        }
    } else {
        if (getenv("HTTP_X_FORWARDED_FOR")){
            $realIP = getenv("HTTP_X_FORWARDED_FOR");
        } else if (getenv("HTTP_CLIENT_IP")) {
            $realIP = getenv("HTTP_CLIENT_IP");
        } else {
            $realIP = getenv("REMOTE_ADDR");
        }
    }
    return $realIP;
}


function writeLog($extraString) {
    //$DOCUMENT_ROOT = $_SERVER['DOCUMENT_ROOT'];
    date_default_timezone_set('Asia/Shanghai');
    $serverTime = date('Y-m-d H:i:s',time());
    $writeLogDst = fopen(".\log\log.txt","a+");

    if (isset($_COOKIE["login"])) {
        $extraString = $_COOKIE["login"]."\t".$extraString;
    } else {
        $extraString = "[_UNLOGINED]\t".$extraString;
    }

    fwrite($writeLogDst,$serverTime."\t".getIP()."\t".$extraString."\r\n");
    fclose($writeLogDst);

    return;
}
?>