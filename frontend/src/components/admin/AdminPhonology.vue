<script setup>
import { computed, onMounted, ref } from 'vue'
import adminApi from '@/api/admin.js'
import { rebuildLocationPhonology } from '@/utils/phonologyRebuild.js'

const locations = ref([])
const loading = ref(true)
const rebuildingId = ref(null)
const progress = ref('')
const error = ref('')
const success = ref('')
const search = ref('')

const areaName = area => [area.second, area.third].filter(Boolean).join('') || area.first || ''
const displayed = computed(() => {
    const needle = search.value.trim().toLowerCase()
    return locations.value.filter(area => !area.archived_at && area.current_release_id)
        .filter(area => !needle || [areaName(area), area.sheetname]
            .some(value => String(value || '').toLowerCase().includes(needle)))
})

async function load() {
    loading.value = true
    try {
        const response = await adminApi.getCommonImport()
        locations.value = response.data.locations || []
    } catch (caught) {
        error.value = caught.response?.data?.error || caught.message || '載入地點失敗'
    } finally {
        loading.value = false
    }
}

async function rebuild(area) {
    rebuildingId.value = area.id
    error.value = ''
    success.value = ''
    progress.value = '準備重建'
    try {
        const result = await rebuildLocationPhonology(area.id, value => {
            progress.value = value.message
        })
        success.value = `${areaName(area)}音系已重建為第 ${result.report.revision_no} 版，共 ${result.payload.statistics.ruleCount} 條規則。`
        await load()
    } catch (caught) {
        error.value = caught.response?.data?.error || caught.message || '音系重建失敗'
    } finally {
        rebuildingId.value = null
        progress.value = ''
    }
}

onMounted(load)
</script>

<template>
    <section class="space-y-4">
        <div class="border-l-4 border-accent bg-white/80 p-4 dark:bg-slate-800/80">
            <h2 class="font-bold">音系重建</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">由目前已發佈的通用字表重新計算四張對照表；不會建立新的字表版本。</p>
        </div>
        <p v-if="error" class="border-l-4 border-red-500 bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-300">{{ error }}</p>
        <p v-if="success" class="border-l-4 border-emerald-500 bg-emerald-50 p-3 text-sm text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300">{{ success }}</p>
        <p v-if="progress" class="border border-slate-200 bg-white p-3 text-sm dark:border-slate-700 dark:bg-slate-800">{{ progress }}</p>
        <input v-model="search" placeholder="搜尋地點或 sheetname…"
            class="w-full border-2 border-slate-200 bg-white p-2 text-sm dark:border-slate-700 dark:bg-slate-900" />
        <div class="overflow-x-auto border border-slate-200 bg-white/80 dark:border-slate-700 dark:bg-slate-800/80">
            <table class="w-full min-w-[760px] text-sm">
                <thead class="bg-slate-100 text-left dark:bg-slate-900/60"><tr><th class="p-2">地點</th><th>字表版本</th><th>音系狀態</th><th>操作</th></tr></thead>
                <tbody>
                    <tr v-if="loading"><td colspan="4" class="p-8 text-center text-slate-400">載入中…</td></tr>
                    <tr v-for="area in displayed" :key="area.id" class="border-t border-slate-200 dark:border-slate-700">
                        <td class="p-2"><span class="mr-2 inline-block h-3 w-3" :style="{ background: area.color }"></span>{{ areaName(area) }}<span class="ml-2 font-mono text-xs text-slate-400">{{ area.sheetname }}</span></td>
                        <td>r{{ area.release_no }} · {{ area.entry_count }} 行</td>
                        <td>
                            <span v-if="area.has_current_phonology" class="text-emerald-600">已與目前字表同步</span>
                            <span v-else class="text-amber-600">未生成或已過期</span>
                        </td>
                        <td class="p-2">
                            <button :disabled="rebuildingId !== null" @click="rebuild(area)"
                                class="border border-accent px-3 py-1.5 text-xs font-bold text-accent hover:bg-accent hover:text-white disabled:opacity-40">
                                {{ rebuildingId === area.id ? '重建中…' : '重建' }}
                            </button>
                            <RouterLink v-if="area.has_current_phonology" :to="{ name: 'location-phonology', params: { areaId: area.id } }"
                                class="ml-3 text-xs text-blue-600 hover:underline">查看</RouterLink>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
