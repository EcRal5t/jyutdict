import { createRouter, createWebHashHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'

const router = createRouter({
    history: createWebHashHistory(import.meta.env.BASE_URL),
    routes: [
        // ===== 现有路由不变 =====
        {
            path: '/',
            name: 'home',
            component: HomeView
        },
        {
            path: '/sheet',
            name: 'sheet',
            component: () => import('../views/SheetView.vue')
        },
        {
            path: '/detail',
            name: 'detail',
            component: () => import('../views/DetailView.vue')
        },
        {
            path: '/articles/:id?',
            name: 'article',
            component: () => import('../views/ArticleView.vue')
        },
        {
            path: '/phonology',
            name: 'phonology',
            component: () => import('../views/PhonologyView.vue')
        },
        {
            path: '/pronunciation',
            name: 'pronunciation',
            component: () => import('../views/PronunciationView.vue')
        },
        {
            path: '/about/:id?',
            name: 'about',
            component: () => import('../views/AboutView.vue')
        },

        // ===== 新增路由 =====
        {
            path: '/user',
            name: 'user-center',
            component: () => import('../views/UserCenterView.vue'),
            meta: { requiresAuth: true }
        },
        {
            path: '/admin',
            name: 'admin',
            component: () => import('../views/AdminView.vue'),
            meta: { requiresAuth: true, requiresRole: 'admin' }
        },
        {
            path: '/locations',
            name: 'locations',
            component: () => import('../views/LocationListView.vue'),
        },
        {
            path: '/location-article/:source/:locationName',
            name: 'location-article',
            component: () => import('../views/LocationArticleView.vue'),
        },

        // ===== 404 保持最后 =====
        {
            path: '/:pathMatch(.*)*',
            name: 'not-found',
            component: () => import('../views/NotFoundView.vue')
        }
    ]
})

// ===== 路由守卫 =====
router.beforeEach(async (to, from, next) => {
    if (!to.meta.requiresAuth) {
        return next()
    }

    // 懒加载 auth store（避免循环依赖）
    const { useAuthStore } = await import('../stores/auth.js')
    const authStore = useAuthStore()

    // 等待认证初始化完成
    if (!authStore.isInitialized) {
        await authStore.init()
    }

    if (!authStore.isLoggedIn) {
        // 未登入，跳轉到首頁（或彈出登入提示）
        return next({ name: 'home', query: { login_required: '1' } })
    }

    // 檢查角色要求
    if (to.meta.requiresRole) {
        const roleLevel = { user: 0, editor: 1, admin: 2, owner: 3 }
        const required = roleLevel[to.meta.requiresRole] || 0
        const current = roleLevel[authStore.userRole] || 0
        if (current < required) {
            return next({ name: 'home' }) // 權限不足，回首頁
        }
    }

    next()
})

export default router
