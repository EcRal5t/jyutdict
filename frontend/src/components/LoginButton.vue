<script setup>
import { useAuthStore } from '@/stores/auth.js'
import RoleBadge from './RoleBadge.vue'
import { ref, onMounted, onUnmounted } from 'vue'

const authStore = useAuthStore()
const showDropdown = ref(false)
const dropdownRef = ref(null)

const toggleDropdown = () => {
    showDropdown.value = !showDropdown.value
}

const handleClickOutside = (event) => {
    if (showDropdown.value && dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        showDropdown.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <!-- 載入中 -->
    <div v-if="authStore.isLoading" class="text-sm text-slate-400">
        ...
    </div>

    <!-- 已登入：顯示用戶菜單 -->
    <div v-else-if="authStore.isLoggedIn" ref="dropdownRef" class="relative flex-shrink-0">
        <button @click="toggleDropdown"
            class="flex items-center gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-300 hover:text-accent dark:hover:text-red-400 transition-colors py-1.5 px-2 rounded-none hover:bg-black/5 dark:hover:bg-white/5 whitespace-nowrap flex-shrink-0">
            <span class="truncate max-w-[70px] sm:max-w-none">{{ authStore.displayName }}</span>
            <svg xmlns="http://www.w3.org/2000/svg"
                :class="{ 'rotate-180': showDropdown }"
                class="h-4 w-4 transition-transform duration-200 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- 下拉菜單 (Glassmorphism + Scales) -->
        <div :class="[
            'absolute right-0 top-full mt-2 w-52 bg-white/75 dark:bg-slate-800/75 border border-gray-200/50 dark:border-slate-700/50 rounded-none shadow-[4px_4px_0_rgba(0,0,0,0.05)] dark:shadow-[4px_4px_0_rgba(0,0,0,0.3)] py-2 origin-top transition-all duration-200 ease-out z-[60] glass-dropdown',
            showDropdown ? 'opacity-100 visible translate-y-0' : 'opacity-0 invisible -translate-y-2'
        ]">
            <!-- 角色標籤 -->
            <div class="px-4 py-2.5 border-b border-gray-100/50 dark:border-slate-700/50 flex flex-col gap-1.5 mb-1">
                <span class="text-xs text-slate-500 font-medium truncate">{{ authStore.user.email }}</span>
                <RoleBadge v-if="authStore.userRole !== 'user'" :role="authStore.userRole" />
            </div>

            <router-link to="/user" @click="showDropdown = false" class="block px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-accent/10 hover:text-accent transition-colors">
                用戶中心
            </router-link>

            <router-link v-if="authStore.isAdmin" to="/admin" @click="showDropdown = false" class="block px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-accent/10 hover:text-accent transition-colors">
                後台管理
            </router-link>

            <button @click="authStore.logout(); showDropdown = false" class="w-full text-left px-4 py-2 mt-1 border-t border-gray-100/50 dark:border-slate-700/50 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                登出
            </button>
        </div>
    </div>

    <!-- 未登入：顯示登入按鈕 -->
    <button v-else @click="authStore.login()"
        class="text-sm font-bold text-white bg-accent dark:bg-[#B72914] shadow-[4px_4px_0_rgba(183,41,20,0.2)] dark:shadow-[4px_4px_0_rgba(183,41,20,0.4)] hover:shadow-[6px_6px_0_rgba(183,41,20,0.3)] dark:hover:shadow-[6px_6px_0_rgba(183,41,20,0.5)] hover:-translate-y-0.5 hover:-translate-x-0.5 transition-all duration-300 py-1.5 px-3 sm:px-4 rounded-none whitespace-nowrap flex-shrink-0">
        登入
    </button>
</template>

<style scoped>
.glass-dropdown {
    -webkit-backdrop-filter: blur(16px) !important;
    backdrop-filter: blur(16px) !important;
}
</style>
