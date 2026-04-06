import axios from 'axios';
import { useAuthStore } from '@/stores/auth.js';

const apiClient = axios.create({
    baseURL: '/api',
    headers: { 'Content-Type': 'application/json' },
    withCredentials: true,
});

// 请求拦截器：自动带 CSRF Token
apiClient.interceptors.request.use((config) => {
    // 注意：store 要在函数内获取，避免在模块加载时 Pinia 未初始化
    try {
        const authStore = useAuthStore();
        if (authStore.csrfToken && ['post', 'put', 'delete', 'patch'].includes(config.method)) {
            config.headers['X-CSRF-Token'] = authStore.csrfToken;
        }
    } catch (e) {
        // Pinia 未初始化时（如初次 getMe 请求），跳过
    }
    return config;
});

export default {
    /**
     * 获取当前登录用户信息
     * @returns {Promise<{user: Object|null, csrf_token: string}>}
     */
    getMe() {
        return apiClient.get('/auth/me');
    },

    /**
     * 登出
     */
    logout() {
        return apiClient.post('/auth/logout');
    },

    /**
     * 获取 Google OAuth 登录 URL
     * 前端直接跳转到此 URL
     */
    getLoginUrl() {
        return '/api/auth/google';
    },
};
