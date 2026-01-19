import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'

const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes: [
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
        }
    ]
})

export default router
