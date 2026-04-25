import axios from 'axios';
import { useAuthStore } from '@/stores/auth.js';

const apiClient = axios.create({
    baseURL: '/api',
    headers: { 'Content-Type': 'application/json' },
    withCredentials: true,
});

// 請求攔截器：自動帶 CSRF Token
apiClient.interceptors.request.use((config) => {
    // 注意：store 要在函數內獲取，避免在模組載入時 Pinia 未初始化
    try {
        const authStore = useAuthStore();
        if (authStore.csrfToken && ['post', 'put', 'delete', 'patch'].includes(config.method)) {
            config.headers['X-CSRF-Token'] = authStore.csrfToken;
        }
    } catch (e) {
        // Pinia 未初始化時（如初次 getMe 請求），跳過
    }
    return config;
});

export default {
    /**
     * 獲取當前登入用戶信息
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
     * 獲取 Google OAuth 登入 URL
     * 前端直接跳轉到此 URL
     */
    getLoginUrl() {
        return '/api/auth/google';
    },
};
