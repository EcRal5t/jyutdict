<?php
/**
 * 數據庫連接
 */
require_once __DIR__ . '/../config/db.php';

try {
    $dbh = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Native prepares are substantially slower for the wide-table
            // REGEXP searches on the deployed MySQL drivers. Values are still
            // safely quoted and bound by PDO in emulation mode.
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]
    );
} catch (\Throwable $th) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed']));
}
