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
    getLocationEditors(source, locationId) {
        return apiClient.get('/editors', { params: { location_source: source, location_id: locationId } });
    },
    getAllLocations() {
        return apiClient.get('/editors', { params: { list_locations: 1 } });
    },
    assignLocation(editorId, source, locationId) {
        return apiClient.post('/editors', { editor_id: editorId, location_source: source, location_id: locationId });
    },
    removeLocation(editorId, source, locationId) {
        return apiClient.delete('/editors', { data: { editor_id: editorId, location_source: source, location_id: locationId } });
    },
};
