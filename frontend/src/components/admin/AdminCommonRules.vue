<script setup>
import { computed, onMounted, ref, shallowRef } from 'vue'
import adminApi from '@/api/admin.js'

const BOOKS = [
    { key: 'i2i', label: 'IPA → IPA', input: 'IPA', output: 'IPA', file: 'rule_i2i.csv' },
    { key: 'i2j', label: 'IPA → J++', input: 'IPA', output: 'J++', file: 'rule_i2j.csv' },
    { key: 'j2i', label: 'J++ → IPA', input: 'J++', output: 'IPA', file: 'rule_j2i.csv' },
    { key: 'j2j', label: 'J++ → J++', input: 'J++', output: 'J++', file: 'rule_j2j.csv' },
    { key: 'tone-j2i', label: 'J++ → IPA 聲調', tone: true, file: 'rule_tone_j2i.csv' },
    { key: 'tone-j2j', label: 'J++ → J++ 聲調', tone: true, file: 'rule_tone_j2j.csv' },
]

const loading = ref(true)
const saving = ref(false)
const version = ref('')
const current = ref(null)
const history = ref([])
const payloadBase = shallowRef(null)
const rowsByBook = ref(Object.fromEntries(BOOKS.map(book => [book.key, []])))
const activeKey = ref('i2i')
const profileFilter = ref('')
const profileRename = ref('')
const search = ref('')
const csvInput = ref(null)
const error = ref('')
const success = ref('')
const dirty = ref(false)
let rowSequence = 0

const activeBook = computed(() => BOOKS.find(book => book.key === activeKey.value) || BOOKS[0])
const currentRows = computed(() => rowsByBook.value[activeKey.value] || [])
const profileOptions = computed(() =>
    [...new Set(currentRows.value.map(row => row.profile).filter(Boolean))]
        .sort((left, right) => left.localeCompare(right))
)
const visibleRows = computed(() => {
    const needle = search.value.trim().toLowerCase()
    return currentRows.value.filter(row => {
        if (profileFilter.value && row.profile !== profileFilter.value) return false
        if (!needle) return true
        const values = row.tone
            ? [row.profile, row.category, row.from, row.to]
            : [row.profile, ...row.fields, row.stop ? '!' : '']
        return values.some(value => String(value || '').toLowerCase().includes(needle))
    })
})

function makeRow(data) {
    return { id: ++rowSequence, ...data }
}

function sameValues(left, right, indexes) {
    return indexes.every(index => left.fields[index] === right.fields[index])
}

function compactSegmentRules(profiles = {}) {
    const source = []
    for (const [profile, rules] of Object.entries(profiles || {})) {
        for (const rule of rules || []) {
            source.push(makeRow({
                tone: false,
                profile,
                fields: Array.from({ length: 6 }, (_, index) => String(rule[index] ?? '')),
                stop: rule[6] === '!',
            }))
        }
    }
    let rows = source
    let changed = true
    while (changed) {
        changed = false
        const next = []
        for (const row of rows) {
            const previous = next[next.length - 1]
            if (!previous || previous.profile !== row.profile || previous.stop !== row.stop ||
                !sameValues(previous, row, [3, 4, 5])) {
                next.push(row)
                continue
            }
            const differences = [0, 1, 2].filter(index => previous.fields[index] !== row.fields[index])
            if (differences.length !== 1 ||
                [0, 1, 2].some(index => index !== differences[0] &&
                    previous.fields[index] !== row.fields[index])) {
                next.push(row)
                continue
            }
            const index = differences[0]
            const values = [...new Set([
                ...previous.fields[index].split('|'),
                ...row.fields[index].split('|'),
            ])]
            previous.fields[index] = values.join('|')
            changed = true
        }
        rows = next
    }
    return rows
}

function flattenToneRules(profiles = {}) {
    const rows = []
    for (const [profile, categories] of Object.entries(profiles || {})) {
        for (const [category, mapping] of Object.entries(categories || {})) {
            for (const [from, to] of Object.entries(mapping || {})) {
                rows.push(makeRow({
                    tone: true,
                    profile,
                    category,
                    from: String(from),
                    to: String(to),
                }))
            }
        }
    }
    return rows
}

