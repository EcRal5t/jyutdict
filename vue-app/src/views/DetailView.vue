<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import DetailApi from '@/api/detail.js';
import AncientTable from '@/components/detail/AncientTable.vue';
import AreaTable from '@/components/detail/AreaTable.vue';
import DetailMap from '@/components/detail/DetailMap.vue';
import RelativeLinks from '@/components/detail/RelativeLinks.vue';

const route = useRoute();
const router = useRouter();

const chara = ref('');
const inputChara = ref('');
const isLoading = ref(false);
const error = ref(null);
const resultData = ref(null);
const showIPA = ref(true);

const performSearch = async () => {
    const q = inputChara.value.trim();
    if (!q) return;
    router.push({ query: { chara: q } });
};

const headerData = ref({});
const isHeaderLoaded = ref(false);

const loadHeaders = async () => {
    try {
        const res = await DetailApi.getLocationList();
        if (res.data && Array.isArray(res.data)) {
            // Map header list to object for easy lookup by ID
            // iarealist: { id, first, second, third, ... }
            const map = {};
            res.data.forEach(item => {
                map[item.id] = item;
            });
            headerData.value = map;
            isHeaderLoaded.value = true;
        }
    } catch (e) {
        console.error("Failed to load headers", e);
    }
};

const loadData = async (queryChara) => {
    if (!queryChara) return;
    
    // Ensure headers are loaded
    if (!isHeaderLoaded.value) {
        await loadHeaders();
    }

    isLoading.value = true;
    error.value = null;
    resultData.value = null;
    
    try {
        const res = await DetailApi.getCharacterDetail(queryChara);
        if (res.data && Array.isArray(res.data)) {
             // Process data to enrich Location IDs with Names
             const enrichedData = res.data.map(charEntry => {
                 if (charEntry['各地']) {
                     // Map '各地' array items
                     charEntry.location = charEntry['各地'].map(loc => {
                         const headerInfo = headerData.value[loc.id] || {};
                         // Construct display name or pass info
                         // v0.9 had: division_adm, city, district...
                         // iarealist has: first (div), second (city), third (district)
                         return {
                             ...loc,
                             city: headerInfo.second || '',
                             division: headerInfo.first || '',
                             district: headerInfo.third || '',
                             color: headerInfo.color || '',
                             latitude: headerInfo.latitude,
                             longitude: headerInfo.longitude
                         };
                     });
                     // Use legacy key 'location' for compatibility with template or update template?
                     // Template uses `entry.location` (from my view_file output of DetailView Step 59, line 128: `entry.location`)
                     // So mapping `各地` to `location` is good.
                     delete charEntry['各地'];

                     // Map '韻書' to 'ancient' if needed?
                     // Step 59 line 118: `entry.ancient`.
                     if (charEntry['韻書']) {
                         charEntry.ancient = charEntry['韻書'];
                         delete charEntry['韻書'];
                     }
                     
                     // Map '字' to 'chara'
                     if (charEntry['字']) {
                         charEntry.chara = charEntry['字'];
                         delete charEntry['字'];
                     }
                 }
                 return charEntry;
             });
             resultData.value = enrichedData;

        } else if (res.data && res.data.error) {
             error.value = res.data.error;
        } else {
             if (!res.data || res.data.length === 0) {
                 error.value = "未找到資料 (No data found)";
             }
        }
    } catch (e) {
        console.error(e);
        error.value = "Failed to load details.";
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    loadHeaders(); // Start loading headers
    if (route.query.chara) {
        inputChara.value = route.query.chara;
        loadData(route.query.chara);
    }
});

watch(() => route.query.chara, (newVal) => {
    if (newVal) {
        inputChara.value = newVal;
        loadData(newVal);
    }
});
</script>

