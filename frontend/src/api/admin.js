import axios from 'axios';
import { useAuthStore } from '@/stores/auth.js';

const apiClient = axios.create({
    baseURL: '/api/v1.0/admin',
    headers: { 'Content-Type': 'application/json' },
    withCredentials: true,
});

// 请求拦截器：自动带 CSRF Token
apiClient.interceptors.request.use((config) => {
    // store 要在函数内获取，避免在模块加载时 Pinia 未初始化
    try {
        const authStore = useAuthStore();
        if (authStore.csrfToken && ['post', 'put', 'delete', 'patch'].includes(config.method)) {
            config.headers['X-CSRF-Token'] = authStore.csrfToken;
        }
    } catch (e) {
        // Pinia 未初始化时跳过
    }
    return config;
});

// 管理 API 一律应返回 JSON。若部署时漏了 .htaccess 路由，Vue 的兜底頁會以
// 200 + text/html 返回；在這裏攔截，避免各管理頁把錯誤響應當成空資料。
apiClient.interceptors.response.use((response) => {
    const contentType = String(response.headers?.['content-type'] || '').toLowerCase();
    if (!contentType.includes('application/json')) {
        const error = new Error('管理 API 路由未正確部署，伺服器返回了網頁而不是資料');
        error.code = 'INVALID_ADMIN_API_RESPONSE';
        throw error;
    }
    return response;
});

export default {
    getUsers(params) {
        return apiClient.get('/users', { params });
    },
    getUserDetail(userId) {
        return apiClient.get('/users', { params: { id: userId } });
    },
    updateUserRole(userId, newRole) {
        return apiClient.put('/users', { user_id: userId, new_role: newRole });
    },
    getEditorLocations(editorId) {
        return apiClient.get('/editors', { params: { editor_id: editorId } });
    },
    getLocationEditors(locationName) {
        return apiClient.get('/editors', { params: { location_name: locationName } });
    },
    getAllLocations() {
        return apiClient.get('/editors', { params: { list_locations: 1 } });
    },
    assignLocation(editorId, locationName) {
        return apiClient.post('/editors', { editor_id: editorId, location_name: locationName });
    },
    removeLocation(editorId, locationName) {
        return apiClient.delete('/editors', { data: { editor_id: editorId, location_name: locationName } });
    },
    getCatalogLocations() {
        return apiClient.get('/locations');
    },
    createCatalogLocation(data) {
        return apiClient.post('/locations', data);
    },
    updateCatalogLocation(data) {
        return apiClient.patch('/locations', data);
    },
    reorderCatalogLocations(orderedIds) {
        return apiClient.put('/locations', { ordered_ids: orderedIds });
    },
    deleteEmptyCatalogLocation(id, confirmSheetname) {
        return apiClient.delete('/locations', { data: { id, confirm_sheetname: confirmSheetname } });
    },
    locationAction(action, data) {
        return apiClient.post('/location-actions', { action, ...data });
    },
    getMaintenance() {
        return apiClient.get('/maintenance');
    },
    maintenanceAction(action, data = {}) {
        return apiClient.post('/maintenance', { action, ...data });
    },
    getMaintenanceAudit(params = {}) {
        return apiClient.get('/audit', { params });
    },
};