function nextVersionName(name) {
    const now = new Date()
    const stamp = [
        now.getFullYear(),
        String(now.getMonth() + 1).padStart(2, '0'),
        String(now.getDate()).padStart(2, '0'),
        '-',
        String(now.getHours()).padStart(2, '0'),
        String(now.getMinutes()).padStart(2, '0'),
    ].join('')
    return `${String(name || 'rules').replace(/-\d{8}(?:-\d{4})?$/, '')}-${stamp}`
}

async function load() {
    loading.value = true
    error.value = ''
    try {
        const response = await adminApi.getCommonRules()
        current.value = response.data.active
        history.value = response.data.history || []
        payloadBase.value = structuredClone(response.data.active.payload)
        rowsByBook.value = {
            i2i: compactSegmentRules(response.data.active.payload.rules?.i2i),
            i2j: compactSegmentRules(response.data.active.payload.rules?.i2j),
            j2i: compactSegmentRules(response.data.active.payload.rules?.j2i),
            j2j: compactSegmentRules(response.data.active.payload.rules?.j2j),
            'tone-j2i': flattenToneRules(response.data.active.payload.tones?.j2i),
            'tone-j2j': flattenToneRules(response.data.active.payload.tones?.j2j),
        }
        version.value = nextVersionName(response.data.active.version)
        profileFilter.value = ''
        profileRename.value = ''
        search.value = ''
        dirty.value = false
    } catch (caught) {
        error.value = caught.response?.data?.error || caught.message || '載入規則書失敗'
    } finally {
        loading.value = false
    }
}

function selectBook(key) {
    activeKey.value = key
    profileFilter.value = ''
    profileRename.value = ''
    search.value = ''
}

function markDirty() {
    dirty.value = true
    success.value = ''
}

function addRow() {
    const profile = profileFilter.value || profileOptions.value[0] || '0'
    const row = activeBook.value.tone
        ? makeRow({ tone: true, profile, category: '舒聲', from: '', to: '' })
        : makeRow({
            tone: false,
            profile,
            fields: ['*', '*', '*', '*', '*', '*'],
            stop: false,
        })
    currentRows.value.push(row)
    markDirty()
}

function duplicateRow(row) {
    const index = currentRows.value.indexOf(row)
    const copy = makeRow(row.tone
        ? {
            tone: true,
            profile: row.profile,
            category: row.category,
            from: row.from,
            to: row.to,
        }
        : {
            tone: false,
            profile: row.profile,
            fields: [...row.fields],
            stop: row.stop,
        })
    currentRows.value.splice(index + 1, 0, copy)
    markDirty()
}

function removeRow(row) {
    const index = currentRows.value.indexOf(row)
    if (index >= 0) currentRows.value.splice(index, 1)
    markDirty()
}

function moveRow(row, direction) {
    const rows = currentRows.value
    const index = rows.indexOf(row)
    const target = index + direction
    if (index < 0 || target < 0 || target >= rows.length) return
    rows.splice(target, 0, rows.splice(index, 1)[0])
    markDirty()
}

function renameProfile() {
    const from = profileFilter.value
    const to = profileRename.value.trim()
    if (!from) {
        error.value = '請先在「規則地點」選擇要改名的地點'
        return
    }
    if (!to) {
        error.value = '請填寫新的規則地點名'
        return
    }
    currentRows.value.forEach(row => {
        if (row.profile === from) row.profile = to
    })
    profileFilter.value = to
    profileRename.value = ''
    error.value = ''
    markDirty()
}

