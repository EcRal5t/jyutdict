<script setup>
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import sheetApi from '@/api/sheet.js'
import ResultCard from '@/components/ResultCard.vue'
import { sheetEntryLabel, sheetEntryMeta } from '@/utils/sheetEntry.js'
import { updateCurrentSeo } from '@/utils/seo.js'
import seoCatalog from 'virtual:jyutdict-seo'

const route = useRoute()
const row = ref(null)
const headers = ref({ cities: [], foreign: [], all: [] })
const loading = ref(false)
const error = ref('')
let sequence = 0
watch(() => route.fullPath, async () => {
  const key = route.params.key
  const request = ++sequence
  const path = route.path
  row.value = null; error.value = ''; loading.value = true
  try {
    const [entry, meta] = await Promise.all([sheetApi.getByKey(`>${key}`), sheetApi.getMeta()])
    if (request !== sequence) return
    if (!Array.isArray(entry.data) || !entry.data.length) {
      error.value = '找不到此詞條。'
      updateCurrentSeo({ title: `找不到詞條｜${seoCatalog.name}`, noindex: true }, path)
      return
    }
    row.value = entry.data[0]
    const columns = meta.data.columns || []
    headers.value = { all: columns, cities: columns.filter(c => c.kind === 1), foreign: columns.filter(c => c.kind === 2) }
    const contentKeys = Object.keys(route.query).filter(k => !k.startsWith('utm_') && !['gclid', 'fbclid'].includes(k))
    const entryMeta = sheetEntryMeta(row.value, seoCatalog.name)
    updateCurrentSeo({ ...entryMeta, noindex: entryMeta.noindex || contentKeys.length > 0 }, path)
  } catch {
    if (request === sequence) error.value = '詞條暫時未能載入，請稍後再試。'
  } finally {
    if (request === sequence) loading.value = false
  }
}, { immediate: true })
</script>

<template>
  <main class="w-full max-w-6xl mx-auto px-4 py-8 space-y-6">
    <nav class="text-sm flex flex-wrap gap-4" aria-label="詞條導覽">
      <RouterLink class="text-accent underline" to="/sheet">泛粵字表檢索</RouterLink>
    </nav>
    <h1 class="text-3xl font-bold border-l-4 border-accent pl-4">{{ row ? sheetEntryLabel(row) : '泛粵字表詞條' }}</h1>
    <p v-if="loading" role="status">載入詞條中…</p>
    <p v-else-if="error" role="alert">{{ error }}</p>
    <template v-else-if="row">
      <ResultCard :row-data="row" :header-info="headers" />
      <RouterLink v-if="/^[\p{Script=Han}〇]$/u.test(row['繁'] || '')" :to="{ path: '/detail', query: { chara: row['繁'] } }" class="inline-block text-accent underline">查閱「{{ row['繁'] }}」的方音與韻書對照</RouterLink>
      <section v-if="row['釋']" class="border-l-4 border-accent bg-white dark:bg-slate-800 p-4 shadow-[4px_4px_0_rgba(0,0,0,0.06)]">
        <h2 class="font-bold mb-2">反查關鍵詞</h2>
        <p>{{ row['釋'] }}</p>
      </section>
    </template>
  </main>
</template>
