import axios from 'axios';
import { useAuthStore } from '@/stores/auth.js';

const apiClient = axios.create({
    baseURL: '/api/v1.0/articles',
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
    getArticle(source, locationId) {
        return apiClient.get('/', { params: { source, location_id: locationId } });
    },
    saveArticle(data) {
        return apiClient.post('/', data);
    },
    getVersions(source, locationId) {
        return apiClient.get('/', { params: { source, location_id: locationId, versions: 1 } });
    },
    getVersion(versionId) {
        return apiClient.get('/', { params: { version_id: versionId } });
    },
    rollback(articleId, versionId) {
        return apiClient.post('/', { article_id: articleId, version_id: versionId }, { params: { action: 'rollback' } });
    },
};
