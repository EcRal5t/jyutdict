<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth.js'
import { marked } from 'marked'
import DOMPurify from 'dompurify'
import articlesApi from '@/api/articles.js'

const route = useRoute()
const authStore = useAuthStore()

const locationName = computed(() => decodeURIComponent(route.params.locationName))  // 地点名称字符串

// ===== 文章数据 =====
const article = ref(null)        // { id, content, updated_at, nickname, email, role }
const isLoading = ref(true)
const error = ref(null)

// ===== 编辑模式 =====
const isEditing = ref(false)
const editContent = ref('')
const editSummary = ref('')
const isSaving = ref(false)
const saveError = ref('')

// ===== 版本历史 =====
const showVersions = ref(false)
const versions = ref([])
const versionsLoading = ref(false)
const previewVersion = ref(null)   // 正在预览的版本

// ===== 权限判断 =====
const canEdit = computed(() => {
    if (!authStore.isLoggedIn) return false
    if (authStore.isAdmin) return true
    if (authStore.userRole === 'editor' && authStore.user?.assigned_locations) {
        return authStore.user.assigned_locations.some(
            loc => loc.location_name === locationName.value
        )
    }
    return false
})

// ===== 加载文章 =====
const loadArticle = async () => {
    isLoading.value = true
    error.value = null
    try {
        const res = await articlesApi.getArticle(locationName.value)
        article.value = res.data.article
    } catch (e) {
        error.value = '載入文章失敗'
        console.error(e)
    } finally {
        isLoading.value = false
    }
}

// ===== Markdown 渲染 =====
const renderedContent = computed(() => {
    const content = previewVersion.value?.content || article.value?.content || ''
    if (!content) return ''
    const raw = marked(content)
    return DOMPurify.sanitize(raw)
})

// ===== 编辑操作 =====
const startEdit = () => {
    editContent.value = article.value?.content || ''
    editSummary.value = ''
    isEditing.value = true
    saveError.value = ''
}

const cancelEdit = () => {
    isEditing.value = false
}

const saveArticle = async () => {
    isSaving.value = true
    saveError.value = ''
    try {
        await articlesApi.saveArticle({
            location_name: locationName.value,
            content: editContent.value,
            edit_summary: editSummary.value || null,
        })
        isEditing.value = false
        await loadArticle() // 刷新
    } catch (e) {
        saveError.value = e.response?.data?.error || '儲存失敗'
    } finally {
        isSaving.value = false
    }
}

// ===== 编辑器实时预览 =====
const editPreviewHtml = computed(() => {
    if (!editContent.value) return ''
    return DOMPurify.sanitize(marked(editContent.value))
})

// ===== 版本历史 =====
const loadVersions = async () => {
    versionsLoading.value = true
    try {
        const res = await articlesApi.getVersions(locationName.value)
        versions.value = res.data.versions || []
    } catch (e) {
        console.error(e)
    } finally {
        versionsLoading.value = false
    }
}

const toggleVersions = () => {
    showVersions.value = !showVersions.value
    if (showVersions.value && versions.value.length === 0) {
        loadVersions()
    }
}

const viewVersion = async (versionId) => {
    try {
        const res = await articlesApi.getVersion(versionId)
        previewVersion.value = res.data.version
    } catch (e) {
        console.error(e)
    }
}

const clearVersionPreview = () => {
    previewVersion.value = null
}

const rollbackToVersion = async (articleId, versionId) => {
    if (!confirm('确认回滚到此版本？当前内容将被覆盖（但保留在版本历史中）。')) return
    try {
        await articlesApi.rollback(articleId, versionId)
        previewVersion.value = null
        await loadArticle()
        await loadVersions()
    } catch (e) {
        alert(e.response?.data?.error || '回滚失败')
    }
}

onMounted(() => {
    loadArticle()
})

watch(locationName, () => {
    loadArticle()
    showVersions.value = false
    previewVersion.value = null
})
</script>

