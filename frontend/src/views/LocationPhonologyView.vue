<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import phonologyApi from '@/api/phonology.js'
import { splitJpp } from '@/utils/commonConverter.js'
import { normaliseCheckedFinal, phonologyColourFor } from '@/utils/phonologyDisplay.js'

const route = useRoute()
const router = useRouter()
const locations = ref([])
const locationsLoading = ref(true)
const reportLoading = ref(false)
const report = ref(null)
const selectedId = ref(null)
const activeSectionId = ref('initials')
const search = ref('')
const tableFilter = ref('')
const error = ref('')

const areaName = area => area.name || [area.second, area.third].filter(Boolean).join('') || area.first || ''
const displayedLocations = computed(() => {
    const needle = search.value.trim().toLowerCase()
    return locations.value.filter(area =>
        !needle || [areaName(area), area.detailed_name].some(value =>
            String(value || '').toLowerCase().includes(needle)
        )
    )
})
const selectedLocation = computed(() =>
    locations.value.find(area => Number(area.id) === Number(selectedId.value)) || null
)
const sections = computed(() => report.value?.sections || [])
const activeSection = computed(() =>
    sections.value.find(section => section.id === activeSectionId.value) || sections.value[0] || null
)
const conditionDepth = computed(() =>
    Math.max(1, ...(activeSection.value?.rules || []).map(rule => rule.conditions.length))
)
const colourCache = new Map()
const colourFor = value => {
    const key = String(value || '')
    if (!colourCache.has(key)) colourCache.set(key, phonologyColourFor(key))
    return colourCache.get(key)
}
const outcomeTotal = outcome => Number(outcome.charCount || 0) + Number(outcome.checkedCharCount || 0)
const positionParts = rule => [
    rule.base,
    ...Array.from({ length: conditionDepth.value }, (_, index) => rule.conditions[index] || null),
]
const flattenedRows = computed(() => {
    const needle = tableFilter.value.trim().toLocaleLowerCase()
    const rows = []
    for (const [ruleIndex, rule] of (activeSection.value?.rules || []).entries()) {
        const total = rule.outcomes.reduce((sum, outcome) => sum + outcomeTotal(outcome), 0)
        const modeCount = Math.max(0, ...rule.outcomes.map(outcomeTotal))
        for (const [outcomeIndex, outcome] of rule.outcomes.entries()) {
            const searchText = [
                rule.base,
                ...rule.conditions.flat(),
                outcome.value,
                ...outcome.examples.flatMap(example => [
                    example.char, example.note || '', ...example.pronunciations,
                ]),
            ].join(' ').toLocaleLowerCase()
            if (needle && !searchText.includes(needle)) continue
            const share = total ? outcomeTotal(outcome) / total : 0
            const relativeToMode = modeCount ? outcomeTotal(outcome) / modeCount : 0
            const prominence = share >= 0.4 ? 1 : 0.35 + 0.65 * share / 0.4
            rows.push({
                key: `${ruleIndex}-${outcomeIndex}`,
                rule,
                outcome,
                parts: positionParts(rule),
                share,
                relativeToMode,
                prominence,
                baseCell: null,
                conditionCells: [],
            })
        }
    }
    for (let column = 0; column <= conditionDepth.value; column += 1) {
        let index = 0
        while (index < rows.length) {
            const key = JSON.stringify(rows[index].parts.slice(0, column + 1))
            let end = index + 1
            while (end < rows.length &&
                JSON.stringify(rows[end].parts.slice(0, column + 1)) === key) end += 1
            const cell = {
                show: true,
                rowspan: end - index,
                value: rows[index].parts[column],
            }
            if (column === 0) rows[index].baseCell = cell
            else rows[index].conditionCells[column - 1] = cell
            for (let hidden = index + 1; hidden < end; hidden += 1) {
                const merged = { show: false, rowspan: 0, value: rows[hidden].parts[column] }
                if (column === 0) rows[hidden].baseCell = merged
                else rows[hidden].conditionCells[column - 1] = merged
            }
            index = end
        }
    }
    return rows
})

