<script setup>
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import sheetApi from '@/api/sheet.js'
import { sheetEntryPath, sheetEntryLabel, sheetDirectoryPath } from '@/utils/sheetEntry.js'
import { updateCurrentSeo } from '@/utils/seo.js'
const route = useRoute()
const directory = ref(null)
const error = ref('')
let sequence = 0
watch(() => route.fullPath, async () => {
  const value = route.params.page
  const request = ++sequence; const path = route.path
  directory.value = null; error.value = ''
  try {
    const response = await sheetApi.getEntries(value || 1)
    if (request !== sequence) return
    directory.value = response.data
    const contentKeys = Object.keys(route.query).filter(k => !k.startsWith('utm_') && !['gclid', 'fbclid'].includes(k))
    updateCurrentSeo({ noindex: contentKeys.length > 0 }, path)
  } catch {
    if (request === sequence) error.value = '找不到此目錄頁，或服務暫時未能使用。'
  }
}, { immediate: true })
</script>

<template>
  <main class="w-full max-w-5xl mx-auto px-4 py-8 space-y-6">
    <h1 class="text-3xl font-bold border-l-4 border-accent pl-4">泛粵詞條目錄<span v-if="directory"> · 第 {{ directory.page }} 頁</span></h1>
    <p v-if="error" role="alert">{{ error }}</p>
    <p v-else-if="!directory" role="status">載入目錄中…</p>
    <template v-else>
      <ul class="space-y-3">
        <li v-for="entry in directory.entries" :key="entry['鍵']" class="border-l-4 border-accent p-4 bg-white dark:bg-slate-800 shadow-[4px_4px_0_rgba(0,0,0,0.06)]">
          <RouterLink :to="sheetEntryPath(entry['鍵'])" class="font-bold text-accent underline">{{ sheetEntryLabel(entry) }}〔{{ entry['綜'] }}〕</RouterLink>
          <p class="mt-1">{{ entry['釋'] }}</p>
        </li>
      </ul>
      <nav class="flex flex-wrap gap-4 items-center" aria-label="詞條目錄分頁">
        <RouterLink v-if="directory.page > 1" :to="sheetDirectoryPath(directory.page - 1)" class="text-accent underline">上一頁</RouterLink>
        <span>{{ directory.page }} / {{ directory.pages }} · {{ directory.total }} 條記錄</span>
        <RouterLink v-if="directory.page < directory.pages" :to="sheetDirectoryPath(directory.page + 1)" class="text-accent underline">下一頁</RouterLink>
      </nav>
    </template>
  </main>
</template>
