<script setup>
import { ref, computed, onMounted } from 'vue';
import {
    LOCATION_NAMES,
    LOCATION_COLOR,
    CHARACTERISTIC_PAIRS,
    CHARACTERISTICS_MATRIX
} from '../data/characteristics_matrix.js';

// Icons replacement (Using simple SVGs or just text for simplicity if lucide is not available, 
// but since we are in Vue SFC, we can just inline SVGs for the few we need)
// Icons: Check, X, Minus, Menu, Search, Upload, Download, RotateCcw, Info, ChevronUp, ChevronDown, BarChart3, ArrowUp

const userVector = ref(new Array(CHARACTERISTIC_PAIRS.length).fill(0));
const featureSearch = ref('');
const isSidenavOpen = ref(false);
const isInfoExpanded = ref(false);

const progressCount = computed(() => userVector.value.filter(v => v !== 0).length);

const getBarColor = (name) => {
    return LOCATION_COLOR[name] || '#0d1014';
};

const rankings = computed(() => {
    const results = LOCATION_NAMES.map((dialectName, index) => {
        const dialectVector = CHARACTERISTICS_MATRIX[index];
        let dotProduct = 0;
        let activeUserFeatures = 0;

        for (let i = 0; i < dialectVector.length; i++) {
            // userVector is ref, use .value if accessing inside JS logic, but here inside computed it's auto-unwrapped if we use it correctly? 
            // Wait, userVector is an array inside a ref. userVector.value[i]
            if (userVector.value[i] !== 0) {
                activeUserFeatures++;
                if (dialectVector[i] === userVector.value[i]) {
                    dotProduct += 1;
                } else if (dialectVector[i] !== 0) {
                    dotProduct -= 1;
                }
            }
        }

        let similarity = 0;
        if (activeUserFeatures > 0) {
            const rawScore = dotProduct / activeUserFeatures;
            similarity = ((rawScore + 1) / 2) * 100;
        }

        const completeCharacteristicThreshold = CHARACTERISTIC_PAIRS.length / 3;
        const activeUserFeatureSquare = activeUserFeatures * activeUserFeatures;
        const similarityCoefficient = 1 / (completeCharacteristicThreshold / (activeUserFeatureSquare || 1) + 1);

        return {
            id: index,
            name: dialectName,
            similarity: similarity * similarityCoefficient
        };
    });

    return results.sort((a, b) => b.similarity - a.similarity);
});

const topRankName = computed(() =>
    rankings.value.length > 0 && rankings.value[0].similarity > 80 ? rankings.value[0].name : ''
);

// Helper for color mixing (simplified port)
const hexToRgb = (hex) => {
    const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return result ? {
        r: parseInt(result[1], 16),
        g: parseInt(result[2], 16),
        b: parseInt(result[3], 16)
    } : null;
};
const rgbToHex = (r, g, b) => {
    return "#" + ((1 << 24) + (Math.round(r) << 16) + (Math.round(g) << 8) + Math.round(b)).toString(16).slice(1);
};
const mixedColor = computed(() => {
    const topCandidates = rankings.value.slice(0, 5);
    if (!topCandidates || topCandidates.length === 0) return 'transparent';

    let totalWeight = 0, totalR = 0, totalG = 0, totalB = 0;
    const similarityThreshold = 80;
    const validCandidates = topCandidates.filter(c => c.similarity > similarityThreshold);

    if (validCandidates.length === 0) return 'transparent';

    validCandidates.forEach(item => {
        const colorHex = getBarColor(item.name);
        if (!colorHex) return;
        const rgb = hexToRgb(colorHex);
        if (rgb) {
            const normSimilarity = (item.similarity - similarityThreshold) / (100 - similarityThreshold);
            const weight = Math.pow(normSimilarity, 3);
            totalR += rgb.r * weight;
            totalG += rgb.g * weight;
            totalB += rgb.b * weight;
            totalWeight += weight;
        }
    });

    if (totalWeight === 0) return 'transparent';
    return rgbToHex(totalR / totalWeight, totalG / totalWeight, totalB / totalWeight);
});


const handleOptionChange = (index, value) => {
    const next = [...userVector.value];
    next[index] = value;
    userVector.value = next;
};

const resetAll = () => {
    if (confirm("確定要清空所有選擇嗎？")) {
        userVector.value = new Array(CHARACTERISTIC_PAIRS.length).fill(0);
    }
};