function outcomeCellStyle(row) {
    const colour = colourFor(row.outcome.value)
    return {
        '--outcome-accent': colour.accent,
        '--outcome-accent-dark': colour.accentDark,
        '--outcome-surface': colour.surface,
        '--outcome-surface-dark': colour.surfaceDark,
        '--outcome-stripe': colour.stripe,
        '--outcome-stripe-dark': colour.stripeDark,
        '--outcome-content-opacity': (0.55 + row.prominence * 0.45).toFixed(3),
        '--outcome-bar-opacity': (0.72 + row.prominence * 0.28).toFixed(3),
        '--outcome-relative': row.relativeToMode,
    }
}

function exampleFinals(example) {
    return [...new Set(example.pronunciations.map(raw => {
        const parts = splitJpp(raw)
        return parts.nuclei ? normaliseCheckedFinal(`${parts.nuclei}${parts.coda}`) : ''
    }).filter(Boolean))].sort()
}

function exampleColourStyle(example) {
    if (activeSectionId.value !== 'reverse-finals') return {}
    const colours = exampleFinals(example).map(final => colourFor(final))
    if (!colours.length) return {}
    const segmented = property => {
        if (colours.length === 1) return colours[0][property]
        const width = 100 / colours.length
        const stops = colours.flatMap((colour, index) => [
            `${colour[property]} ${(index * width).toFixed(2)}%`,
            `${colour[property]} ${((index + 1) * width).toFixed(2)}%`,
        ])
        return `linear-gradient(90deg, ${stops.join(', ')})`
    }
    return {
        '--example-accent': segmented('accent'),
        '--example-accent-dark': segmented('accentDark'),
        '--example-surface': segmented('surface'),
        '--example-surface-dark': segmented('surfaceDark'),
    }
}

function exampleTitle(example) {
    const finals = activeSectionId.value === 'reverse-finals'
        ? `；現代韻母：${exampleFinals(example).join('/')}`
        : ''
    return `${example.pronunciations.join('、')}${finals}`
}

async function loadReport(areaId, updateRoute = false) {
    const area = locations.value.find(item => Number(item.id) === Number(areaId))
    if (!area) return
    selectedId.value = Number(area.id)
    report.value = null
    error.value = ''
    tableFilter.value = ''
    if (updateRoute) {
        await router.replace({ name: 'location-phonology', params: { areaId: area.id } })
    }
    if (!area.has_phonology) return
    reportLoading.value = true
    try {
        const response = await phonologyApi.getReport(area.id)
        report.value = typeof response.data === 'string' ? JSON.parse(response.data) : response.data
        activeSectionId.value = report.value.sections?.[0]?.id || 'initials'
    } catch (caught) {
        error.value = caught.response?.data?.error || caught.message || '音系表載入失敗'
    } finally {
        reportLoading.value = false
    }
}

async function loadLocations() {
    locationsLoading.value = true
    try {
        const response = await phonologyApi.getLocations()
        locations.value = response.data.locations || []
        const routeId = Number(route.params.areaId || 0)
        const initial = locations.value.find(area => area.id === routeId) ||
            locations.value.find(area => area.has_phonology) ||
            locations.value[0]
        if (initial) await loadReport(initial.id, !routeId)
    } catch (caught) {
        error.value = caught.response?.data?.error || caught.message || '地點目錄載入失敗'
    } finally {
        locationsLoading.value = false
    }
}

function activateSection(sectionId) {
    activeSectionId.value = sectionId
    tableFilter.value = ''
}

function onTabKeydown(event, index) {
    if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return
    event.preventDefault()
    const offset = event.key === 'ArrowRight' ? 1 : -1
    const target = sections.value[(index + offset + sections.value.length) % sections.value.length]
    activateSection(target.id)
    requestAnimationFrame(() => document.getElementById(`location-phonology-tab-${target.id}`)?.focus())
}

