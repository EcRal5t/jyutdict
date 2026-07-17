<?php
/** Lightweight integration checks for location maintenance helpers. */

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/LocationMaintenance.php';

function maintenanceTestAssert($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

jyutdictMaintenanceRequireSchema($dbh);
maintenanceTestAssert(
    jyutdictMaintenanceValidateSheetname('z_test_location') === 'z_test_location',
    'Valid sheetname should be preserved'
);

$invalidNameRejected = false;
try {
    jyutdictMaintenanceValidateSheetname('z_test-location');
} catch (RuntimeException $error) {
    $invalidNameRejected = true;
}
maintenanceTestAssert($invalidNameRejected, 'Invalid sheetname must be rejected');

$metadata = jyutdictMaintenanceValidateMetadata([
    'longitude' => '113.25',
    'latitude' => '23.10',
    'first' => '測試',
    'color' => '#aabbcc',
], ['second' => '', 'third' => '']);
maintenanceTestAssert($metadata['longitude'] === 113.25, 'Longitude should be normalized');
maintenanceTestAssert($metadata['color'] === '#AABBCC', 'Color should be normalized');

$badCoordinateRejected = false;
try {
    jyutdictMaintenanceValidateMetadata(['latitude' => 91]);
} catch (RuntimeException $error) {
    $badCoordinateRejected = true;
}
maintenanceTestAssert($badCoordinateRejected, 'Out-of-range coordinates must be rejected');

$area = jyutdictMaintenanceGetArea($dbh, (int)$dbh->query('SELECT MIN(`id`) FROM `i_area_list`')->fetchColumn());
maintenanceTestAssert(isset($area['sheetname']) && $area['sheetname'] !== '', 'Existing area should load');
maintenanceTestAssert(
    jyutdictMaintenanceTableExists($dbh, $area['sheetname']),
    'Existing area physical table should be detected'
);

$dbh->beginTransaction();
try {
    $stmt = $dbh->prepare(
        "UPDATE `i_area_list` SET `is_visible` = 0, `archived_at` = NOW() WHERE `id` = ?"
    );
    $stmt->execute([$area['id']]);
    $publicIds = array_map(function ($row) {
        return (int)$row['id'];
    }, jyutdictLoadAreas($dbh));
    maintenanceTestAssert(
        !in_array($area['id'], $publicIds, true),
        'Archived area must be excluded from public location loading'
    );
} finally {
    $dbh->rollBack();
}

echo "Location maintenance tests passed.\n";
