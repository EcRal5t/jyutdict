<script setup>
import { RouterLink, RouterView, useRoute } from 'vue-router'
import { ref, onMounted, computed } from 'vue'
import { updateLogs, version } from './utils/updates.js'

const route = useRoute()
const isDarkMode = ref(false)

const toggleTheme = () => {
    isDarkMode.value = !isDarkMode.value
    if (isDarkMode.value) {
        document.documentElement.classList.add('dark')
    } else {
        document.documentElement.classList.remove('dark')
    }
    localStorage.setItem('theme', isDarkMode.value ? 'dark' : 'light')
}

// Tooltip Logic
const showTooltip = ref(false)
const tooltipContent = computed(() => {
    if (route.path === '/sheet') {
        return `Version: ${version}\nLast Update: 2026-01-19`; // Placeholder or simplified for home
    }
    // Default / Home
    return updateLogs.map(l => `${l.city} - ${l.date}`).join('\n') + `\n\n主版本: ${version}`;
})

onMounted(() => {
    // Check local storage or system preference
    if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        isDarkMode.value = true
        document.documentElement.classList.add('dark')
    } else {
        isDarkMode.value = false
        document.documentElement.classList.remove('dark')
    }
})

const menuItems = [
    { label: '檢字', path: '/' }, // Originally index.php, now Home
    { label: '檢音', path: '/pron', external: true },
    { label: '泛粵字表', path: '/sheet' },
    { label: '紀文', path: '/articles/post', external: true },
    { label: '相似音系測試', path: '/probe', external: true },
]

const externalLinks = [
    { label: 'GoT', url: 'https://got.jyutdict.org' },
    { label: '關於', url: 'https://jyutjam.org/' },
    { label: '說明', url: 'https://jyutdict.org/about', hasTooltip: true },
]
</script>

<template>
  <div class="min-h-screen bg-background dark:bg-slate-900 text-slate-800 dark:text-slate-100 font-sans transition-colors duration-300 font-serif">
      
    <header class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border-b border-gray-200 dark:border-slate-800 sticky top-0 z-50 transition-colors duration-300">
      <div class="container mx-auto px-4 py-3">
        <div class="flex justify-between items-center gap-2">
            <!-- Brand -->
            <RouterLink to="/" class="flex items-center gap-2 group flex-shrink-0">
                <span class="text-2xl font-bold text-accent dark:text-red-500 tracking-tight group-hover:opacity-80 transition-opacity">
                    泛粵大典
                </span>
            </RouterLink>

            <!-- Mobile Nav (Secondary Links) -->
            <div class="md:hidden flex items-center gap-3 text-sm font-medium">
                 <template v-for="link in externalLinks" :key="link.label">
                     <!-- Only show simple links or handle tooltip simply -->
                     <a :href="link.url" :target="link.label === 'GoT' ? '' : '_blank'" class="text-slate-600 dark:text-slate-400 hover:text-accent text-xs">
                         {{ link.label }}
                     </a>
                 </template>
            </div>
            
            <!-- Desktop Nav -->
            <nav class="hidden md:flex items-center gap-6 text-sm font-medium">
                <template v-for="item in menuItems" :key="item.label">
                   <a v-if="item.external" :href="item.path" class="nav-link">{{ item.label }}</a>
                   <RouterLink v-else :to="item.path" class="nav-link" active-class="active">{{ item.label }}</RouterLink>
                </template>
                <div class="h-4 w-px bg-gray-300 dark:bg-slate-700 mx-2"></div>
                <template v-for="link in externalLinks" :key="link.label">
                    <div v-if="link.hasTooltip" class="relative group/tooltip">
                         <a :href="link.url" class="nav-link">{{ link.label }}</a>
                         <!-- Tooltip -->
                         <div class="absolute right-0 top-full mt-2 w-64 max-h-[80vh] overflow-y-auto bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 shadow-xl rounded-lg p-4 text-xs text-slate-600 dark:text-slate-300 whitespace-pre-wrap opacity-0 invisible group-hover/tooltip:opacity-100 group-hover/tooltip:visible transition-all duration-200 z-[60]">
                             {{ tooltipContent }}
                         </div>
                    </div>
                    <a v-else :href="link.url" target="_blank" class="nav-link">{{ link.label }}</a>
                </template>
            </nav>

            <!-- Theme Toggle -->
            <button @click="toggleTheme" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors text-slate-600 dark:text-slate-400">
                <!-- Sun Icon -->
                <svg v-if="isDarkMode" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <!-- Moon Icon -->
                <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                </svg>
            </button>
        </div>
      </div>
    </header>

    <main class="w-full">
        <RouterView />
    </main>

    <footer class="mt-20 py-8 border-t border-gray-200 dark:border-slate-800 text-center text-sm text-gray-500 dark:text-slate-500">
        <p>© 2019-2026 <a href="https://jyutjam.org" class="hover:text-accent transition-colors">嶺南粵音</a> <a href="https://jyutdict.org" class="hover:text-accent transition-colors">泛粵大典</a> 開發組 版權所有</p>
    </footer>
  </div>
</template>

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
.nav-link:hover::after, .nav-link.active::after {
    @apply transform scale-x-100 origin-left;
}
</style>