watch(() => route.params.areaId, areaId => {
    if (locations.value.length && Number(areaId) !== Number(selectedId.value)) loadReport(Number(areaId))
})

onMounted(loadLocations)
</script>

<template>
    <div class="container mx-auto max-w-7xl px-4 py-6">
        <h1 class="mb-5 border-l-4 border-accent pl-3 text-xl font-bold text-slate-800 dark:text-slate-100">音系</h1>
        <p v-if="error" class="mb-4 border-l-4 border-red-500 bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-300">{{ error }}</p>

        <div class="mb-4 lg:hidden">
            <select :value="selectedId" class="w-full border-2 border-slate-300 bg-white p-2 text-sm dark:border-slate-600 dark:bg-slate-800"
                @change="loadReport(Number($event.target.value), true)">
                <option v-for="area in locations" :key="area.id" :value="area.id">
                    {{ areaName(area) }}{{ area.has_phonology ? '' : '（尚無音系表）' }}
                </option>
            </select>
        </div>

        <div class="flex flex-col gap-5 lg:flex-row">
            <aside class="hidden w-64 flex-shrink-0 lg:block">
                <input v-model="search" placeholder="搜尋地點…"
                    class="mb-3 w-full border-2 border-slate-300 bg-white p-2 text-sm outline-none focus:border-accent dark:border-slate-600 dark:bg-slate-800" />
                <div v-if="locationsLoading" class="py-8 text-center text-slate-400">載入中…</div>
                <div v-else class="max-h-[72vh] space-y-0.5 overflow-y-auto overflow-x-hidden pr-1">
                    <button v-for="area in displayedLocations" :key="area.id"
                        class="w-full border-l-4 p-2 text-left transition-all hover:translate-x-1"
                        :class="Number(selectedId) === Number(area.id)
                            ? 'border-accent bg-accent/5 dark:bg-accent/10'
                            : 'border-transparent hover:border-slate-300 hover:bg-slate-50 dark:hover:border-slate-600 dark:hover:bg-slate-800'"
                        @click="loadReport(area.id, true)">
                        <span class="mr-2 inline-block h-2.5 w-2.5" :style="{ background: area.color }"></span>
                        <span class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ areaName(area) }}</span>
                        <span v-if="!area.has_phonology" class="mt-0.5 block pl-5 text-[11px] text-slate-400">尚無音系表</span>
                    </button>
                </div>
            </aside>

            <main class="min-w-0 flex-1">
                <div v-if="!selectedLocation" class="border-2 border-dashed border-slate-200 py-16 text-center text-slate-400 dark:border-slate-700">請選擇地點</div>
                <div v-else-if="reportLoading" class="py-16 text-center">
                    <span class="inline-block h-7 w-7 animate-spin rounded-full border-2 border-slate-200 border-t-accent"></span>
                </div>
                <div v-else-if="!selectedLocation.has_phonology || !report"
                    class="border-2 border-dashed border-slate-200 py-16 text-center text-slate-400 dark:border-slate-700">
                    {{ areaName(selectedLocation) }}尚未生成音系表
                </div>
                <template v-else>
                    <div class="mb-4 border border-slate-200 bg-white p-4 shadow-[5px_5px_0_rgba(0,0,0,0.06)] dark:border-slate-700 dark:bg-slate-800 dark:shadow-[5px_5px_0_rgba(0,0,0,0.3)]">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-slate-100">{{ report.locationName || areaName(selectedLocation) }}</h2>
                        <div class="mt-4 text-sm">本表爲程序生成，音之分合均爲程序依條件熵所定</div>
                        <div class="mt-4 flex flex-wrap gap-1" role="tablist" aria-label="選擇音系對照表">
                            <button v-for="(section, index) in sections" :id="`location-phonology-tab-${section.id}`" :key="section.id"
                                role="tab" :aria-selected="activeSectionId === section.id"
                                class="border-2 px-3 py-2 text-sm font-bold transition-colors"
                                :class="activeSectionId === section.id
                                    ? 'border-accent bg-accent text-white'
                                    : 'border-slate-200 text-slate-600 hover:border-accent hover:text-accent dark:border-slate-600 dark:text-slate-300'"
                                @click="activateSection(section.id)" @keydown="onTabKeydown($event, index)">
                                {{ section.label }}
                            </button>
                        </div>
                    </div>

                    <section v-if="activeSection" :aria-labelledby="`location-phonology-tab-${activeSection.id}`"
                        class="border border-slate-200 bg-white shadow-[6px_6px_0_rgba(0,0,0,0.06)] dark:border-slate-700 dark:bg-slate-800 dark:shadow-[6px_6px_0_rgba(0,0,0,0.3)]">
                        <div class="flex flex-col gap-3 border-b border-slate-200 p-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700">
                            <strong class="text-sm">{{ activeSection.label }}</strong>
                            <input v-model="tableFilter" type="search" placeholder="篩選中古音、現音、條件或例字"
                                class="w-full border-2 border-slate-200 p-2 text-sm outline-none focus:border-accent sm:w-80 dark:border-slate-600 dark:bg-slate-900" />
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[760px] border-collapse text-sm">
                                <thead class="bg-slate-900 text-left text-xs text-white dark:bg-slate-950">
                                    <tr><th class="p-2">{{ activeSection.baseLabel }}</th><th v-for="index in conditionDepth" :key="index" class="p-2">條件 {{ index }}</th><th class="p-2">{{ activeSection.outcomeLabel }}</th><th class="p-2">轄字</th><th class="p-2">例字</th></tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in flattenedRows" :key="row.key"
                                        class="border-b border-slate-200 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-700/40">
                                        <th v-if="row.baseCell?.show" :rowspan="row.baseCell.rowspan"
                                            class="border-r border-slate-200 bg-slate-100 px-2 py-1 text-center text-base align-middle dark:border-slate-700 dark:bg-slate-900/60">
                                            {{ row.rule.base }}
                                        </th>
                                        <template v-for="(cell, conditionIndex) in row.conditionCells" :key="conditionIndex">
                                            <td v-if="cell?.show" :rowspan="cell.rowspan"
                                                class="border-r border-slate-200 px-2 py-1 text-center align-middle dark:border-slate-700">
                                                <template v-if="cell.value">
                                                    <span class="mr-1 text-[10px] text-slate-400">{{ cell.value[0] }}</span>
                                                    <strong>{{ cell.value[1] }}</strong>
                                                </template>
                                                <span v-else class="text-slate-300">—</span>
                                            </td>
                                        </template>
                                        <td class="phonology-outcome-cell" :style="outcomeCellStyle(row)"
                                            :title="`相對主要讀音 ${Math.round(row.relativeToMode * 100)}%；同條件佔比 ${Math.round(row.share * 100)}%`">
                                            <span aria-hidden="true" class="phonology-outcome-bar"
                                                :class="{ 'phonology-outcome-bar--she': row.outcome.level === 'she' }"></span>
                                            <span class="phonology-outcome-content">
                                                <strong>{{ row.outcome.value || '∅' }}</strong>
                                                <span v-if="row.outcome.level === 'she'" class="phonology-she-badge">攝</span>
                                            </span>
                                        </td>
                                        <td class="px-2 py-1 tabular-nums text-slate-500">
                                            <span :style="{ opacity: 0.72 + row.prominence * 0.28 }">
                                                {{ row.outcome.charCount }}<template v-if="row.outcome.checkedCharCount != null">+{{ row.outcome.checkedCharCount }}</template>
                                            </span>
                                        </td>
                                        <td class="px-2 py-0.5">
                                            <span class="phonology-examples" :style="{ opacity: 0.72 + row.prominence * 0.28 }">
                                                <span v-for="example in row.outcome.examples" :key="example.char"
                                                    class="phonology-example"
                                                    :class="{ 'phonology-example--coloured': activeSectionId === 'reverse-finals' }"
                                                    :style="exampleColourStyle(example)"
                                                    :title="exampleTitle(example)">
                                                    <span class="text-base">{{ example.char }}</span>
                                                    <span v-if="example.note" class="phonology-example-note">({{ example.note }})</span>
                                                </span>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="flattenedRows.length === 0"><td :colspan="conditionDepth + 4" class="p-10 text-center text-slate-400">沒有符合條件的行</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </template>
            </main>
        </div>
    </div>
