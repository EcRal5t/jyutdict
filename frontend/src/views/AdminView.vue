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
    <div class="container mx-auto px-4 py-6 max-w-6xl">
        <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100 mb-5">後臺管理</h1>

        <!-- Tab 切換 -->
        <div class="flex gap-1 mb-6 bg-slate-100/50 dark:bg-slate-800/50 p-1 rounded-none w-fit border border-slate-200 dark:border-slate-700">
            <button @click="activeTab = 'users'"
                class="px-4 py-1.5 text-sm font-medium rounded-none transition-all duration-300"
                :class="activeTab === 'users' ? 'bg-white dark:bg-slate-700 text-accent shadow-[2px_2px_0_rgba(211,41,19,0.2)] dark:shadow-[2px_2px_0_rgba(211,41,19,0.4)]' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 hover:bg-slate-200/50 dark:hover:bg-slate-700/50'">
                用戶管理
            </button>
            <button @click="activeTab = 'editors'; searchEditors()"
                class="px-4 py-1.5 text-sm font-medium rounded-none transition-all duration-300"
                :class="activeTab === 'editors' ? 'bg-white dark:bg-slate-700 text-accent shadow-[2px_2px_0_rgba(211,41,19,0.2)] dark:shadow-[2px_2px_0_rgba(211,41,19,0.4)]' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 hover:bg-slate-200/50 dark:hover:bg-slate-700/50'">
                編纂者地點分配
            </button>
        </div>

        <!-- ===== 用戶管理 Tab ===== -->
        <div v-if="activeTab === 'users'">
            <!-- 搜索與篩選 -->
            <div class="flex flex-col sm:flex-row gap-2 mb-4">
                <input v-model="searchQuery" @keypress.enter="loadUsers(1)" placeholder="搜索郵箱或暱稱..."
                    class="flex-1 p-2 text-sm border-2 border-gray-200 dark:border-slate-700 dark:bg-slate-900/50 rounded-none outline-none focus:border-accent transition-all" />
                <select v-model="roleFilter" @change="loadUsers(1)"
                    class="p-2 text-sm border-2 border-gray-200 dark:border-slate-700 dark:bg-slate-900/50 rounded-none outline-none focus:border-accent transition-all">
                    <option value="">全部角色</option>
                    <option value="user">普通用戶</option>
                    <option value="editor">編纂者</option>
                    <option value="admin">管理員</option>
                    <option value="owner">Owner</option>
                </select>
                <button @click="loadUsers(1)" class="bg-accent text-white px-4 py-2 text-sm font-medium rounded-none shadow-[4px_4px_0_rgba(183,41,20,0.3)] hover:shadow-[5px_5px_0_rgba(183,41,20,0.4)] hover:-translate-y-0.5 active:translate-y-0 active:shadow-[2px_2px_0_rgba(183,41,20,0.3)] transition-all duration-300">
                    搜尋
                </button>
            </div>

            <!-- 用戶表格 -->
            <div class="bg-white/80 dark:bg-slate-800/80 backdrop-blur-md rounded-none shadow-[4px_4px_0_rgba(0,0,0,0.06)] dark:shadow-[4px_4px_0_rgba(0,0,0,0.3)] border border-slate-200/50 dark:border-slate-700/50 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/30">
                                <th class="text-left p-3 font-semibold text-slate-600 dark:text-slate-400 whitespace-nowrap">ID</th>
                                <th class="text-left p-3 font-semibold text-slate-600 dark:text-slate-400 whitespace-nowrap">暱稱</th>
                                <th class="text-left p-3 font-semibold text-slate-600 dark:text-slate-400 whitespace-nowrap">郵箱</th>
                                <th class="text-left p-3 font-semibold text-slate-600 dark:text-slate-400 whitespace-nowrap">角色</th>
                                <th class="text-left p-3 font-semibold text-slate-600 dark:text-slate-400 whitespace-nowrap">註冊時間</th>
                                <th class="text-left p-3 font-semibold text-slate-600 dark:text-slate-400 whitespace-nowrap">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="usersLoading">
                                <td colspan="6" class="text-center py-6 text-slate-400">載入中...</td>
                            </tr>
                            <tr v-for="u in users" :key="u.id" class="border-b border-gray-100 dark:border-slate-700/30 hover:bg-slate-50/80 dark:hover:bg-slate-800/80 transition-colors">
                                <td class="p-2 text-slate-500 whitespace-nowrap">{{ u.id }}</td>
                                <td class="p-2 whitespace-nowrap">{{ u.nickname || '-' }}</td>
                                <td class="p-2 text-slate-500 text-xs whitespace-nowrap">{{ u.email }}</td>
                                <td class="p-2 whitespace-nowrap">
                                    <span class="text-xs px-1.5 py-0.5 rounded-none border-l-2"
                                          :class="{
                                              'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 border-red-500': u.role === 'owner',
                                              'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 border-blue-500': u.role === 'admin',
                                              'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 border-green-500': u.role === 'editor',
                                              'bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 border-slate-400': u.role === 'user',
                                          }">
                                        {{ { owner: '站長', admin: '管理員', editor: '編纂者', user: '普通用戶' }[u.role] }}
                                    </span>
                                </td>
                                <td class="p-2 text-xs text-slate-400 whitespace-nowrap">{{ u.created_at }}</td>
                                <td class="p-2">
                                    <!-- 角色操作按钮 -->
                                    <div v-if="u.id !== authStore.user?.id && u.role !== 'owner'" class="flex gap-1 flex-wrap">
                                        <button v-if="u.role !== 'editor'" @click="changeRole(u.id, 'editor')"
                                            :disabled="changingRoleUserId === u.id"
                                            class="text-xs px-2 py-1 rounded-none border border-green-300 dark:border-green-600 text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/20 disabled:opacity-50 transition-all whitespace-nowrap">
                                            設為編纂者
                                        </button>
                                        <button v-if="u.role !== 'user'" @click="changeRole(u.id, 'user')"
                                            :disabled="changingRoleUserId === u.id"
                                            class="text-xs px-2 py-1 rounded-none border border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-50 transition-all whitespace-nowrap">
                                            降為普通用戶
                                        </button>
                                        <button v-if="authStore.isOwner && u.role !== 'admin'" @click="changeRole(u.id, 'admin')"
                                            :disabled="changingRoleUserId === u.id"
                                            class="text-xs px-2 py-1 rounded-none border border-blue-300 dark:border-blue-600 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 disabled:opacity-50 transition-all whitespace-nowrap">
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
            <div v-if="usersPagination.total_pages > 1" class="flex justify-center gap-1 mt-3">
                <button v-for="p in usersPagination.total_pages" :key="p" @click="loadUsers(p)"
                    class="px-3 py-1 text-sm rounded-none border transition-all hover:-translate-y-0.5"
                    :class="p === usersPagination.page ? 'bg-accent text-white border-accent shadow-[2px_2px_0_rgba(183,41,20,0.3)]' : 'border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800'">
                    {{ p }}
                </button>
            </div>
        </div>

        <!-- ===== 編纂者地點分配 Tab ===== -->
        <div v-if="activeTab === 'editors'" class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <!-- 左欄：選擇編纂者 -->
            <div class="bg-white/80 dark:bg-slate-800/80 backdrop-blur-md rounded-none shadow-[4px_4px_0_rgba(0,0,0,0.06)] dark:shadow-[4px_4px_0_rgba(0,0,0,0.3)] border border-slate-200/50 dark:border-slate-700/50 p-4">
                <h3 class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-3 border-l-4 border-accent pl-2">
                    選擇編纂者
                </h3>
                <div class="mb-3">
                    <input v-model="editorSearchQuery" @input="searchEditors" placeholder="搜尋編纂者..."
                        class="w-full p-2 text-sm border-2 border-gray-200 dark:border-slate-700 dark:bg-slate-900/50 rounded-none outline-none focus:border-accent transition-all" />
                </div>

                <div class="space-y-0.5 max-h-48 overflow-y-auto overflow-x-hidden pr-2">
                    <button v-for="editor in editorUsers" :key="editor.id" @click="selectEditor(editor)"
                        class="w-full text-left p-2 text-sm rounded-none transition-all duration-200 border-l-4"
                        :class="selectedEditor?.id === editor.id ? 'bg-accent/10 border-accent text-accent font-medium' : 'border-transparent hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:border-slate-300 dark:hover:border-slate-600'">
                        {{ editor.nickname || editor.email }} <span class="text-[10px] text-slate-400 font-normal ml-1">#{{ editor.id }}</span>
                    </button>
                    <p v-if="editorUsers.length === 0" class="text-xs text-slate-400 text-center py-4">無編纂者</p>
                </div>

                <!-- 已分配地點 -->
                <div v-if="selectedEditor" class="mt-3 pt-3 border-t border-gray-200 dark:border-slate-700">
                    <h4 class="text-xs font-bold text-slate-500 mb-2">
                        {{ selectedEditor.nickname || selectedEditor.email }} 已分配的地點：
                    </h4>
                    <div class="space-y-0.5">
                        <div v-for="loc in editorLocations" :key="loc.location_name"
                            class="flex items-center justify-between p-1.5 bg-green-50 dark:bg-green-900/20 rounded-none text-sm border-l-4 border-green-500">
                            <span class="text-slate-800 dark:text-slate-200 text-xs">{{ loc.location_name }}</span>
                            <button @click="removeLocation(loc.location_name)"
                                class="text-xs text-red-500 hover:underline hover:text-red-600">取消</button>
                        </div>
                        <p v-if="editorLocations.length === 0" class="text-xs text-slate-400">暫無分配</p>
                    </div>
                </div>
            </div>

            <!-- 右欄：地點列表（可點擊分配） -->
            <div class="bg-white/80 dark:bg-slate-800/80 backdrop-blur-md rounded-none shadow-[4px_4px_0_rgba(0,0,0,0.06)] dark:shadow-[4px_4px_0_rgba(0,0,0,0.3)] border border-slate-200/50 dark:border-slate-700/50 p-4">
                <h3 class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-3 border-l-4 border-green-500 pl-2">
                    可分配地點
                </h3>
                <p v-if="!selectedEditor" class="text-xs text-slate-400 text-center py-3">請先選擇一個編纂者</p>

                <template v-else>
                    <h4 class="text-xs font-bold text-slate-500 mb-2">所有地點</h4>
                    <div class="space-y-0.5 max-h-64 overflow-y-auto overflow-x-hidden">
                        <button v-for="loc in allLocations.locations" :key="loc.name"
                            @click="assignLocation(loc.name)"
                            class="w-full text-left p-1.5 text-xs rounded-none hover:bg-green-50 dark:hover:bg-green-900/20 transition-all border-l-4 border-transparent hover:border-green-500 hover:translate-x-1">
                            {{ loc.name }}
                            <span v-if="loc.first" class="text-slate-400">({{ loc.first }})</span>
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
