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
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100">
                    地點文章
                </h1>
                <p class="text-sm text-slate-400 mt-1">
                    {{ locationName }}
                </p>
            </div>
            <div class="flex gap-2">
                <button v-if="article && !isEditing" @click="toggleVersions"
                    class="text-xs px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700">
                    {{ showVersions ? '隱藏' : '版本歷史' }}
                </button>
                <button v-if="canEdit && !isEditing" @click="startEdit"
                    class="text-xs px-3 py-1.5 rounded-lg bg-accent text-white hover:bg-red-700">
                    {{ article ? '編輯' : '撰寫文章' }}
                </button>
            </div>
        </div>

        <!-- 版本预览提示 -->
        <div v-if="previewVersion"
            class="mb-4 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg flex items-center justify-between">
            <span class="text-sm text-amber-700 dark:text-amber-400">
                正在查看版本 #{{ previewVersion.id }}（{{ previewVersion.created_at }}）
            </span>
            <div class="flex gap-2">
                <button v-if="authStore.isAdmin && article" @click="rollbackToVersion(previewVersion.article_id, previewVersion.id)"
                    class="text-xs px-2 py-1 bg-amber-600 text-white rounded hover:bg-amber-700">
                    回滾到此版本
                </button>
                <button @click="clearVersionPreview" class="text-xs px-2 py-1 border border-amber-300 text-amber-600 rounded hover:bg-amber-100">
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
                    <label class="text-xs font-bold text-slate-500 mb-1 block">Markdown 編輯</label>
                    <textarea v-model="editContent"
                        class="w-full h-[60vh] p-4 text-sm font-mono bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-600 rounded-lg resize-none focus:ring-1 focus:ring-accent outline-none"
                        placeholder="在此輸入 Markdown 內容..."></textarea>
                </div>
                <!-- 即時預覽 -->
                <div>
                    <label class="text-xs font-bold text-slate-500 mb-1 block">預覽</label>
                    <div class="w-full h-[60vh] p-4 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg overflow-y-auto prose dark:prose-invert max-w-none"
                         v-html="editPreviewHtml"></div>
                </div>
            </div>

            <!-- 編輯摘要 -->
            <input v-model="editSummary" placeholder="編輯摘要（可選，如：'修正聲調描述'）"
                class="w-full p-2 text-sm border border-gray-300 dark:border-slate-600 dark:bg-slate-800 rounded-lg outline-none" />

            <p v-if="saveError" class="text-sm text-red-500">{{ saveError }}</p>

            <!-- 按鈕 -->
            <div class="flex gap-3">
                <button @click="saveArticle" :disabled="isSaving"
                    class="bg-accent text-white px-6 py-2 text-sm rounded-lg hover:bg-red-700 disabled:opacity-50 font-bold">
                    {{ isSaving ? '儲存中...' : '儲存' }}
                </button>
                <button @click="cancelEdit" class="px-6 py-2 text-sm rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50">
                    取消
                </button>
            </div>
        </div>

        <!-- ===== 查看模式 ===== -->
        <template v-else>
            <!-- 文章内容 -->
            <div v-if="renderedContent"
                class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-6 md:p-8 border border-gray-100 dark:border-slate-700 prose dark:prose-invert max-w-none"
                v-html="renderedContent">
            </div>

            <!-- 無文章 -->
            <div v-else class="text-center py-16 text-slate-400">
                <p class="text-lg mb-2">暫無文章</p>
                <p v-if="canEdit" class="text-sm">點擊右上角「撰寫文章」開始編寫</p>
            </div>

            <!-- 文章元數據 -->
            <div v-if="article" class="mt-4 text-xs text-slate-400 flex items-center gap-4">
                <span>最後編輯：{{ article.nickname || article.email }}</span>
                <span>{{ article.updated_at }}</span>
            </div>
        </template>

        <!-- ===== 版本歷史側欄 ===== -->
        <div v-if="showVersions"
            class="mt-6 bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-slate-700 p-4">
            <h3 class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-3">版本歷史</h3>

            <div v-if="versionsLoading" class="text-center py-4 text-slate-400 text-sm">載入中...</div>
            <div v-else-if="versions.length === 0" class="text-center py-4 text-slate-400 text-sm">無版本歷史</div>

            <div v-else class="space-y-2 max-h-80 overflow-y-auto">
                <button v-for="v in versions" :key="v.id" @click="viewVersion(v.id)"
                    class="w-full text-left p-3 rounded-lg border transition-colors text-sm"
                    :class="previewVersion?.id === v.id
                        ? 'border-accent bg-accent/5'
                        : 'border-gray-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700'">
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-slate-700 dark:text-slate-300">
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