</template>

<style scoped>
.phonology-outcome-cell {
    position: relative;
    width: 8.5rem;
    min-width: 8.5rem;
    padding: 0;
    overflow: hidden;
    background: white;
    color: rgb(30 41 59);
    white-space: nowrap;
}

.phonology-outcome-bar {
    position: absolute;
    inset-block: 0;
    right: 0;
    width: max(4rem, calc(var(--outcome-relative) * 100%));
    border-left: 4px solid var(--outcome-accent);
    background: var(--outcome-surface);
    opacity: var(--outcome-bar-opacity);
}

.phonology-outcome-bar--she {
    background:
        repeating-linear-gradient(
            -45deg,
            transparent 0 6px,
            var(--outcome-stripe) 6px 10px
        ),
        var(--outcome-surface);
}

.phonology-outcome-content {
    position: relative;
    z-index: 1;
    display: flex;
    min-height: 2rem;
    align-items: center;
    justify-content: flex-end;
    gap: 0.25rem;
    padding: 0.3rem 0.75rem;
    opacity: var(--outcome-content-opacity);
}

.phonology-she-badge {
    border: 1px solid var(--outcome-accent);
    padding: 0 0.2rem;
    color: rgb(71 85 105);
    font-size: 9px;
    line-height: 1.3;
}

