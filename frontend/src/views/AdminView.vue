<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth.js'
import adminApi from '@/api/admin.js'

const authStore = useAuthStore()

// ===== Tab 切换 =====
const activeTab = ref('users') // 'users' | 'editors'

// ===== 用户管理 =====
const users = ref([])
const usersPagination = ref({})
const usersLoading = ref(false)
const searchQuery = ref('')
const roleFilter = ref('')

const loadUsers = async (page = 1) => {
    usersLoading.value = true
    try {
        const res = await adminApi.getUsers({
            page,
            per_page: 20,
            search: searchQuery.value || undefined,
            role: roleFilter.value || undefined,
        })
        users.value = res.data.users || []
        usersPagination.value = res.data.pagination || {}
    } catch (e) {
        console.error('Failed to load users', e)
    } finally {
        usersLoading.value = false
    }
}

// 角色变更
const changingRoleUserId = ref(null)
const changeRole = async (userId, newRole) => {
    if (!confirm(`确认将此用户的角色变更为「${newRole}」？`)) return
    changingRoleUserId.value = userId
    try {
        await adminApi.updateUserRole(userId, newRole)
        await loadUsers(usersPagination.value.page || 1)
    } catch (e) {
        alert(e.response?.data?.error || '操作失敗')
    } finally {
        changingRoleUserId.value = null
    }
}

// ===== 编纂者地点分配 =====
const allLocations = ref({ locations: [] })
const selectedEditor = ref(null)
const editorLocations = ref([])
const editorSearchQuery = ref('')
const editorUsers = ref([])

const loadLocations = async () => {
    try {
        const res = await adminApi.getAllLocations()
        allLocations.value = res.data
    } catch (e) {
        console.error('Failed to load locations', e)
    }
}

const searchEditors = async () => {
    try {
        const res = await adminApi.getUsers({ search: editorSearchQuery.value, role: 'editor', per_page: 50 })
        editorUsers.value = res.data.users || []
    } catch (e) {
        console.error(e)
    }
}

const selectEditor = async (editor) => {
    selectedEditor.value = editor
    try {
        const res = await adminApi.getEditorLocations(editor.id)
        editorLocations.value = res.data.locations || []
    } catch (e) {
        console.error(e)
    }
}

const assignLocation = async (locationName) => {
    if (!selectedEditor.value) return
    try {
        await adminApi.assignLocation(selectedEditor.value.id, locationName)
        await selectEditor(selectedEditor.value) // 刷新列表
    } catch (e) {
        alert(e.response?.data?.error || '分配失敗')
    }
}

const removeLocation = async (locationName) => {
    if (!selectedEditor.value) return
    if (!confirm('确认取消此地点分配？')) return
    try {
        await adminApi.removeLocation(selectedEditor.value.id, locationName)
        await selectEditor(selectedEditor.value)
    } catch (e) {
        alert(e.response?.data?.error || '取消分配失敗')
    }
}

onMounted(() => {
    loadUsers()
    loadLocations()
})
</script>

