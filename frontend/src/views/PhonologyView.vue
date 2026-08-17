<script setup>
import { ref, computed } from 'vue';
import {
    LOCATION_NAMES,
    LOCATION_COLOR,
    EFFICIENT_CHARACTERISTIC_PAIRS,
    EFFICIENT_CHARACTERISTICS_MATRIX,
    SIMPLIFIED_CHARACTERISTIC_INDICES,
    CHARACTERISTIC_PAIRS,
    CHARACTERISTICS_MATRIX
} from '../data/characteristics_matrix.js';

const efficientUserVector = ref(new Array(EFFICIENT_CHARACTERISTIC_PAIRS.length).fill(0));
const traditionalUserVector = ref(new Array(CHARACTERISTIC_PAIRS.length).fill(0));
const featureSearch = ref('');
const isInfoExpanded = ref(false);
const mode = ref('efficient');

const isEfficientMode = computed(() => mode.value === 'efficient');
const isSimplifiedMode = computed(() => mode.value === 'simplified');
const activeCharacteristicPairs = computed(() => isEfficientMode.value
    ? EFFICIENT_CHARACTERISTIC_PAIRS
    : CHARACTERISTIC_PAIRS
);
const activeCharacteristicsMatrix = computed(() => isEfficientMode.value
    ? EFFICIENT_CHARACTERISTICS_MATRIX
    : CHARACTERISTICS_MATRIX
);
const activeUserVector = computed(() => isEfficientMode.value
    ? efficientUserVector.value
    : traditionalUserVector.value
);
const visibleCharacteristicIndices = computed(() => {
    if (isEfficientMode.value) return EFFICIENT_CHARACTERISTIC_PAIRS.map((_, index) => index);
    if (isSimplifiedMode.value) return SIMPLIFIED_CHARACTERISTIC_INDICES;
    return CHARACTERISTIC_PAIRS.map((_, index) => index);
});
const visibleCharacteristics = computed(() => visibleCharacteristicIndices.value.map(index => ({
    index,
    name: activeCharacteristicPairs.value[index]
})));
const progressCount = computed(() => visibleCharacteristicIndices.value
    .filter(index => activeUserVector.value[index] !== 0).length
);

const setActiveUserVector = (next) => {
    if (isEfficientMode.value) efficientUserVector.value = next;
    else traditionalUserVector.value = next;
};

const getBarColor = (name) => LOCATION_COLOR[name] || '#0d1014';

const rankings = computed(() => {
    const results = LOCATION_NAMES.map((dialectName, index) => {
        const dialectVector = activeCharacteristicsMatrix.value[index];
        let dotProduct = 0;
        let comparableUserFeatures = 0;

        for (const featureIndex of visibleCharacteristicIndices.value) {
            const userValue = activeUserVector.value[featureIndex];
            const dialectValue = dialectVector[featureIndex];
            if (userValue === 0 || dialectValue === 0) continue;
            comparableUserFeatures++;
            dotProduct += dialectValue === userValue ? 1 : -1;
        }

        let similarity = 0;
        if (comparableUserFeatures > 0) {
            const rawScore = dotProduct / comparableUserFeatures;
            similarity = ((rawScore + 1) / 2) * 100;
        }

        const completeCharacteristicThreshold = visibleCharacteristicIndices.value.length / 3;
        const comparableFeatureSquare = comparableUserFeatures * comparableUserFeatures;
        const similarityCoefficient = 1 / (completeCharacteristicThreshold / (comparableFeatureSquare || 1) + 1);

        return {
            id: index,
            name: dialectName,
            similarity: similarity * similarityCoefficient
        };
    });

    return results.sort((left, right) => right.similarity - left.similarity);
});

const topRankName = computed(() =>
    rankings.value.length > 0 && rankings.value[0].similarity > 80 ? rankings.value[0].name : ''
);

