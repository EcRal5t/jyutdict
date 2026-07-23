<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import Sortable from 'sortablejs'
import adminApi from '@/api/admin.js'
import { useAuthStore } from '@/stores/auth.js'

const authStore = useAuthStore()
const locations = ref([])
const loading = ref(false)
const saving = ref(false)
const search = ref('')
const filter = ref('active')
const listElement = ref(null)
const editing = ref(null)
const creating = ref(false)
const error = ref('')
let sortable = null

const emptyForm = () => ({
    sheetname: '', first: '', second: '', third: '', detailed_name: '', sheet_author: '',
    longitude: 0, latitude: 0, color: '#CCCCCC',
})
const form = ref(emptyForm())

const activeLocations = computed(() => locations.value.filter((item) => !item.archived_at))
const canDrag = computed(() => filter.value === 'active' && search.value.trim() === '')
const displayed = computed(() => {
    let rows = locations.value
    if (filter.value === 'active') rows = rows.filter((item) => !item.archived_at)
    if (filter.value === 'visible') rows = rows.filter((item) => !item.archived_at && item.is_visible)
    if (filter.value === 'hidden') rows = rows.filter((item) => !item.archived_at && !item.is_visible)
    if (filter.value === 'pending') rows = rows.filter((item) => !item.archived_at && !item.current_release_id)
    if (filter.value === 'archived') rows = rows.filter((item) => item.archived_at)
    const needle = search.value.trim().toLowerCase()
    if (needle) {
        rows = rows.filter((item) => [item.sheetname, item.first, item.second, item.third]
            .some((value) => String(value || '').toLowerCase().includes(needle)))
    }
    return rows
})

const locationName = (item) => [item.first, item.second, item.third].filter(Boolean).join(' · ') || '未命名地點'
const statusLabel = (item) => {
    if (item.archived_at) return '已歸檔'
    if (!item.current_release_id) return '待首次同步'
    return item.is_visible ? '公開' : '隱藏'
}

const load = async () => {
    loading.value = true
    error.value = ''
    try {
        const response = await adminApi.getCatalogLocations()
        if (!Array.isArray(response.data?.locations)) {
            throw new Error('地點目錄 API 的返回格式不正確')
        }
        locations.value = response.data.locations
        await nextTick()
    } catch (e) {
        locations.value = []
        error.value = e.response?.data?.error || e.message || '載入地點目錄失敗'
    } finally {
        loading.value = false
    }
}

const saveOrder = async (ordered) => {
    saving.value = true
    try {
        await adminApi.reorderCatalogLocations(ordered.map((item) => item.id))
        await load()
    } catch (e) {
        error.value = e.response?.data?.error || '儲存排序失敗'
        await load()
    } finally {
        saving.value = false
    }
}

const move = async (item, direction) => {
    const ordered = activeLocations.value.slice()
    const index = ordered.findIndex((row) => row.id === item.id)
    const target = index + direction
    if (index < 0 || target < 0 || target >= ordered.length) return
    const [moved] = ordered.splice(index, 1)
    ordered.splice(target, 0, moved)
    await saveOrder(ordered)
}

const setupSortable = () => {
    if (!listElement.value || sortable) return
    sortable = Sortable.create(listElement.value, {
        animation: 140,
        handle: '.drag-handle',
        draggable: '.location-row',
        onMove: () => canDrag.value && !saving.value,
        onEnd: async ({ oldIndex, newIndex }) => {
            if (!canDrag.value || oldIndex === newIndex || oldIndex == null || newIndex == null) return
            const ordered = activeLocations.value.slice()
            const [moved] = ordered.splice(oldIndex, 1)
            ordered.splice(newIndex, 0, moved)
            await saveOrder(ordered)
        },
    })
}

const openCreate = () => {
    editing.value = null
    form.value = emptyForm()
    creating.value = true
}

const openEdit = (item) => {
    creating.value = false
    editing.value = item
    form.value = {
        id: item.id,
        sheetname: item.sheetname,
        first: item.first,
        second: item.second,
        third: item.third,
        detailed_name: item.detailed_name || '',
        sheet_author: item.sheet_author || '',
        longitude: item.longitude,
        latitude: item.latitude,
        color: item.color,
        is_visible: Boolean(item.is_visible),
    }
}

const closeForm = () => {
    creating.value = false
    editing.value = null
}

