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
include_once(__DIR__ . '/../../core/LocationArticleIdentity.php');
include_once(__DIR__ . '/../../middleware/auth.php');
include_once(__DIR__ . '/../../middleware/role.php');

requireRole('editor'); // 至少是编纂者



$search = $_GET['search'] ?? '';

try {
    $identities = jyutdictLoadLocationArticleIdentities($dbh);
    $allowed = null;
    if (getRoleLevel($currentUserRole) < getRoleLevel('admin')) {
        $stmt = $dbh->prepare("SELECT `location_name` FROM `editor_locations` WHERE `editor_id` = :eid");
        $stmt->execute([':eid' => $currentUserId]);
        $allowed = [];
        foreach (jyutdictCanonicalizeAssignedLocationRows($dbh, $stmt->fetchAll(PDO::FETCH_ASSOC)) as $row) {
            $allowed[$row['location_name']] = true;
        }
    }

    $locations = [];
    foreach ($identities as $identity) {
        if ($allowed !== null && !isset($allowed[$identity['name']])) {
            continue;
        }
        if ($search) {
            $haystacks = array_merge(
                [$identity['name']],
                $identity['aliases'],
                array_map(function ($source) {
                    return $source['display_name'];
                }, $identity['sources'])
            );
            $matched = false;
            foreach ($haystacks as $value) {
                if (mb_strpos($value, $search) !== false) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                continue;
            }
        }
        $locations[] = $identity;
    }

    outputJson(['locations' => $locations]);
} catch (PDOException $e) {
    outputJson(['error' => 'Database error'], 500);
}