const hexToRgb = (hex) => {
    const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return result ? {
        r: parseInt(result[1], 16),
        g: parseInt(result[2], 16),
        b: parseInt(result[3], 16)
    } : null;
};
const rgbToHex = (r, g, b) =>
    "#" + ((1 << 24) + (Math.round(r) << 16) + (Math.round(g) << 8) + Math.round(b)).toString(16).slice(1);
const mixedColor = computed(() => {
    const topCandidates = rankings.value.slice(0, 5);
    let totalWeight = 0, totalR = 0, totalG = 0, totalB = 0;
    const similarityThreshold = 80;
    const validCandidates = topCandidates.filter(candidate => candidate.similarity > similarityThreshold);

    for (const item of validCandidates) {
        const rgb = hexToRgb(getBarColor(item.name));
        if (!rgb) continue;
        const normSimilarity = (item.similarity - similarityThreshold) / (100 - similarityThreshold);
        const weight = Math.pow(normSimilarity, 3);
        totalR += rgb.r * weight;
        totalG += rgb.g * weight;
        totalB += rgb.b * weight;
        totalWeight += weight;
    }

    return totalWeight === 0
        ? 'transparent'
        : rgbToHex(totalR / totalWeight, totalG / totalWeight, totalB / totalWeight);
});

const handleOptionChange = (index, value) => {
    const next = [...activeUserVector.value];
    next[index] = value;
    setActiveUserVector(next);
};

const resetAll = () => {
    if (confirm("確定要清空目前模式的所有選擇嗎？")) {
        setActiveUserVector(new Array(activeCharacteristicPairs.value.length).fill(0));
    }
};

const loadDialectData = (index, name) => {
    if (!confirm(`確定要將目前模式的選項重置爲【${name}】的數據嗎？`)) return;
    if (isEfficientMode.value) {
        efficientUserVector.value = [...EFFICIENT_CHARACTERISTICS_MATRIX[index]];
        return;
    }
    const next = new Array(CHARACTERISTIC_PAIRS.length).fill(0);
    for (const featureIndex of visibleCharacteristicIndices.value) {
        next[featureIndex] = CHARACTERISTICS_MATRIX[index][featureIndex];
    }
    traditionalUserVector.value = next;
};
</script>