const loadDialectData = (index, name) => {
    if (confirm(`確定要將所有選項重置爲【${name}】的數據嗎？`)) {
        userVector.value = [...CHARACTERISTICS_MATRIX[index]];
    }
};

</script>

<template>
    <div
        class="flex flex-col h-[calc(100vh-64px)] bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 overflow-hidden font-sans">

        <!-- Toolbar -->
        <div
            class="bg-white dark:bg-slate-800 border-b border-gray-200 dark:border-slate-700 px-4 py-2 flex items-center justify-between shadow-sm z-20 h-14 shrink-0">
            <div class="flex items-center gap-3">
                <h1 class="text-xl font-bold hidden sm:block">粵語探針</h1>
                <div
                    class="text-sm bg-slate-100 dark:bg-slate-700 px-2 py-1 rounded text-slate-500 dark:text-slate-300">
                    <span class="font-bold text-blue-600 dark:text-blue-400">{{ progressCount }}</span>
                    <span class="hidden sm:inline"> / {{ CHARACTERISTIC_PAIRS.length }}</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <input v-model="featureSearch" type="text" placeholder="搜特徵..."
                    class="pl-3 pr-2 py-1 text-sm border border-slate-300 dark:border-slate-600 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 w-32 sm:w-48 bg-slate-50 dark:bg-slate-700 dark:text-white">
                <button @click="resetAll"
                    class="px-3 py-1.5 text-sm font-medium text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 rounded-md">
                    重置
                </button>
            </div>
        </div>

        <div class="flex-1 flex flex-col lg:flex-row overflow-hidden relative">

            <!-- Characteristics Grid -->
            <div class="flex-1 flex flex-col min-w-0 h-full overflow-hidden bg-slate-100/50 dark:bg-slate-900/50">
                <div class="flex-1 overflow-y-auto p-2 scroll-smooth">
                    <div class="grid grid-cols-2 xs:grid-cols-3 sm:grid-cols-4 md:grid-cols-5 xl:grid-cols-7 gap-2 m-2">
                        <template v-for="(name, index) in CHARACTERISTIC_PAIRS" :key="index">
                            <div v-if="!featureSearch || name.includes(featureSearch)"
                                class="border dark:border-slate-700 rounded-md p-2 flex flex-col justify-between gap-2 transition-colors"
                                :class="{
                                    'bg-white dark:bg-slate-800': userVector[index] === 0,
                                    'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800': userVector[index] === 1,
                                    'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800': userVector[index] === -1
                                }">
                                <div class="flex justify-between items-start">
                                    <span class="text-sm font-medium truncate w-full" :title="name">{{ name }}</span>
                                    <span class="text-[10px] text-slate-400 font-mono shrink-0">#{{ index + 1 }}</span>
                                </div>
                                <div class="grid grid-cols-3 gap-1">
                                    <button @click="handleOptionChange(index, 1)"
                                        class="h-6 rounded text-xs flex items-center justify-center transition-colors"
                                        :class="userVector[index] === 1 ? 'bg-green-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-400 hover:bg-green-100 dark:hover:bg-green-900 hover:text-green-600'">
                                        ✓
                                    </button>
                                    <button @click="handleOptionChange(index, -1)"
                                        class="h-6 rounded text-xs flex items-center justify-center transition-colors"
                                        :class="userVector[index] === -1 ? 'bg-red-500 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-400 hover:bg-red-100 dark:hover:bg-red-900 hover:text-red-600'">
                                        ✗
                                    </button>
                                    <button @click="handleOptionChange(index, 0)"
                                        class="h-6 rounded text-xs flex items-center justify-center transition-colors"
                                        :class="userVector[index] === 0 ? 'bg-slate-200 dark:bg-slate-600 text-slate-600 dark:text-slate-300' : 'bg-slate-50 dark:bg-slate-700 text-slate-300 hover:bg-slate-200'">
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
                            <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                                成對的兩個字，只要一者較另一者多出了一個音，即爲不同；少數情況下，二字都有多音，則要求讀音分別相同，沒有多出來的音。
                            </p>
                        </div>
                        <div class="pt-2 border-t border-slate-100 dark:border-slate-700 text-center mt-2">
                            <p class="text-[10px] text-slate-400">
                                © 2019-2026 <a href="https://jyutjam.org"
                                    class="hover:text-slate-600 dark:hover:text-slate-300">嶺南粵音</a> <a
                                    href="https://jyutdict.org"
                                    class="hover:text-slate-600 dark:hover:text-slate-300">泛粵大典</a> 開發組 版權所有
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
