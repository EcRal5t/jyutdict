<script setup>
import { ref, onMounted, computed } from 'vue'
import { useAuthStore } from '@/stores/auth.js'
import { marked } from 'marked'
import DOMPurify from 'dompurify'
import articlesApi from '@/api/articles.js'

const authStore = useAuthStore()

// ===== 文章列表 =====
const articles = ref([])
const isLoading = ref(true)
const searchQuery = ref('')

const loadArticles = async () => {
    isLoading.value = true
    try {
        const res = await articlesApi.getArticleList(searchQuery.value)
        articles.value = res.data.articles || []
    } catch (e) {
        console.error('Failed to load articles', e)
    } finally {
        isLoading.value = false
    }
}

const search = () => {
    loadArticles()
}

// ===== 选中文章 =====
const selectedArticle = ref(null)
const articleContent = ref(null)
const articleLoading = ref(false)

const selectArticle = async (art) => {
    selectedArticle.value = art
    articleLoading.value = true
    try {
        const res = await articlesApi.getArticle(art.location_name)
        articleContent.value = res.data.article
    } catch (e) {
        console.error(e)
        articleContent.value = null
    } finally {
        articleLoading.value = false
    }
}

const renderedContent = computed(() => {
    if (!articleContent.value?.content) return ''
    return DOMPurify.sanitize(marked(articleContent.value.content))
})

// 判断是否可以编辑选中的文章
const canEditSelected = computed(() => {
    if (!selectedArticle.value || !authStore.isLoggedIn) return false
    if (authStore.isAdmin) return true
    if (authStore.userRole === 'editor' && authStore.user?.assigned_locations) {
        return authStore.user.assigned_locations.some(
            loc => loc.location_name === selectedArticle.value.location_name
        )
    }
    return false
})

onMounted(() => {
    loadArticles()
})
</script>

<template>
    <div class="container mx-auto px-4 py-6 max-w-6xl">
        <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100 mb-5 border-l-4 border-accent pl-3">地點介紹</h1>

        <div class="flex flex-col lg:flex-row gap-5">
            <!-- 左側：地點列表 -->
            <div class="lg:w-72 flex-shrink-0">
                <!-- 搜索框 -->
                <div class="flex gap-2 mb-4">
                    <input v-model="searchQuery" @keypress.enter="search" placeholder="搜尋地點..."
                        class="flex-1 p-2 text-sm border-2 border-gray-300 dark:border-slate-600 dark:bg-slate-800 rounded-none outline-none focus:border-accent transition-colors" />
                    <button @click="search"
                        class="px-4 py-2 text-sm bg-accent text-white rounded-none hover:bg-red-700 hover:-translate-y-0.5 hover:shadow-[3px_3px_0_rgba(183,41,20,0.3)] transition-all font-bold">
                        搵
                    </button>
                </div>

                <!-- 加载中 -->
                <div v-if="isLoading" class="text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-6 w-6 border-2 border-gray-200 border-t-accent"></div>
                </div>

                <!-- 列表 -->
                <div v-else class="space-y-0.5 max-h-[70vh] overflow-y-auto overflow-x-hidden pr-1">
                    <button v-for="art in articles" :key="art.location_name"
                        @click="selectArticle(art)"
                        class="w-full text-left p-2 rounded-none border-l-4 transition-all duration-300 hover:translate-x-1"
                        :class="selectedArticle?.location_name === art.location_name
                            ? 'border-l-accent bg-accent/5 dark:bg-accent/10'
                            : 'border-l-transparent hover:border-l-slate-300 dark:hover:border-l-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800'">
                        <div class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ art.location_name }}</div>
                        <div class="text-xs text-slate-400 mt-0.5">
                            {{ art.author_nickname }} · {{ art.updated_at?.split(' ')[0] }}
                        </div>
                    </button>

                    <p v-if="articles.length === 0" class="text-center text-slate-400 py-6 text-sm border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-none">
                        {{ searchQuery ? '未找到匹配的地點文章' : '暫無地點文章' }}
                    </p>
                </div>
            </div>

            <!-- 右側：文章内容 -->
            <div class="flex-1 min-w-0">
                <!-- 未选中 -->
                <div v-if="!selectedArticle" class="text-center text-slate-400 py-12 bg-slate-50 dark:bg-slate-800/50 rounded-none border-2 border-dashed border-slate-200 dark:border-slate-700">
                    <p class="text-base">請從左側選擇一個地點</p>
                </div>

                <!-- 加载中 -->
                <div v-else-if="articleLoading" class="text-center py-12">
                    <div class="inline-block animate-spin rounded-full h-6 w-6 border-2 border-gray-200 border-t-accent"></div>
                </div>

                <!-- 文章内容 -->
                <div v-else-if="articleContent" class="bg-white dark:bg-slate-800 rounded-none shadow-[6px_6px_0_rgba(0,0,0,0.06)] dark:shadow-[6px_6px_0_rgba(0,0,0,0.3)] p-5 md:p-6 border border-gray-100 dark:border-slate-700 hover:shadow-[8px_8px_0_rgba(0,0,0,0.08)] dark:hover:shadow-[8px_8px_0_rgba(0,0,0,0.4)] hover:-translate-y-0.5 transition-all duration-300">
                    <div v-if="renderedContent"
                        class="prose dark:prose-invert max-w-none"
                        v-html="renderedContent">
                    </div>
                    <div v-else class="text-center text-slate-400 py-6">文章內容為空</div>

                    <!-- 文章元数据 -->
                    <div class="mt-4 pt-3 border-t border-gray-100 dark:border-slate-700 flex items-center justify-between text-xs text-slate-400">
                        <div class="flex items-center gap-3 pl-2 border-l-2 border-slate-200 dark:border-slate-700">
                            <span>最後編輯：{{ articleContent.nickname || articleContent.email }}</span>
                            <span>{{ articleContent.updated_at }}</span>
                        </div>
                        <router-link v-if="canEditSelected"
                            :to="{ name: 'location-article', params: { locationName: selectedArticle.location_name } }"
                            class="text-xs px-3 py-1.5 rounded-none bg-accent text-white hover:bg-red-700 hover:-translate-y-0.5 hover:shadow-[2px_2px_0_rgba(183,41,20,0.3)] transition-all font-bold">
                            前往編輯
                        </router-link>
                    </div>
                </div>

                <!-- 无文章 -->
                <div v-else class="text-center text-slate-400 py-12 bg-slate-50 dark:bg-slate-800/50 rounded-none border-2 border-dashed border-slate-200 dark:border-slate-700">
                    <p>此地點暫無文章</p>
                </div>
            </div>
        </div>
    </div>
</template>
