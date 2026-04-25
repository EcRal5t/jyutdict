import axios from 'axios';

const apiClient = axios.create({
    baseURL: '/api/v1.0',
    headers: {
        'Content-Type': 'application/json',
    },
});

export default {
    /**
     * 獲取元信息（表頭）
     * GET /api/v1.0/sheet
     */
    getMeta() {
        return apiClient.get('/sheet');
    },

    /**
     * 搜索
     * @param {Object} params
     * @param {string} params.q - 查詢內容
     * @param {string} params.col - 查詢列
     * @param {string} params.mode - 查詢模式：exact|fuzzy|regex|trim|meaning|auto
     * @param {number} params.limit - 返回條數上限
     */
    search({ q, col, mode, limit }) {
        const params = {};
        if (q !== undefined) params.q = q;
        if (col) params.col = col;
        if (mode && mode !== 'auto') params.mode = mode;
        if (limit) params.limit = limit;
        return apiClient.get('/sheet', { params });
    },

    /**
     * 按鍵精確查詢
     * @param {string} key - 鍵值（格式：>{數字}）
     */
    getByKey(key) {
        return apiClient.get('/sheet', { params: { key } });
    },

    /**
     * 隨機返回
     * @param {number} count - 返回條數（1-30）
     */
    getRandom(count = 10) {
        return apiClient.get('/sheet', { params: { random: count } });
    }
};
