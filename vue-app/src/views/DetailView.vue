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
const showIPA = ref(false);

const performSearch = async () => {
    const q = inputChara.value.trim();
    if (!q) return;
    router.push({ query: { chara: q } });
};

const loadData = async (queryChara) => {
    if (!queryChara) return;
    
    isLoading.value = true;
    error.value = null;
    resultData.value = null;
    
    // Legacy: split by char and only take top 10?
    // API logic handles splitting.
    
    try {
        const res = await DetailApi.getCharacterDetail(queryChara);
        // API returns array of results (for each char in string)
        if (res.data && Array.isArray(res.data)) {
             resultData.value = res.data;
        } else if (res.data && res.data.error) {
             error.value = res.data.error;
        } else {
             // Sometimes it might return raw object if single? No, logic says array_push to concat.
             // If empty?
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
            placeholder="輸入單字查詢..."
            class="p-4 border border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-xl shadow-sm focus:ring-2 focus:ring-accent outline-none w-full max-w-md text-lg"
          >
          <button 
            @click="performSearch"
            class="bg-accent text-white px-6 py-4 rounded-xl font-bold hover:bg-red-700 transition-colors shadow-lg active:shadow-sm"
          >
            查字
          </button>
      </div>

      <!-- Loading/Error -->
      <div v-if="isLoading" class="text-center py-10">
           <div class="inline-block animate-spin rounded-full h-10 w-10 border-4 border-gray-200 border-t-accent"></div>
      </div>
      <div v-if="error" class="text-red-500 text-center py-4 bg-red-50 dark:bg-red-900/10 rounded-xl border border-red-100 dark:border-red-900/30">{{ error }}</div>

      <!-- Results -->
      <div v-if="resultData" class="space-y-12">
          <div v-for="(entry, index) in resultData" :key="index" class="bg-white dark:bg-slate-900 p-6 md:p-8 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800">
              
              <!-- Character Header -->
              <div class="flex justify-center mb-8">
                  <div class="relative group cursor-default">
                    <h2 class="text-6xl md:text-7xl font-serif font-bold text-slate-800 dark:text-slate-100 relative z-10">{{ entry.chara }}</h2>
                    <div class="absolute -bottom-2 w-full h-3 bg-accent/20 dark:bg-red-500/20 rounded-full blur-sm group-hover:bg-accent/40 transition-colors"></div>
                  </div>
              </div>
              
              <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                  <!-- Ancient (WanShyu) -->
                  <div>
                      <h3 class="text-xl font-bold mb-6 text-slate-800 dark:text-slate-200 border-l-4 border-wood pl-3 flex items-center gap-2">
                          <span>韻書</span>
                          <span class="text-xs font-normal text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-full">Ancient</span>
                      </h3>
                      
                      <div v-if="entry.ancient && entry.ancient.length > 0">
                           <template v-for="(bookData, bIdx) in entry.ancient" :key="bIdx">
                               <AncientTable :data="bookData" />
                           </template>
                      </div>
                      <div v-else class="text-slate-400 italic text-sm text-center py-4 bg-slate-50 dark:bg-slate-800/50 rounded-lg">
                          暫無韻書資料
                      </div>

                      <!-- Moved Map here as requested -->
                      <div v-if="entry.location && entry.location.length > 0" class="mt-8">
                           <DetailMap :locations="entry.location" />
                      </div>
                      
                      <!-- Moved Links here -->
                      <RelativeLinks :chara="entry.chara" />
                  </div>

                  <!-- Location (Area) -->
                  <div>
                      <div class="flex justify-between items-center mb-6 border-l-4 border-accent pl-3">
                          <h3 class="text-xl font-bold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                              <span>方言</span>
                              <span class="text-xs font-normal text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-full">Dialects</span>
                          </h3>
                          <button 
                             @click="showIPA = !showIPA"
                             class="text-xs font-bold px-3 py-1.5 rounded-lg border transition-all"
                             :class="showIPA ? 'bg-slate-800 text-white border-slate-800 dark:bg-slate-200 dark:text-slate-800' : 'bg-white text-slate-600 border-slate-300 hover:border-slate-400 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700'"
                          >
                             顯示 IPA
                          </button>
                      </div>
                       
                      <div v-if="entry.location && entry.location.length > 0">
                           <template v-for="(locData, lIdx) in entry.location" :key="lIdx">
                               <AreaTable :data="locData" :show-i-p-a="showIPA" />
                           </template>
                      </div>
                       <div v-else class="text-slate-400 italic text-sm text-center py-4 bg-slate-50 dark:bg-slate-800/50 rounded-lg">
                          暫無方言資料
                      </div>
                  </div>
              </div>
          </div>
      </div>
  </div>
</template>
