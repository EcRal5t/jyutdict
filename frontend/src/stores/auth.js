import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import authApi from '@/api/auth.js';

export const useAuthStore = defineStore('auth', () => {
    // ========== State ==========
    const user = ref(null);           // { id, email, nickname, role, assigned_locations }
    const csrfToken = ref('');
    const isLoading = ref(true);      // 初始載入狀態
    const isInitialized = ref(false); // 是否已完成初始化檢查

    // ========== Getters ==========
    const isLoggedIn = computed(() => !!user.value);
    const userRole = computed(() => user.value?.role || 'guest');
    const displayName = computed(() => {
        if (!user.value) return '';
        return user.value.nickname || user.value.email.split('@')[0];
    });
    const isAdmin = computed(() => ['admin', 'owner'].includes(userRole.value));
    const isEditor = computed(() => ['editor', 'admin', 'owner'].includes(userRole.value));
    const isOwner = computed(() => userRole.value === 'owner');

    // ========== Actions ==========

    /**
     * 初始化：檢查登入狀態
     * 在 App.vue 的 onMounted 中調用
     */
    async function init() {
        if (isInitialized.value) return;
        isLoading.value = true;
        try {
            const res = await authApi.getMe();
            if (res.data.user) {
                user.value = res.data.user;
                csrfToken.value = res.data.csrf_token || '';
            }
        } catch (e) {
            console.error('Auth init failed:', e);
            user.value = null;
        } finally {
            isLoading.value = false;
            isInitialized.value = true;
        }
    }

    /**
     * 跳轉到 Google OAuth 登入
     */
    function login() {
        window.location.href = authApi.getLoginUrl();
    }

    /**
     * 登出
     */
    async function logout() {
        try {
            await authApi.logout();
        } catch (e) {
            console.error('Logout failed:', e);
        } finally {
            user.value = null;
            csrfToken.value = '';
        }
    }

    /**
     * 刷新用戶信息（角色變更後調用）
     */
    async function refreshUser() {
        try {
            const res = await authApi.getMe();
            if (res.data.user) {
                user.value = res.data.user;
                csrfToken.value = res.data.csrf_token || '';
            }
        } catch (e) {
            console.error('Refresh user failed:', e);
        }
    }

    return {
        user, csrfToken, isLoading, isInitialized,
        isLoggedIn, userRole, displayName, isAdmin, isEditor, isOwner,
        init, login, logout, refreshUser,
    };
});
