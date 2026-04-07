<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import axios from 'axios';

// 粵拼解析正則
// 空格在音節中表示模糊匹配該位置
const format = /^[a-z ]{1,10}\d{0,2}$/;
// 聲母正則：允許後面是元音或空格（模糊匹配韻核）
const initialFormat = /^^(mb?|n[jrd]?|ngg?|[bdg]{1,2}|g[hn]?|r[bdgzscrh]|[zcs][hrjl]?|[ptkvw]h?|[hqfjlrx0])([jwv]?)(?=[aeoiuymn])/;
// 韻尾正則：匹配末尾的韻尾（不包含前面的元音）
const codaFormat = /(?<=[aoreiwuy])(n[ng]?|[mptkh])(?=[\\d`*]|$)$/;
const toneFormat = /[0-9]?[0-9*][0-9']?(`\\d+)?$/;
const vowelFormat = /(^ng?$|^m$|i[rwi]?|u[rwu]?|[aeo][aeowr]?|yu$|y)$/;

const form = reactive({
    pron: '',
    in: '',
    nu: '',
    co: '',
    to: ''
});

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
        const response = await axios.get('/api/v1.0/detail.php', {
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
// 規則：
// - 空格在音節中間表示該位置模糊匹配
// - 沒有輸入的部分是精確匹配（空字符串）
// - 例如 "gwaa" -> 精確匹配 gwaa
// - 例如 "gwaa " -> 模糊匹配韻尾
// - 例如 "j t6" -> 模糊匹配韻核
const analyzeInput = () => {
    const pron = form.pron.trim();
    // Reset parsed components
    parsedComponents.in = '';
    parsedComponents.nu = '';
    parsedComponents.co = '';
    parsedComponents.to = '';

    if (!pron) {
        parseStatus.value = 'neutral';
        inputDisabled.value = true;
        return;
    }

    // 提取聲調（末尾數字）
    const toneMatch = pron.match(toneFormat);
    const tone = toneMatch ? toneMatch[0] : "";
    const withoutTone = tone ? pron.slice(0, -tone.length) : pron;

    // 提取聲母
    const initialMatch = withoutTone.match(initialFormat);
    const initial = initialMatch ? initialMatch[1] : "";
    const afterInitial = initial ? withoutTone.slice(initial.length) : withoutTone;

    // 提取韻尾
    let coda = "";
    let nuclei = afterInitial;
    const codaMatch = afterInitial.match(codaFormat);
    if (codaMatch) {
        coda = codaMatch[1];
        nuclei = afterInitial.slice(0, -coda.length);
    }

    // 驗證韻核
    if (nuclei === '' && initial === '' && coda === '') {
        parseStatus.value = 'invalid';
        inputDisabled.value = true;
        return;
    }

    // 檢查韻核是否有效（可能包含空格表示模糊匹配）
    const nucleiWithoutSpace = nuclei.replace(/ /g, '');
    if (nucleiWithoutSpace) {
        // 驗證非空格部分是否是有效的元音組合
        let pos = 0;
        let validVowels = true;
        while (pos < nucleiWithoutSpace.length) {
            const sub = nucleiWithoutSpace.substr(pos);
            const match = sub.match(vowelFormat);
            if (match) {
                pos += match[0].length;
            } else {
                validVowels = false;
                break;
            }
        }
        if (!validVowels) {
            parseStatus.value = 'invalid';
            inputDisabled.value = true;
            return;
        }
    }

    // 設置解析結果
    parsedComponents.in = initial;
    parsedComponents.nu = nuclei;
    parsedComponents.co = coda;
    parsedComponents.to = tone;

    // 轉換為查詢參數
    // 規則：
    // - 空格表示模糊匹配該位置
    // - 未輸入的組件（聲調）使用 % 模糊匹配
    // - 已輸入的組件使用精確匹配
    form.in = initial === ' ' ? '%' : (initial || '');
    form.nu = nuclei.includes(' ') ? nuclei.replace(/ /g, '%') : (nuclei || '');
    form.co = coda === ' ' ? '%' : (coda || '');
    // 聲調：未輸入時使用 % 模糊匹配所有聲調
    form.to = tone || '%';

    parseStatus.value = 'valid';
    inputDisabled.value = false;
};

// 結果相關
const results = ref(null);
const loading = ref(false);

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
        // 空数组时发送 wanshyu=none 表示不查询任何韵书
        if (wanshyuSelected.length === 0) {
            params.append('wanshyu', 'none');
        } else {
            wanshyuSelected.forEach(w => params.append('wanshyu[]', w));
        }

        const response = await axios.get('/api/v1.0/detail.php', { params });
        results.value = response.data;
    } catch (e) {
        console.error('API 错误:', e);
    } finally {
        loading.value = false;
    }
};

// 處理結果顯示
const hasResults = computed(() => {
    if (!results.value) return false;
    const ancient = results.value['韻書'] || [];
    const locs = results.value['各地'] || [];
    // 检查是否有非 __ 开头的 key（即音节数据）
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
    if (!color) color = '#999999';
    // 将颜色转换为 RGB
    const hex = color.replace('#', '');
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

onMounted(() => {
    loadLocations();
});
</script>

<template>
    <div class="container mx-auto px-4 pt-20 pb-12 flex flex-col items-center">
        <!-- Input Section -->
        <div class="bg-white dark:bg-slate-800 rounded-none shadow-[6px_6px_0_rgba(0,0,0,0.06)] dark:shadow-[6px_6px_0_rgba(0,0,0,0.3)] p-5 w-full max-w-2xl border border-gray-100 dark:border-slate-700">
            <!-- <h1 class="text-3xl font-bold mb-6 text-center text-slate-800 dark:text-slate-100 font-serif">粵語檢音</h1> -->

            <div class="flex flex-col sm:flex-row gap-2 mb-4">
                <input v-model="form.pron" @input="analyzeInput" type="text"
                    class="flex-1 p-2 text-lg font-mono border-2 rounded-none outline-none transition-colors dark:bg-slate-900 dark:text-white"
                    :class="{
                        'border-gray-200 dark:border-slate-700': parseStatus === 'neutral',
                        'border-green-500': parseStatus === 'valid',
                        'border-red-500': parseStatus === 'invalid'
                    }" placeholder="輸入擴展粵拼…">
                <button
                    @click="submitSearch"
                    :disabled="inputDisabled"
                    class="px-6 py-2 font-bold text-white rounded-none transition-all shadow-[4px_4px_0_rgba(0,0,0,0.2)] active:translate-y-0 active:shadow-[2px_2px_0_rgba(0,0,0,0.2)] disabled:opacity-50 disabled:cursor-not-allowed disabled:shadow-none"
                    :class="inputDisabled ? 'bg-gray-400' : 'bg-green-600 hover:bg-green-700 hover:shadow-[6px_6px_0_rgba(0,0,0,0.25)] hover:-translate-y-0.5'"
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
                    class="w-auto min-w-10 px-2 flex items-center justify-center rounded-none bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300">
                    {{ parsedComponents.in || '-' }}</div>
                <div
                    class="w-auto min-w-10 px-2 flex items-center justify-center rounded-none bg-orange-100 dark:bg-orange-900/50 text-orange-700 dark:text-orange-300">
                    {{ parsedComponents.nu || '-' }}</div>
                <div
                    class="w-auto min-w-10 px-2 flex items-center justify-center rounded-none bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-300">
                    {{ parsedComponents.co || '-' }}</div>
                <div
                    class="w-auto min-w-10 px-2 flex items-center justify-center rounded-none bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300">
                    {{ parsedComponents.to || '-' }}</div>
            </div>
        </div>

        <!-- Location Selection -->
        <div class="bg-white dark:bg-slate-800 rounded-none shadow-[6px_6px_0_rgba(0,0,0,0.06)] dark:shadow-[6px_6px_0_rgba(0,0,0,0.3)] p-5 w-full max-w-4xl mt-6 border border-gray-100 dark:border-slate-700">
            <div class="flex justify-between items-center mb-3">
                <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100 border-l-4 border-accent pl-3">選擇地點</h2>
                <div class="flex gap-1">
                    <button @click="selectAll" class="px-2 py-1 text-xs bg-slate-200 dark:bg-slate-700 rounded-none hover:bg-slate-300 dark:hover:bg-slate-600 transition-colors">全選</button>
                    <button @click="deselectAll" class="px-2 py-1 text-xs bg-slate-200 dark:bg-slate-700 rounded-none hover:bg-slate-300 dark:hover:bg-slate-600 transition-colors">全不選</button>
                    <button @click="invertSelection" class="px-2 py-1 text-xs bg-slate-200 dark:bg-slate-700 rounded-none hover:bg-slate-300 dark:hover:bg-slate-600 transition-colors">反選</button>
                </div>
            </div>

            <div v-if="loadingLocations" class="text-center py-4 text-slate-500">載入中...</div>

            <div v-else class="max-h-60 overflow-y-auto overflow-x-hidden">
                <!-- 韻書選項 -->
                <div class="mb-3">
                    <h3 class="text-sm font-bold text-slate-600 dark:text-slate-400 mb-2 border-l-4 border-wood pl-2">韻書</h3>
                    <div class="flex flex-wrap gap-2">
                        <label class="flex items-center gap-1.5 px-3 py-1.5 rounded-none cursor-pointer transition-all text-sm border-l-4 hover:-translate-y-0.5 hover:shadow-sm"
                            :class="selectedLocations.has('fanwan') ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 border-amber-500' : 'bg-slate-50 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:border-amber-400'">
                            <input type="checkbox" :checked="selectedLocations.has('fanwan')" @change="toggleLocation('fanwan')" class="w-3 h-3">
                            分韻
                        </label>
                        <label class="flex items-center gap-1.5 px-3 py-1.5 rounded-none cursor-pointer transition-all text-sm border-l-4 hover:-translate-y-0.5 hover:shadow-sm"
                            :class="selectedLocations.has('jingwaa') ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 border-amber-500' : 'bg-slate-50 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:border-amber-400'">
                            <input type="checkbox" :checked="selectedLocations.has('jingwaa')" @change="toggleLocation('jingwaa')" class="w-3 h-3">
                            英華
                        </label>
                    </div>
                </div>

                <!-- 各地點 -->
                <div v-for="(locs, division) in groupedLocations" :key="division" class="mb-3">
                    <h3 class="text-sm font-bold text-slate-600 dark:text-slate-400 mb-2 pl-2 border-b border-slate-200 dark:border-slate-700 pb-1">{{ division }}</h3>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="loc in locs" :key="loc.id"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-none cursor-pointer transition-all text-sm border-l-4 hover:-translate-y-0.5 hover:shadow-sm location-label"
                            :style="selectedLocations.has(loc.id) ? { borderColor: loc.color || '#999', ...getSelectedStyle(loc.color) } : { borderColor: loc.color || '#999' }"
                            :class="selectedLocations.has(loc.id) ? 'selected' : 'bg-slate-50 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700'">
                            <input type="checkbox" :checked="selectedLocations.has(loc.id)" @change="toggleLocation(loc.id)" class="w-3 h-3">
                            {{ loc.second }}{{ loc.third ? ' ' + loc.third : '' }}
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results -->
        <div v-if="loading" class="mt-8 text-slate-500">載入中...</div>

        <div v-if="hasResults" class="mt-8 w-full max-w-4xl">
            <!-- 韻書結果 -->
            <div v-if="results['韻書'] && results['韻書'].length > 0" class="mb-6">
                <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100 mb-4 border-l-4 border-wood pl-3">韻書</h2>
                <div v-for="(book, idx) in results['韻書']" :key="idx" class="mb-4 p-4 bg-white dark:bg-slate-800 rounded-none shadow-[4px_4px_0_rgba(0,0,0,0.04)] dark:shadow-[4px_4px_0_rgba(0,0,0,0.2)] border border-slate-100 dark:border-slate-700">
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
                <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100 mb-4 border-l-4 border-accent pl-3">各地</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div v-for="loc in results['各地']" :key="loc.__id"
                        class="p-3 rounded-none border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:shadow-[4px_4px_0_rgba(0,0,0,0.06)] dark:hover:shadow-[4px_4px_0_rgba(0,0,0,0.3)] hover:-translate-y-0.5 transition-all">
                        <div class="flex items-center gap-2 mb-1 border-b border-slate-100 dark:border-slate-700 pb-1">
                            <div class="w-3 h-3 rounded-none" :style="{ backgroundColor: getLocationById(loc.__id).color || '#999' }"></div>
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
