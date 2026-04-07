import axios from 'axios';
import { useAuthStore } from '@/stores/auth.js';

const apiClient = axios.create({
    baseURL: '/api/v1.0/comments',
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
    // 字评论
    getCharComments(chara) {
        return apiClient.get('/char', { params: { chara } });
    },
    postCharComment(chara, content) {
        return apiClient.post('/char', { chara, content });
    },
    editCharComment(commentId, content) {
        return apiClient.put('/char', { comment_id: commentId, content });
    },
    deleteCharComment(commentId) {
        return apiClient.delete('/char', { data: { comment_id: commentId } });
    },
    getCharCommentVersions(commentId) {
        return apiClient.get('/char', { params: { comment_id: commentId, versions: 1 } });
    },

    // 字表评论
    getSheetComments(sheetKey) {
        return apiClient.get('/sheet', { params: { key: sheetKey } });
    },
    postSheetComment(sheetKey, content) {
        return apiClient.post('/sheet', { sheet_key: sheetKey, content });
    },
    editSheetComment(commentId, content) {
        return apiClient.put('/sheet', { comment_id: commentId, content });
    },
    deleteSheetComment(commentId) {
        return apiClient.delete('/sheet', { data: { comment_id: commentId } });
    },

    // 批量获取评论数量
    getCounts(type, targets) {
        if (!targets || targets.length === 0) {
            return Promise.resolve({ data: { counts: {} } });
        }
        return apiClient.get('/counts.php', {
            params: { type, targets: targets.join(',') }
        });
    },
};
