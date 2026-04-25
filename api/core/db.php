<?php
/**
 * 數據庫連接
 */
try {
    $dbh = new PDO('mysql:host=localhost;dbname=jyutdict', 'jyut', '615v9qjVs1k8siMp');
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $dbh->exec('SET NAMES UTF8MB4');
} catch (\Throwable $th) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed']));
}
