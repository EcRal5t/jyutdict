<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import DetailApi from '@/api/detail.js';
import commentsApi from '@/api/comments.js';
import AncientTable from '@/components/detail/AncientTable.vue';
import AreaTable from '@/components/detail/AreaTable.vue';
import DetailMap from '@/components/detail/DetailMap.vue';
import RelativeLinks from '@/components/detail/RelativeLinks.vue';
import CommentSidebar from '@/components/CommentSidebar.vue';

// 評論側邊欄
const commentSidebarVisible = ref(false)
const commentTarget = ref('')

const openComments = (chara) => {
    commentTarget.value = chara
    commentSidebarVisible.value = true
}

// 評論數量
const commentCounts = ref({})
const commentCountsLoading = ref(false)

const loadCommentCounts = async () => {
    if (!resultData.value || resultData.value.length === 0) return
    const charas = resultData.value.map(e => e.chara).filter(Boolean)
    if (charas.length === 0) return

    commentCountsLoading.value = true
    try {
        const res = await commentsApi.getCounts('char', charas)
        commentCounts.value = res.data.counts || {}
    } catch (e) {
        console.error('Failed to load comment counts', e)
    } finally {
        commentCountsLoading.value = false
    }
}

const getCommentCount = (chara) => {
    return commentCounts.value[chara] || 0
}

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
        loadCommentCounts();
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
        <div class="flex gap-3 mb-8 justify-center">
            <input v-model="inputChara" @keypress.enter="performSearch" type="text" placeholder="輸入查詢..."
                class="p-2 px-3 border-2 border-gray-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-none focus:border-accent outline-none flex-1 max-w-md text-lg transition-all">
            <button @click="performSearch"
                class="bg-accent text-white px-5 py-2 rounded-none font-bold shadow-[4px_4px_0_rgba(183,41,20,0.3)] hover:shadow-[6px_6px_0_rgba(183,41,20,0.4)] hover:-translate-y-0.5 active:translate-y-0 active:shadow-[2px_2px_0_rgba(183,41,20,0.3)] transition-all flex-shrink-0">
                耖
            </button>
        </div>

        <!-- Loading/Error -->
        <div v-if="isLoading" class="text-center py-10">
            <div class="inline-block animate-spin rounded-full h-10 w-10 border-4 border-gray-200 border-t-accent">
            </div>
        </div>
        <div v-if="error"
            class="text-red-500 text-center py-4 bg-red-50 dark:bg-red-900/10 rounded-none border-l-4 border-red-500 dark:border-red-400">
            {{ error }}</div>

        <!-- Main Layout -->
        <div v-if="resultData" class="flex flex-col md:flex-row gap-8 relative items-start">

            <!-- Sticky Sidebar (Character Nav) -->
            <aside
                class="hidden md:block w-24 flex-shrink-0 sticky top-24 self-start max-h-[80vh] overflow-y-auto no-scrollbar py-2">
                <div class="flex flex-col gap-2">
                    <div v-for="(entry, idx) in resultData" :key="idx" class="relative">
                        <a :href="'#char-' + idx"
                            class="block text-center p-2 rounded-none transition-all group relative overflow-hidden"
                            :class="activeIndex === idx ? 'bg-accent/10 dark:bg-accent/20' : 'hover:bg-slate-100 dark:hover:bg-slate-700/50'"
                            @click.prevent="scrollToChar(idx)">
                            <!-- Color Block Indicator -->
                            <div class="absolute left-0 top-0 bottom-0 w-1 transition-colors"
                                :class="activeIndex === idx ? 'bg-accent' : 'bg-transparent group-hover:bg-slate-300 dark:group-hover:bg-slate-600'"></div>

                            <span class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ entry.chara }}</span>
                            <span class="block text-xs text-slate-400 mt-0.5"
                                v-if="entry.location && entry.location.length">({{ entry.location.length }})</span>
                        </a>
                        <!-- 評論按鈕 -->
                        <button @click="openComments(entry.chara)"
                            class="absolute bottom-1 right-1 flex items-center gap-0.5 text-xs px-1.5 py-0.5 rounded-none transition-colors"
                            :class="getCommentCount(entry.chara) > 0 ? 'bg-accent/10 text-accent hover:bg-accent/20' : 'text-slate-400 hover:text-accent hover:bg-slate-100 dark:hover:bg-slate-700'"
                            title="評論">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                            </svg>
                            <span v-if="getCommentCount(entry.chara) > 0">{{ getCommentCount(entry.chara) }}</span>
                        </button>
                    </div>
                </div>
            </aside>

            <!-- Content Area -->
            <div class="flex-grow w-full space-y-16">
                <div v-for="(entry, index) in resultData" :key="index" :id="'char-' + index" class="scroll-mt-6">
                    <!-- Header for Mobile only (since Sidebar handles desktop) -->
                    <div class="md:hidden flex justify-center items-center mb-4 gap-2">
                        <h2 class="text-4xl font-bold text-slate-800 dark:text-slate-100 relative z-10 leading-none">{{
                            entry.chara }}</h2>
                        <button @click="openComments(entry.chara)"
                            class="flex items-center gap-1 text-sm px-2 py-1 rounded-none transition-colors"
                            :class="getCommentCount(entry.chara) > 0 ? 'bg-accent/10 text-accent hover:bg-accent/20' : 'text-slate-400 hover:text-accent hover:bg-slate-100 dark:hover:bg-slate-700'"
                            title="評論">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                            </svg>
                            <span v-if="getCommentCount(entry.chara) > 0">{{ getCommentCount(entry.chara) }}</span>
                        </button>
                    </div>

                    <!-- Per Character Card -->
                    <div class="bg-white dark:bg-slate-800 rounded-none shadow-[6px_6px_0_rgba(0,0,0,0.06)] dark:shadow-[6px_6px_0_rgba(0,0,0,0.3)] p-3 md:p-8">
                        <!-- Grid Layout: Desktop 2 cols, Mobile 1 col -->
                        <!-- Mobile Order Requirement: Dialect Table FIRST, then Ancient, then Map/Links -->
                        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 md:gap-6">

                            <!-- Dialect Table (Area) -->
                            <!-- On Mobile: Order 1. On Desktop: Order 2 (Right Col) -->
                            <div class="order-1 xl:order-2 p-4 h-fit">
                                <div class="flex justify-between items-center mb-4 border-l-4 border-accent pl-3">
                                    <h3
                                        class="text-lg font-bold text-slate-700 dark:text-slate-300 flex items-center gap-2">
                                        <span>方音</span>
                                    </h3>
                                    <button @click="showIPA = !showIPA"
                                        class="text-xs font-bold px-3 py-1 rounded-none transition-all hover:-translate-y-0.5 active:translate-y-0"
                                        :class="showIPA ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-800 shadow-[2px_2px_0_rgba(0,0,0,0.2)]' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:shadow-[2px_2px_0_rgba(0,0,0,0.1)]'">
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
                                    class="text-slate-400 italic text-xs text-center py-2">
                                    暫無資料
                                </div>
                            </div>

                            <!-- Left Column Group: Ancient + Map + Links -->
                            <!-- On Mobile: Order 2. On Desktop: Order 1 (Left Col) -->
                            <div class="order-2 xl:order-1 flex flex-col gap-4">
                                <!-- Ancient (WanShyu) -->
                                <div class="p-4">
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
                                        class="text-slate-400 italic text-xs text-center py-2">
                                        暫無資料
                                    </div>
                                </div>

                                <!-- Map (Separate container) -->
                                <div v-if="entry.location && entry.location.length > 0" class="p-4">
                                    <DetailMap :locations="entry.location" />
                                </div>

                                <!-- Links -->
                                <div class="p-4">
                                    <RelativeLinks :chara="entry.chara" />
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 評論側邊欄 -->
        <CommentSidebar
            type="char"
            :target="commentTarget"
            :visible="commentSidebarVisible"
            @close="commentSidebarVisible = false"
        />
    </div>
</template>
