<script setup>
import { ref, onMounted, reactive, watch, nextTick } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import SheetApi from '@/api/sheet.js';
import ResultCard from '@/components/ResultCard.vue';
import { darkenColor } from '@/utils/formatters.js';

const router = useRouter();
const route = useRoute();

const query = ref('');
const location = ref('');
const isFuzzy = ref(true);
const isTrim = ref(true);
const isRegex = ref(false);
const isDef = ref(false);

const results = ref([]);
const headerInfo = reactive({ cities: [], foreign: [], all: [] });
const isLoading = ref(false);
const error = ref(null);

const locations = ref([]);

// Initialize state from URL
const initFromUrl = () => {
    const q = route.query;
    if (q.q !== undefined) query.value = q.q;
    if (q.col !== undefined) location.value = q.col;
    if (q.fuzzy !== undefined) isFuzzy.value = q.fuzzy === '1';
    if (q.trim !== undefined) isTrim.value = q.trim === '1';
    if (q.regex !== undefined) isRegex.value = q.regex === '1';
    if (q.def !== undefined) isDef.value = q.def === '1';
};

// Sync state to URL
const syncToUrl = () => {
    router.replace({
        query: {
            q: query.value,
            col: location.value || undefined,
            fuzzy: isFuzzy.value ? '1' : undefined,
            trim: isTrim.value ? '1' : undefined,
            regex: isRegex.value ? '1' : undefined,
            def: isDef.value ? '1' : undefined
        }
    });
};

const loadHeaders = async () => {
  try {
    const res = await SheetApi.getHeaderInfo();
    const data = res.data;
    if (data && data.__valid_options) {
        const headers = data.__valid_options;
        headerInfo.all = headers;
        headerInfo.cities = headers.filter(h => h.is_city == 1);
        headerInfo.foreign = headers.filter(h => h.is_city == 2);
        
        locations.value = headerInfo.cities.map(h => ({
            value: h.col,
            label: h.city + (h.sub || '')
        }));
        
        // Default select to 2nd option if not set
        if (!location.value && locations.value.length > 1) {
             // Logic from user req: "Default to 2nd option '檢索音/字'"
             // But wait, "檢索音/字" is a hardcoded option in the select, not from API?
             // In HTML: <option value="檢">檢索音/字</option>
             // So user probably means that option.
             location.value = '檢'; 
        }
    }
  } catch (e) {
    console.error('Failed to load headers', e);
  }
};

const performSearch = async () => {
    try {
        let q = query.value.trim();
        let finalQ = q;
        // If query is empty, treat as random search '!'
        // But do NOT show '!' in the UI input
        if (!q) {
            finalQ = '!';
        }
        
        // Update URL: If it's random, maybe don't put '!' in URL to be cleaner?
        // Or keep it to allow sharing random results? 
        // User dislikes '!', so let's keep URL clean if empty.
        if (finalQ === '!') {
            router.replace({
                query: {
                    ...route.query, // keep other params
                    q: undefined // remove q from url if it's random
                }
            });
        } else {
            router.replace({
                query: {
                    q: query.value,
                    col: location.value || undefined,
                    fuzzy: isFuzzy.value ? '1' : undefined,
                    trim: isTrim.value ? '1' : undefined,
                    regex: isRegex.value ? '1' : undefined,
                    def: isDef.value ? '1' : undefined
                }
            });
        }

        const res = await SheetApi.search({
            query: finalQ,
            location: location.value,
            isFuzzy: isFuzzy.value,
            isTrim: isTrim.value,
            isRegex: isRegex.value,
            isDef: isDef.value
        });
        
        const data = res.data;
        if (data.error) {
            error.value = data.error;
        } else if (Array.isArray(data)) {
            let rows = [];
            if (data.length > 0) {
                 // The API includes header info as first element sometimes, 
                 // but if it's random search (data.length <= limit) it might not?
                 // Let's check structure. Legacy: array_merge(array($sheetHeaderList), $inCharaSheetArray);
                 // So always index 0 is header.
                 rows = data.slice(1);
            }
            
            // Optimization: Calculate score once
            // Add a temporary _score property
            rows.forEach(row => {
                row._score = calculateDensityScore(row);
                // Also pre-calculate dominant color for strip if we want to sort by that too? (optional)
            });
            
            rows.sort((a, b) => b._score - a._score);
            
            results.value = rows;
            
            if (rows.length === 0) error.value = "未找到結果。";
        }
    } catch (e) {
        error.value = "查詢出錯，請檢查輸入或稍後再試。";
        console.error(e);
    } finally {
        isLoading.value = false;
    }
};

const calculateDensityScore = (rowData) => {
    let score = 0;
    // We can use the already loaded headerInfo.cities
    const cities = headerInfo.cities; 
    const len = cities.length;
    for (let i = 0; i < len; i++) {
        const key = cities[i].col;
        const val = rowData[key];
        if (val && val !== '_' && val !== '?') {
            score++;
        }
    }
    return score;
};

onMounted(async () => {
    initFromUrl();
    await loadHeaders();
    
    // If we have query params (like shared link), search immediately
    if (query.value || route.query.q !== undefined) {
        performSearch();
    } else {
        // If "!" search needs to be triggered by default?
        // Legacy: "若不指定 limit 參數則默認返回一項"
        // Let's trigger a search if nothing is there, defaulting to random
        performSearch(); 
    }
});

