<?php
/** Safely rename a legacy location table while preserving its stable area ID. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/LocationMaintenance.php';

$options = getopt('', ['from:', 'to:', 'apply']);
$from = isset($options['from']) ? (string)$options['from'] : '';
$to = isset($options['to']) ? (string)$options['to'] : '';
$apply = isset($options['apply']);

if ($from === '' || $to === '') {
    throw new RuntimeException('Both --from and --to are required');
}
$stmt = $dbh->prepare("SELECT `id` FROM `i_area_list` WHERE `sheetname` = ?");
$stmt->execute([jyutdictMaintenanceValidateSheetname($from)]);
$areaId = $stmt->fetchColumn();
if ($areaId === false) {
    throw new RuntimeException("No i_area_list row uses sheetname {$from}");
}

$preview = jyutdictMaintenanceRenamePreview($dbh, (int)$areaId, $to);
$preview['mode'] = $apply ? 'apply' : 'dry-run';
echo json_encode($preview, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if (!$apply) {
    echo "No changes applied. Re-run with --apply after checking the summary.\n";
    exit(0);
}

$result = jyutdictMaintenanceRename($dbh, (int)$areaId, $to);
echo json_encode([
    'status' => 'renamed',
    'area_id' => (int)$areaId,
    'from' => $result['from'],
    'to' => $result['to'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
