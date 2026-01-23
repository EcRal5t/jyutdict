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
        {
            path: '/:pathMatch(.*)*',
            name: 'not-found',
            component: () => import('../views/NotFoundView.vue')
        }
    ]
})

export default router