function csvCell(value) {
    const text = String(value ?? '')
    return /[",\r\n]/.test(text) ? `"${text.replaceAll('"', '""')}"` : text
}

function csvTextForCurrentBook() {
    return currentRows.value.map(row => {
        const values = row.tone
            ? [row.profile, row.category, row.from, row.to]
            : [row.profile, ...row.fields, ...(row.stop ? ['!'] : [])]
        return values.map(csvCell).join(',')
    }).join('\r\n') + '\r\n'
}

function downloadBlob(text, type, filename) {
    const blob = new Blob([text], { type })
    const anchor = document.createElement('a')
    anchor.href = URL.createObjectURL(blob)
    anchor.download = filename
    anchor.click()
    URL.revokeObjectURL(anchor.href)
}

function exportCurrentCsv() {
    downloadBlob(csvTextForCurrentBook(), 'text/csv;charset=utf-8', activeBook.value.file)
}

function parseCsv(text) {
    const rows = []
    let row = []
    let cell = ''
    let quoted = false
    for (let index = 0; index < text.length; index += 1) {
        const char = text[index]
        if (quoted) {
            if (char === '"' && text[index + 1] === '"') {
                cell += '"'
                index += 1
            } else if (char === '"') {
                quoted = false
            } else {
                cell += char
            }
        } else if (char === '"') {
            quoted = true
        } else if (char === ',') {
            row.push(cell)
            cell = ''
        } else if (char === '\n') {
            row.push(cell.replace(/\r$/, ''))
            if (row.some(value => value !== '')) rows.push(row)
            row = []
            cell = ''
        } else {
            cell += char
        }
    }
    if (quoted) throw new Error('CSV 有未閉合的引號')
    row.push(cell.replace(/\r$/, ''))
    if (row.some(value => value !== '')) rows.push(row)
    return rows
}

async function importCurrentCsv(event) {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file) return
    error.value = ''
    try {
        const parsed = parseCsv(await file.text())
        const header = String(parsed[0]?.[0] || '').toLowerCase()
        if (['profile', '規則地點', '地点', '地點'].includes(header)) parsed.shift()
        const next = []
        for (const [index, values] of parsed.entries()) {
            if (activeBook.value.tone) {
                if (values.length !== 4) {
                    throw new Error(`CSV 第 ${index + 1} 行應有 4 欄：規則地點、聲類、原調、新調`)
                }
                next.push(makeRow({
                    tone: true,
                    profile: values[0].trim(),
                    category: values[1].trim(),
                    from: values[2].trim(),
                    to: values[3].trim(),
                }))
            } else {
                if (values.length < 7 || values.length > 8 ||
                    (values.length === 8 && values[7].trim() !== '!')) {
                    throw new Error(`CSV 第 ${index + 1} 行應有 7 欄，或以第 8 欄 ! 表示終止`)
                }
                next.push(makeRow({
                    tone: false,
                    profile: values[0].trim(),
                    fields: values.slice(1, 7).map(value => value.trim()),
                    stop: values[7]?.trim() === '!',
                }))
            }
        }
        if (!next.length) throw new Error('CSV 沒有規則行')
        if (!window.confirm(`以 ${next.length} 行取代「${activeBook.value.label}」目前的全部規則？`)) return
        rowsByBook.value[activeKey.value] = next
        profileFilter.value = ''
        search.value = ''
        markDirty()
    } catch (caught) {
        error.value = caught.message || '匯入 CSV 失敗'
    }
}

function splitAlternatives(value) {
    const values = String(value ?? '').split('|').map(item => item.trim())
    return [...new Set(values)]
}

function expandInputs(fields) {
    let combinations = [[]]
    for (const value of fields.slice(0, 3)) {
        combinations = combinations.flatMap(prefix =>
            splitAlternatives(value).map(item => [...prefix, item])
        )
    }
    return combinations.map(input => [...input, ...fields.slice(3)])
}

function toneValue(value) {
    const text = String(value)
    if (/^(0|[1-9]\d*)$/.test(text)) return Number(text)
    return text
}

function buildPayload() {
    const payload = structuredClone(payloadBase.value)
    payload.rules = {}
    for (const key of ['i2i', 'i2j', 'j2i', 'j2j']) {
        const profiles = {}
        for (const [index, row] of rowsByBook.value[key].entries()) {
            const profile = row.profile.trim()
            if (!profile) throw new Error(`${BOOKS.find(book => book.key === key).label} 第 ${index + 1} 行缺少規則地點`)
            if (!profiles[profile]) profiles[profile] = []
            for (const fields of expandInputs(row.fields.map(value => String(value)))) {
                profiles[profile].push([...fields, ...(row.stop ? ['!'] : [])])
            }
        }
        payload.rules[key] = profiles
    }
    payload.tones = {}
    for (const [key, bookKey] of [['j2i', 'tone-j2i'], ['j2j', 'tone-j2j']]) {
        const profiles = {}
        const seen = new Set()
        for (const [index, row] of rowsByBook.value[bookKey].entries()) {
            const profile = row.profile.trim()
            const category = row.category.trim()
            const from = row.from.trim()
            const to = row.to.trim()
            if (!profile || !category || !from) {
                throw new Error(`${BOOKS.find(book => book.key === bookKey).label} 第 ${index + 1} 行有空白必填欄`)
            }
            const identity = JSON.stringify([profile, category, from])
            if (seen.has(identity)) {
                throw new Error(`${profile}／${category} 的原調 ${from} 重複`)
            }
            seen.add(identity)
            if (!profiles[profile]) profiles[profile] = {}
            if (!profiles[profile][category]) profiles[profile][category] = {}
            profiles[profile][category][from] = toneValue(to)
        }
        payload.tones[key] = profiles
    }
    payload.bundleVersion = version.value.trim()
    return payload
}

