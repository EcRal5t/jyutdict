<?php
/** Public location phonology catalogue and compressed structured report. */

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/helpers.php';

$areaId = isset($_GET['area_id']) ? (int)$_GET['area_id'] : 0;

if ($areaId < 1) {
    header('Content-Type: application/json; charset=utf-8');
    $rows = $dbh->query(
        "SELECT a.`id`, a.`first`, a.`second`, a.`third`, a.`detailed_name`, a.`color`,
                a.`current_release_id`, a.`current_phonology_id`,
                IF(p.`id` IS NOT NULL AND p.`source_release_id` = a.`current_release_id`, 1, 0)
                  AS `has_phonology`
         FROM `i_area_list` AS a
         LEFT JOIN `phonology_reports` AS p ON p.`id` = a.`current_phonology_id`
         WHERE a.`is_visible` = 1
           AND a.`current_release_id` IS NOT NULL
           AND a.`archived_at` IS NULL
         ORDER BY a.`sort_order`, a.`id`"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        foreach (['id', 'current_release_id', 'current_phonology_id', 'has_phonology'] as $field) {
            $row[$field] = $row[$field] === null ? null : (int)$row[$field];
        }
        $row['name'] = implode('', array_filter([$row['second'], $row['third']]));
        if ($row['name'] === '') {
            $row['name'] = (string)$row['first'];
        }
    }
    unset($row);
    outputPublicJson(['locations' => $rows], 200, 300);
}

$stmt = $dbh->prepare(
    "SELECT p.`payload_gzip`, p.`compressed_bytes`, HEX(p.`payload_hash`) AS `payload_hash`,
            p.`created_at`
     FROM `i_area_list` AS a
     JOIN `phonology_reports` AS p
       ON p.`id` = a.`current_phonology_id`
      AND p.`area_id` = a.`id`
      AND p.`source_release_id` = a.`current_release_id`
     WHERE a.`id` = ?
       AND a.`is_visible` = 1
       AND a.`current_release_id` IS NOT NULL
       AND a.`archived_at` IS NULL"
);
$stmt->execute([$areaId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    header('Content-Type: application/json; charset=utf-8');
    outputJson(['error' => 'Current phonology report not found'], 404);
}

$etag = '"' . strtolower($row['payload_hash']) . '"';
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    header('ETag: ' . $etag);
    exit;
}
header('Content-Type: application/vnd.jyutdict.phonology+json; charset=utf-8');
header('Content-Encoding: gzip');
header('Content-Length: ' . (int)$row['compressed_bytes']);
header('ETag: ' . $etag);
header('Cache-Control: public, max-age=86400, stale-while-revalidate=604800');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', strtotime($row['created_at'])) . ' GMT');
echo $row['payload_gzip'];