<template>
    <div
        class="flex flex-col h-[calc(100vh-64px)] bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 overflow-hidden font-sans">

        <!-- Toolbar -->
        <div
            class="bg-white dark:bg-slate-800 border-b border-gray-200 dark:border-slate-700 px-2 sm:px-4 py-2 flex items-center justify-between shadow-sm z-20 h-14 shrink-0">
            <div class="flex items-center gap-3">
                <h1 class="text-xl font-bold hidden sm:block">粵語探針</h1>
                <div
                    class="text-sm bg-slate-100 dark:bg-slate-700 px-2 py-1 rounded text-slate-500 dark:text-slate-300">
                    <span class="font-bold text-blue-600 dark:text-blue-400">{{ progressCount }}</span>
                    <span class="hidden sm:inline"> / {{ visibleCharacteristicIndices.length }}</span>
                </div>
            </div>

            <div class="flex items-center gap-1 sm:gap-2 min-w-0">
                <div class="inline-flex rounded-md bg-slate-100 p-0.5 dark:bg-slate-700" role="group" aria-label="特徵顯示模式">
                    <button type="button" class="rounded px-1.5 sm:px-2 py-1 text-xs font-medium transition-colors"
                        :class="isEfficientMode ? 'bg-accent text-white shadow-sm' : 'text-slate-500 hover:text-slate-800 dark:text-slate-300 dark:hover:text-white'"
                        :aria-pressed="isEfficientMode" @click="mode = 'efficient'">
                        高效
                    </button>
                    <button type="button" class="rounded px-1.5 sm:px-2 py-1 text-xs font-medium transition-colors"
                        :class="isSimplifiedMode ? 'bg-accent text-white shadow-sm' : 'text-slate-500 hover:text-slate-800 dark:text-slate-300 dark:hover:text-white'"
                        :aria-pressed="isSimplifiedMode" @click="mode = 'simplified'">
                        簡化
                    </button>
                    <button type="button" class="rounded px-1.5 sm:px-2 py-1 text-xs font-medium transition-colors"
                        :class="mode === 'complete' ? 'bg-accent text-white shadow-sm' : 'text-slate-500 hover:text-slate-800 dark:text-slate-300 dark:hover:text-white'"
                        :aria-pressed="mode === 'complete'" @click="mode = 'complete'">
                        完整
                    </button>
                </div>
                <input v-model="featureSearch" type="text" placeholder="搜特徵..."
                    class="pl-2 sm:pl-3 pr-2 py-1 text-sm border border-slate-300 dark:border-slate-600 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 w-20 sm:w-48 bg-slate-50 dark:bg-slate-700 dark:text-white">
                <button @click="resetAll"
                    class="px-2 sm:px-3 py-1.5 text-sm font-medium text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 rounded-md">
                    重置
                </button>
            </div>
        </div>

        <div class="flex-1 flex flex-col lg:flex-row overflow-hidden relative">

            <!-- Characteristics Grid -->
            <div class="flex-1 flex flex-col min-w-0 h-full overflow-hidden bg-slate-100/50 dark:bg-slate-900/50">
                <div class="flex-1 overflow-y-auto p-2 scroll-smooth">
                    <div class="grid grid-cols-2 xs:grid-cols-3 sm:grid-cols-4 md:grid-cols-5 xl:grid-cols-7 gap-2 m-2">
                        <template v-for="(item, displayIndex) in visibleCharacteristics" :key="item.index">
                            <div v-if="!featureSearch || item.name.includes(featureSearch)"
                                class="border dark:border-slate-700 rounded-md p-2 flex flex-col justify-between gap-2 transition-colors"
                                :class="{
                                    'bg-white dark:bg-slate-800': activeUserVector[item.index] === 0,
                                    'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800': activeUserVector[item.index] === 1,
                                    'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800': activeUserVector[item.index] === -1
                                }">
                                <div class="flex justify-between items-start">
                                    <span class="text-sm font-medium truncate w-full" :title="item.name">{{ item.name }}</span>
                                    <span class="text-[10px] text-slate-400 font-mono shrink-0">#{{ displayIndex + 1 }}</span>
                                </div>
                                <div class="grid grid-cols-3 gap-1">
                                    <button @click="handleOptionChange(item.index, 1)"
                                        class="h-6 rounded text-xs flex items-center justify-center transition-colors"
                                        :class="activeUserVector[item.index] === 1 ? 'bg-green-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-400 hover:bg-green-100 dark:hover:bg-green-900 hover:text-green-600'">
                                        ✓
                                    </button>
                                    <button @click="handleOptionChange(item.index, -1)"
                                        class="h-6 rounded text-xs flex items-center justify-center transition-colors"
                                        :class="activeUserVector[item.index] === -1 ? 'bg-red-500 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-400 hover:bg-red-100 dark:hover:bg-red-900 hover:text-red-600'">
                                        ✗
                                    </button>
                                    <button @click="handleOptionChange(item.index, 0)"
                                        class="h-6 rounded text-xs flex items-center justify-center transition-colors"
                                        :class="activeUserVector[item.index] === 0 ? 'bg-slate-200 dark:bg-slate-600 text-slate-600 dark:text-slate-300' : 'bg-slate-50 dark:bg-slate-700 text-slate-300 hover:bg-slate-200'">
                                        -
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Bottom Info Panel -->
                <div
                    class="shrink-0 bg-white dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 shadow-lg z-10 p-4 transition-all duration-300">
                    <div class="flex justify-between items-center cursor-pointer mb-2"
                        @click="isInfoExpanded = !isInfoExpanded">
                        <span class="text-sm font-semibold flex items-center gap-2">
                            關於本頁
                            <span v-if="!isInfoExpanded && topRankName"
                                class="text-xs px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 font-normal">
                                最接近: {{ topRankName }}
                            </span>
                        </span>
                        <button class="text-slate-400">{{ isInfoExpanded ? '▼' : '▲' }}</button>
                    </div>

                    <div v-show="isInfoExpanded" class="space-y-3 animate-fade-in">
                        <div class="flex items-center gap-3">
                            <div class="w-6 h-6 rounded-full shadow-sm ring-2 ring-white dark:ring-slate-700"
                                :style="{ backgroundColor: mixedColor }"></div>
                            <p v-if="isEfficientMode" class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                                高效模式從本站及普通話中篩選 {{ EFFICIENT_CHARACTERISTIC_PAIRS.length }} 對高區分度常用字。
                                以主成分分析降低特徵冗餘，再優先拉開本站各點的特徵距離。
                                每對均比較包含聲調的完整讀音；訓讀、錯讀、存疑、推導音、又音及標明層次的文白讀音不參與，多音仍有歧義時則不計該地。
                            </p>
                            <p v-else class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                                成對的兩個字，只要一者較另一者多出了一個音，即爲不同；少數情況下，二字都有多音，則要求讀音分別相同，沒有多出來的音。
                                簡化模式以本站粵語字表篩選的 {{ SIMPLIFIED_CHARACTERISTIC_INDICES.length }} 對去冗餘特徵，覆蓋樣本中所有可區分的地點組合。
                            </p>
                        </div>

                        <!-- Footer inside Info Panel -->
                        <div
                            class="pt-2 border-t border-slate-100 dark:border-slate-700 text-center mt-2 hidden sm:block">
                            <p class="text-[10px] text-slate-400 dark:text-slate-500 leading-relaxed">
                                © 2019-2026 
                                <a href="https://jyutjam.org" target="_blank" class="hover:text-slate-600 dark:hover:text-slate-300 transition-colors">嶺南粵音</a>
                                <a
                                href="https://jyutdict.org" target="_blank"
                                class="hover:text-slate-600 dark:hover:text-slate-300 transition-colors">泛粵大典</a>
                                開發組 |
                                <a href="https://github.com/EcRal5t/jyutdict" class="hover:text-slate-600 dark:hover:text-slate-300 transition-colors">網站依 MIT 開放</a>
                                ，數據保留所有權
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rankings Sidebar -->
            <div
                class="w-full lg:w-80 h-48 lg:h-auto border-t lg:border-t-0 lg:border-l border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex flex-col shrink-0 z-10">
                <div
                    class="p-3 bg-slate-50 dark:bg-slate-700 border-b border-slate-100 dark:border-slate-600 flex justify-between items-center">
                    <h2 class="font-semibold text-sm">相似度排行</h2>
                    <span class="text-xs text-slate-400">已計入 {{ LOCATION_NAMES.length }} 點</span>
                </div>
                <div class="flex-1 overflow-y-auto">
                    <template v-for="(item, rank) in rankings" :key="item.id">
                        <div @click="loadDialectData(item.id, item.name)"
                            class="relative px-3 py-2 border-b border-slate-50 dark:border-slate-700 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700 group overflow-hidden">
                            <!-- Bar Background -->
                            <div class="absolute inset-0 opacity-10 pointer-events-none transition-all duration-500 ease-out"
                                :style="{ width: item.similarity + '%', backgroundColor: getBarColor(item.name) }">
                            </div>

                            <div class="relative z-10 flex justify-between items-center">
                                <div class="flex items-center gap-2 overflow-hidden">
                                    <span
                                        class="w-5 h-5 flex items-center justify-center rounded text-[10px] font-bold shrink-0"
                                        :class="rank < 3 ? 'bg-yellow-100 dark:bg-yellow-900 text-yellow-700 dark:text-yellow-400' : 'bg-slate-100 dark:bg-slate-700 text-slate-400'">
                                        {{ rank + 1 }}
                                    </span>
                                    <span class="text-sm font-medium truncate">{{ item.name }}</span>
                                </div>
                                <span class="text-sm font-bold"
                                    :style="{ color: item.similarity > 50 ? getBarColor(item.name) : '#94a3b8' }">
                                    {{ item.similarity.toFixed(0) }}%
                                </span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

        </div>
    </div>
</template>