async function save() {
    saving.value = true
    error.value = ''
    success.value = ''
    try {
        const cleanVersion = version.value.trim()
        if (!/^[A-Za-z0-9._-]+$/.test(cleanVersion)) {
            throw new Error('版本號只可使用英文字母、數字、點、底線和連字號')
        }
        const response = await adminApi.saveCommonRules(cleanVersion, buildPayload())
        success.value = `規則版本 ${response.data.active.version} 已建立並啟用；已有字表不會自動改變。`
        await load()
    } catch (caught) {
        error.value = caught.response?.data?.error || caught.message || '儲存規則書失敗'
    } finally {
        saving.value = false
    }
}

function exportFullJson() {
    try {
        const payload = buildPayload()
        downloadBlob(
            JSON.stringify(payload, null, 2),
            'application/json;charset=utf-8',
            `${current.value?.version || 'common-rules'}.json`
        )
    } catch (caught) {
        error.value = caught.message
    }
}

async function discardChanges() {
    if (dirty.value && !window.confirm('放棄尚未建立版本的全部修改？')) return
    await load()
}

onMounted(load)
</script>

<template>
    <section class="space-y-4">
        <div class="border-l-4 border-accent bg-white/80 p-4 dark:bg-slate-800/80">
            <h2 class="font-bold">轉寫規則書</h2>
            <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                六份規則書分開編輯；建立版本時才會合併驗證。新版本只影響其後解析的 Excel，不會自動重算已有字表。
            </p>
        </div>

        <p v-if="error" class="border-l-4 border-red-500 bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-300">{{ error }}</p>
        <p v-if="success" class="border-l-4 border-emerald-500 bg-emerald-50 p-3 text-sm text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300">{{ success }}</p>

        <template v-if="!loading">
            <form class="flex flex-col gap-2 border border-slate-200 bg-white/80 p-3 dark:border-slate-700 dark:bg-slate-800/80 sm:flex-row sm:items-center" @submit.prevent="save">
                <label class="flex min-w-0 flex-1 items-center gap-2 text-xs text-slate-500">
                    新版本號
                    <input v-model.trim="version" required pattern="[A-Za-z0-9._-]+"
                        class="min-w-0 flex-1 border-2 border-slate-200 p-2 font-mono text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100" />
                </label>
                <span v-if="dirty" class="text-xs font-bold text-amber-600">有未儲存修改</span>
                <button type="button" class="border px-3 py-2 text-xs hover:border-accent hover:text-accent" @click="exportFullJson">匯出整包 JSON</button>
                <button type="button" class="border px-3 py-2 text-xs hover:border-accent hover:text-accent" @click="discardChanges">放棄修改</button>
                <button :disabled="saving || !dirty" class="bg-accent px-4 py-2 text-sm font-bold text-white disabled:opacity-40">
                    {{ saving ? '建立中…' : '建立並啟用' }}
                </button>
            </form>

            <div class="overflow-x-auto border-b-2 border-slate-200 dark:border-slate-700">
                <div class="flex min-w-max gap-1">
                    <button v-for="book in BOOKS" :key="book.key" type="button"
                        class="border-x border-t px-4 py-2 text-xs font-bold"
                        :class="activeKey === book.key
                            ? 'border-accent bg-accent text-white'
                            : 'border-slate-200 bg-white text-slate-600 hover:text-accent dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'"
                        @click="selectBook(book.key)">
                        {{ book.label }}
                        <span class="ml-1 opacity-70">{{ rowsByBook[book.key].length }}</span>
                    </button>
                </div>
            </div>

            <div class="space-y-3 border border-slate-200 bg-white/80 p-3 dark:border-slate-700 dark:bg-slate-800/80">
                <div class="grid grid-cols-1 gap-2 lg:grid-cols-[minmax(12rem,1fr)_13rem_minmax(12rem,1fr)_auto]">
                    <input v-model="search" placeholder="搜尋地點或規則內容…"
                        class="border-2 border-slate-200 p-2 text-sm dark:border-slate-700 dark:bg-slate-900" />
                    <select v-model="profileFilter"
                        class="border-2 border-slate-200 p-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                        <option value="">全部規則地點</option>
                        <option v-for="profile in profileOptions" :key="profile" :value="profile">{{ profile }}</option>
                    </select>
                    <div class="flex">
                        <input v-model="profileRename" :disabled="!profileFilter" placeholder="批量改成新地點名"
                            class="min-w-0 flex-1 border-2 border-r-0 border-slate-200 p-2 text-sm disabled:opacity-40 dark:border-slate-700 dark:bg-slate-900" />
                        <button type="button" :disabled="!profileFilter" class="border-2 border-slate-200 px-3 text-xs disabled:opacity-40 dark:border-slate-700" @click="renameProfile">改名</button>
                    </div>
                    <button type="button" class="bg-accent px-4 py-2 text-sm font-bold text-white" @click="addRow">新增一行</button>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <button type="button" class="border px-3 py-1.5 hover:border-accent hover:text-accent" @click="exportCurrentCsv">
                        匯出本書 CSV
                    </button>
                    <button type="button" class="border px-3 py-1.5 hover:border-accent hover:text-accent" @click="csvInput?.click()">
                        匯入 CSV 並取代本書
                    </button>
                    <input ref="csvInput" type="file" accept=".csv,text/csv" class="hidden" @change="importCurrentCsv" />
                    <span v-if="!activeBook.tone" class="text-slate-500 dark:text-slate-400">
                        輸入三欄可用 <code>|</code> 合併同類值；<code>*</code> 表示任意值，空欄表示零聲母／零韻尾。規則按由上至下的次序套用。
                    </span>
                    <span v-else class="text-slate-500 dark:text-slate-400">
                        聲類通常為「舒聲」或「入聲」；原調在同一地點、同一聲類內不可重複，新調留空表示刪除聲調。
                    </span>
                </div>

                <div class="max-h-[62vh] overflow-auto border border-slate-200 dark:border-slate-700">
                    <table v-if="!activeBook.tone" class="w-full min-w-[1120px] border-collapse text-xs">
                        <thead class="sticky top-0 z-10 bg-slate-100 text-left dark:bg-slate-900">
                            <tr>
                                <th class="w-12 p-2">#</th>
                                <th class="min-w-32 p-2">規則地點</th>
                                <th class="p-2">{{ activeBook.input }} 聲母</th>
                                <th class="p-2">{{ activeBook.input }} 韻核</th>
                                <th class="p-2">{{ activeBook.input }} 韻尾</th>
                                <th class="p-2">{{ activeBook.output }} 聲母</th>
                                <th class="p-2">{{ activeBook.output }} 韻核</th>
                                <th class="p-2">{{ activeBook.output }} 韻尾</th>
                                <th class="w-14 p-2 text-center">終止</th>
                                <th class="w-44 p-2">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, visibleIndex) in visibleRows" :key="row.id"
                                class="border-t border-slate-200 dark:border-slate-700">
                                <td class="p-1 text-center font-mono text-slate-400">{{ currentRows.indexOf(row) + 1 }}</td>
                                <td class="p-1">
                                    <input v-model="row.profile" :aria-label="`第 ${visibleIndex + 1} 行規則地點`"
                                        class="w-full min-w-32 border p-1.5 dark:bg-slate-900" @input="markDirty" />
                                </td>
                                <td v-for="index in 6" :key="index" class="p-1">
                                    <input v-model="row.fields[index - 1]" :aria-label="`第 ${visibleIndex + 1} 行第 ${index} 欄`"
                                        class="w-full min-w-24 border p-1.5 font-mono dark:bg-slate-900" @input="markDirty" />
                                </td>
                                <td class="p-1 text-center">
                                    <input v-model="row.stop" type="checkbox" :aria-label="`第 ${visibleIndex + 1} 行終止`" @change="markDirty" />
                                </td>
                                <td class="whitespace-nowrap p-1">
                                    <button type="button" class="border px-2 py-1" title="上移" @click="moveRow(row, -1)">↑</button>
                                    <button type="button" class="ml-1 border px-2 py-1" title="下移" @click="moveRow(row, 1)">↓</button>
                                    <button type="button" class="ml-1 border px-2 py-1" @click="duplicateRow(row)">複製</button>
                                    <button type="button" class="ml-1 border border-red-300 px-2 py-1 text-red-600" @click="removeRow(row)">刪除</button>
                                </td>
                            </tr>
                            <tr v-if="!visibleRows.length"><td colspan="10" class="p-10 text-center text-slate-400">沒有符合條件的規則</td></tr>
                        </tbody>
                    </table>

                    <table v-else class="w-full min-w-[760px] border-collapse text-xs">
                        <thead class="sticky top-0 z-10 bg-slate-100 text-left dark:bg-slate-900">
                            <tr>
                                <th class="w-12 p-2">#</th>
                                <th class="p-2">規則地點</th>
                                <th class="p-2">聲類</th>
                                <th class="p-2">原調</th>
                                <th class="p-2">新調</th>
                                <th class="w-44 p-2">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, visibleIndex) in visibleRows" :key="row.id"
                                class="border-t border-slate-200 dark:border-slate-700">
                                <td class="p-1 text-center font-mono text-slate-400">{{ currentRows.indexOf(row) + 1 }}</td>
                                <td class="p-1">
                                    <input v-model="row.profile" :aria-label="`第 ${visibleIndex + 1} 行規則地點`"
                                        class="w-full border p-1.5 dark:bg-slate-900" @input="markDirty" />
                                </td>
                                <td class="p-1">
                                    <select v-model="row.category" :aria-label="`第 ${visibleIndex + 1} 行聲類`"
                                        class="w-full border p-1.5 dark:bg-slate-900" @change="markDirty">
                                        <option value="舒聲">舒聲</option>
                                        <option value="入聲">入聲</option>
                                    </select>
                                </td>
                                <td class="p-1"><input v-model="row.from" :aria-label="`第 ${visibleIndex + 1} 行原調`" class="w-full border p-1.5 font-mono dark:bg-slate-900" @input="markDirty" /></td>
                                <td class="p-1"><input v-model="row.to" :aria-label="`第 ${visibleIndex + 1} 行新調`" class="w-full border p-1.5 font-mono dark:bg-slate-900" @input="markDirty" /></td>
                                <td class="whitespace-nowrap p-1">
                                    <button type="button" class="border px-2 py-1" title="上移" @click="moveRow(row, -1)">↑</button>
                                    <button type="button" class="ml-1 border px-2 py-1" title="下移" @click="moveRow(row, 1)">↓</button>
                                    <button type="button" class="ml-1 border px-2 py-1" @click="duplicateRow(row)">複製</button>
                                    <button type="button" class="ml-1 border border-red-300 px-2 py-1 text-red-600" @click="removeRow(row)">刪除</button>
                                </td>
                            </tr>
                            <tr v-if="!visibleRows.length"><td colspan="6" class="p-10 text-center text-slate-400">沒有符合條件的規則</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <details class="border border-slate-200 bg-white/80 p-3 text-xs dark:border-slate-700 dark:bg-slate-800/80">
                <summary class="cursor-pointer font-bold">版本記錄（目前：{{ current?.version }}）</summary>
                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="item in history" :key="item.id" class="border-l-2 border-slate-200 pl-2 dark:border-slate-700">
                        <div class="font-mono font-bold">{{ item.version }}</div>
                        <div class="text-slate-400">{{ item.created_at }}</div>
                        <span v-if="item.is_active" class="text-emerald-600">目前啟用</span>
                    </div>
                </div>
            </details>
        </template>
    </section>
</template>
