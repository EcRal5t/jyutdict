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

const activeIndex = ref(0);

// Use IntersectionObserver to highlight active character
// When a character section comes into view, set activeIndex.
let observer = null;

const setupObserver = () => {
    // Cleanup previous
    if (observer) observer.disconnect();

    const options = {
        root: null,
        rootMargin: '-20% 0px -60% 0px', // Trigger when element is near top but not too far
        threshold: 0
    };

    observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                // ID format: 'char-INDEX'
                const id = entry.target.id;
                if (id && id.startsWith('char-')) {
                    const idx = parseInt(id.replace('char-', ''), 10);
                    if (!isNaN(idx)) {
                        activeIndex.value = idx;
                    }
                }
            }
        });
    }, options);

    // Observe all character sections
    // Wait for DOM
    setTimeout(() => {
        resultData.value?.forEach((_, idx) => {
            const el = document.getElementById(`char-${idx}`);
            if (el) observer.observe(el);
        });
    }, 500);
};

// Also scroll logic
const scrollToChar = (idx) => {
    const el = document.getElementById(`char-${idx}`);
    if (el) {
        // Offset for header (approx 80px)
        const headerOffset = 100;
        const elementPosition = el.getBoundingClientRect().top;
        const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

        window.scrollTo({
            top: offsetPosition,
            behavior: "smooth"
        });

        // Setup active index immediately
        activeIndex.value = idx;
    }
}

