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
    // 获取单个地点的文章
    getArticle(locationName) {
        return apiClient.get('/', { params: { location_name: locationName } });
    },
    // 保存文章（创建或更新）
    saveArticle(data) {
        return apiClient.post('/', data);
    },
    // 获取版本历史
    getVersions(locationName) {
        return apiClient.get('/', { params: { location_name: locationName, versions: 1 } });
    },
    // 获取单个版本内容
    getVersion(versionId) {
        return apiClient.get('/', { params: { version_id: versionId } });
    },
    // 回滚到指定版本
    rollback(articleId, versionId) {
        return apiClient.post('/', { article_id: articleId, version_id: versionId }, { params: { action: 'rollback' } });
    },
    // 【新增】获取所有有文章的地点列表
    getArticleList(search = '') {
        const params = { list: 1 };
        if (search) params.search = search;
        return apiClient.get('/', { params });
    },
    // 【新增】获取可编辑地点列表
    getAvailableLocations(search = '') {
        const params = {};
        if (search) params.search = search;
        return axios.get('/api/v1.0/locations/available', { params, withCredentials: true });
    },
};
