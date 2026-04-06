<script setup>
import { ref, onMounted, computed } from 'vue'
import { useAuthStore } from '@/stores/auth.js'
import axios from 'axios'
import articlesApi from '@/api/articles.js'

const authStore = useAuthStore()

// ===== 个人资料编辑 =====
const editingNickname = ref(false)
const nicknameInput = ref('')
const nicknameSaving = ref(false)
const nicknameError = ref('')

const startEditNickname = () => {
    nicknameInput.value = authStore.user?.nickname || ''
    editingNickname.value = true
    nicknameError.value = ''
}

const saveNickname = async () => {
    const val = nicknameInput.value.trim()
    if (!val || val.length > 50) {
        nicknameError.value = '暱稱長度為 1-50 個字元'
        return
    }
    nicknameSaving.value = true
    try {
        const client = axios.create({ baseURL: '/api/v1.0', withCredentials: true })
        client.defaults.headers['X-CSRF-Token'] = authStore.csrfToken
        await client.put('/user/profile', { nickname: val })
        await authStore.refreshUser()
        editingNickname.value = false
    } catch (e) {
        nicknameError.value = e.response?.data?.error || '儲存失敗'
    } finally {
        nicknameSaving.value = false
    }
}

// ===== 我的评论 =====
const comments = ref([])
const commentsLoading = ref(false)
const commentsPagination = ref({ total: 0, page: 1, per_page: 20, total_pages: 0 })

const loadComments = async (page = 1) => {
    commentsLoading.value = true
    try {
        const client = axios.create({ baseURL: '/api/v1.0', withCredentials: true })
        const res = await client.get('/user/comments', { params: { page, per_page: 20 } })
        comments.value = res.data.comments || []
        commentsPagination.value = res.data.pagination || {}
    } catch (e) {
        console.error('Failed to load comments', e)
    } finally {
        commentsLoading.value = false
    }
}

// ===== 角色显示 =====
const roleLabel = computed(() => {
    const map = { owner: '站長', admin: '管理員', editor: '編纂者', user: '普通用戶' }
    return map[authStore.userRole] || '普通用戶'
})

const roleColor = computed(() => {
    const map = {
        owner: 'text-red-600 bg-red-50 dark:text-red-400 dark:bg-red-900/20',
        admin: 'text-blue-600 bg-blue-50 dark:text-blue-400 dark:bg-blue-900/20',
        editor: 'text-green-600 bg-green-50 dark:text-green-400 dark:bg-green-900/20',
        user: 'text-slate-600 bg-slate-50 dark:text-slate-400 dark:bg-slate-800',
    }
    return map[authStore.userRole] || map.user
})

// ===== 地点文章管理 =====
const availableLocations = ref([])
const locationsLoading = ref(false)
const locationSearch = ref('')

const loadAvailableLocations = async () => {
    locationsLoading.value = true
    try {
        const res = await articlesApi.getAvailableLocations(locationSearch.value)
        availableLocations.value = res.data.locations || []
    } catch (e) {
        console.error('Failed to load locations', e)
    } finally {
        locationsLoading.value = false
    }
}

const searchLocations = () => {
    loadAvailableLocations()
}

onMounted(() => {
    loadComments()
    if (authStore.isEditor) {
        loadAvailableLocations()
    }
})
</script>

