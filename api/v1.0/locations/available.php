<?php
/**
 * 获取用户可编辑的地点列表
 *
 * GET /api/v1.0/locations/available
 * GET /api/v1.0/locations/available?search=廣
 *
 * 权限逻辑：
 * - 编纂者：返回 editor_locations 中已分配的地点
 * - 管理员/站长：返回所有地点
 *
 * 每个地点附带 has_article 标记，表示是否已有文章
 */

header('Content-Type: application/json; charset=utf-8');

include_once(__DIR__ . '/../../core/db.php');
include_once(__DIR__ . '/../../core/helpers.php');
include_once(__DIR__ . '/../../middleware/auth.php');
include_once(__DIR__ . '/../../middleware/role.php');

requireRole('editor'); // 至少是编纂者



$search = $_GET['search'] ?? '';

try {
    $locations = [];

    if (getRoleLevel($currentUserRole) >= getRoleLevel('admin')) {
        // 管理员/站长：获取所有地点（合并去重）
        $locationSet = [];

        // 1. i_faamjyut 地点（kind=1 才是地名）
        $stmt = $dbh->prepare("SELECT `fullname`, `fullname_note` FROM `i_faamjyut` WHERE `kind` = 1 ORDER BY `id`");
        $stmt->execute();
        $faamjyutRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($faamjyutRows as $row) {
            $name = $row['fullname'] . ($row['fullname_note'] ?: '');
            if ($search && mb_strpos($name, $search) === false) continue;
            $locationSet[$name] = true;
        }

        // 2. i_area_list 地点
        $stmt = $dbh->prepare("SELECT `second`, `third` FROM `i_area_list` ORDER BY `id`");
        $stmt->execute();
        $areaRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($areaRows as $row) {
            $name = $row['second'] . ($row['third'] ?: '');
            if ($search && mb_strpos($name, $search) === false) continue;
            $locationSet[$name] = true;
        }

        foreach ($locationSet as $name => $_) {
            $locations[] = ['name' => $name];
        }
    } else {
        // 编纂者：只获取已分配的地点
        $stmt = $dbh->prepare("SELECT `location_name` FROM `editor_locations` WHERE `editor_id` = :eid");
        $stmt->execute([':eid' => $currentUserId]);
        $assigned = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($assigned as $row) {
            if ($search && mb_strpos($row['location_name'], $search) === false) continue;
            $locations[] = ['name' => $row['location_name']];
        }
    }

    // 附加 has_article 标记
    if (!empty($locations)) {
        $stmt = $dbh->prepare("SELECT `location_name` FROM `articles`");
        $stmt->execute();
        $existingArticles = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $articleSet = array_flip($existingArticles);

        foreach ($locations as &$loc) {
            $loc['has_article'] = isset($articleSet[$loc['name']]);
        }
    }

    outputJson(['locations' => $locations]);
} catch (PDOException $e) {
    outputJson(['error' => 'Database error'], 500);
}