const submitForm = async () => {
    saving.value = true
    error.value = ''
    try {
        if (creating.value) await adminApi.createCatalogLocation(form.value)
        else await adminApi.updateCatalogLocation(form.value)
        closeForm()
        await load()
    } catch (e) {
        error.value = e.response?.data?.error || '儲存失敗'
    } finally {
        saving.value = false
    }
}

const toggleVisible = async (item) => {
    try {
        await adminApi.updateCatalogLocation({ id: item.id, is_visible: !item.is_visible })
        await load()
    } catch (e) {
        error.value = e.response?.data?.error || '切換顯示失敗'
    }
}

const archive = async (item) => {
    const typed = prompt(`歸檔會立即隱藏地點，但保留全部歷史。請輸入 ${item.sheetname} 確認：`)
    if (typed !== item.sheetname) return
    try {
        await adminApi.locationAction('archive', { id: item.id, confirm_sheetname: typed })
        await load()
    } catch (e) { error.value = e.response?.data?.error || '歸檔失敗' }
}

const restore = async (item) => {
    if (!confirm(`恢復 ${item.sheetname}？恢復後仍保持隱藏。`)) return
    try {
        await adminApi.locationAction('restore', { id: item.id })
        await load()
    } catch (e) { error.value = e.response?.data?.error || '恢復失敗' }
}

const rename = async (item) => {
    const nextName = prompt('輸入新的 sheetname：', item.sheetname)
    if (!nextName || nextName === item.sheetname) return
    try {
        const preview = await adminApi.locationAction('rename_preview', { id: item.id, new_sheetname: nextName })
        const data = preview.data.preview
        if (!confirm(`將 ${data.from} 改為 ${data.to}\n物理表 ${data.physical_rows} 行，統一表更新 ${data.common_entries_to_update} 行。繼續？`)) return
        const typed = prompt(`請輸入目前的 sheetname ${item.sheetname} 作最後確認：`)
        if (typed !== item.sheetname) return
        await adminApi.locationAction('rename_apply', {
            id: item.id, new_sheetname: nextName, confirm_sheetname: typed,
        })
        await load()
    } catch (e) { error.value = e.response?.data?.error || '重命名失敗' }
}

const removeEmpty = async (item) => {
    const typed = prompt(`永久刪除空地點。請輸入 ${item.sheetname}：`)
    if (typed !== item.sheetname) return
    try {
        await adminApi.deleteEmptyCatalogLocation(item.id, typed)
        await load()
    } catch (e) { error.value = e.response?.data?.error || '刪除失敗' }
}

onMounted(async () => { await load(); setupSortable() })
onBeforeUnmount(() => { sortable?.destroy(); sortable = null })
</script>

