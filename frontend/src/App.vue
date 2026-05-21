<script setup>
import { RouterLink, RouterView, useRoute } from 'vue-router'
import { ref, onMounted, computed } from 'vue'
import { updateLogs, version, pansheetver } from './utils/updates.js'
import { useAuthStore } from './stores/auth.js'
import LoginButton from './components/LoginButton.vue'

const route = useRoute()
const authStore = useAuthStore()

// 主题状态: 'light' | 'dark' | 'system'
const themeMode = ref('system')
const showMobileMenu = ref(false)

// 计算当前是否为深色模式
const isDarkMode = computed(() => {
    if (themeMode.value === 'system') {
        return window.matchMedia('(prefers-color-scheme: dark)').matches
    }
    return themeMode.value === 'dark'
})

// 应用主题
const applyTheme = () => {
    if (isDarkMode.value) {
        document.documentElement.classList.add('dark')
    } else {
        document.documentElement.classList.remove('dark')
    }
}

// 切换主题 (循环: light -> dark -> system -> light)
const toggleTheme = () => {
    const modes = ['light', 'dark', 'system']
    const currentIndex = modes.indexOf(themeMode.value)
    themeMode.value = modes[(currentIndex + 1) % modes.length]
    localStorage.setItem('themeMode', themeMode.value)
    applyTheme()
}

// 主题图标
const themeIcon = computed(() => {
    if (themeMode.value === 'light') return 'sun'
    if (themeMode.value === 'dark') return 'moon'
    return 'system'
})

// Tooltip Logic
const showTooltip = ref(false)
const tooltipContent = computed(() => {
    if (route.path === '/sheet') {
        return `Main Version: ${version}\nLast Update: ${pansheetver}`; // Placeholder or simplified for home
    }
    // Default / Home
    return updateLogs.map(l => `${l.city} - ${l.date}`).join('\n') + `\n\n主版本: ${version}`;
})

onMounted(() => {
    // 读取保存的主题设置
    const savedMode = localStorage.getItem('themeMode')
    if (savedMode && ['light', 'dark', 'system'].includes(savedMode)) {
        themeMode.value = savedMode
    }
    applyTheme()

    // 监听系统主题变化
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (themeMode.value === 'system') {
            applyTheme()
        }
    })

    // 初始化登入狀態
    authStore.init()
})

const menuItems = [
    { label: '檢字', path: '/' }, // Originally index.php, now Home
    { label: '檢音', path: '/pronunciation' },
    { label: '泛粵字表', path: '/sheet' },
    { label: '紀文', path: '/articles' },
    { label: '地點介紹', path: '/locations' },
    { label: '相似音系測試', path: '/phonology' },
]
const externalLinks = [
    { label: 'GoT', url: 'https://got.jyutdict.org' },
    { label: '關於', url: 'https://jyutjam.org/' },
    { label: '說明', path: '/about', hasTooltip: true },
]
</script>

