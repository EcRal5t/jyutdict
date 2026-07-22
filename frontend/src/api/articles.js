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

// 模块级缓存：文章地点列表（整个 session 只请求一次）
let articleLocationSetCache = null;
let articleLocationSetPromise = null;
const articleExistsCache = new Map();
const articleExistsPromises = new Map();

export default {
    // 获取单个地点的文章
    getArticle(locationName, type = 'location') {
        return apiClient.get('/', { params: { location_name: locationName, type } });
    },
    // 只查询存在性，不下载正文；同一地点及类型在本次会话内只请求一次。
    async checkArticle(locationName, type = 'location') {
        const key = `${type}:${locationName}`;
        if (articleExistsCache.has(key)) return articleExistsCache.get(key);
        if (articleExistsPromises.has(key)) return articleExistsPromises.get(key);

        const request = apiClient.get('/', {
            params: { location_name: locationName, type, exists: 1 }
        }).then((res) => {
            const exists = Boolean(res.data?.exists);
            articleExistsCache.set(key, exists);
            return exists;
        }).finally(() => {
            articleExistsPromises.delete(key);
        });
        articleExistsPromises.set(key, request);
        return request;
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
    // 【新增】获取所有有文章的地点列表（带缓存）
    async getArticleList(search = '') {
        // 如果有搜索参数，不走缓存
        if (search) {
            const params = { list: 1, search };
            return apiClient.get('/', { params });
        }

        // 如果已经有缓存，直接返回
        if (articleLocationSetCache !== null) {
            return { data: { articles: Array.from(articleLocationSetCache).map(name => ({ location_name: name })) } };
        }

        // 如果正在请求中，等待现有请求完成
        if (articleLocationSetPromise) {
            await articleLocationSetPromise;
            return { data: { articles: Array.from(articleLocationSetCache).map(name => ({ location_name: name })) } };
        }

        // 发起新请求
        articleLocationSetPromise = apiClient.get('/', { params: { list: 1 } });
        const res = await articleLocationSetPromise;
        articleLocationSetPromise = null;

        // 缓存结果
        const articles = res.data.articles || [];
        articleLocationSetCache = new Set(articles.map(a => a.location_name));

        return res;
    },
    // 【新增】获取缓存的地点 Set（同步方法，用于已加载后的场景）
    getArticleLocationSet() {
        return articleLocationSetCache || new Set();
    },
    // 清除缓存（用于文章更新后刷新）
    clearArticleLocationCache() {
        articleLocationSetCache = null;
        articleExistsCache.clear();
    },
    // 【新增】获取可编辑地点列表
    getAvailableLocations(search = '') {
        const params = {};
        if (search) params.search = search;
        return axios.get('/api/v1.0/locations/available', { params, withCredentials: true });
    },
    // 删除文章（管理员+）
    deleteArticle(locationName) {
        return apiClient.delete('/', { params: { location_name: locationName } });
    },
};
