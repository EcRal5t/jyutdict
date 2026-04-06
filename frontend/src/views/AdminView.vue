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
        alert(e.response?.data?.error || '操作失败')
    } finally {
        changingRoleUserId.value = null
    }
}

// ===== 编纂者地点分配 =====
const allLocations = ref({ area_locations: [], faamjyut_locations: [] })
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

const assignLocation = async (source, locationId) => {
    if (!selectedEditor.value) return
    try {
        await adminApi.assignLocation(selectedEditor.value.id, source, locationId)
        await selectEditor(selectedEditor.value) // 刷新列表
    } catch (e) {
        alert(e.response?.data?.error || '分配失败')
    }
}

const removeLocation = async (source, locationId) => {
    if (!selectedEditor.value) return
    if (!confirm('确认取消此地点分配？')) return
    try {
        await adminApi.removeLocation(selectedEditor.value.id, source, locationId)
        await selectEditor(selectedEditor.value)
    } catch (e) {
        alert(e.response?.data?.error || '取消分配失败')
    }
}

onMounted(() => {
    loadUsers()
    loadLocations()
})
</script>

<template>
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 mb-6">后台管理</h1>

        <!-- Tab 切换 -->
        <div class="flex gap-4 mb-6 border-b border-gray-200 dark:border-slate-700">
            <button @click="activeTab = 'users'"
                class="pb-2 text-sm font-medium transition-colors border-b-2"
                :class="activeTab === 'users' ? 'border-accent text-accent' : 'border-transparent text-slate-500 hover:text-slate-700'">
                用户管理
            </button>
            <button @click="activeTab = 'editors'; searchEditors()"
                class="pb-2 text-sm font-medium transition-colors border-b-2"
                :class="activeTab === 'editors' ? 'border-accent text-accent' : 'border-transparent text-slate-500 hover:text-slate-700'">
                编纂者地点分配
            </button>
        </div>

        <!-- ===== 用户管理 Tab ===== -->
        <div v-if="activeTab === 'users'">
            <!-- 搜索与筛选 -->
            <div class="flex flex-col sm:flex-row gap-3 mb-4">
                <input v-model="searchQuery" @keypress.enter="loadUsers(1)" placeholder="搜索邮箱或昵称..."
                    class="flex-1 p-2 text-sm border border-gray-300 dark:border-slate-600 dark:bg-slate-800 rounded-lg outline-none focus:ring-1 focus:ring-accent" />
                <select v-model="roleFilter" @change="loadUsers(1)"
                    class="p-2 text-sm border border-gray-300 dark:border-slate-600 dark:bg-slate-800 rounded-lg">
                    <option value="">全部角色</option>
                    <option value="user">普通用户</option>
                    <option value="editor">编纂者</option>
                    <option value="admin">管理员</option>
                    <option value="owner">Owner</option>
                </select>
                <button @click="loadUsers(1)" class="bg-accent text-white px-4 py-2 text-sm rounded-lg hover:bg-red-700">
                    搜索
                </button>
            </div>

            <!-- 用户表格 -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-slate-700 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50">
                            <th class="text-left p-3 font-medium text-slate-600 dark:text-slate-400">ID</th>
                            <th class="text-left p-3 font-medium text-slate-600 dark:text-slate-400">昵称</th>
                            <th class="text-left p-3 font-medium text-slate-600 dark:text-slate-400">邮箱</th>
                            <th class="text-left p-3 font-medium text-slate-600 dark:text-slate-400">角色</th>
                            <th class="text-left p-3 font-medium text-slate-600 dark:text-slate-400">注册时间</th>
                            <th class="text-left p-3 font-medium text-slate-600 dark:text-slate-400">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="usersLoading">
                            <td colspan="6" class="text-center py-8 text-slate-400">加载中...</td>
                        </tr>
                        <tr v-for="u in users" :key="u.id" class="border-b border-gray-100 dark:border-slate-700/50 hover:bg-slate-50 dark:hover:bg-slate-900/30">
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
                                    {{ { owner: '站长', admin: '管理员', editor: '编纂者', user: '普通用户' }[u.role] }}
                                </span>
                            </td>
                            <td class="p-3 text-xs text-slate-400">{{ u.created_at }}</td>
                            <td class="p-3">
                                <!-- 角色操作按钮 -->
                                <div v-if="u.id !== authStore.user?.id && u.role !== 'owner'" class="flex gap-1 flex-wrap">
                                    <button v-if="u.role !== 'editor'" @click="changeRole(u.id, 'editor')"
                                        :disabled="changingRoleUserId === u.id"
                                        class="text-xs px-2 py-1 rounded border border-green-300 text-green-600 hover:bg-green-50 disabled:opacity-50">
                                        设为编纂者
                                    </button>
                                    <button v-if="u.role !== 'user'" @click="changeRole(u.id, 'user')"
                                        :disabled="changingRoleUserId === u.id"
                                        class="text-xs px-2 py-1 rounded border border-slate-300 text-slate-600 hover:bg-slate-50 disabled:opacity-50">
                                        降为普通用户
                                    </button>
                                    <button v-if="authStore.isOwner && u.role !== 'admin'" @click="changeRole(u.id, 'admin')"
                                        :disabled="changingRoleUserId === u.id"
                                        class="text-xs px-2 py-1 rounded border border-blue-300 text-blue-600 hover:bg-blue-50 disabled:opacity-50">
                                        设为管理员
                                    </button>
                                </div>
                                <span v-else class="text-xs text-slate-300">-</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
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

        <!-- ===== 编纂者地点分配 Tab ===== -->
        <div v-if="activeTab === 'editors'" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- 左栏：选择编纂者 -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-slate-700 p-4">
                <h3 class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-3">选择编纂者</h3>
                <input v-model="editorSearchQuery" @input="searchEditors" placeholder="搜索编纂者..."
                    class="w-full p-2 text-sm border border-gray-300 dark:border-slate-600 dark:bg-slate-900 rounded-lg mb-3 outline-none" />

                <div class="space-y-1 max-h-60 overflow-y-auto">
                    <button v-for="editor in editorUsers" :key="editor.id" @click="selectEditor(editor)"
                        class="w-full text-left p-2 text-sm rounded-lg transition-colors"
                        :class="selectedEditor?.id === editor.id ? 'bg-accent/10 text-accent' : 'hover:bg-slate-50 dark:hover:bg-slate-700'">
                        {{ editor.nickname || editor.email }} <span class="text-xs text-slate-400">#{{ editor.id }}</span>
                    </button>
                    <p v-if="editorUsers.length === 0" class="text-xs text-slate-400 text-center py-4">无编纂者</p>
                </div>

                <!-- 已分配地点 -->
                <div v-if="selectedEditor" class="mt-4 pt-4 border-t border-gray-200 dark:border-slate-700">
                    <h4 class="text-xs font-bold text-slate-500 mb-2">
                        {{ selectedEditor.nickname || selectedEditor.email }} 已分配的地点：
                    </h4>
                    <div class="space-y-1">
                        <div v-for="loc in editorLocations" :key="`${loc.location_source}-${loc.location_id}`"
                            class="flex items-center justify-between p-2 bg-green-50 dark:bg-green-900/20 rounded text-sm">
                            <span>{{ loc.location_source }} #{{ loc.location_id }}</span>
                            <button @click="removeLocation(loc.location_source, loc.location_id)"
                                class="text-xs text-red-500 hover:underline">取消</button>
                        </div>
                        <p v-if="editorLocations.length === 0" class="text-xs text-slate-400">暂无分配</p>
                    </div>
                </div>
            </div>

            <!-- 右栏：地点列表（可点击分配） -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-slate-700 p-4">
                <h3 class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-3">可分配地点</h3>
                <p v-if="!selectedEditor" class="text-xs text-slate-400 text-center py-4">请先选择一个编纂者</p>

                <template v-else>
                    <h4 class="text-xs font-bold text-slate-500 mb-2">i_area_list 地点</h4>
                    <div class="space-y-1 max-h-40 overflow-y-auto mb-4">
                        <button v-for="loc in allLocations.area_locations" :key="'area-'+loc.id"
                            @click="assignLocation('area', loc.id)"
                            class="w-full text-left p-2 text-xs rounded hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors">
                            #{{ loc.id }} {{ loc.second }}{{ loc.third ? '·' + loc.third : '' }}
                            <span class="text-slate-400">({{ loc.first }})</span>
                        </button>
                    </div>

                    <h4 class="text-xs font-bold text-slate-500 mb-2">i_faamjyut 地点</h4>
                    <div class="space-y-1 max-h-40 overflow-y-auto">
                        <button v-for="loc in allLocations.faamjyut_locations" :key="'faam-'+loc.id"
                            @click="assignLocation('faamjyut', loc.id)"
                            class="w-full text-left p-2 text-xs rounded hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors">
                            #{{ loc.id }} {{ loc.fullname || loc.col }}
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
