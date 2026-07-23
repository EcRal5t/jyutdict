<script setup>
import { computed, onMounted, reactive, ref, shallowRef, watch } from 'vue'
import adminApi from '@/api/admin.js'
import { COMMON_CONVERTER_VERSION } from '@/utils/commonConverter.js'
import { prepareImportTransfer } from '@/utils/commonTransfer.js'
import { runCommonWorker } from '@/utils/commonWorkerClient.js'
import { rebuildLocationPhonology } from '@/utils/phonologyRebuild.js'

const metadata = shallowRef(null)
const ruleBundle = shallowRef(null)
const loading = ref(true)
const busy = ref(false)
const error = ref('')
const success = ref('')
const progress = ref({ percent: 0, message: '' })
const selectedFile = shallowRef(null)
const parsed = shallowRef(null)
const transfer = shallowRef(null)
const jobId = ref('')
const targetMode = ref('existing')
const areaId = ref('')
const rebuildAfterPublish = ref(false)

const config = reactive({
    sheetName: 'Sheet1',
    localeName: '',
    charColumn: 'A',
    pronColumns: 'B',
    secondaryPronColumns: '',
    ipaColumns: '',
    meaningColumns: 'C',
    startRow: 2,
    separator: '/',
    s2tMode: 'legacy',
    keepS2tCollision: true,
    convertMeanings: false,
    removeRedundantMeaning: false,
    sortPronunciations: false,
})
const sourceDate = ref('')
const stable = reactive({ detailed_name: '', sheet_author: '' })
const newArea = reactive({
    sheetname: '', first: '', second: '', third: '',
    longitude: 0, latitude: 0, color: '#CCCCCC',
})

const locations = computed(() => metadata.value?.locations?.filter(area => !area.archived_at) || [])
const selectedArea = computed(() =>
    locations.value.find(area => Number(area.id) === Number(areaId.value)) || null
)
const areaName = area => [area.second, area.third].filter(Boolean).join('') || area.first || ''

function inferDate(filename) {
    const matches = [...String(filename).matchAll(/(?:^|\D)(\d{8}|\d{6})(?=\D|$)/g)]
    for (const match of matches.reverse()) {
        const raw = match[1]
        const year = raw.length === 8 ? Number(raw.slice(0, 4)) : 2000 + Number(raw.slice(0, 2))
        const month = Number(raw.slice(-4, -2))
        const day = Number(raw.slice(-2))
        const date = new Date(Date.UTC(year, month - 1, day))
        if (date.getUTCFullYear() === year && date.getUTCMonth() === month - 1 &&
            date.getUTCDate() === day) {
            return `${year.toString().padStart(4, '0')}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`
        }
    }
    return ''
}

function applySelectedArea() {
    const area = selectedArea.value
    if (!area) return
    config.localeName = [area.second, area.third].filter(Boolean).join('') || area.first
    stable.detailed_name = area.detailed_name || ''
    stable.sheet_author = area.sheet_author || ''
}

watch(areaId, applySelectedArea)

async function load() {
    loading.value = true
    error.value = ''
    try {
        const [metaResponse, rulesResponse] = await Promise.all([
            adminApi.getCommonImport(),
            adminApi.getCommonRules(),
        ])
        metadata.value = metaResponse.data
        ruleBundle.value = rulesResponse.data.active
        if (!areaId.value && locations.value.length) {
            areaId.value = locations.value[0].id
            applySelectedArea()
        }
    } catch (caught) {
        error.value = caught.response?.data?.error || caught.message || '載入導入工具失敗'
    } finally {
        loading.value = false
    }
}

function chooseFile(event) {
    const file = event.target.files?.[0] || null
    selectedFile.value = file
    parsed.value = null
    transfer.value = null
    jobId.value = ''
    success.value = ''
    error.value = ''
    if (file) sourceDate.value = inferDate(file.name)
}

function validateBeforeParse() {
    const file = selectedFile.value
    if (!file) throw new Error('請選擇 .xlsx 字表')
    if (!/\.xlsx$/i.test(file.name)) throw new Error('目前只接受 .xlsx 活頁簿')
    if (file.size > (metadata.value?.limits?.max_xlsx_bytes || 20971520)) {
        throw new Error('活頁簿超過 20 MB 上限')
    }
    if (!config.localeName.trim()) throw new Error('請填寫規則地點名')
    if (!config.charColumn.trim()) throw new Error('請填寫字頭欄')
    if (!config.pronColumns.trim() && !config.ipaColumns.trim()) {
        throw new Error('J++ 欄與 IPA 欄至少填一項')
    }
}

