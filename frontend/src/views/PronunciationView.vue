<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { parsePronunciationQuery } from '@/utils/pronunciationQuery.js';
import AreaColorBadge from '@/components/common/AreaColorBadge.vue';
import { getPrimaryAreaColor } from '@/utils/areaColor.js';
import LoadingBlocks from '@/components/common/LoadingBlocks.vue';

const form = reactive({
    pron: '',
    in: '',
    nu: '',
    co: '',
    to: ''
});
const route = useRoute();

const parseStatus = ref('neutral'); // neutral, valid, invalid
const inputDisabled = ref(true);

const parsedComponents = reactive({
    in: '',
    nu: '',
    co: '',
    to: ''
});

// 地點選擇相關
const locations = ref([]);
const selectedLocations = ref(new Set());
const loadingLocations = ref(false);
const STORAGE_KEY = 'jyutdict_selected_locations';

// 載入地點列表
const loadLocations = async () => {
    loadingLocations.value = true;
    try {
        const response = await axios.get('/api/v1.0/detail', {
            params: { chara: '' }
        });
        if (response.data && Array.isArray(response.data)) {
            locations.value = response.data;
            // 從 localStorage 恢復選擇，否則默認全選
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved) {
                try {
                    const savedSet = new Set(JSON.parse(saved));
                    // 只保留仍然存在的地點
                    const validIds = new Set(response.data.map(l => l.id));
                    savedSet.forEach(id => {
                        if (validIds.has(id)) selectedLocations.value.add(id);
                    });
                    // 如果沒有有效的選擇，全選
                    if (selectedLocations.value.size === 0) {
                        response.data.forEach(loc => selectedLocations.value.add(loc.id));
                    }
                } catch (e) {
                    response.data.forEach(loc => selectedLocations.value.add(loc.id));
                }
            } else {
                response.data.forEach(loc => selectedLocations.value.add(loc.id));
            }
            // 默認選中韻書
            selectedLocations.value.add('fanwan');
            selectedLocations.value.add('jingwaa');
        }
    } catch (e) {
        console.error('Failed to load locations', e);
    } finally {
        loadingLocations.value = false;
    }
};

// 保存選擇到 localStorage
watch(selectedLocations, (newSet) => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify([...newSet]));
}, { deep: true });

// 地點選擇操作
const selectAll = () => {
    locations.value.forEach(loc => selectedLocations.value.add(loc.id));
};

const deselectAll = () => {
    selectedLocations.value.clear();
};

const invertSelection = () => {
    locations.value.forEach(loc => {
        if (selectedLocations.value.has(loc.id)) {
            selectedLocations.value.delete(loc.id);
        } else {
            selectedLocations.value.add(loc.id);
        }
    });
};

const toggleLocation = (id) => {
    if (selectedLocations.value.has(id)) {
        selectedLocations.value.delete(id);
    } else {
        selectedLocations.value.add(id);
    }
};

// 按片區分組的地點
const groupedLocations = computed(() => {
    const groups = {};
    locations.value.forEach(loc => {
        const div = loc.first || '其他';
        if (!groups[div]) groups[div] = [];
        groups[div].push(loc);
    });
    return groups;
});

// 解析輸入
const analyzeInput = () => {
    const parsed = parsePronunciationQuery(form.pron);
    Object.assign(parsedComponents, parsed.components);
    Object.assign(form, parsed.query, { pron: form.pron });
    parseStatus.value = parsed.status;
    inputDisabled.value = !parsed.valid;
};

// 結果相關
const results = ref(null);
const loading = ref(false);
const resultsVersion = ref(0);

const submitSearch = async () => {
    if (inputDisabled.value) return;
    await fetchResults();
};

