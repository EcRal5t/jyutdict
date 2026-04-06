<script setup>
import { useAuthStore } from '@/stores/auth.js'

const authStore = useAuthStore()
</script>

<template>
    <!-- 加载中 -->
    <div v-if="authStore.isLoading" class="text-sm text-slate-400">
        ...
    </div>

    <!-- 已登录：显示用户菜单 -->
    <div v-else-if="authStore.isLoggedIn" class="relative group/user">
        <button class="flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-400 hover:text-accent dark:hover:text-red-400 transition-colors py-1">
            <span>{{ authStore.displayName }}</span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- 下拉菜单 -->
        <div class="absolute right-0 top-full mt-1 w-48 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg shadow-xl py-1 opacity-0 invisible group-hover/user:opacity-100 group-hover/user:visible transition-all duration-200 z-[60]">
            <!-- 角色标签 -->
            <div class="px-4 py-2 border-b border-gray-100 dark:border-slate-700">
                <span class="text-xs text-slate-400">{{ authStore.user.email }}</span>
                <span v-if="authStore.userRole !== 'user'"
                      class="ml-1 inline-block text-xs px-1.5 py-0.5 rounded"
                      :class="{
                          'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400': authStore.isOwner,
                          'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400': authStore.userRole === 'admin',
                          'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400': authStore.userRole === 'editor',
                      }">
                    {{ { owner: '站长', admin: '管理员', editor: '编纂者' }[authStore.userRole] }}
                </span>
            </div>

            <router-link to="/user" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-gray-100 dark:hover:bg-slate-700">
                用户中心
            </router-link>

            <router-link v-if="authStore.isAdmin" to="/admin" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-gray-100 dark:hover:bg-slate-700">
                后台管理
            </router-link>

            <button @click="authStore.logout()" class="w-full text-left px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-slate-700">
                登出
            </button>
        </div>
    </div>

    <!-- 未登录：显示登录按钮 -->
    <button v-else @click="authStore.login()"
        class="text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-accent dark:hover:text-red-400 transition-colors py-1 px-3 border border-slate-300 dark:border-slate-600 rounded-lg hover:border-accent dark:hover:border-red-500">
        登录
    </button>
</template>