async function parseWorkbook() {
    busy.value = true
    error.value = ''
    success.value = ''
    parsed.value = null
    transfer.value = null
    try {
        validateBeforeParse()
        const buffer = await selectedFile.value.arrayBuffer()
        const result = await runCommonWorker('parse-workbook', {
            buffer,
            config: { ...config },
            ruleBundle: ruleBundle.value.payload,
        }, {
            transfer: [buffer],
            onProgress: value => { progress.value = value },
        })
        if (!result.rows.length) throw new Error('轉換後沒有可發佈的讀音')
        parsed.value = result
        config.sheetName = result.sourceSheet
        progress.value = { percent: 100, message: '正在準備斷點上傳資料' }
        transfer.value = await prepareImportTransfer(
            result.rows,
            metadata.value?.limits?.recommended_chunk_rows || 750,
            metadata.value?.limits?.max_compressed_chunk_bytes || 1048576
        )
        const resumable = metadata.value.jobs?.find(job =>
            job.content_hash === transfer.value.contentHash &&
            !['published', 'aborted'].includes(job.status) &&
            (targetMode.value !== 'existing' || Number(job.area_id) === Number(areaId.value)) &&
            job.source_filename === selectedFile.value.name &&
            job.source_date === sourceDate.value &&
            job.source_sheet === result.sourceSheet &&
            job.rule_bundle_id === metadata.value.rule_bundle.id &&
            job.rule_profile === config.localeName &&
            job.expected_chunk_count === transfer.value.chunks.length &&
            job.expected_row_count === result.stats.entry_count &&
            JSON.stringify(job.config || {}) === JSON.stringify({ ...config }) &&
            JSON.stringify(job.stable_metadata || {}) === JSON.stringify({ ...stable }) &&
            JSON.stringify(job.new_area || null) === JSON.stringify(
                targetMode.value === 'new' ? { ...newArea } : null
            )
        )
        jobId.value = resumable?.id || crypto.randomUUID()
        success.value = resumable
            ? `已找到相同內容的未完成任務，將從 ${resumable.received_chunk_count}/${resumable.expected_chunk_count} 分塊續傳。`
            : '轉換完成。原始 Excel 尚未離開瀏覽器。'
    } catch (caught) {
        error.value = caught.message || '轉換失敗'
    } finally {
        busy.value = false
    }
}

async function uploadChunkWithRetry(job, chunk) {
    let lastError
    for (let attempt = 1; attempt <= 3; attempt += 1) {
        try {
            return await adminApi.uploadCommonImportChunk(
                job,
                chunk.number,
                chunk.payload,
                chunk.hash
            )
        } catch (caught) {
            lastError = caught
            if (attempt < 3) {
                progress.value = {
                    percent: progress.value.percent,
                    message: `第 ${chunk.number + 1} 分塊連線失敗，正在第 ${attempt + 1} 次重試`,
                }
            }
        }
    }
    throw lastError
}