const fetchResults = async () => {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        params.append('in', form.in);
        params.append('nu', form.nu);
        params.append('co', form.co);
        params.append('to', form.to);

        // 添加選中的地點 ID
        const selectedIds = [...selectedLocations.value].filter(id => typeof id === 'number');
        if (selectedIds.length > 0 && selectedIds.length < locations.value.length) {
            // 只有當不是全選時才傳遞地點參數
            params.append('locations', selectedIds.join(','));
        }

        // 添加韵书选项
        const wanshyuSelected = [];
        if (selectedLocations.value.has('fanwan')) {
            wanshyuSelected.push('fanwan');
        }
        if (selectedLocations.value.has('jingwaa')) {
            wanshyuSelected.push('jingwaa');
        }
        // 始终发送 wanshyu 参数
        if (wanshyuSelected.length === 0) {
            params.append('wanshyu', 'none');
        } else {
            wanshyuSelected.forEach(w => params.append('wanshyu[]', w));
        }

        const response = await axios.get('/api/v1.0/detail', { params });
        results.value = response.data;
        resultsVersion.value++;
    } catch (e) {
        console.error('API 错误:', e);
    } finally {
        loading.value = false;
    }
};

const getQueryString = (value) => {
    if (Array.isArray(value)) return value[0] || '';
    return value || '';
};

// 處理結果顯示
const hasResults = computed(() => {
    if (!results.value) return false;
    const ancient = results.value['韻書'] || [];
    const locs = results.value['各地'] || [];
    const hasAncientData = ancient.some(a => Object.keys(a).some(k => !k.startsWith('__')));
    const hasLocationData = locs.some(l => Object.keys(l).some(k => !k.startsWith('__')));
    return hasAncientData || hasLocationData;
});

// 根据 ID 获取地点信息
const getLocationById = (id) => {
    return locations.value.find(loc => loc.id === id) || {};
};

// 根据颜色生成选中状态的样式
const getSelectedStyle = (color) => {
    const primary = getPrimaryAreaColor(color, '#999999');
    const hex = primary.replace('#', '');
    const r = parseInt(hex.substring(0, 2), 16);
    const g = parseInt(hex.substring(2, 4), 16);
    const b = parseInt(hex.substring(4, 6), 16);
    return {
        '--selected-bg-light': `rgba(${r}, ${g}, ${b}, 0.1)`,
        '--selected-bg-dark': `rgba(${r}, ${g}, ${b}, 0.25)`,
        '--selected-text-light': `rgb(${Math.max(0, r - 50)}, ${Math.max(0, g - 50)}, ${Math.max(0, b - 50)})`,
        '--selected-text-dark': `rgb(${Math.min(255, r + 80)}, ${Math.min(255, g + 80)}, ${Math.min(255, b + 80)})`,
    };
};

onMounted(async () => {
    await loadLocations();

    const initialPron = getQueryString(route.query.pron);
    if (initialPron) {
        form.pron = initialPron;
        analyzeInput();
        await submitSearch();
    }
});
</script>