.phonology-examples {
    position: relative;
    z-index: 1;
}

.phonology-example {
    display: inline-flex;
    align-items: baseline;
    margin-right: 0.35rem;
    padding-inline: 0.05rem;
    line-height: 1.35;
}

.phonology-example--coloured {
    position: relative;
    min-height: 1.4rem;
    margin-right: 0.25rem;
    margin-block: 0.0625rem;
    border: 1px solid rgb(226 232 240);
    padding: 0 0.32rem 0.12rem;
    overflow: hidden;
    background: var(--example-surface, rgb(248 250 252));
    color: rgb(30 41 59);
}

.phonology-example--coloured::after {
    position: absolute;
    right: 0;
    bottom: 0;
    left: 0;
    height: 2px;
    background: var(--example-accent, rgb(148 163 184));
    content: "";
}

.phonology-example-note {
    margin-left: 0.125rem;
    color: rgb(100 116 139);
    font-size: 10px;
}

:global(.dark .phonology-outcome-cell) {
    background: rgb(30 41 59);
    color: rgb(241 245 249);
}

:global(.dark .phonology-outcome-bar) {
    border-left-color: var(--outcome-accent-dark);
    background: var(--outcome-surface-dark);
}

:global(.dark .phonology-outcome-bar--she) {
    background:
        repeating-linear-gradient(
            -45deg,
            transparent 0 6px,
            var(--outcome-stripe-dark) 6px 10px
        ),
        var(--outcome-surface-dark);
}

:global(.dark .phonology-she-badge) {
    border-color: var(--outcome-accent-dark);
    color: rgb(203 213 225);
}

:global(.dark .phonology-example--coloured) {
    border-color: rgb(71 85 105);
    background: var(--example-surface-dark, rgb(30 41 59));
    color: rgb(241 245 249);
}

:global(.dark .phonology-example--coloured::after) {
    background: var(--example-accent-dark, rgb(148 163 184));
}

:global(.dark .phonology-example-note) {
    color: rgb(203 213 225);
}
</style>
