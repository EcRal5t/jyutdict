<script setup>
import { onMounted, ref } from 'vue'
import adminApi from '@/api/admin.js'

const events = ref([])
const pagination = ref({})
const status = ref('')
const loading = ref(false)
const expanded = ref(null)
const error = ref('')

const load = async (page = 1) => {
    loading.value = true
    try {
        const response = await adminApi.getMaintenanceAudit({ page, per_page: 30, status: status.value || undefined })
        events.value = response.data.events || []
        pagination.value = response.data.pagination || {}
        error.value = ''
    } catch (e) { error.value = e.response?.data?.error || '載入操作記錄失敗' }
    finally { loading.value = false }
}
onMounted(load)
</script>

<template>
    <section>
        <div class="flex gap-2 mb-4"><select v-model="status" @change="load(1)" class="p-2 text-sm border dark:bg-slate-900"><option value="">全部結果</option><option value="success">成功</option><option value="failed">失敗</option></select><button @click="load(pagination.page || 1)" class="px-3 py-2 text-sm border">刷新</button></div>
        <p v-if="error" class="p-2 mb-3 text-sm text-red-700 bg-red-50">{{ error }}</p>
        <div class="overflow-x-auto border dark:border-slate-700"><table class="w-full text-sm min-w-[850px]"><thead><tr class="bg-slate-50 dark:bg-slate-900/40"><th class="p-2 text-left">時間</th><th class="p-2 text-left">操作者</th><th class="p-2 text-left">動作</th><th class="p-2 text-left">地點</th><th class="p-2 text-left">結果</th><th class="p-2"></th></tr></thead>
            <tbody><template v-for="event in events" :key="event.id"><tr class="border-t dark:border-slate-700"><td class="p-2 text-xs">{{ event.created_at }}</td><td class="p-2 text-xs">{{ event.nickname || event.email || `#${event.user_id || '-'}` }}</td><td class="p-2 font-mono text-xs">{{ event.action }}</td><td class="p-2 font-mono text-xs">{{ event.sheetname || '—' }}</td><td class="p-2" :class="event.status === 'failed' ? 'text-red-600' : 'text-green-600'">{{ event.status }}<span v-if="event.error_message" class="block text-xs">{{ event.error_message }}</span></td><td class="p-2"><button @click="expanded = expanded === event.id ? null : event.id" class="text-xs text-blue-600">詳情</button></td></tr>
                <tr v-if="expanded === event.id"><td colspan="6" class="p-3 bg-slate-50 dark:bg-slate-900/40"><pre class="text-xs whitespace-pre-wrap break-all">{{ JSON.stringify({ before: event.before_json, after: event.after_json, request_id: event.request_id }, null, 2) }}</pre></td></tr></template>
                <tr v-if="loading"><td colspan="6" class="p-6 text-center">載入中…</td></tr><tr v-if="!loading && events.length === 0"><td colspan="6" class="p-6 text-center text-slate-400">尚無操作記錄</td></tr></tbody></table></div>
        <div v-if="pagination.total_pages > 1" class="flex justify-center gap-1 mt-3"><button v-for="page in pagination.total_pages" :key="page" @click="load(page)" class="px-3 py-1 border text-sm" :class="page === pagination.page ? 'bg-accent text-white' : ''">{{ page }}</button></div>
    </section>
</template>