<template>
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 mb-6">後台管理</h1>

        <!-- Tab 切換 -->
        <div class="flex gap-2 mb-8 bg-gray-100/50 dark:bg-slate-800/50 p-1.5 rounded-xl w-fit">
            <button @click="activeTab = 'users'"
                class="px-5 py-2 text-sm font-medium rounded-lg transition-all duration-300"
                :class="activeTab === 'users' ? 'bg-white dark:bg-slate-700 text-accent shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 hover:bg-gray-200/50 dark:hover:bg-slate-700/50'">
                用戶管理
            </button>
            <button @click="activeTab = 'editors'; searchEditors()"
                class="px-5 py-2 text-sm font-medium rounded-lg transition-all duration-300"
                :class="activeTab === 'editors' ? 'bg-white dark:bg-slate-700 text-accent shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 hover:bg-gray-200/50 dark:hover:bg-slate-700/50'">
                編纂者地點分配
            </button>
        </div>

        <!-- ===== 用戶管理 Tab ===== -->
        <div v-if="activeTab === 'users'">
            <!-- 搜索與篩選 -->
            <div class="flex flex-col sm:flex-row gap-3 mb-6">
                <input v-model="searchQuery" @keypress.enter="loadUsers(1)" placeholder="搜索郵箱或暱稱..."
                    class="flex-1 p-2.5 text-sm border border-gray-200 dark:border-slate-700 dark:bg-slate-900/50 rounded-xl outline-none focus:ring-2 focus:ring-accent/50 focus:border-accent transition-all shadow-sm" />
                <select v-model="roleFilter" @change="loadUsers(1)"
                    class="p-2.5 text-sm border border-gray-200 dark:border-slate-700 dark:bg-slate-900/50 rounded-xl outline-none focus:ring-2 focus:ring-accent/50 focus:border-accent transition-all shadow-sm">
                    <option value="">全部角色</option>
                    <option value="user">普通用戶</option>
                    <option value="editor">編纂者</option>
                    <option value="admin">管理員</option>
                    <option value="owner">Owner</option>
                </select>
                <button @click="loadUsers(1)" class="bg-accent text-white px-5 py-2.5 text-sm font-medium rounded-xl shadow-md shadow-accent/20 hover:shadow-lg hover:shadow-accent/40 hover:-translate-y-0.5 transition-all duration-300">
                    搜尋
                </button>
            </div>

            <!-- 用戶表格 -->
            <div class="bg-white/80 dark:bg-slate-800/80 backdrop-blur-md rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] dark:shadow-[0_8px_30px_rgb(0,0,0,0.1)] border border-white/50 dark:border-slate-700/50 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-slate-700/50 bg-slate-50/50 dark:bg-slate-900/30">
                                <th class="text-left p-4 font-semibold text-slate-600 dark:text-slate-400">ID</th>
                                <th class="text-left p-4 font-semibold text-slate-600 dark:text-slate-400">暱稱</th>
                                <th class="text-left p-4 font-semibold text-slate-600 dark:text-slate-400">郵箱</th>
                                <th class="text-left p-4 font-semibold text-slate-600 dark:text-slate-400">角色</th>
                                <th class="text-left p-4 font-semibold text-slate-600 dark:text-slate-400">註冊時間</th>
                                <th class="text-left p-4 font-semibold text-slate-600 dark:text-slate-400">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="usersLoading">
                                <td colspan="6" class="text-center py-8 text-slate-400">載入中...</td>
                            </tr>
                            <tr v-for="u in users" :key="u.id" class="border-b border-gray-50 dark:border-slate-700/30 hover:bg-slate-50/80 dark:hover:bg-slate-800/80 transition-colors">
                            <td class="p-3 text-slate-500">{{ u.id }}</td>
                            <td class="p-3">{{ u.nickname || '-' }}</td>
                            <td class="p-3 text-slate-500 text-xs">{{ u.email }}</td>
                            <td class="p-3">
                                <span class="text-xs px-1.5 py-0.5 rounded"
                                      :class="{
                                          'bg-red-100 text-red-700': u.role === 'owner',
                                          'bg-blue-100 text-blue-700': u.role === 'admin',
                                          'bg-green-100 text-green-700': u.role === 'editor',
                                          'bg-slate-100 text-slate-600': u.role === 'user',
                                      }">
                                    {{ { owner: '站長', admin: '管理員', editor: '編纂者', user: '普通用戶' }[u.role] }}
                                </span>
                            </td>
                            <td class="p-3 text-xs text-slate-400">{{ u.created_at }}</td>
                            <td class="p-3">
                                <!-- 角色操作按钮 -->
                                <div v-if="u.id !== authStore.user?.id && u.role !== 'owner'" class="flex gap-1 flex-wrap">
                                    <button v-if="u.role !== 'editor'" @click="changeRole(u.id, 'editor')"
                                        :disabled="changingRoleUserId === u.id"
                                        class="text-xs px-2 py-1 rounded border border-green-300 text-green-600 hover:bg-green-50 disabled:opacity-50">
                                        設為編纂者
                                    </button>
                                    <button v-if="u.role !== 'user'" @click="changeRole(u.id, 'user')"
                                        :disabled="changingRoleUserId === u.id"
                                        class="text-xs px-2 py-1 rounded border border-slate-300 text-slate-600 hover:bg-slate-50 disabled:opacity-50">
                                        降為普通用戶
                                    </button>
                                    <button v-if="authStore.isOwner && u.role !== 'admin'" @click="changeRole(u.id, 'admin')"
                                        :disabled="changingRoleUserId === u.id"
                                        class="text-xs px-2 py-1 rounded border border-blue-300 text-blue-600 hover:bg-blue-50 disabled:opacity-50">
                                        設為管理員
                                    </button>
                                </div>
                                <span v-else class="text-xs text-slate-300">-</span>
                            </td>
                                </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 分页 -->
            <div v-if="usersPagination.total_pages > 1" class="flex justify-center gap-2 mt-4">
                <button v-for="p in usersPagination.total_pages" :key="p" @click="loadUsers(p)"
                    class="px-3 py-1 text-sm rounded border"
                    :class="p === usersPagination.page ? 'bg-accent text-white border-accent' : 'border-slate-300 hover:bg-slate-100'">
                    {{ p }}
                </button>
            </div>
        </div>

        <!-- ===== 編纂者地點分配 Tab ===== -->
        <div v-if="activeTab === 'editors'" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- 左欄：選擇編纂者 -->
            <div class="bg-white/80 dark:bg-slate-800/80 backdrop-blur-md rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] dark:shadow-[0_8px_30px_rgb(0,0,0,0.1)] border border-white/50 dark:border-slate-700/50 p-5">
                <h3 class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-4 flex items-center gap-2">
                    <span class="w-1 h-4 bg-accent rounded-full"></span> 選擇編纂者
                </h3>
                <div class="mb-4">
                    <input v-model="editorSearchQuery" @input="searchEditors" placeholder="搜尋編纂者..."
                        class="w-full p-2.5 text-sm border border-gray-200 dark:border-slate-700 dark:bg-slate-900/50 rounded-xl outline-none focus:ring-2 focus:ring-accent/50 focus:border-accent transition-all shadow-sm" />
                </div>

                <div class="space-y-1 max-h-60 overflow-y-auto pr-2">
                    <button v-for="editor in editorUsers" :key="editor.id" @click="selectEditor(editor)"
                        class="w-full text-left p-3 text-sm rounded-xl transition-all duration-200"
                        :class="selectedEditor?.id === editor.id ? 'bg-accent/10 border border-accent/20 text-accent font-medium' : 'border border-transparent hover:bg-slate-50 dark:hover:bg-slate-700/50'">
                        {{ editor.nickname || editor.email }} <span class="text-[10px] text-slate-400 font-normal ml-1">#{{ editor.id }}</span>
                    </button>
                    <p v-if="editorUsers.length === 0" class="text-xs text-slate-400 text-center py-6">無編纂者</p>
                </div>

                <!-- 已分配地點 -->
                <div v-if="selectedEditor" class="mt-4 pt-4 border-t border-gray-200 dark:border-slate-700">
                    <h4 class="text-xs font-bold text-slate-500 mb-2">
                        {{ selectedEditor.nickname || selectedEditor.email }} 已分配的地點：
                    </h4>
                    <div class="space-y-1">
                        <div v-for="loc in editorLocations" :key="loc.location_name"
                            class="flex items-center justify-between p-2 bg-green-50 dark:bg-green-900/20 rounded text-sm">
                            <span class="text-slate-800 dark:text-slate-200">{{ loc.location_name }}</span>
                            <button @click="removeLocation(loc.location_name)"
                                class="text-xs text-red-500 hover:underline">取消</button>
                        </div>
                        <p v-if="editorLocations.length === 0" class="text-xs text-slate-400">暫無分配</p>
                    </div>
                </div>
            </div>

            <!-- 右欄：地點列表（可點擊分配） -->
            <div class="bg-white/80 dark:bg-slate-800/80 backdrop-blur-md rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] dark:shadow-[0_8px_30px_rgb(0,0,0,0.1)] border border-white/50 dark:border-slate-700/50 p-5">
                <h3 class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-4 flex items-center gap-2">
                    <span class="w-1 h-4 bg-green-500 rounded-full"></span> 可分配地點
                </h3>
                <p v-if="!selectedEditor" class="text-xs text-slate-400 text-center py-4">請先選擇一個編纂者</p>

                <template v-else>
                    <h4 class="text-xs font-bold text-slate-500 mb-2">所有地點</h4>
                    <div class="space-y-1 max-h-80 overflow-y-auto">
                        <button v-for="loc in allLocations.locations" :key="loc.name"
                            @click="assignLocation(loc.name)"
                            class="w-full text-left p-2 text-xs rounded hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors">
                            {{ loc.name }}
                            <span v-if="loc.first" class="text-slate-400">({{ loc.first }})</span>
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