<template>
  <div class="container mx-auto px-4 py-8 max-w-5xl">
      
      <!-- Search Bar -->
      <div class="flex gap-4 mb-8 justify-center">
          <input 
            v-model="inputChara"
            @keypress.enter="performSearch"
            type="text" 
            placeholder="輸入查詢..."
            class="p-4 border border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg shadow-sm focus:ring-2 focus:ring-accent outline-none w-full max-w-md text-lg"
          >
          <button 
            @click="performSearch"
            class="bg-accent text-white px-6 py-4 rounded-lg font-bold hover:bg-red-700 transition-colors shadow-lg active:shadow-sm"
          >
            耖
          </button>
      </div>

      <!-- Loading/Error -->
      <div v-if="isLoading" class="text-center py-10">
           <div class="inline-block animate-spin rounded-full h-10 w-10 border-4 border-gray-200 border-t-accent"></div>
      </div>
      <div v-if="error" class="text-red-500 text-center py-4 bg-red-50 dark:bg-red-900/10 rounded-lg border border-red-100 dark:border-red-900/30">{{ error }}</div>

      <!-- Results -->
      <div v-if="resultData" class="space-y-12">
          <div v-for="(entry, index) in resultData" :key="index" class="glass rounded-lg p-6 md:p-8">
              
              <div class="flex flex-col md:flex-row gap-8 items-start">
                   <!-- Character Header (Side or smaller) -->
                   <div class="w-full md:w-auto flex-shrink-0 flex justify-center md:block">
                      <div class="relative group cursor-default inline-block">
                        <h2 class="text-6xl font-bold text-slate-800 dark:text-slate-100 relative z-10 leading-none">{{ entry.chara }}</h2>
                        <div class="absolute -bottom-1 w-full h-2 bg-accent/20 dark:bg-red-500/20 rounded-full blur-sm group-hover:bg-accent/40 transition-colors"></div>
                      </div>
                   </div>

                   <!-- Content Grid -->
                   <div class="flex-grow w-full">
                       
                       <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
                          <!-- Ancient (WanShyu) -->
                          <div class="bg-slate-50/50 dark:bg-slate-800/30 rounded-lg p-4">
                              <h3 class="text-lg font-bold mb-4 text-slate-700 dark:text-slate-300 border-l-4 border-wood pl-3 flex items-center gap-2">
                                  <span>韻書</span>
                              </h3>
                              
                              <div v-if="entry.ancient && entry.ancient.length > 0">
                                   <template v-for="(bookData, bIdx) in entry.ancient" :key="bIdx">
                                       <AncientTable :data="bookData" />
                                   </template>
                              </div>
                              <div v-else class="text-slate-400 italic text-sm text-center py-4">
                                  暫無韻書資料
                              </div>

                              <!-- Map -->
                              <div v-if="entry.location && entry.location.length > 0" class="mt-6">
                                   <DetailMap :locations="entry.location" />
                              </div>

                              <!-- Links -->
                              <div class="mt-6">
                                <RelativeLinks :chara="entry.chara" />
                              </div>
                          </div>

                          <!-- Location (Area) -->
                          <div class="bg-slate-50/50 dark:bg-slate-800/30 rounded-lg p-4">
                              <div class="flex justify-between items-center mb-4 border-l-4 border-accent pl-3">
                                  <h3 class="text-lg font-bold text-slate-700 dark:text-slate-300 flex items-center gap-2">
                                      <span>方音</span>
                                  </h3>
                                  <button 
                                     @click="showIPA = !showIPA"
                                     class="text-xs font-bold px-3 py-1 rounded border transition-all"
                                     :class="showIPA ? 'bg-slate-800 text-white border-slate-800 dark:bg-slate-200 dark:text-slate-800' : 'bg-white/80 text-slate-600 border-slate-300 hover:border-slate-400 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700'"
                                  >
                                     顯示 IPA
                                  </button>
                              </div>
                               
                              <div v-if="entry.location && entry.location.length > 0">
                                   <AreaTable :data="entry.location.flat()" :show-i-p-a="showIPA" />
                              </div>
                               <div v-else class="text-slate-400 italic text-sm text-center py-4">
                                  暫無方言資料
                              </div>

                          </div>
                       </div>
                   </div>
              </div>
          </div>
      </div>
  </div>
</template>