async function publishWorkbook() {
    if (!parsed.value || !transfer.value) return
    if (!sourceDate.value) {
        error.value = '檔名未能解析版本日期，請先人工填寫'
        return
    }
    if (targetMode.value === 'existing' && !areaId.value) {
        error.value = '請選擇目標地點'
        return
    }
    busy.value = true
    error.value = ''
    success.value = ''
    try {
        const createResponse = await adminApi.createCommonImport({
            job_id: jobId.value,
            area_id: targetMode.value === 'existing' ? Number(areaId.value) : null,
            new_area: targetMode.value === 'new' ? { ...newArea } : null,
            stable_metadata: { ...stable },
            source_filename: selectedFile.value.name,
            source_date: sourceDate.value,
            source_sheet: parsed.value.sourceSheet,
            converter_version: COMMON_CONVERTER_VERSION,
            rule_profile: config.localeName,
            rule_bundle_id: metadata.value.rule_bundle.id,
            rule_bundle_hash: metadata.value.rule_bundle.payload_hash,
            config: { ...config },
            expected_chunk_count: transfer.value.chunks.length,
            expected_row_count: parsed.value.stats.entry_count,
            character_count: parsed.value.stats.character_count,
            syllable_count: parsed.value.stats.syllable_count,
            toneless_syllable_count: parsed.value.stats.toneless_syllable_count,
            skipped_row_count: parsed.value.stats.skipped_row_count,
            content_hash: transfer.value.contentHash,
        })
        const receivedResponse = await adminApi.getCommonImport({ job: jobId.value })
        const received = new Set(receivedResponse.data.chunks.map(chunk => chunk.chunk_no))
        for (const chunk of transfer.value.chunks) {
            if (received.has(chunk.number)) continue
            progress.value = {
                percent: Math.round(received.size / transfer.value.chunks.length * 80),
                message: `正在上傳第 ${chunk.number + 1}/${transfer.value.chunks.length} 分塊`,
            }
            await uploadChunkWithRetry(createResponse.data.job.id, chunk)
            received.add(chunk.number)
        }
        progress.value = { percent: 85, message: '正在原子發佈字表版本' }
        const publishResponse = await adminApi.publishCommonImport(jobId.value)
        const publication = publishResponse.data.publication
        success.value = `已發佈 r${publication.release_no || ''}：${publication.entry_count} 行、${publication.character_count} 字。`
        if (rebuildAfterPublish.value) {
            progress.value = { percent: 90, message: '正在重建音系表' }
            await rebuildLocationPhonology(publication.area_id, value => {
                progress.value = { percent: 90, message: value.message }
            })
            success.value += ' 音系四表亦已重建。'
        }
        progress.value = { percent: 100, message: '全部完成' }
        await load()
    } catch (caught) {
        error.value = caught.response?.data?.error || caught.message || '發佈失敗；可再次按下發佈續傳'
    } finally {
        busy.value = false
    }
}

onMounted(load)
</script>