watch(resultData, () => {
    // Re-setup observer when data changes
    if (resultData.value && resultData.value.length > 0) {
        setupObserver();
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
    <div class="container mx-auto px-2 py-4 md:px-4 md:py-8 max-w-7xl">

        <!-- Search Bar -->
        <div class="flex gap-4 mb-8 justify-center">
            <input v-model="inputChara" @keypress.enter="performSearch" type="text" placeholder="輸入查詢..."
                class="p-2 border border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg shadow-sm focus:ring-2 focus:ring-accent outline-none w-full max-w-md text-lg transition-all">
            <button @click="performSearch"
                class="bg-accent text-white px-6 py-2 rounded-lg font-bold hover:bg-red-700 transition-colors shadow-lg active:shadow-sm">
                耖
            </button>
        </div>

        <!-- Loading/Error -->
        <div v-if="isLoading" class="text-center py-10">
            <div class="inline-block animate-spin rounded-full h-10 w-10 border-4 border-gray-200 border-t-accent">
            </div>
        </div>
        <div v-if="error"
            class="text-red-500 text-center py-4 bg-red-50 dark:bg-red-900/10 rounded-lg border border-red-100 dark:border-red-900/30">
            {{ error }}</div>

        <!-- Main Layout -->
        <div v-if="resultData" class="flex flex-col md:flex-row gap-8 relative items-start">

            <!-- Sticky Sidebar (Character Nav) -->
            <!-- Increased top offset to 6rem (approx 96px) to avoid header overlap -->
            <aside
                class="hidden md:block w-24 flex-shrink-0 sticky top-24 self-start max-h-[80vh] overflow-y-auto no-scrollbar py-2">
                <div class="flex flex-col gap-3">
                    <div v-for="(entry, idx) in resultData" :key="idx">
                        <a :href="'#char-' + idx"
                            class="block text-center p-2 mx-1 rounded-lg bg-white dark:bg-slate-800 shadow-sm border border-slate-200 dark:border-slate-700 hover:bg-blue-50 dark:hover:bg-slate-700 transition-all group relative overflow-hidden"
                            :class="{ 'ring-2 ring-accent dark:ring-red-500 bg-blue-50 dark:bg-slate-700': activeIndex === idx }"
                            @click.prevent="scrollToChar(idx)">
                            <!-- Color Block Indicator -->
                            <div class="absolute left-0 top-0 bottom-0 w-1 bg-accent/0 group-hover:bg-accent transition-colors"
                                :class="{ 'bg-accent dark:bg-red-500': activeIndex === idx }"></div>

                            <span class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ entry.chara }}</span>
                            <span class="block text-xs text-slate-400 mt-1"
                                v-if="entry.location && entry.location.length">({{ entry.location.length }})</span>
                        </a>
                    </div>
                </div>
            </aside>

            <!-- Content Area -->
            <div class="flex-grow w-full space-y-16">
                <div v-for="(entry, index) in resultData" :key="index" :id="'char-' + index" class="scroll-mt-6">
                    <!-- Header for Mobile only (since Sidebar handles desktop) -->
                    <div class="md:hidden flex justify-center mb-4">
                        <h2 class="text-4xl font-bold text-slate-800 dark:text-slate-100 relative z-10 leading-none">{{
                            entry.chara }}</h2>
                    </div>

                    <!-- Per Character Card -->
                    <div class="glass rounded-xl p-3 md:p-8">
                        <!-- Grid Layout: Desktop 2 cols, Mobile 1 col -->
                        <!-- Mobile Order Requirement: Dialect Table FIRST, then Ancient, then Map/Links -->
                        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 md:gap-8">

                            <!-- Dialect Table (Area) -->
                            <!-- On Mobile: Order 1. On Desktop: Order 2 (Right Col) -->
                            <div class="order-1 xl:order-2 bg-slate-50/50 dark:bg-slate-800/30 rounded-lg p-4 h-fit">
                                <div class="flex justify-between items-center mb-4 border-l-4 border-accent pl-3">
                                    <h3
                                        class="text-lg font-bold text-slate-700 dark:text-slate-300 flex items-center gap-2">
                                        <span>方音</span>
                                    </h3>
                                    <button @click="showIPA = !showIPA"
                                        class="text-xs font-bold px-3 py-1 rounded border transition-all hover:scale-105 active:scale-95"
                                        :class="showIPA ? 'bg-slate-800 text-white border-slate-800 dark:bg-slate-200 dark:text-slate-800' : 'bg-white/80 text-slate-600 border-slate-300 hover:border-slate-400 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700'">
                                        IPA
                                    </button>
                                </div>

                                <div v-if="entry.location && entry.location.length > 0">
                                    <!-- Flat Map is handled in JS logic in template or separate component, 
                                        here we assume entry.location is already an array of location objects.
                                        AreaTable expects flat array.
                                   -->
                                    <AreaTable :data="entry.location.flat()" :show-i-p-a="showIPA" />
                                </div>
                                <div v-else
                                    class="text-slate-400 italic text-xs text-center py-2 bg-slate-100/50 dark:bg-slate-800/50 rounded">
                                    暫無資料
                                </div>
                            </div>

                            <!-- Left Column Group: Ancient + Map + Links -->
                            <!-- On Mobile: Order 2. On Desktop: Order 1 (Left Col) -->
                            <div class="order-2 xl:order-1 flex flex-col">
                                <!-- Ancient (WanShyu) -->
                                <div class="bg-slate-50/50 dark:bg-slate-800/30 rounded-lg p-4">
                                    <h3
                                        class="text-lg font-bold mb-4 text-slate-700 dark:text-slate-300 border-l-4 border-wood pl-3 flex items-center gap-2">
                                        <span>韻書</span>
                                    </h3>

                                    <div v-if="entry.ancient && entry.ancient.length > 0">
                                        <template v-for="(bookData, bIdx) in entry.ancient" :key="bIdx">
                                            <AncientTable :data="bookData" />
                                        </template>
                                    </div>
                                    <div v-else
                                        class="text-slate-400 italic text-xs text-center py-2 bg-slate-100/50 dark:bg-slate-800/50 rounded">
                                        暫無資料
                                    </div>
                                </div>

                                <!-- Map (Separate container) -->
                                <div v-if="entry.location && entry.location.length > 0"
                                    class="bg-slate-50/50 dark:bg-slate-800/30 rounded-lg p-4">
                                    <DetailMap :locations="entry.location" />
                                </div>

                                <!-- Links -->
                                <div class="bg-slate-50/50 dark:bg-slate-800/30 rounded-lg p-4">
                                    <RelativeLinks :chara="entry.chara" />
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