// Watch query for external changes (back button)
watch(() => route.query, (newQ) => {
    if (newQ.q !== query.value) { // avoid loop
        // initFromUrl();
        // performSearch(); 
        // We might not want to auto-search on every route change if it's internal
    }
});
</script>

<template>
  <div class="container mx-auto px-4 py-8 max-w-5xl">

    <!-- Search Box -->
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 mb-8 transition-colors duration-300">
        <div class="flex flex-col gap-4">
            <div class="flex flex-col md:flex-row gap-3">
                <div class="flex-1 relative">
                    <input 
                        v-model="query" 
                        @keypress.enter="performSearch"
                        type="text" 
                        placeholder="輸入字或音... (留空隨機)" 
                        class="w-full p-4 pl-5 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-accent/50 focus:border-accent outline-none transition-all dark:text-slate-100 placeholder-gray-400"
                    >
                    <button 
                        @click="query=''"
                        v-if="query"
                        class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" /></svg>
                    </button>
                </div>
                
                <select v-model="location" class="p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-accent/50 focus:border-accent outline-none dark:text-slate-100 min-w-[150px]">
                    <option value="">綜合音/字</option>
                    <option value="檢">檢索音/字</option>
                    <option v-for="loc in locations" :key="loc.value" :value="loc.value">
                        {{ loc.label }}
                    </option>
                </select>
                
                <button 
                    @click="performSearch"
                    class="bg-accent hover:bg-red-700 text-white px-8 py-4 rounded-xl font-bold shadow-lg hover:shadow-xl hover:-translate-y-0.5 active:translate-y-0 transition-all duration-200 whitespace-nowrap"
                >
                    查詢 Search
                </button>
            </div>
            
            <div class="flex flex-wrap justify-center gap-4 md:gap-8 pt-2">
                <label class="flex items-center gap-2 cursor-pointer group text-slate-600 dark:text-slate-400 select-none">
                    <div class="relative flex items-center">
                        <input type="checkbox" v-model="isFuzzy" class="peer h-5 w-5 cursor-pointer appearance-none rounded-md border border-slate-300 dark:border-slate-600 checked:bg-accent checked:border-transparent transition-all">
                        <svg class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-3.5 h-3.5 pointer-events-none opacity-0 peer-checked:opacity-100 text-white" viewBox="0 0 14 14" fill="none"><path d="M3 8L6 11L11 3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <span class="group-hover:text-accent transition-colors">模糊查詢 (查字)</span>
                </label>
                
                <label class="flex items-center gap-2 cursor-pointer group text-slate-600 dark:text-slate-400 select-none">
                    <div class="relative flex items-center">
                        <input type="checkbox" v-model="isTrim" class="peer h-5 w-5 cursor-pointer appearance-none rounded-md border border-slate-300 dark:border-slate-600 checked:bg-accent checked:border-transparent transition-all">
                        <svg class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-3.5 h-3.5 pointer-events-none opacity-0 peer-checked:opacity-100 text-white" viewBox="0 0 14 14" fill="none"><path d="M3 8L6 11L11 3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <span class="group-hover:text-accent transition-colors">音節整體 (查音)</span>
                </label>
                
                <label class="flex items-center gap-2 cursor-pointer group text-slate-600 dark:text-slate-400 select-none">
                    <div class="relative flex items-center">
                        <input type="checkbox" v-model="isRegex" class="peer h-5 w-5 cursor-pointer appearance-none rounded-md border border-slate-300 dark:border-slate-600 checked:bg-accent checked:border-transparent transition-all">
                        <svg class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-3.5 h-3.5 pointer-events-none opacity-0 peer-checked:opacity-100 text-white" viewBox="0 0 14 14" fill="none"><path d="M3 8L6 11L11 3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <span class="group-hover:text-accent transition-colors">正則表達式</span>
                </label>
                
                <label class="flex items-center gap-2 cursor-pointer group text-slate-600 dark:text-slate-400 select-none">
                    <div class="relative flex items-center">
                        <input type="checkbox" v-model="isDef" class="peer h-5 w-5 cursor-pointer appearance-none rounded-md border border-slate-300 dark:border-slate-600 checked:bg-accent checked:border-transparent transition-all">
                        <svg class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-3.5 h-3.5 pointer-events-none opacity-0 peer-checked:opacity-100 text-white" viewBox="0 0 14 14" fill="none"><path d="M3 8L6 11L11 3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <span class="group-hover:text-accent transition-colors">反查釋義</span>
                </label>
            </div>
        </div>
    </div>

    <!-- Results List -->
    <div v-if="isLoading" class="text-center py-16">
        <div class="inline-block animate-spin rounded-full h-10 w-10 border-4 border-gray-200 border-t-accent"></div>
        <p class="mt-4 text-slate-500">載入中 Loading...</p>
    </div>
    
    <div v-else-if="error" class="bg-red-50 dark:bg-red-900/10 text-red-600 dark:text-red-400 p-6 rounded-xl text-center border border-red-100 dark:border-red-900/30">
        {{ error }}
    </div>
    
    <div v-else class="space-y-4">
        <ResultCard 
            v-for="row in results" 
            :key="row.id || Math.random()" 
            :row-data="row"
            :header-info="headerInfo"
        />
        <div v-if="results.length > 0" class="text-center text-slate-400 text-sm py-8">
            —— 到底了 End ——
        </div>
    </div>
  </div>
</template>