<template>
    <div class="container mx-auto px-4 pt-12 pb-12 flex flex-col items-center">
        <!-- Input Section -->
        <div class="jd-panel p-5 w-full max-w-2xl jd-enter">
            <div class="flex flex-col sm:flex-row gap-2 mb-4">
                <input v-model="form.pron" @input="analyzeInput" type="text"
                    class="jd-input flex-1 p-2 text-lg font-mono"
                    :class="{
                        '!border-green-500 focus:!shadow-[3px_3px_0_rgba(34,197,94,0.18)]': parseStatus === 'valid',
                        '!border-red-500': parseStatus === 'invalid'
                    }" placeholder="輸入擴展粵拼…">
                <button
                    @click="submitSearch"
                    :disabled="inputDisabled"
                    class="jd-btn-primary px-6 py-2"
                >
                    耖
                </button>
            </div>

            <p class="text-sm text-slate-500 dark:text-slate-400 mb-4 text-center">
                空格表示模糊匹配該位置，如 "j t6" 匹配 jyut6/jit6
            </p>

            <!-- Color Blocks Visualization -->
            <div class="flex justify-center gap-1 font-mono text-xl h-10">
                <div
                    class="w-auto min-w-10 px-2 flex items-center justify-center rounded-none border-b-2 border-red-400/70 dark:border-red-500/60 bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300 transition-[min-width] duration-150">
                    <span :key="parsedComponents.in" class="jd-tick">{{ parsedComponents.in || '-' }}</span></div>
                <div
                    class="w-auto min-w-10 px-2 flex items-center justify-center rounded-none border-b-2 border-orange-400/70 dark:border-orange-500/60 bg-orange-100 dark:bg-orange-900/50 text-orange-700 dark:text-orange-300 transition-[min-width] duration-150">
                    <span :key="parsedComponents.nu" class="jd-tick">{{ parsedComponents.nu || '-' }}</span></div>
                <div
                    class="w-auto min-w-10 px-2 flex items-center justify-center rounded-none border-b-2 border-green-400/70 dark:border-green-500/60 bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-300 transition-[min-width] duration-150">
                    <span :key="parsedComponents.co" class="jd-tick">{{ parsedComponents.co || '-' }}</span></div>
                <div
                    class="w-auto min-w-10 px-2 flex items-center justify-center rounded-none border-b-2 border-blue-400/70 dark:border-blue-500/60 bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 transition-[min-width] duration-150">
                    <span :key="parsedComponents.to" class="jd-tick">{{ parsedComponents.to || '-' }}</span></div>
            </div>
        </div>

        <!-- Location Selection -->
        <div class="jd-panel p-5 w-full max-w-4xl mt-6 jd-enter" style="--i: 2">
            <div class="flex justify-between items-center mb-3">
                <h2 class="jd-section-title text-lg">選擇地點</h2>
                <div class="flex gap-1">
                    <button @click="selectAll" class="jd-btn-ghost px-2 py-1 text-xs">全選</button>
                    <button @click="deselectAll" class="jd-btn-ghost px-2 py-1 text-xs">全不選</button>
                    <button @click="invertSelection" class="jd-btn-ghost px-2 py-1 text-xs">反選</button>
                </div>
            </div>

            <LoadingBlocks v-if="loadingLocations" size="sm" class="py-4" />

            <div v-else class="max-h-60 overflow-y-auto overflow-x-hidden jd-enter-fade">
                <!-- 韻書選項 -->
                <div class="mb-3">
                    <h3 class="text-sm font-bold text-slate-600 dark:text-slate-400 mb-2 border-l-4 border-wood pl-2">韻書</h3>
                    <div class="flex flex-wrap gap-2">
                        <label class="flex items-center gap-1.5 px-3 py-1.5 rounded-none cursor-pointer transition-[transform,background-color,border-color,color] duration-150 text-sm border-l-4 hover:-translate-y-px active:translate-y-0"
                            :class="selectedLocations.has('fanwan') ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 border-amber-500' : 'bg-slate-50 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:border-amber-400'">
                            <input type="checkbox" :checked="selectedLocations.has('fanwan')" @change="toggleLocation('fanwan')" class="w-3 h-3 accent-amber-600">
                            分韻
                        </label>
                        <label class="flex items-center gap-1.5 px-3 py-1.5 rounded-none cursor-pointer transition-[transform,background-color,border-color,color] duration-150 text-sm border-l-4 hover:-translate-y-px active:translate-y-0"
                            :class="selectedLocations.has('jingwaa') ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 border-amber-500' : 'bg-slate-50 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:border-amber-400'">
                            <input type="checkbox" :checked="selectedLocations.has('jingwaa')" @change="toggleLocation('jingwaa')" class="w-3 h-3 accent-amber-600">
                            英華
                        </label>
                    </div>
                </div>

                <!-- 各地點 -->
                <div v-for="(locs, division) in groupedLocations" :key="division" class="mb-3">
                    <h3 class="text-sm font-bold text-slate-600 dark:text-slate-400 mb-2 pl-2 border-b border-slate-200 dark:border-slate-700 pb-1">{{ division }}</h3>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="loc in locs" :key="loc.id"
                            class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-none cursor-pointer transition-[transform,background-color,border-color,color] duration-150 text-sm border hover:-translate-y-px active:translate-y-0 location-label"
                            :style="selectedLocations.has(loc.id) ? getSelectedStyle(loc.color) : {}"
                            :class="selectedLocations.has(loc.id)
                                ? 'selected border-accent/40 dark:border-red-500/40'
                                : 'border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700'">
                            <input type="checkbox" :checked="selectedLocations.has(loc.id)" @change="toggleLocation(loc.id)" class="w-3 h-3 accent-[#D32913]">
                            <AreaColorBadge :color="loc.color" badge-class="w-3.5 h-3.5" />
                            <span>{{ loc.second }}{{ loc.third ? ' ' + loc.third : '' }}</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results -->
        <LoadingBlocks v-if="loading" class="mt-8" />

        <div v-if="hasResults && !loading" :key="resultsVersion" class="mt-8 w-full max-w-4xl jd-enter">
            <!-- 韻書結果 -->
            <div v-if="results['韻書'] && results['韻書'].length > 0" class="mb-6">
                <h2 class="jd-section-title !border-wood text-xl mb-4">韻書</h2>
                <div v-for="(book, idx) in results['韻書']" :key="idx" class="jd-panel mb-4 p-4 border-l-4 !border-l-wood/70 jd-enter" :style="{ '--i': idx }">
                    <h3 class="font-bold text-slate-700 dark:text-slate-300 mb-2">{{ book.__name }}</h3>
                    <div v-for="(tones, pron) in book" :key="pron" class="mb-2">
                        <template v-if="pron !== '__name'">
                            <span class="font-mono text-accent">{{ pron }}</span>
                            <span v-for="(chars, tone) in tones" :key="tone" class="ml-2">
                                <span class="text-amber-600 dark:text-amber-400">{{ tone }}</span>
                                <span class="text-slate-700 dark:text-slate-300">{{ chars }}</span>
                            </span>
                        </template>
                    </div>
                </div>
            </div>

            <!-- 各地結果 -->
            <div v-if="results['各地'] && results['各地'].length > 0">
                <h2 class="jd-section-title text-xl mb-4">各地</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 jd-stagger">
                    <div v-for="loc in results['各地']" :key="loc.__id"
                        class="relative p-3 pl-4 rounded-none border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 jd-lift">
                        <div class="absolute left-0 top-0 bottom-0 w-1" :style="{ backgroundColor: getPrimaryAreaColor(getLocationById(loc.__id).color, '#999999') }"></div>
                        <div class="flex items-center gap-2 mb-1 border-b border-slate-100 dark:border-slate-700 pb-1">
                            <AreaColorBadge :color="getLocationById(loc.__id).color" badge-class="w-3.5 h-3.5" />
                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ getLocationById(loc.__id).second }}</span>
                            <span v-if="getLocationById(loc.__id).third" class="text-sm text-slate-500">{{ getLocationById(loc.__id).third }}</span>
                        </div>
                        <div v-for="(tones, pron) in loc" :key="pron" class="text-sm">
                            <template v-if="!pron.startsWith('__')">
                                <span class="font-mono text-accent">{{ pron }}</span>
                                <span v-for="(chars, tone) in tones" :key="tone" class="ml-1">
                                    <span class="text-amber-600 dark:text-amber-400">{{ tone }}</span>
                                    <span class="text-slate-700 dark:text-slate-300">{{ chars }}</span>
                                </span>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.location-label.selected {
    background-color: var(--selected-bg-light);
    color: var(--selected-text-light);
}

.dark .location-label.selected {
    background-color: var(--selected-bg-dark);
    color: var(--selected-text-dark);
}
</style>
