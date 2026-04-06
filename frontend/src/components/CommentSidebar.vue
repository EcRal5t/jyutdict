<script setup>
import { ref, watch, computed } from 'vue'
import { useAuthStore } from '@/stores/auth.js'
import commentsApi from '@/api/comments.js'
import RoleBadge from './RoleBadge.vue'

const props = defineProps({
    /** 評論類型：'char' 或 'sheet' */
    type: { type: String, required: true },
    /** 評論目標標識：char 時為漢字字符，sheet 時為鍵值 */
    target: { type: String, default: '' },
    /** 是否可見 */
    visible: { type: Boolean, default: false },
})

const emit = defineEmits(['close'])

const authStore = useAuthStore()

// ===== 數據 =====
const comments = ref([])
const isLoading = ref(false)
const error = ref(null)

// ===== 發表評論 =====
const newComment = ref('')
const isSubmitting = ref(false)

// ===== 編輯評論 =====
const editingId = ref(null)
const editContent = ref('')
const isEditSaving = ref(false)

// ===== 載入評論 =====
const loadComments = async () => {
    if (!props.target) return
    isLoading.value = true
    error.value = null
    try {
        const res = props.type === 'char'
            ? await commentsApi.getCharComments(props.target)
            : await commentsApi.getSheetComments(props.target)
        comments.value = res.data.comments || []
    } catch (e) {
        error.value = '載入評論失敗'
        console.error(e)
    } finally {
        isLoading.value = false
    }
}

// 當目標變化時重新載入
watch(() => props.target, (newVal) => {
    if (newVal && props.visible) {
        loadComments()
    }
    // 關閉編輯狀態
    editingId.value = null
    newComment.value = ''
})

watch(() => props.visible, (newVal) => {
    if (newVal && props.target) {
        loadComments()
    }
})

// ===== 發表 =====
const submitComment = async () => {
    const content = newComment.value.trim()
    if (!content) return
    isSubmitting.value = true
    try {
        if (props.type === 'char') {
            await commentsApi.postCharComment(props.target, content)
        } else {
            await commentsApi.postSheetComment(props.target, content)
        }
        newComment.value = ''
        await loadComments()
    } catch (e) {
        alert(e.response?.data?.error || '發表失敗')
    } finally {
        isSubmitting.value = false
    }
}

// ===== 編輯 =====
const startEdit = (comment) => {
    editingId.value = comment.id
    editContent.value = comment.content
}

const cancelEdit = () => {
    editingId.value = null
    editContent.value = ''
}

const saveEdit = async () => {
    if (!editContent.value.trim()) return
    isEditSaving.value = true
    try {
        if (props.type === 'char') {
            await commentsApi.editCharComment(editingId.value, editContent.value.trim())
        } else {
            await commentsApi.editSheetComment(editingId.value, editContent.value.trim())
        }
        editingId.value = null
        await loadComments()
    } catch (e) {
        alert(e.response?.data?.error || '修改失敗')
    } finally {
        isEditSaving.value = false
    }
}

// ===== 刪除 =====
const deleteComment = async (commentId) => {
    if (!confirm('確認刪除此評論？')) return
    try {
        if (props.type === 'char') {
            await commentsApi.deleteCharComment(commentId)
        } else {
            await commentsApi.deleteSheetComment(commentId)
        }
        await loadComments()
    } catch (e) {
        alert(e.response?.data?.error || '刪除失敗')
    }
}

// ===== 判斷是否可操作某條評論 =====
const canEditComment = (comment) => {
    return authStore.isLoggedIn && comment.user_id === authStore.user?.id && !comment.is_deleted
}
const canDeleteComment = (comment) => {
    if (comment.is_deleted) return false
    return (authStore.isLoggedIn && comment.user_id === authStore.user?.id) || authStore.isAdmin
}

const commentCount = computed(() => comments.value.filter(c => !c.is_deleted).length)
</script>

