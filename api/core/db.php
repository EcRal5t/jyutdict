<?php
/**
 * 數據庫連接
 */
require_once __DIR__ . '/../config/db.php';

try {
    $dbh = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $dbh->exec('SET NAMES UTF8MB4');
} catch (\Throwable $th) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed']));
}