<template>
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <!-- 顶部信息 -->
        <div class="flex items-center justify-between mb-5 pb-3 border-b-2 border-slate-200 dark:border-slate-700">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100 border-l-4 border-accent pl-3">
                    地點文章
                </h1>
                <p class="text-sm text-slate-400 mt-1 pl-3">
                    {{ locationName }}
                </p>
            </div>
            <div class="flex gap-2">
                <button v-if="article && !isEditing && authStore.isLoggedIn" @click="toggleVersions"
                    class="text-xs px-3 py-1.5 rounded-none border-2 border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700 hover:-translate-y-0.5 transition-all font-medium">
                    {{ showVersions ? '隱藏' : '版本歷史' }}
                </button>
                <button v-if="canEdit && !isEditing" @click="startEdit"
                    class="text-xs px-3 py-1.5 rounded-none bg-accent text-white hover:bg-red-700 hover:-translate-y-0.5 hover:shadow-[3px_3px_0_rgba(183,41,20,0.3)] transition-all font-bold">
                    {{ article ? '編輯' : '撰寫文章' }}
                </button>
            </div>
        </div>

        <!-- 版本预览提示 -->
        <div v-if="previewVersion"
            class="mb-4 p-4 bg-amber-50 dark:bg-amber-900/20 border-l-4 border-amber-500 dark:border-amber-400 flex items-center justify-between">
            <span class="text-sm text-amber-700 dark:text-amber-400 font-medium">
                正在查看版本 #{{ previewVersion.id }}（{{ previewVersion.created_at }}）
            </span>
            <div class="flex gap-2">
                <button v-if="authStore.isAdmin && article" @click="rollbackToVersion(previewVersion.article_id, previewVersion.id)"
                    class="text-xs px-3 py-1.5 bg-amber-600 text-white rounded-none hover:bg-amber-700 hover:-translate-y-0.5 hover:shadow-[2px_2px_0_rgba(180,83,9,0.3)] transition-all font-medium">
                    回滾到此版本
                </button>
                <button @click="clearVersionPreview" class="text-xs px-3 py-1.5 border-2 border-amber-400 text-amber-600 dark:text-amber-400 rounded-none hover:bg-amber-100 dark:hover:bg-amber-900/40 hover:-translate-y-0.5 transition-all font-medium">
                    返回當前版本
                </button>
            </div>
        </div>

        <!-- 加载中 -->
        <div v-if="isLoading" class="text-center py-16">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-2 border-gray-200 border-t-accent"></div>
        </div>

        <!-- 错误 -->
        <div v-else-if="error" class="text-center text-red-500 py-8">{{ error }}</div>

        <!-- ===== 编辑模式 ===== -->
        <div v-else-if="isEditing" class="space-y-4">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <!-- 编辑器 -->
                <div>
                    <label class="text-xs font-bold text-slate-500 dark:text-slate-400 mb-2 block border-l-4 border-accent pl-2">Markdown 編輯</label>
                    <textarea v-model="editContent"
                        class="w-full h-[60vh] p-4 text-sm font-mono bg-white dark:bg-slate-900 border-2 border-gray-300 dark:border-slate-600 rounded-none resize-none focus:border-accent outline-none transition-colors"
                        placeholder="在此輸入 Markdown 內容..."></textarea>
                </div>
                <!-- 即時預覽 -->
                <div>
                    <label class="text-xs font-bold text-slate-500 dark:text-slate-400 mb-2 block border-l-4 border-slate-400 pl-2">預覽</label>
                    <div class="w-full h-[60vh] p-4 bg-white dark:bg-slate-800 border-2 border-gray-200 dark:border-slate-700 rounded-none overflow-y-auto prose dark:prose-invert max-w-none"
                         v-html="editPreviewHtml"></div>
                </div>
            </div>

            <!-- 編輯摘要 -->
            <input v-model="editSummary" placeholder="編輯摘要（可選，如：'修正聲調描述'）"
                class="w-full p-3 text-sm border-2 border-gray-300 dark:border-slate-600 dark:bg-slate-800 rounded-none outline-none focus:border-accent transition-colors" />

            <p v-if="saveError" class="text-sm text-red-500 border-l-4 border-red-500 pl-3 bg-red-50 dark:bg-red-900/20 py-2">{{ saveError }}</p>

            <!-- 按鈕 -->
            <div class="flex gap-3">
                <button @click="saveArticle" :disabled="isSaving"
                    class="bg-accent text-white px-8 py-3 text-sm rounded-none hover:bg-red-700 disabled:opacity-50 font-bold hover:-translate-y-0.5 hover:shadow-[4px_4px_0_rgba(183,41,20,0.3)] active:translate-y-0 active:shadow-none transition-all">
                    {{ isSaving ? '儲存中...' : '儲存' }}
                </button>
                <button @click="cancelEdit" class="px-8 py-3 text-sm rounded-none border-2 border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 hover:-translate-y-0.5 transition-all font-medium">
                    取消
                </button>
            </div>
        </div>

        <!-- ===== 查看模式 ===== -->
        <template v-else>
            <!-- 文章内容 -->
            <div v-if="renderedContent"
                class="bg-white dark:bg-slate-800 rounded-none shadow-[8px_8px_0_rgba(0,0,0,0.06)] dark:shadow-[8px_8px_0_rgba(0,0,0,0.3)] bg-gradient-to-br from-white via-slate-50/30 to-white dark:from-slate-800 dark:via-slate-800/50 dark:to-slate-800 p-6 md:p-8 border border-gray-100 dark:border-slate-700 prose dark:prose-invert max-w-none hover:shadow-[10px_10px_0_rgba(0,0,0,0.08)] dark:hover:shadow-[10px_10px_0_rgba(0,0,0,0.4)] hover:-translate-y-1 transition-all duration-300"
                v-html="renderedContent">
            </div>

            <!-- 無文章 -->
            <div v-else class="text-center py-16 text-slate-400 bg-slate-50 dark:bg-slate-800/50 rounded-none border-2 border-dashed border-slate-200 dark:border-slate-700">
                <p class="text-lg mb-2">暫無文章</p>
                <p v-if="canEdit" class="text-sm">點擊右上角「撰寫文章」開始編寫</p>
            </div>

            <!-- 文章元數據 -->
            <div v-if="article" class="mt-4 text-xs text-slate-400 flex items-center gap-4 pl-3 border-l-2 border-slate-200 dark:border-slate-700">
                <span>最後編輯：{{ article.nickname || article.email }}</span>
                <span>{{ article.updated_at }}</span>
            </div>
        </template>

        <!-- ===== 版本歷史側欄（僅登錄用戶可見） ===== -->
        <div v-if="showVersions && authStore.isLoggedIn"
            class="mt-6 bg-white dark:bg-slate-800 rounded-none shadow-[6px_6px_0_rgba(0,0,0,0.04)] dark:shadow-[6px_6px_0_rgba(0,0,0,0.2)] border border-gray-100 dark:border-slate-700 p-5">
            <h3 class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-4 border-l-4 border-slate-500 pl-3">版本歷史</h3>

            <div v-if="versionsLoading" class="text-center py-4 text-slate-400 text-sm">載入中...</div>
            <div v-else-if="versions.length === 0" class="text-center py-4 text-slate-400 text-sm border-2 border-dashed border-slate-200 dark:border-slate-700">無版本歷史</div>

            <div v-else class="space-y-2 max-h-80 overflow-y-auto overflow-x-hidden pr-2">
                <button v-for="v in versions" :key="v.id" @click="viewVersion(v.id)"
                    class="w-full text-left p-3 rounded-none border-l-4 transition-all duration-300 text-sm hover:translate-x-1 hover:shadow-sm"
                    :class="previewVersion?.id === v.id
                        ? 'border-l-accent bg-accent/5 shadow-[4px_4px_0_rgba(211,41,19,0.1)] dark:shadow-[4px_4px_0_rgba(211,41,19,0.3)]'
                        : 'border-l-transparent hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:border-l-slate-300 dark:hover:border-l-slate-500 hover:shadow-[3px_3px_0_rgba(0,0,0,0.05)]'">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-slate-700 dark:text-slate-300">
                            #{{ v.id }}
                        </span>
                        <span class="text-xs text-slate-400">{{ v.created_at }}</span>
                    </div>
                    <div class="text-xs text-slate-500 mt-1">
                        {{ v.nickname || v.email }}
                        <span v-if="v.edit_summary" class="ml-2 text-slate-400">— {{ v.edit_summary }}</span>
                    </div>
                </button>
            </div>
        </div>
    </div>
</template>
