<?php
include("../connectDB.php");
include("writeLog.inc.php");
switch ($_GET['ty']) {
    case 1: {
        //改讀音
        $onset     = $_GET['on'];
        $nuclei    = $_GET['nu'];
        $coda      = $_GET['co'];
        $tone      = $_GET['to'];
        $charsheet = "Z" . $_GET['cs'];
        $index     = $_GET['id'];
        $ipa       = $_GET['ip'];
        
        $query_editPron_sql = "UPDATE `$charsheet` "
            . "SET `initial`='$onset',`nuclei`='$nuclei',`coda`='$coda',`tone`=$tone,`ipa`='$ipa' "
            . "WHERE `id` = $index";
        //writeLog("Locate: 2, modified: (\n  $query_editPron_sql\n)", "..");
        $query_editPron_query = mysqli_query($con, $query_editPron_sql);
        echo $query_editPron_sql;
    }
    case 2: {
        //改備註
        
    }
    default: {
    
    }
}

?>