<template>
    <div
        class="min-h-screen flex flex-col bg-background dark:bg-slate-900 text-slate-800 dark:text-slate-100 font-sans transition-colors duration-300 font-serif">

        <header
            class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border-b border-gray-200 dark:border-slate-800 sticky top-0 z-50 transition-colors duration-300">
            <div class="container mx-auto px-4 py-3">
                <div class="flex justify-between items-center gap-2">
                    <!-- Brand container with Dropdown -->
                    <div class="relative group flex-shrink-0">
                        <button @click="showMobileMenu = !showMobileMenu"
                            class="flex items-center gap-2 group/btn flex-shrink-0 focus:outline-none">
                            <span
                                class="text-xl sm:text-2xl font-bold text-accent dark:text-red-500 tracking-tight transition-opacity whitespace-nowrap">
                                泛粵大典
                            </span>
                            <svg xmlns="http://www.w3.org/2000/svg"
                                class="h-5 w-5 text-gray-500 lg:hidden transition-transform flex-shrink-0"
                                :class="{ 'rotate-180': showMobileMenu }" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <!-- Mobile Dropdown Menu -->
                        <div v-if="showMobileMenu"
                            class="absolute left-0 top-full mt-2 w-56 bg-white dark:bg-slate-800 border-2 border-slate-200 dark:border-slate-700 rounded-none shadow-[4px_4px_0_rgba(0,0,0,0.1)] dark:shadow-[4px_4px_0_rgba(0,0,0,0.3)] py-2 lg:hidden animate-fade-in z-50">
                            <template v-for="item in menuItems" :key="item.label">
                                <a v-if="item.external" :href="item.path"
                                    class="block px-4 py-3 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 border-l-4 border-transparent hover:border-accent hover:translate-x-1 transition-all"
                                    :class="{ 'line-through opacity-60': item.strikethrough }"
                                    @click="showMobileMenu = false">
                                    {{ item.label }}
                                </a>
                                <RouterLink v-else :to="item.path"
                                    class="block px-4 py-3 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 border-l-4 border-transparent hover:border-accent hover:translate-x-1 transition-all"
                                    :class="{ 'line-through opacity-60': item.strikethrough }"
                                    active-class="bg-accent/5 border-l-accent font-bold"
                                    @click="showMobileMenu = false">
                                    {{ item.label }}
                                </RouterLink>
                            </template>

                            <!-- Mobile Only Links (shown inside menu when screen is small) -->
                            <div class="sm:hidden">
                                <div class="h-px bg-slate-200 dark:bg-slate-700 my-2"></div>
                                <a href="https://got.jyutdict.org" target="_blank"
                                    class="block px-4 py-3 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 border-l-4 border-transparent hover:border-accent hover:translate-x-1 transition-all"
                                    @click="showMobileMenu = false">
                                    GoT
                                </a>
                                <a href="https://jyutjam.org/" target="_blank"
                                    class="block px-4 py-3 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 border-l-4 border-transparent hover:border-accent hover:translate-x-1 transition-all"
                                    @click="showMobileMenu = false">
                                    關於
                                </a>
                                <RouterLink to="/about"
                                    class="block px-4 py-3 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 border-l-4 border-transparent hover:border-accent hover:translate-x-1 transition-all"
                                    @click="showMobileMenu = false">
                                    說明
                                </RouterLink>
                            </div>
                        </div>
                    </div>

                    <!-- Right side navigation and actions -->
                    <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0">
                        <!-- Tablet Nav Links (visible on tablet, hidden on mobile phone/desktop) -->
                        <div class="hidden sm:flex lg:hidden items-center gap-3 text-sm font-medium flex-shrink-0">
                            <a href="https://got.jyutdict.org" target="_blank"
                                class="text-slate-600 dark:text-slate-400 hover:text-accent text-xs whitespace-nowrap">GoT</a>
                            <a href="https://jyutjam.org/" target="_blank"
                                class="text-slate-600 dark:text-slate-400 hover:text-accent text-xs whitespace-nowrap">關於</a>
                            <RouterLink to="/about"
                                class="text-slate-600 dark:text-slate-400 hover:text-accent text-xs whitespace-nowrap">說明</RouterLink>
                        </div>

                        <!-- Mobile/Tablet User Entry -->
                        <div class="lg:hidden flex items-center flex-shrink-0">
                            <LoginButton />
                        </div>

                        <!-- Desktop Nav -->
                        <nav class="hidden lg:flex items-center gap-4 text-sm font-medium flex-shrink-0">
                            <template v-for="item in menuItems" :key="item.label">
                                <a v-if="item.external" :href="item.path" class="nav-link whitespace-nowrap"
                                    :class="{ 'line-through opacity-60': item.strikethrough }">{{ item.label }}</a>
                                <RouterLink v-else :to="item.path" class="nav-link whitespace-nowrap"
                                    :class="{ 'line-through opacity-60': item.strikethrough }" active-class="active">{{
                                        item.label }}
                                </RouterLink>
                            </template>
                            <div class="h-4 w-px bg-gray-300 dark:bg-slate-700 mx-1"></div>
                            <!-- Desktop External Links -->
                            <template v-for="link in externalLinks" :key="link.label">
                                <div v-if="link.hasTooltip" class="relative group/tooltip">
                                    <RouterLink v-if="link.path" :to="link.path" class="nav-link whitespace-nowrap" active-class="active">{{
                                        link.label }}</RouterLink>
                                    <a v-else :href="link.url" class="nav-link whitespace-nowrap">{{ link.label }}</a>
                                    <!-- Tooltip -->
                                    <div
                                        class="absolute right-0 top-full mt-2 w-64 max-h-[80vh] overflow-y-auto bg-white dark:bg-slate-800 border-2 border-slate-200 dark:border-slate-700 shadow-[4px_4px_0_rgba(0,0,0,0.1)] dark:shadow-[4px_4px_0_rgba(0,0,0,0.3)] rounded-none p-4 text-xs text-slate-600 dark:text-slate-300 whitespace-pre-wrap opacity-0 invisible group-hover/tooltip:opacity-100 group-hover/tooltip:visible transition-all duration-200 z-[60]">
                                        {{ tooltipContent }}
                                    </div>
                                </div>
                                <RouterLink v-else-if="link.path" :to="link.path" class="nav-link whitespace-nowrap" active-class="active">{{
                                    link.label }}</RouterLink>
                                <a v-else :href="link.url" target="_blank" class="nav-link whitespace-nowrap">{{ link.label }}</a>
                            </template>
                        </nav>

                        <!-- 用戶登入/菜單 (Desktop) -->
                        <div class="hidden lg:block flex-shrink-0">
                            <LoginButton />
                        </div>

                        <!-- Theme Toggle -->
                        <button @click="toggleTheme"
                            class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors text-slate-600 dark:text-slate-400 flex-shrink-0"
                            :title="themeMode === 'light' ? '亮色模式' : themeMode === 'dark' ? '深色模式' : '跟隨系統'">
                            <!-- Sun Icon (light mode) -->
                            <svg v-if="themeIcon === 'sun'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <!-- Moon Icon (dark mode) -->
                            <svg v-else-if="themeIcon === 'moon'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                            </svg>
                            <!-- System Icon -->
                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <main class="w-full flex-1 flex flex-col">
            <RouterView />
        </main>

        <footer v-if="route.path !== '/phonology'"
            class="mt-20 py-8 border-t border-gray-200 dark:border-slate-800 text-center text-sm text-gray-500 dark:text-slate-500 flex-shrink-0 relative z-10">
            <p>© 2019-2026 <a href="https://jyutjam.org" class="hover:text-accent transition-colors">嶺南粵音</a> <a
                    href="https://jyutdict.org" class="hover:text-accent transition-colors">泛粵大典</a> 開發組 版權所有</p>
        </footer>
    </div>
</template>

<style>
/* Global Scrollbar Styles */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: transparent;
}

::-webkit-scrollbar-thumb {
    @apply bg-gray-300 dark:bg-slate-600 rounded-full;
}

::-webkit-scrollbar-thumb:hover {
    @apply bg-gray-400 dark:bg-slate-500;
}
</style>

<style scoped>
.nav-link {
    @apply text-slate-600 dark:text-slate-400 hover:text-accent dark:hover:text-red-400 transition-colors relative py-1;
}

.nav-link.active {
    @apply text-accent dark:text-red-400 font-bold;
}

.nav-link::after {
    content: '';
    @apply absolute bottom-0 left-0 w-full h-0.5 bg-accent dark:bg-red-500 transform scale-x-0 transition-transform origin-right duration-300;
}

.nav-link:hover::after,
.nav-link.active::after {
    @apply transform scale-x-100 origin-left;
}
</style>
