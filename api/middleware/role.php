<?php
/**
 * 角色权限检查函数
 *
 * 需要先引入 auth.php（确保 $currentUserRole 已设置）
 *
 * 用法：
 *   include_once(__DIR__ . '/../middleware/auth.php');
 *   include_once(__DIR__ . '/../middleware/role.php');
 *   requireRole('admin');  // 要求至少是管理员
 */

/**
 * 角色层级映射（数字越大权限越高）
 */
function getRoleLevel($role) {
    $levels = [
        'user'   => 0,
        'editor' => 1,
        'admin'  => 2,
        'owner'  => 3,
    ];
    return $levels[$role] ?? 0;
}

/**
 * 要求当前用户至少具有指定角色权限
 * 如果不满足，返回 403 并终止
 *
 * @param string $minimumRole 最低要求的角色
 */
function requireRole($minimumRole) {
    global $currentUserRole;

    if (getRoleLevel($currentUserRole) < getRoleLevel($minimumRole)) {
        http_response_code(403);
        echo json_encode([
            'error' => 'Forbidden: Insufficient permissions',
            'required_role' => $minimumRole,
            'current_role' => $currentUserRole
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/**
 * 检查当前用户是否为指定角色之一
 *
 * @param array $roles 允许的角色列表
 * @return bool
 */
function hasRole($roles) {
    global $currentUserRole;
    return in_array($currentUserRole, $roles);
}

/**
 * 检查当前用户是否是 Owner
 *
 * @return bool
 */
function isOwner() {
    global $currentUserRole;
    return $currentUserRole === 'owner';
}