<template>
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 mb-8">用戶中心</h1>

        <!-- 個人資料卡片 -->
        <section class="bg-white/80 dark:bg-slate-800/80 backdrop-blur-md rounded-none shadow-[8px_8px_0_rgba(0,0,0,0.04)] dark:shadow-[8px_8px_0_rgba(0,0,0,0.2)] border border-white/50 dark:border-slate-700/50 p-6 mb-8 transition-all hover:shadow-[10px_10px_0_rgba(0,0,0,0.06)] hover:-translate-y-0.5">
            <h2 class="text-lg font-bold text-slate-700 dark:text-slate-200 mb-4 border-l-4 border-accent pl-3">個人資料</h2>

            <div class="space-y-4">
                <!-- 郵箱（不可編輯） -->
                <div class="flex items-center gap-4">
                    <span class="text-sm text-slate-500 w-20">郵箱</span>
                    <span class="text-sm text-slate-800 dark:text-slate-200">{{ authStore.user?.email }}</span>
                </div>

                <!-- 角色 -->
                <div class="flex items-center gap-4">
                    <span class="text-sm text-slate-500 w-20">角色</span>
                    <span class="text-xs px-2 py-1 rounded-none font-medium text-center border-l-2" :class="[roleColor, authStore.userRole === 'owner' ? 'border-red-500' : authStore.userRole === 'admin' ? 'border-blue-500' : authStore.userRole === 'editor' ? 'border-green-500' : 'border-slate-500']">{{ roleLabel }}</span>
                </div>

                <!-- 暱稱（可編輯） -->
                <div class="flex items-center gap-4">
                    <span class="text-sm text-slate-500 w-20">暱稱</span>
                    <template v-if="!editingNickname">
                        <span class="text-sm text-slate-800 dark:text-slate-200">{{ authStore.user?.nickname || '(未設定)' }}</span>
                        <button @click="startEditNickname" class="text-xs text-accent hover:underline">修改</button>
                    </template>
                    <template v-else>
                        <input v-model="nicknameInput" @keypress.enter="saveNickname"
                            class="text-sm p-2 border border-gray-200 dark:border-slate-600 dark:bg-slate-900/50 rounded-none w-48 focus:ring-2 focus:ring-accent/50 focus:border-accent outline-none transition-all shadow-sm"
                            maxlength="50" placeholder="輸入暱稱" />
                        <button @click="saveNickname" :disabled="nicknameSaving"
                            class="text-xs bg-accent text-white px-3 py-1 rounded-none hover:bg-red-700 disabled:opacity-50 hover:-translate-y-0.5 transition-transform hover:shadow-[2px_2px_0_rgba(183,41,20,0.3)] shadow-sm">
                            {{ nicknameSaving ? '...' : '儲存' }}
                        </button>
                        <button @click="editingNickname = false" class="text-xs text-slate-400 hover:text-slate-600">取消</button>
                    </template>
                </div>
                <p v-if="nicknameError" class="text-xs text-red-500 ml-24">{{ nicknameError }}</p>

                <!-- 註冊時間 -->
                <div class="flex items-center gap-4">
                    <span class="text-sm text-slate-500 w-20">註冊時間</span>
                    <span class="text-sm text-slate-800 dark:text-slate-200">{{ authStore.user?.created_at }}</span>
                </div>
            </div>
        </section>

        <!-- 地點文章管理（編纂者/管理員/站長可见） -->
        <section v-if="authStore.isEditor"
            class="bg-white/80 dark:bg-slate-800/80 backdrop-blur-md rounded-none shadow-[8px_8px_0_rgba(0,0,0,0.04)] dark:shadow-[8px_8px_0_rgba(0,0,0,0.2)] border border-white/50 dark:border-slate-700/50 p-6 mb-8 transition-all hover:shadow-[10px_10px_0_rgba(0,0,0,0.06)] hover:-translate-y-0.5">
            <h2 class="text-lg font-bold text-slate-700 dark:text-slate-200 mb-4 border-l-4 border-green-500 pl-3">地點文章管理</h2>

            <!-- 搜索框 -->
            <div class="flex gap-2 mb-4">
                <input v-model="locationSearch" @keypress.enter="searchLocations" placeholder="搜尋地點名稱..."
                    class="flex-1 p-2.5 text-sm border border-gray-200 dark:border-slate-700 dark:bg-slate-900/50 rounded-none outline-none focus:ring-2 focus:ring-accent/50 focus:border-accent transition-all shadow-sm" />
                <button @click="searchLocations"
                    class="px-5 py-2.5 text-sm font-medium bg-accent text-white rounded-none shadow-[4px_4px_0_rgba(183,41,20,0.2)] dark:shadow-[4px_4px_0_rgba(183,41,20,0.4)] hover:shadow-[6px_6px_0_rgba(183,41,20,0.3)] hover:-translate-y-0.5 transition-all duration-300">
                    搜尋
                </button>
            </div>

            <!-- 加载中 -->
            <div v-if="locationsLoading" class="text-center py-4">
                <div class="inline-block animate-spin rounded-full h-5 w-5 border-2 border-gray-200 border-t-accent"></div>
            </div>

            <!-- 地点列表 -->
            <div v-else-if="availableLocations.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-[300px] overflow-y-auto overflow-x-hidden pr-2">
                <div v-for="loc in availableLocations" :key="`${loc.source}-${loc.name}`"
                    class="flex items-center justify-between px-3 py-2 bg-white dark:bg-slate-800/50 rounded-none border border-gray-100 dark:border-slate-700/50 border-l-4 transition-all duration-300 group hover:translate-x-0.5 hover:shadow-[4px_4px_0_rgba(0,0,0,0.05)] dark:hover:shadow-[4px_4px_0_rgba(0,0,0,0.3)]"
                    :class="loc.has_article ? 'border-l-accent' : 'border-l-gray-300 dark:border-l-slate-600 hover:border-l-accent/50'">
                    <div>
                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200 group-hover:text-accent transition-colors">{{ loc.name }}</span>
                    </div>
                    <router-link
                        :to="{ name: 'location-article', params: { source: loc.source, locationName: loc.name } }"
                        class="text-[10px] px-2 py-1 flex-shrink-0 rounded-none transition-all shadow-sm hover:-translate-y-0.5 hover:shadow-[2px_2px_0_rgba(0,0,0,0.1)]"
                        :class="loc.has_article
                            ? 'border border-accent text-accent hover:bg-accent/10 dark:hover:shadow-[2px_2px_0_rgba(183,41,20,0.3)] dark:shadow-[2px_2px_0_rgba(0,0,0,0.3)]'
                            : 'bg-accent text-white hover:bg-red-700 dark:hover:shadow-[2px_2px_0_rgba(183,41,20,0.5)]'">
                        {{ loc.has_article ? '編輯文章' : '撰寫文章' }}
                    </router-link>
                </div>
            </div>

            <!-- 空状态 -->
            <div v-else class="text-center text-slate-400 py-4 text-sm">
                {{ locationSearch ? '未找到匹配的地點' : '暫無可管理的地點' }}
            </div>
        </section>

        <!-- 我的評論 -->
        <section class="bg-white/80 dark:bg-slate-800/80 backdrop-blur-md rounded-none shadow-[8px_8px_0_rgba(0,0,0,0.04)] dark:shadow-[8px_8px_0_rgba(0,0,0,0.2)] border border-white/50 dark:border-slate-700/50 p-6 transition-all hover:shadow-[10px_10px_0_rgba(0,0,0,0.06)] hover:-translate-y-0.5">
            <h2 class="text-lg font-bold text-slate-700 dark:text-slate-200 mb-4 border-l-4 border-slate-700 dark:border-slate-300 pl-3">我的評論</h2>

            <div v-if="commentsLoading" class="text-center py-8">
                <div class="inline-block animate-spin rounded-full h-6 w-6 border-2 border-gray-200 border-t-accent"></div>
            </div>

            <div v-else-if="comments.length === 0" class="text-center text-slate-400 py-8 text-sm">
                暫無評論
            </div>

            <div v-else class="space-y-3">
                <div v-for="comment in comments" :key="`${comment.type}-${comment.id}`"
                    class="p-4 bg-white dark:bg-slate-800/50 rounded-none border border-gray-100 dark:border-slate-700/50 transition-all duration-300 group hover:translate-x-1 hover:border-l-4 hover:border-l-slate-400 dark:hover:border-l-slate-500 hover:shadow-[4px_4px_0_rgba(0,0,0,0.05)] dark:hover:shadow-[4px_4px_0_rgba(0,0,0,0.3)]">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-xs px-1.5 py-0.5 rounded-none border-l-2"
                              :class="comment.type === 'char' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 border-amber-500' : 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400 border-purple-500'">
                            {{ comment.type === 'char' ? '字評論' : '字表評論' }}
                        </span>
                        <span class="text-xs text-slate-400">{{ comment.target }}</span>
                        <span class="text-xs text-slate-400 ml-auto">{{ comment.created_at }}</span>
                        <span v-if="comment.is_deleted" class="text-xs text-red-400">(已刪除)</span>
                    </div>
                    <p class="text-sm text-slate-700 dark:text-slate-300 line-clamp-2">
                        {{ comment.is_deleted ? '該評論已刪除' : comment.content }}
                    </p>
                </div>
            </div>

            <div v-if="commentsPagination.total_pages > 1" class="flex justify-center gap-2 mt-6">
                <button v-for="p in commentsPagination.total_pages" :key="p" @click="loadComments(p)"
                    class="px-3 py-1 text-sm rounded-none border transition-all hover:-translate-y-0.5 hover:shadow-[2px_2px_0_rgba(0,0,0,0.1)]"
                    :class="p === commentsPagination.page ? 'bg-accent text-white border-accent shadow-[2px_2px_0_rgba(183,41,20,0.3)]' : 'border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700'">
                    {{ p }}
                </button>
            </div>
        </section>
    </div>
</template>