<template>
    <!-- 半透明遮罩 -->
    <Transition name="fade">
        <div v-if="visible" class="fixed inset-0 bg-black/30 backdrop-blur-sm z-40 lg:hidden" @click="emit('close')"></div>
    </Transition>

    <!-- 側邊欄面板 -->
    <Transition name="slide">
        <aside v-if="visible"
            class="fixed right-0 top-0 h-full w-80 sm:w-96 bg-white/95 dark:bg-slate-900/90 backdrop-blur-2xl shadow-[-10px_0_40px_rgba(0,0,0,0.1)] dark:shadow-[-10px_0_40px_rgba(0,0,0,0.5)] z-50 flex flex-col border-l border-gray-200/50 dark:border-slate-700/50">

            <!-- 頭部 -->
            <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-slate-700">
                <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200">
                    {{ type === 'char' ? '字評論' : '字表評論' }}
                    <span class="text-xs text-slate-400 font-normal ml-1">{{ target }}</span>
                    <span class="text-xs text-slate-400 font-normal ml-1">({{ commentCount }})</span>
                </h3>
                <button @click="emit('close')" class="p-1 hover:bg-slate-100 dark:hover:bg-slate-700 rounded">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- 評論列表（可滾動） -->
            <div class="flex-1 overflow-y-auto p-4 space-y-4">
                <div v-if="isLoading" class="text-center py-8 text-slate-400 text-sm">載入中...</div>
                <div v-else-if="error" class="text-center py-8 text-red-500 text-sm">{{ error }}</div>
                <div v-else-if="comments.length === 0" class="text-center py-8 text-slate-400 text-sm">暫無評論</div>

                <!-- 單條評論 -->
                <div v-for="comment in comments" :key="comment.id"
                    class="p-4 rounded-xl bg-white dark:bg-slate-800/60 border border-gray-100 dark:border-slate-700/50 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-300 group">

                    <!-- 已刪除 -->
                    <div v-if="comment.is_deleted" class="text-xs text-slate-400 italic">該評論已刪除</div>

                    <!-- 正常評論 -->
                    <template v-else>
                        <!-- 頭部：暱稱 + 角色標籤 + 時間 -->
                        <div class="flex items-center gap-1.5 mb-2">
                            <span class="text-xs font-medium text-slate-700 dark:text-slate-300">
                                {{ comment.nickname || comment.email.split('@')[0] }}
                            </span>
                            <RoleBadge :role="comment.role" />
                            <span class="text-[10px] text-slate-400 ml-auto">{{ comment.created_at }}</span>
                            <span v-if="comment.updated_at !== comment.created_at" class="text-[10px] text-slate-400">(已編輯)</span>
                        </div>

                        <!-- 內容（查看模式） -->
                        <div v-if="editingId !== comment.id" class="text-sm text-slate-600 dark:text-slate-300 whitespace-pre-wrap break-words">
                            {{ comment.content }}
                        </div>

                        <!-- 內容（編輯模式） -->
                        <div v-else class="space-y-2">
                            <textarea v-model="editContent"
                                class="w-full p-3 text-sm border border-gray-300/50 dark:border-slate-600/50 dark:bg-slate-900/50 rounded-xl resize-none focus:ring-2 focus:ring-accent/50 focus:border-accent outline-none transition-shadow"
                                rows="3"></textarea>
                            <div class="flex gap-2">
                                <button @click="saveEdit" :disabled="isEditSaving"
                                    class="text-xs bg-accent text-white px-3 py-1 rounded hover:bg-red-700 disabled:opacity-50">
                                    {{ isEditSaving ? '...' : '儲存' }}
                                </button>
                                <button @click="cancelEdit" class="text-xs text-slate-400 hover:text-slate-600">取消</button>
                            </div>
                        </div>

                        <!-- 操作按鈕 -->
                        <div v-if="editingId !== comment.id" class="flex gap-3 mt-2">
                            <button v-if="canEditComment(comment)" @click="startEdit(comment)"
                                class="text-[10px] text-slate-400 hover:text-accent">修改</button>
                            <button v-if="canDeleteComment(comment)" @click="deleteComment(comment.id)"
                                class="text-[10px] text-slate-400 hover:text-red-500">刪除</button>
                        </div>
                    </template>
                </div>
            </div>

            <!-- 發表新評論（底部固定） -->
            <div v-if="authStore.isLoggedIn" class="p-4 border-t border-gray-200/50 dark:border-slate-700/50 bg-white/50 dark:bg-slate-900/50">
                <textarea v-model="newComment" @keydown.ctrl.enter="submitComment"
                    class="w-full p-3 text-sm border border-gray-300/50 dark:border-slate-600/50 dark:bg-slate-900/50 rounded-xl resize-none focus:ring-2 focus:ring-accent/50 focus:border-accent outline-none transition-shadow"
                    rows="2" placeholder="發表評論... (Ctrl+Enter 發送)"></textarea>
                <div class="flex justify-end mt-2">
                    <button @click="submitComment" :disabled="isSubmitting || !newComment.trim()"
                        class="bg-accent text-white px-4 py-1.5 text-xs rounded-lg hover:bg-red-700 disabled:opacity-50 font-bold">
                        {{ isSubmitting ? '發送中...' : '發表' }}
                    </button>
                </div>
            </div>
            <div v-else class="p-4 border-t border-gray-200 dark:border-slate-700 text-center">
                <button @click="authStore.login()" class="text-sm text-accent hover:underline">登入後發表評論</button>
            </div>
        </aside>
    </Transition>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.slide-enter-active, .slide-leave-active { transition: transform 0.3s ease; }
.slide-enter-from, .slide-leave-to { transform: translateX(100%); }
</style>
