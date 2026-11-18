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
import AreaColorBadge from '@/components/common/AreaColorBadge.vue';
import { parseAreaColors } from '@/utils/areaColor.js';
import { updateCurrentSeo } from '@/utils/seo.js';
import LoadingBlocks from '@/components/common/LoadingBlocks.vue';

// 評論側邊欄
const commentSidebarVisible = ref(false);
const commentTarget = ref('');

const openComments = (chara) => {
    commentTarget.value = chara;
    commentSidebarVisible.value = true;
};

// 評論數量
const commentCounts = ref({});
const commentCountsLoading = ref(false);

const loadCommentCounts = async () => {
    if (!resultData.value || resultData.value.length === 0) return;
    const charas = resultData.value.map(e => e.chara).filter(Boolean);
    if (charas.length === 0) return;

    commentCountsLoading.value = true;
    try {
        const res = await commentsApi.getCounts('char', charas);
        commentCounts.value = res.data.counts || {};
    } catch (e) {
        console.error('Failed to load comment counts', e);
    } finally {
        commentCountsLoading.value = false;
    }
};

const getCommentCount = (chara) => {
    return commentCounts.value[chara] || 0;
};

const route = useRoute();
const router = useRouter();

const chara = ref('');
const inputChara = ref('');
const isLoading = ref(false);
const error = ref(null);
const resultData = ref(null);
const showIPA = ref(true);
const areaPickerOpen = ref(false);
const selectedAreaIds = ref([]);
const draftAreaIds = ref([]);

const isAreaMode = computed(() => selectedAreaIds.value.length > 0);
const draftAreaIdSet = computed(() => new Set(draftAreaIds.value));

const performSearch = async () => {
    const q = inputChara.value.trim();
    if (!q) return;
    router.push({
        query: {
            chara: q,
            ...(isAreaMode.value ? { areas: selectedAreaIds.value.join(',') } : {})
        }
    });
};

const headerData = ref({});
const headerList = ref([]);
const isHeaderLoaded = ref(false);

const loadHeaders = async () => {
    try {
        const res = await DetailApi.getLocationList();
        if (res.data && Array.isArray(res.data)) {
            const map = {};
            res.data.forEach(item => {
                map[item.id] = item;
            });
            headerList.value = res.data;
            headerData.value = map;
            isHeaderLoaded.value = true;
        }
    } catch (e) {
        console.error("Failed to load headers", e);
    }
};

