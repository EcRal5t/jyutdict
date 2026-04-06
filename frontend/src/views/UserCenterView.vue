<script setup>
import { ref, onMounted, computed } from 'vue'
import { useAuthStore } from '@/stores/auth.js'
import axios from 'axios'

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
        nicknameError.value = '昵称长度为 1-50 个字符'
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
        nicknameError.value = e.response?.data?.error || '保存失败'
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
    const map = { owner: '站长', admin: '管理员', editor: '编纂者', user: '普通用户' }
    return map[authStore.userRole] || '普通用户'
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

onMounted(() => {
    loadComments()
})
</script>

<template>
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 mb-8">用户中心</h1>

        <!-- 个人资料卡片 -->
        <section class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 p-6 mb-8">
            <h2 class="text-lg font-bold text-slate-700 dark:text-slate-200 mb-4 border-l-4 border-accent pl-3">个人资料</h2>

            <div class="space-y-4">
                <!-- 邮箱（不可编辑） -->
                <div class="flex items-center gap-4">
                    <span class="text-sm text-slate-500 w-20">邮箱</span>
                    <span class="text-sm text-slate-800 dark:text-slate-200">{{ authStore.user?.email }}</span>
                </div>

                <!-- 角色 -->
                <div class="flex items-center gap-4">
                    <span class="text-sm text-slate-500 w-20">角色</span>
                    <span class="text-xs px-2 py-1 rounded font-medium" :class="roleColor">{{ roleLabel }}</span>
                </div>

                <!-- 昵称（可编辑） -->
                <div class="flex items-center gap-4">
                    <span class="text-sm text-slate-500 w-20">昵称</span>
                    <template v-if="!editingNickname">
                        <span class="text-sm text-slate-800 dark:text-slate-200">{{ authStore.user?.nickname || '(未设置)' }}</span>
                        <button @click="startEditNickname" class="text-xs text-accent hover:underline">修改</button>
                    </template>
                    <template v-else>
                        <input v-model="nicknameInput" @keypress.enter="saveNickname"
                            class="text-sm p-1.5 border border-gray-300 dark:border-slate-600 dark:bg-slate-900 rounded w-48 focus:ring-1 focus:ring-accent outline-none"
                            maxlength="50" placeholder="输入昵称" />
                        <button @click="saveNickname" :disabled="nicknameSaving"
                            class="text-xs bg-accent text-white px-3 py-1 rounded hover:bg-red-700 disabled:opacity-50">
                            {{ nicknameSaving ? '...' : '保存' }}
                        </button>
                        <button @click="editingNickname = false" class="text-xs text-slate-400 hover:text-slate-600">取消</button>
                    </template>
                </div>
                <p v-if="nicknameError" class="text-xs text-red-500 ml-24">{{ nicknameError }}</p>

                <!-- 注册时间 -->
                <div class="flex items-center gap-4">
                    <span class="text-sm text-slate-500 w-20">注册时间</span>
                    <span class="text-sm text-slate-800 dark:text-slate-200">{{ authStore.user?.created_at }}</span>
                </div>
            </div>
        </section>

        <!-- 编纂者：负责地点 -->
        <section v-if="authStore.isEditor && authStore.user?.assigned_locations?.length"
            class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 p-6 mb-8">
            <h2 class="text-lg font-bold text-slate-700 dark:text-slate-200 mb-4 border-l-4 border-green-500 pl-3">我负责的地点</h2>
            <div class="flex flex-wrap gap-2">
                <router-link v-for="loc in authStore.user.assigned_locations" :key="`${loc.location_source}-${loc.location_id}`"
                    :to="{ name: 'location-article', params: { source: loc.location_source, locationId: loc.location_id } }"
                    class="text-sm px-3 py-1.5 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 rounded-lg hover:bg-green-100 dark:hover:bg-green-900/30 transition-colors">
                    {{ loc.location_source === 'area' ? 'i_area_list' : 'i_faamjyut' }} #{{ loc.location_id }}
                </router-link>
            </div>
        </section>

        <!-- 我的评论 -->
        <section class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 p-6">
            <h2 class="text-lg font-bold text-slate-700 dark:text-slate-200 mb-4 border-l-4 border-wood pl-3">我的评论</h2>

            <div v-if="commentsLoading" class="text-center py-8">
                <div class="inline-block animate-spin rounded-full h-6 w-6 border-2 border-gray-200 border-t-accent"></div>
            </div>

            <div v-else-if="comments.length === 0" class="text-center text-slate-400 py-8 text-sm">
                暂无评论
            </div>

            <div v-else class="space-y-3">
                <div v-for="comment in comments" :key="`${comment.type}-${comment.id}`"
                    class="p-3 bg-slate-50 dark:bg-slate-900/50 rounded-lg border border-slate-100 dark:border-slate-700">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-xs px-1.5 py-0.5 rounded"
                              :class="comment.type === 'char' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' : 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'">
                            {{ comment.type === 'char' ? '字评论' : '字表评论' }}
                        </span>
                        <span class="text-xs text-slate-400">{{ comment.target }}</span>
                        <span class="text-xs text-slate-400 ml-auto">{{ comment.created_at }}</span>
                        <span v-if="comment.is_deleted" class="text-xs text-red-400">(已删除)</span>
                    </div>
                    <p class="text-sm text-slate-700 dark:text-slate-300 line-clamp-2">
                        {{ comment.is_deleted ? '该评论已删除' : comment.content }}
                    </p>
                </div>
            </div>

            <!-- 分页 -->
            <div v-if="commentsPagination.total_pages > 1" class="flex justify-center gap-2 mt-6">
                <button v-for="p in commentsPagination.total_pages" :key="p" @click="loadComments(p)"
                    class="px-3 py-1 text-sm rounded border"
                    :class="p === commentsPagination.page ? 'bg-accent text-white border-accent' : 'border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700'">
                    {{ p }}
                </button>
            </div>
        </section>
    </div>
</template>