<template>
    <section>
        <div class="flex flex-col md:flex-row gap-2 mb-4">
            <input v-model="search" placeholder="搜尋地名或 sheetname…" class="flex-1 p-2 text-sm border-2 border-slate-200 dark:border-slate-700 dark:bg-slate-900" />
            <select v-model="filter" class="p-2 text-sm border-2 border-slate-200 dark:border-slate-700 dark:bg-slate-900">
                <option value="active">全部未歸檔</option><option value="visible">公開</option>
                <option value="hidden">隱藏</option><option value="pending">待首次同步</option><option value="archived">已歸檔</option>
            </select>
            <button v-if="authStore.isOwner" @click="openCreate" class="bg-accent text-white px-4 py-2 text-sm">新增地點</button>
        </div>
        <p v-if="!canDrag && filter !== 'archived'" class="text-xs text-amber-600 mb-2">清除搜尋並選擇「全部未歸檔」後可拖拽排序。</p>
        <p v-if="error" class="p-2 mb-3 text-sm text-red-700 bg-red-50 border-l-4 border-red-500">{{ error }}</p>

        <form v-if="creating || editing" @submit.prevent="submitForm" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 p-4 mb-4 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
            <input v-model.trim="form.sheetname" :disabled="!!editing" required placeholder="sheetname" class="p-2 border dark:bg-slate-900" />
            <input v-model.trim="form.first" placeholder="一级分类" class="p-2 border dark:bg-slate-900" />
            <input v-model.trim="form.second" placeholder="二级地名" class="p-2 border dark:bg-slate-900" />
            <input v-model.trim="form.third" placeholder="三级地名" class="p-2 border dark:bg-slate-900" />
            <input v-model.trim="form.detailed_name" placeholder="完整地點" class="p-2 border dark:bg-slate-900 sm:col-span-2" />
            <textarea v-model.trim="form.sheet_author" rows="2" placeholder="字表作者／署名" class="p-2 border dark:bg-slate-900 sm:col-span-2"></textarea>
            <input v-model.number="form.longitude" type="number" step="any" required placeholder="经度" class="p-2 border dark:bg-slate-900" />
            <input v-model.number="form.latitude" type="number" step="any" required placeholder="纬度" class="p-2 border dark:bg-slate-900" />
            <label class="flex items-center gap-2 p-2 border"><input v-model="form.color" type="color" /> {{ form.color }}</label>
            <label v-if="editing" class="flex items-center gap-2 p-2"><input v-model="form.is_visible" type="checkbox" /> 對外顯示</label>
            <div class="flex gap-2"><button :disabled="saving" class="bg-accent text-white px-4 py-2 text-sm disabled:opacity-50">儲存</button><button type="button" @click="closeForm" class="px-4 py-2 text-sm border">取消</button></div>
        </form>

        <div class="bg-white/80 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 overflow-x-auto">
            <table class="w-full text-sm min-w-[900px]">
                <thead><tr class="bg-slate-50 dark:bg-slate-900/40 border-b"><th class="p-2 w-10"></th><th class="p-2 text-left">順序</th><th class="p-2 text-left">地點</th><th class="p-2 text-left">sheetname</th><th class="p-2 text-left">狀態</th><th class="p-2 text-left">版本/行數</th><th class="p-2 text-left">隊列</th><th class="p-2 text-left">操作</th></tr></thead>
                <tbody ref="listElement">
                    <tr v-if="loading"><td colspan="8" class="p-6 text-center text-slate-400">載入中…</td></tr>
                    <tr v-for="item in displayed" :key="item.id" class="location-row border-b border-slate-100 dark:border-slate-700/50">
                        <td class="p-2"><button v-if="!item.archived_at" :class="canDrag ? 'cursor-grab' : 'opacity-30'" class="drag-handle text-lg" title="拖拽排序">⋮⋮</button></td>
                        <td class="p-2 whitespace-nowrap">{{ item.sort_order }} <button v-if="!item.archived_at" @click="move(item,-1)" class="ml-1">↑</button><button v-if="!item.archived_at" @click="move(item,1)" class="ml-1">↓</button></td>
                        <td class="p-2"><span class="inline-block w-3 h-3 mr-1" :style="{background:item.color}"></span>{{ locationName(item) }}</td>
                        <td class="p-2 font-mono text-xs">{{ item.sheetname }}</td>
                        <td class="p-2"><span :class="item.archived_at ? 'text-slate-400' : item.is_visible ? 'text-green-600' : 'text-amber-600'">{{ statusLabel(item) }}</span></td>
                        <td class="p-2 text-xs">
                            <div>{{ item.release_no ? `r${item.release_no} / ${item.entry_count}` : '—' }}</div>
                            <div v-if="item.source_date" class="text-slate-400">{{ item.source_date }} · {{ item.syllable_count ?? '—' }} 音節</div>
                            <div v-if="item.current_release_id" :class="item.has_current_phonology ? 'text-emerald-600' : 'text-amber-600'">
                                音系：{{ item.has_current_phonology ? '已同步' : '未生成／過期' }}
                            </div>
                        </td>
                        <td class="p-2 text-xs" :class="item.queue_status === 'failed' ? 'text-red-600' : ''">{{ item.queue_status || '—' }}</td>
                        <td class="p-2"><div class="flex flex-wrap gap-2 text-xs">
                            <button @click="openEdit(item)" class="text-blue-600">編輯</button>
                            <button v-if="!item.archived_at && item.current_release_id" @click="toggleVisible(item)" class="text-amber-600">{{ item.is_visible ? '隱藏' : '顯示' }}</button>
                            <template v-if="authStore.isOwner">
                                <button v-if="!item.archived_at && item.physical_table_exists" @click="rename(item)" class="text-purple-600">改表名</button>
                                <button v-if="!item.archived_at && item.current_release_id" @click="archive(item)" class="text-slate-600">歸檔</button>
                                <button v-if="item.archived_at" @click="restore(item)" class="text-green-600">恢復</button>
                                <button v-if="!item.current_release_id && !item.physical_table_exists" @click="removeEmpty(item)" class="text-red-600">永久刪除</button>
                            </template>
                        </div></td>
                    </tr>
                    <tr v-if="!loading && displayed.length === 0"><td colspan="8" class="p-6 text-center text-slate-400">沒有符合條件的地點</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