const loadData = async (queryChara, areaIds = []) => {
    if (!queryChara) return;

    // Ensure headers are loaded
    if (!isHeaderLoaded.value) {
        await loadHeaders();
    }

    isLoading.value = true;
    error.value = null;
    resultData.value = null;

    try {
        const res = await DetailApi.getCharacterDetail(queryChara, areaIds);
        if (res.data && Array.isArray(res.data)) {
            // Process data to enrich Location IDs with Names
            const enrichedData = res.data.map(charEntry => {
                if (charEntry['各地']) {
                    charEntry.location = charEntry['各地'].map(loc => {
                        const headerInfo = headerData.value[loc.id] || {};
                        return {
                            ...loc,
                            city: headerInfo.second || '',
                            division: headerInfo.first || '',
                            district: headerInfo.third || '',
                            color: headerInfo.color || '',
                            latitude: headerInfo.latitude,
                            longitude: headerInfo.longitude,
                            detailedName: headerInfo.detailed_name || '',
                            sheetInfo: headerInfo.sheet_info || '',
                            hasPhonology: Boolean(headerInfo.has_phonology)
                        };
                    });
                    delete charEntry['各地'];

                    if (charEntry['韻書']) {
                        charEntry.ancient = charEntry['韻書'];
                        delete charEntry['韻書'];
                    }

                    if (charEntry['字']) {
                        charEntry.chara = charEntry['字'];
                        delete charEntry['字'];
                    }
                }
                return charEntry;
            });
            if (areaIds.length) {
                const entriesWithLocationData = enrichedData.filter(entry => entry.location?.length > 0);
                resultData.value = entriesWithLocationData;
                if (entriesWithLocationData.length === 0) {
                    error.value = '所選地點暫無資料';
                }
            } else {
                resultData.value = enrichedData;
            }

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
        const hasData = resultData.value?.some(entry => entry.location?.length || entry.ancient?.length);
        if (!error.value && resultData.value && !hasData) updateCurrentSeo({ noindex: true }, '/detail');
    }
};

const syncAreaFromRoute = () => {
    const routeAreas = Array.isArray(route.query.areas) ? route.query.areas[0] : route.query.areas;
    const legacyArea = Array.isArray(route.query.area) ? route.query.area[0] : route.query.area;
    const raw = routeAreas || legacyArea || '';
    const ids = [...new Set(String(raw).split(',')
        .map(value => Number(value))
        .filter(id => Number.isInteger(id) && headerData.value[id]))];
    selectedAreaIds.value = ids;
    draftAreaIds.value = [...ids];
};

const toggleAreaPicker = () => {
    areaPickerOpen.value = !areaPickerOpen.value;
    if (areaPickerOpen.value) draftAreaIds.value = [...selectedAreaIds.value];
};

const toggleDraftArea = (areaId) => {
    if (draftAreaIdSet.value.has(areaId)) {
        draftAreaIds.value = draftAreaIds.value.filter(id => id !== areaId);
    } else {
        draftAreaIds.value = [...draftAreaIds.value, areaId];
    }
};

const applyAreaMode = () => {
    const query = { ...route.query };
    delete query.area;
    if (draftAreaIds.value.length) {
        query.areas = draftAreaIds.value.join(',');
    } else {
        delete query.areas;
    }
    areaPickerOpen.value = false;
    router.push({ query });
};

const leaveAreaMode = () => {
    const query = { ...route.query };
    delete query.area;
    delete query.areas;
    areaPickerOpen.value = false;
    router.push({ query });
};

const getAreaColors = (color) => parseAreaColors(color);

onMounted(async () => {
    await loadHeaders();
    syncAreaFromRoute();
    if (route.query.chara) {
        inputChara.value = route.query.chara;
        loadData(route.query.chara, selectedAreaIds.value);
    }
});

const activeIndex = ref(0);

// Use IntersectionObserver to highlight active character
let observer = null;

const setupObserver = () => {
    if (observer) observer.disconnect();

    const options = {
        root: null,
        rootMargin: '-20% 0px -60% 0px',
        threshold: 0
    };

    observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
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

    setTimeout(() => {
        resultData.value?.forEach((_, idx) => {
            const el = document.getElementById(`char-${idx}`);
            if (el) observer.observe(el);
        });
    }, 500);
};

const scrollToChar = (idx) => {
    const el = document.getElementById(`char-${idx}`);
    if (el) {
        const headerOffset = 100;
        const elementPosition = el.getBoundingClientRect().top;
        const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

        window.scrollTo({
            top: offsetPosition,
            behavior: "smooth"
        });

        activeIndex.value = idx;
    }
};

watch(resultData, () => {
    if (resultData.value && resultData.value.length > 0) {
        setupObserver();
        if (isAreaMode.value) {
            commentCounts.value = {};
        } else {
            loadCommentCounts();
        }
    }
});

watch([() => route.query.chara, () => route.query.areas, () => route.query.area], ([newChara]) => {
    syncAreaFromRoute();
    if (newChara) {
        inputChara.value = newChara;
        loadData(newChara, selectedAreaIds.value);
    }
});

</script>

<template>
    <div class="container mx-auto px-2 py-4 md:px-4 md:py-8 max-w-7xl">

        <!-- Search Bar -->
        <div class="flex gap-2 sm:gap-3 mb-3 justify-center w-full max-w-4xl mx-auto">
            <input v-model="inputChara" @keypress.enter="performSearch" type="text" placeholder="輸入查詢..."
                class="jd-input p-2 px-3 dark:bg-slate-800 flex-1 min-w-0 text-lg font-serif">
            <button @click="performSearch"
                class="jd-btn-primary px-3 sm:px-5 py-2 flex-shrink-0">
                耖
            </button>
            <button @click="toggleAreaPicker" type="button"
                class="px-2.5 sm:px-4 py-2 border-2 font-bold transition-colors duration-150 flex-shrink-0 flex items-center gap-1 sm:gap-1.5 active:translate-y-px"
                :class="isAreaMode
                    ? 'border-accent bg-accent/10 text-accent dark:text-red-400'
                    : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-accent hover:text-accent'"
                :aria-expanded="areaPickerOpen" aria-controls="area-mode-picker">
                <span>{{ isAreaMode ? `地點 ${selectedAreaIds.length}` : '地點' }}</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 transition-transform duration-200"
                    :class="{ 'rotate-180': areaPickerOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
        </div>

        <Transition name="jd-drop">
            <div v-if="areaPickerOpen" id="area-mode-picker"
                class="jd-panel mx-auto mb-6 max-w-5xl border-l-4 !border-l-accent px-3 sm:px-4 py-3">
                <div class="flex items-center justify-between gap-3 mb-2">
                    <span class="text-sm font-bold text-slate-700 dark:text-slate-200">指定地點查詢</span>
                    <span class="text-xs text-slate-500">已選 <span :key="draftAreaIds.length" class="inline-block jd-tick tabular-nums">{{ draftAreaIds.length }}</span></span>
                </div>
                <div class="max-h-64 overflow-y-auto grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-1.5 pr-1">
                    <label v-for="area in headerList" :key="area.id"
                        class="flex items-center gap-2 min-w-0 px-2.5 py-2 border cursor-pointer transition-colors duration-150 active:translate-y-px"
                        :class="draftAreaIdSet.has(area.id)
                            ? 'bg-accent/5 border-accent/40 text-slate-900 dark:text-slate-100'
                            : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-slate-400'">
                        <input type="checkbox" class="sr-only" :checked="draftAreaIdSet.has(area.id)"
                            @change="toggleDraftArea(area.id)">
                        <AreaColorBadge :color="area.color" badge-class="w-4 h-4" />
                        <span class="truncate text-sm">{{ area.second }}{{ area.third }}</span>
                        <Transition name="jd-check"><span v-if="draftAreaIdSet.has(area.id)" class="ml-auto text-accent text-xs">✓</span></Transition>
                    </label>
                </div>
                <div class="flex flex-wrap justify-end gap-1.5 sm:gap-2 mt-3">
                    <button v-if="isAreaMode" type="button" @click="leaveAreaMode"
                        class="px-3 py-1.5 text-xs font-bold text-slate-500 hover:text-accent transition-colors duration-150">
                        返回全量查詢
                    </button>
                    <button type="button" @click="draftAreaIds = []"
                        class="jd-btn-ghost px-3 py-1.5 text-xs font-bold">
                        清空選擇
                    </button>
                    <button type="button" @click="applyAreaMode" :disabled="draftAreaIds.length === 0"
                        class="jd-btn-primary px-4 py-1.5 text-xs !shadow-none">
                        套用 {{ draftAreaIds.length }} 個地點
                    </button>
                </div>
            </div>
        </Transition>

        <!-- Loading/Error -->
        <LoadingBlocks v-if="isLoading" class="py-10" />
        <Transition name="jd-rise">
            <div v-if="error"
                class="max-w-4xl mx-auto text-red-600 dark:text-red-400 text-center py-4 bg-red-50 dark:bg-red-900/10 rounded-none border-l-4 border-red-500 dark:border-red-400">
                {{ error }}</div>
        </Transition>

        <!-- Main Layout -->
        <div v-if="resultData && resultData.length" class="flex flex-col md:flex-row relative items-start" :class="isAreaMode ? 'gap-3' : 'gap-8'">

            <!-- Sticky Sidebar (Character Nav) -->
            <aside
                class="hidden md:block flex-shrink-0 sticky top-24 self-start max-h-[80vh] overflow-y-auto no-scrollbar py-2"
                :class="isAreaMode ? 'w-12' : 'w-24'">
                <div class="flex flex-col jd-stagger" :class="isAreaMode ? 'gap-0.5' : 'gap-2'">
                    <div v-for="(entry, idx) in resultData" :key="idx" class="relative">
                        <a :href="'#char-' + idx"
                            class="block text-center rounded-none transition-colors duration-150 group relative overflow-hidden"
                            :class="[
                                isAreaMode ? 'p-1' : 'p-2',
                                activeIndex === idx ? 'bg-accent/10 dark:bg-accent/20' : 'hover:bg-slate-100 dark:hover:bg-slate-700/50'
                            ]"
                            @click.prevent="scrollToChar(idx)">
                            <!-- Color Block Indicator -->
                            <div class="absolute left-0 top-0 bottom-0 w-1 origin-center transition-[transform,background-color] duration-200 ease-out"
                                :class="activeIndex === idx ? 'bg-accent scale-y-100' : 'bg-transparent scale-y-50 group-hover:scale-y-100 group-hover:bg-slate-300 dark:group-hover:bg-slate-600'"></div>

                            <span class="font-serif jd-headword font-bold text-slate-800 dark:text-slate-100" :class="isAreaMode ? 'text-xl' : 'text-2xl'">{{ entry.chara }}</span>
                            <span class="block text-xs text-slate-400 mt-0.5"
                                v-if="!isAreaMode && entry.location && entry.location.length">({{ entry.location.length }})</span>
                        </a>
                        <!-- 評論按鈕 -->
                        <button v-if="!isAreaMode" @click="openComments(entry.chara)"
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
            <div class="flex-grow w-full jd-stagger" :class="isAreaMode ? 'space-y-3' : 'space-y-16'">
                <div v-for="(entry, index) in resultData" :key="index" :id="'char-' + index" class="scroll-mt-6">
                    <!-- Header for Mobile only (since Sidebar handles desktop) -->
                    <div class="md:hidden flex justify-center items-center gap-2" :class="isAreaMode ? 'mb-1' : 'mb-4'">
                        <h2 class="font-serif jd-headword font-bold text-slate-800 dark:text-slate-100 relative z-10 leading-none"
                            :class="isAreaMode ? 'text-2xl' : 'text-4xl'">{{
                            entry.chara }}</h2>
                        <button v-if="!isAreaMode" @click="openComments(entry.chara)"
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
                    <div class="bg-white dark:bg-slate-800 rounded-none"
                        :class="isAreaMode
                            ? 'p-1 md:p-2 border border-slate-200/80 dark:border-slate-700'
                            : 'jd-panel p-3 md:p-8'">
                        <!-- Grid Layout: Desktop 2 cols, Mobile 1 col -->
                        <!-- Mobile Order Requirement: Dialect Table FIRST, then Ancient, then Map/Links -->
                        <div :class="isAreaMode ? 'block' : 'grid grid-cols-1 xl:grid-cols-2 gap-4 md:gap-6'">

                            <!-- Dialect Table (Area) -->
                            <!-- On Mobile: Order 1. On Desktop: Order 2 (Right Col) -->
                            <div class="order-1 xl:order-2 h-fit" :class="isAreaMode ? 'p-0' : 'p-4'">
                                <div v-if="!isAreaMode" class="flex justify-between items-center mb-4">
                                    <h3
                                        class="jd-section-title text-lg flex items-center gap-2">
                                        <span>方音</span>
                                    </h3>
                                    <button @click="showIPA = !showIPA"
                                        class="text-xs font-bold px-3 py-1 rounded-none transition-all duration-150 hover:-translate-y-0.5 active:translate-y-0"
                                        :class="showIPA ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-800 shadow-[2px_2px_0_rgba(0,0,0,0.2)]' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:shadow-[2px_2px_0_rgba(0,0,0,0.1)]'">
                                        IPA
                                    </button>
                                </div>

                                <div v-if="entry.location && entry.location.length > 0">
                                    <!-- Flat Map is handled in JS logic in template or separate component,
                                        here we assume entry.location is already an array of location objects.
                                        AreaTable expects flat array.
                                    -->
                                    <AreaTable :data="entry.location.flat()" :show-i-p-a="showIPA" :compact="isAreaMode">
                                        <template v-if="isAreaMode" #actions>
                                            <RelativeLinks :chara="entry.chara" compact compact-label="查" />
                                        </template>
                                    </AreaTable>
                                </div>
                                <div v-else
                                    class="text-slate-400 italic text-xs text-center py-2">
                                    暫無資料
                                </div>
                            </div>

                            <!-- Left Column Group: Ancient + Map + Links -->
                            <!-- On Mobile: Order 2. On Desktop: Order 1 (Left Col) -->
                            <div v-if="!isAreaMode" class="order-2 xl:order-1 flex flex-col gap-4">
                                <!-- Ancient (WanShyu) -->
                                <div class="p-4">
                                    <h3
                                        class="jd-section-title !border-wood text-lg mb-4 flex items-center gap-2">
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

<style scoped>
.jd-check-enter-active,
.jd-check-leave-active {
    transition: opacity var(--jd-fast) var(--jd-ease), transform var(--jd-fast) var(--jd-ease);
}

.jd-check-enter-from,
.jd-check-leave-to {
    opacity: 0;
    transform: translateX(-3px);
}
</style>
