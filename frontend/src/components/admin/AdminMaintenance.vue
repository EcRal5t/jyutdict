<script setup>
import { computed, onMounted, ref } from 'vue'
import adminApi from '@/api/admin.js'
import { useAuthStore } from '@/stores/auth.js'

const authStore = useAuthStore()
const data = ref({ summary: [], worker: null, queue: [] })
const loading = ref(false)
const error = ref('')
const filter = ref('outstanding')

const rows = computed(() => {
    if (filter.value === 'all') return data.value.queue
    if (filter.value === 'failed') return data.value.queue.filter((row) => row.status === 'failed')
    return data.value.queue.filter((row) => row.outstanding)
})
const workerLabel = computed(() => {
    if (!data.value.worker) return '尚無心跳記錄'
    if (data.value.worker.is_stale) return `心跳逾時：${data.value.worker.last_seen_at || '從未運行'}`
    return `正常，最近心跳 ${data.value.worker.last_seen_at}`
})

const load = async () => {
    loading.value = true
    try { data.value = (await adminApi.getMaintenance()).data; error.value = '' }
    catch (e) { error.value = e.response?.data?.error || '載入同步狀態失敗' }
    finally { loading.value = false }
}
const act = async (action, row = null) => {
    const message = action === 'enqueue' ? `重新同步 ${row.legacy_table}？` : action === 'retry' ? `重試 ${row.legacy_table}？` : '重試全部失敗任務？'
    if (!confirm(message)) return
    try {
        await adminApi.maintenanceAction(action, row ? { area_id: row.area_id } : {})
        await load()
    } catch (e) { error.value = e.response?.data?.error || '隊列操作失敗' }
}
onMounted(load)
</script>

<template>
    <section>
        <div class="flex flex-wrap items-center gap-3 mb-4">
            <div class="px-3 py-2 text-sm border-l-4" :class="data.worker?.is_stale ? 'border-red-500 bg-red-50 text-red-700' : 'border-green-500 bg-green-50 text-green-700'">cron：{{ workerLabel }}</div>
            <select v-model="filter" class="p-2 text-sm border dark:bg-slate-900"><option value="outstanding">只看未完成</option><option value="failed">只看失敗</option><option value="all">全部</option></select>
            <button @click="load" class="px-3 py-2 text-sm border">刷新</button>
            <button v-if="authStore.isOwner" @click="act('retry_all_failed')" class="px-3 py-2 text-sm text-red-600 border border-red-300">重試全部失敗</button>
        </div>
        <p v-if="error" class="p-2 mb-3 text-sm text-red-700 bg-red-50 border-l-4 border-red-500">{{ error }}</p>
        <div class="overflow-x-auto border dark:border-slate-700"><table class="w-full text-sm min-w-[900px]">
            <thead><tr class="bg-slate-50 dark:bg-slate-900/40"><th class="p-2 text-left">地點表</th><th class="p-2 text-left">狀態</th><th class="p-2 text-left">generation</th><th class="p-2 text-left">嘗試</th><th class="p-2 text-left">請求/完成</th><th class="p-2 text-left">錯誤</th><th class="p-2"></th></tr></thead>
            <tbody><tr v-if="loading"><td colspan="7" class="p-6 text-center">載入中…</td></tr>
                <tr v-for="row in rows" :key="row.area_id" class="border-t dark:border-slate-700"><td class="p-2 font-mono text-xs">{{ row.legacy_table }}</td><td class="p-2" :class="row.status === 'failed' ? 'text-red-600' : row.outstanding ? 'text-amber-600' : 'text-green-600'">{{ row.status }}{{ row.outstanding ? ' · 未完成' : '' }}</td><td class="p-2">{{ row.processed_generation }}/{{ row.requested_generation }}</td><td class="p-2">{{ row.attempt_count }}</td><td class="p-2 text-xs">{{ row.requested_at }}<br>{{ row.completed_at || '—' }}</td><td class="p-2 text-xs text-red-600 max-w-sm break-words">{{ row.last_error || '—' }}</td><td class="p-2"><div v-if="authStore.isOwner" class="flex gap-2 text-xs"><button @click="act('enqueue',row)" class="text-blue-600">重新同步</button><button v-if="row.status === 'failed' && row.outstanding" @click="act('retry',row)" class="text-red-600">重試</button></div></td></tr>
                <tr v-if="!loading && rows.length === 0"><td colspan="7" class="p-6 text-center text-slate-400">沒有符合條件的任務</td></tr>
            </tbody></table></div>
    </section>
</template>
