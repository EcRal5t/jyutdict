<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { marked } from 'marked'
import DOMPurify from 'dompurify'
import { useAuthStore } from '@/stores/auth.js'
import articlesApi from '@/api/articles.js'

const props = defineProps({
    source: { type: String, required: true },       // 'area' | 'faamjyut'
    locationName: { type: String, required: true },  // 地点名称
})

const emit = defineEmits(['close'])

const authStore = useAuthStore()
const article = ref(null)
const isLoading = ref(true)
const error = ref(null)

const renderedContent = computed(() => {
    if (!article.value?.content) return ''
    return DOMPurify.sanitize(marked(article.value.content))
})

const canEdit = computed(() => {
    if (!authStore.isLoggedIn) return false
    if (authStore.isAdmin) return true
    if (authStore.userRole === 'editor' && authStore.user?.assigned_locations) {
        return authStore.user.assigned_locations.some(
            loc => loc.location_source === props.source && loc.location_name === props.locationName
        )
    }
    return false
})

const loadArticle = async () => {
    isLoading.value = true
    error.value = null
    try {
        const res = await articlesApi.getArticle(props.source, props.locationName)
        article.value = res.data.article
    } catch (e) {
        error.value = '載入文章失敗'
    } finally {
        isLoading.value = false
    }
}

// 点击遮罩关闭
const onOverlayClick = (e) => {
    if (e.target === e.currentTarget) {
        emit('close')
    }
}

// ESC 关闭
const onKeydown = (e) => {
    if (e.key === 'Escape') emit('close')
}

watch(() => [props.source, props.locationName], () => {
    loadArticle()
}, { immediate: true })

// 挂载时监听键盘事件
onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
    <!-- 遮罩层 -->
    <div class="fixed inset-0 z-[100] bg-black/50 flex items-center justify-center p-4 md:py-[100px]"
         @click="onOverlayClick">
        <!-- 弹窗主体 -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-[600px] max-h-full overflow-hidden flex flex-col">
            <!-- 标题栏 -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-slate-700">
                <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ locationName }}</h2>
                <button @click="emit('close')"
                    class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- 内容区（滚动） -->
            <div class="flex-1 overflow-y-auto px-6 py-4">
                <!-- 加载中 -->
                <div v-if="isLoading" class="text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-6 w-6 border-2 border-gray-200 border-t-accent"></div>
                </div>

                <!-- 错误 -->
                <div v-else-if="error" class="text-center text-red-500 py-4">{{ error }}</div>

                <!-- 文章内容 -->
                <div v-else-if="article && renderedContent"
                    class="prose dark:prose-invert max-w-none text-sm"
                    v-html="renderedContent">
                </div>

                <!-- 无文章 -->
                <div v-else class="text-center text-slate-400 py-8">
                    <p>此地點暫無介紹文章</p>
                </div>
            </div>

            <!-- 底栏 -->
            <div v-if="article" class="px-6 py-3 border-t border-gray-100 dark:border-slate-700 flex items-center justify-between text-xs text-slate-400">
                <div>
                    <span>{{ article.nickname || article.email }}</span>
                    <span class="ml-3">{{ article.updated_at }}</span>
                </div>
                <router-link v-if="canEdit"
                    :to="{ name: 'location-article', params: { source, locationName } }"
                    @click="emit('close')"
                    class="text-xs px-3 py-1.5 rounded-lg bg-accent text-white hover:bg-red-700">
                    前往修改
                </router-link>
            </div>
        </div>
    </div>
</template>