<template>
    <section class="space-y-5">
        <div class="border-l-4 border-accent bg-white/80 dark:bg-slate-800/80 p-4 shadow-[4px_4px_0_rgba(0,0,0,0.06)]">
            <h2 class="font-bold text-slate-800 dark:text-slate-100">整表導入</h2>
            <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                Excel 只在本機瀏覽器解析；發往伺服器的是已驗證、壓縮且可斷點續傳的統一資料行。
                公式欄只讀取活頁簿內的快取結果。
            </p>
        </div>

        <p v-if="error" class="border-l-4 border-red-500 bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-300">{{ error }}</p>
        <p v-if="success" class="border-l-4 border-emerald-500 bg-emerald-50 p-3 text-sm text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300">{{ success }}</p>
        <div v-if="busy || progress.message" class="border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-800">
            <div class="mb-2 flex justify-between text-xs text-slate-500"><span>{{ progress.message }}</span><span>{{ progress.percent || 0 }}%</span></div>
            <div class="h-2 bg-slate-100 dark:bg-slate-900"><div class="h-full bg-accent transition-all" :style="{ width: `${progress.percent || 0}%` }"></div></div>
        </div>

        <div v-if="!loading" class="grid grid-cols-1 gap-5 xl:grid-cols-2">
            <form class="space-y-4 border border-slate-200 bg-white/80 p-4 dark:border-slate-700 dark:bg-slate-800/80" @submit.prevent="parseWorkbook">
                <h3 class="border-l-4 border-accent pl-2 text-sm font-bold">1　檔案與地點</h3>
                <input type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                    class="block w-full border-2 border-slate-200 p-2 text-sm dark:border-slate-700 dark:bg-slate-900" @change="chooseFile" />

                <div class="flex gap-4 text-sm">
                    <label><input v-model="targetMode" type="radio" value="existing" /> 更新已有地點</label>
                    <label><input v-model="targetMode" type="radio" value="new" /> 新增地點</label>
                </div>
                <select v-if="targetMode === 'existing'" v-model="areaId" class="w-full border-2 border-slate-200 p-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                    <option v-for="area in locations" :key="area.id" :value="area.id">{{ areaName(area) }}（{{ area.sheetname }}）</option>
                </select>
                <div v-else class="grid grid-cols-2 gap-2">
                    <input v-model.trim="newArea.sheetname" required placeholder="舊表識別名（如 z_siuhing）" class="col-span-2 border p-2 text-sm dark:bg-slate-900" />
                    <input v-model.trim="newArea.first" placeholder="片區" class="border p-2 text-sm dark:bg-slate-900" />
                    <input v-model.trim="newArea.second" placeholder="城市" class="border p-2 text-sm dark:bg-slate-900" />
                    <input v-model.trim="newArea.third" placeholder="地點短名" class="border p-2 text-sm dark:bg-slate-900" />
                    <label class="flex items-center gap-2 border p-2 text-xs"><input v-model="newArea.color" type="color" />{{ newArea.color }}</label>
                    <input v-model.number="newArea.longitude" type="number" step="any" placeholder="經度" class="border p-2 text-sm dark:bg-slate-900" />
                    <input v-model.number="newArea.latitude" type="number" step="any" placeholder="緯度" class="border p-2 text-sm dark:bg-slate-900" />
                </div>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <label class="text-xs text-slate-500">完整地點
                        <input v-model.trim="stable.detailed_name" class="mt-1 w-full border p-2 text-sm text-slate-800 dark:bg-slate-900 dark:text-slate-100" />
                    </label>
                    <label class="text-xs text-slate-500">字表作者／署名
                        <input v-model.trim="stable.sheet_author" class="mt-1 w-full border p-2 text-sm text-slate-800 dark:bg-slate-900 dark:text-slate-100" />
                    </label>
                    <label class="text-xs text-slate-500">版本日期
                        <input v-model="sourceDate" required type="date" class="mt-1 w-full border p-2 text-sm text-slate-800 dark:bg-slate-900 dark:text-slate-100" />
                    </label>
                    <label class="text-xs text-slate-500">規則地點名
                        <input v-model.trim="config.localeName" required class="mt-1 w-full border p-2 text-sm text-slate-800 dark:bg-slate-900 dark:text-slate-100" />
                    </label>
                </div>

                <h3 class="border-l-4 border-accent pl-2 text-sm font-bold">2　Excel 欄位</h3>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <label class="text-xs text-slate-500">工作表<input v-model.trim="config.sheetName" class="mt-1 w-full border p-2 text-sm dark:bg-slate-900" /></label>
                    <label class="text-xs text-slate-500">字頭 -c<input v-model.trim="config.charColumn" class="mt-1 w-full border p-2 text-sm dark:bg-slate-900" /></label>
                    <label class="text-xs text-slate-500">J++ -p<input v-model.trim="config.pronColumns" class="mt-1 w-full border p-2 text-sm dark:bg-slate-900" /></label>
                    <label class="text-xs text-slate-500">次音 -P<input v-model.trim="config.secondaryPronColumns" class="mt-1 w-full border p-2 text-sm dark:bg-slate-900" /></label>
                    <label class="text-xs text-slate-500">IPA -I<input v-model.trim="config.ipaColumns" class="mt-1 w-full border p-2 text-sm dark:bg-slate-900" /></label>
                    <label class="text-xs text-slate-500">釋義 -m<input v-model.trim="config.meaningColumns" class="mt-1 w-full border p-2 text-sm dark:bg-slate-900" /></label>
                    <label class="text-xs text-slate-500">首個資料行<input v-model.number="config.startRow" min="2" type="number" class="mt-1 w-full border p-2 text-sm dark:bg-slate-900" /></label>
                    <label class="text-xs text-slate-500">又音分隔符<input v-model="config.separator" maxlength="4" class="mt-1 w-full border p-2 text-sm dark:bg-slate-900" /></label>
                </div>
                <div class="grid grid-cols-1 gap-2 text-xs sm:grid-cols-2">
                    <label class="flex items-center gap-2">簡繁策略
                        <select v-model="config.s2tMode" class="border p-1 dark:bg-slate-900">
                            <option value="legacy">舊版自動簡轉繁</option>
                            <option value="off">不轉換（--no-s2t）</option>
                        </select>
                    </label>
                    <label><input v-model="config.keepS2tCollision" type="checkbox" :disabled="config.s2tMode !== 'legacy'" /> 簡轉繁碰撞時保留簡體（--keep-s2t）</label>
                    <label><input v-model="config.convertMeanings" type="checkbox" /> 釋義轉繁體（--cc-mean）</label>
                    <label><input v-model="config.removeRedundantMeaning" type="checkbox" /> 單音字刪除釋義（-RM）</label>
                    <label><input v-model="config.sortPronunciations" type="checkbox" /> 讀音排序（--sort-pron）</label>
                </div>
                <button :disabled="busy || !selectedFile" class="w-full bg-accent px-4 py-2 text-sm font-bold text-white disabled:opacity-40">解析並預覽</button>
            </form>

            <div class="space-y-4">
                <div class="border border-slate-200 bg-white/80 p-4 dark:border-slate-700 dark:bg-slate-800/80">
                    <h3 class="border-l-4 border-accent pl-2 text-sm font-bold">3　驗證結果</h3>
                    <p v-if="!parsed" class="py-10 text-center text-sm text-slate-400">尚未解析字表</p>
                    <template v-else>
                        <dl class="mt-4 grid grid-cols-2 gap-2 text-sm sm:grid-cols-3">
                            <div class="border p-2"><dt class="text-xs text-slate-400">有效行</dt><dd class="font-bold">{{ parsed.stats.entry_count }}</dd></div>
                            <div class="border p-2"><dt class="text-xs text-slate-400">字數</dt><dd class="font-bold">{{ parsed.stats.character_count }}</dd></div>
                            <div class="border p-2"><dt class="text-xs text-slate-400">音節數</dt><dd class="font-bold">{{ parsed.stats.syllable_count }}</dd></div>
                            <div class="border p-2"><dt class="text-xs text-slate-400">不帶調音節</dt><dd class="font-bold">{{ parsed.stats.toneless_syllable_count }}</dd></div>
                            <div class="border p-2"><dt class="text-xs text-slate-400">略過行</dt><dd class="font-bold">{{ parsed.stats.skipped_row_count }}</dd></div>
                            <div class="border p-2"><dt class="text-xs text-slate-400">上傳分塊</dt><dd class="font-bold">{{ transfer?.chunks.length || '…' }}</dd></div>
                        </dl>
                        <details v-if="parsed.warnings.length" class="mt-3 border-l-4 border-amber-500 bg-amber-50 p-2 text-xs dark:bg-amber-950/20">
                            <summary class="cursor-pointer font-bold">{{ parsed.warnings.length }} 項提示</summary>
                            <ul class="mt-2 max-h-40 list-disc overflow-auto pl-5"><li v-for="warning in parsed.warnings" :key="warning">{{ warning }}</li></ul>
                        </details>
                        <div class="mt-3 max-h-72 overflow-auto border">
                            <table class="w-full min-w-[720px] text-xs">
                                <thead class="sticky top-0 bg-slate-100 dark:bg-slate-900"><tr><th class="p-2">字</th><th>J++</th><th>IPA</th><th>釋義</th><th>Excel 行</th></tr></thead>
                                <tbody><tr v-for="row in parsed.rows.slice(0, 30)" :key="row.row_no" class="border-t"><td class="p-2 text-center text-base">{{ row.chara }}</td><td>{{ row.initial }}{{ row.nuclei }}{{ row.coda }}{{ row.tone }}</td><td>{{ row.ipa }}</td><td>{{ row.note }}</td><td>{{ row.source_row }}</td></tr></tbody>
                            </table>
                        </div>
                    </template>
                </div>
                <div class="border border-slate-200 bg-white/80 p-4 dark:border-slate-700 dark:bg-slate-800/80">
                    <label class="mb-3 block text-sm"><input v-model="rebuildAfterPublish" type="checkbox" /> 發佈成功後立即重建音系</label>
                    <button :disabled="busy || !parsed || !transfer" @click="publishWorkbook"
                        class="w-full bg-slate-900 px-4 py-3 text-sm font-bold text-white hover:bg-accent disabled:opacity-40 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-accent dark:hover:text-white">
                        斷點上傳並發佈
                    </button>
                    <p class="mt-2 text-xs leading-relaxed text-slate-400">發佈使用單一資料庫交易；斷線或驗證失敗不會留下半張新字表。再次按下可續傳已完成分塊。</p>
                </div>
            </div>
        </div>
    </section>
</template>
