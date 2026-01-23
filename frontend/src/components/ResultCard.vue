<script setup>
import { computed, ref } from 'vue';
import { darkenColor, formatCharacter, formatUnicode, formatPronunciation, formatMeanings } from '@/utils/formatters.js';

const props = defineProps({
  rowData: {
    type: Object,
    required: true
  },
  headerInfo: {
    type: Object,
    default: () => ({ cities: [], foreign: [] })
  }
});

const charInfo = computed(() => formatCharacter(props.rowData));
const unicodeInfo = computed(() => formatUnicode(props.rowData));
const pronInfo = computed(() => formatPronunciation(props.rowData));
const meaningInfo = computed(() => formatMeanings(props.rowData));

const activeNote = ref(null);

const toggleNote = (key) => {
  activeNote.value = activeNote.value === key ? null : key;
};

// Compute the decorative strip color logic
// "determined by the calculation result of the value of all points marked as 'city' in this entry"
// We'll simplisticly take the average color of all present city columns
const stripColor = computed(() => {
    // === 配置区域 ===
    const CONFIG = {
        // 只有1个数据时：深沉、鲜艳
        minL: 35,      // 亮度 35% (深)
        maxS: 90,      // 饱和度 90% (艳)
        
        // 数据极多时：明亮、低饱和 (高级灰)
        maxL: 92,      // 亮度 92% (接近白)
        minS: 15,      // 饱和度 15% (灰)
        
        // 达到多少个数据时达到“最浅/最灰”的阈值
        saturationThreshold: 20 
    };

    let rSum = 0, gSum = 0, bSum = 0;
    let validCount = 0;

    // 1. 遍历：只收集有效数据的颜色向量
    props.headerInfo.cities.forEach(info => {
        const val = props.rowData[info.col];
        // 判定有效性
        if (val && val !== '_' && val !== '?') { 
            // 解析 Hex
            const c = info.color.startsWith('#') ? info.color.slice(1) : info.color;
            if (c.length === 6) {
                rSum += parseInt(c.substring(0, 2), 16);
                gSum += parseInt(c.substring(2, 4), 16);
                bSum += parseInt(c.substring(4, 6), 16);
                validCount++;
            }
        }
        // 完全忽略无效数据的颜色，
        // 不让它们通过数学运算干扰色相（Hue）。
    });

    // 2. 只有0个有效数据 -> 透明
    if (validCount === 0) return 'transparent';

    // 3. 计算有效颜色的平均 RGB
    // 这一步确保：如果只有一个红色，平均值就是纯红，绝不会偏色
    const r = rSum / validCount;
    const g = gSum / validCount;
    const b = bSum / validCount;

    // 4. 提取色相 (Hue)
    // 标准 RGB -> HSL 转换算法（仅取 H）
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    let h = 0;
    
    // 这是一个保险：如果有效数据的平均值本身就是黑白灰（比如有效数据是 #000000），
    // 那么它的色相并不重要，我们记录下来，后面强制饱和度为0
    const isInherentlyGrayscale = (max - min) < 1; 

    if (!isInherentlyGrayscale) {
        const d = max - min;
        switch (max) {
            case r: h = (g - b) / d + (g < b ? 6 : 0); break;
            case g: h = (b - r) / d + 2; break;
            case b: h = (r - g) / d + 4; break;
        }
        h /= 6;
        h = Math.round(h * 360);
    }

    // 5. 动态计算 Saturation (S) 和 Lightness (L)
    // 使用“进度条”逻辑 (t)：0 代表数据极少，1 代表数据极多
    // 我们把 validCount 限制在 1 到 saturationThreshold 之间
    const t = Math.min((validCount - 1) / (CONFIG.saturationThreshold - 1), 1);
    const norm_t = Math.sqrt(t);

    // 亮度计算：数据越少(t=0)越深(minL)，数据越多(t=1)越浅(maxL)
    const l = Math.round(CONFIG.minL + (CONFIG.maxL - CONFIG.minL) * norm_t);

    // 饱和度计算：数据越少(t=0)越艳(maxS)，数据越多(t=1)越灰(minS)
    let s = Math.round(CONFIG.maxS - (CONFIG.maxS - CONFIG.minS) * norm_t);

    // 如果原始平均色本身就是灰色（比如有效数据全是 #888888），
    // 那么无论数据多少，饱和度都必须是 0，否则会把灰色变成红色（因为 Hue 默认为 0）
    if (isInherentlyGrayscale) s = 0;

    return `hsl(${h}, ${s}%, ${l}%)`;
});

const processedLocations = computed(() => {
    let cellNotes = {};
    try {
        const notesString = props.rowData['附'] || '{}';
        // Note: Using a safer parser or library would be better, but sticking to logic
        const jsonStr = notesString.replace(/\n/g, '\\n').replace(/\t/g, '\\t').replace(/'/g, '"');
        cellNotes = JSON.parse(jsonStr);
    } catch(e) { /* Error handling */ }

    
    const lightness_scale = (localStorage.theme === 'dark') ? 1.12 : 0.88;

    const cities = [];
    props.headerInfo.cities.forEach(info => {
        const key = info.col;
        const value = props.rowData[key] ? String(props.rowData[key]).trim() : '';
        if (!value) return;

        const color = info.color ? darkenColor(info.color, lightness_scale) : '#000'; // Using new darken algorithm
        const fullName = info.city + (info.sub ? info.sub : '');
        
        let displayValueHtml = value;
        if (value.includes('^')) {
            const parts = value.split('^');
            displayValueHtml = parts[0] + parts.slice(1).map(p => `<del class="opacity-50">${p}</del>`).join('');
        }
        
        cities.push({
            key,
            label: fullName,
            value: displayValueHtml,
            color,
            isItalic: value.includes('?'),
            isDim: value === '_',
            note: cellNotes[key]
        });
    });

    const foreign = [];
    props.headerInfo.foreign.forEach(info => {
        const key = info.col; // Check if key varies?
        const value = props.rowData[key] ? String(props.rowData[key]).trim() : '';
        if (!value) return;
        
        const color = info.color ? darkenColor(info.color, lightness_scale) : '#000';
         foreign.push({
            key,
            label: info.fullname,
            value: value.replace(/\n/g, ', '),
            color,
            isItalic: value.includes('?'),
            note: cellNotes[key]
         });
    });

    return { cities, foreign };
});

const classification = computed(() => {
    let classified = props.rowData['大類'] || '';
    if (classified) {
        const class_secondary = props.rowData['中類'] || '';
        const class_minor = props.rowData['小類'] || '';
        if (class_secondary) classified += ` > ${class_secondary}`;
        if (class_minor) classified += ` > ${class_minor}`;
        return classified;
    }
    return '';
});

</script>

<template>
  <div class="group relative bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-all duration-300">
    
    <!-- Color Strip -->
    <div class="absolute left-0 top-0 bottom-0 w-2 transition-colors duration-500" :style="{ backgroundColor: stripColor }"></div>

    <div class="flex flex-col md:flex-row">
        <!-- Left: Char Meta (fixed width) -->
        <div class="md:w-32 bg-gray-50 dark:bg-slate-900/50 flex flex-row md:flex-col items-center md:justify-center px-6 py-2 gap-4 md:gap-2 border-b md:border-b-0 md:border-r border-gray-100 dark:border-slate-700">
            <div class="text-4xl md:text-5xl font-bold leading-tight" :style="charInfo.style">
                {{ charInfo.display }}
            </div>
            
            <div class="flex flex-col md:items-center text-left md:text-center">
                <div class="text-xs text-slate-400 font-mono mb-1">
                    {{ unicodeInfo }}
                </div>
                
                <div class="text-lg text-slate-700 dark:text-slate-300 font-medium whitespace-pre-wrap leading-tight" :class="{'italic': pronInfo.isItalic}">
                    {{ pronInfo.text }}
                </div>
                
                <div v-if="pronInfo.simplifiedText" class="text-xs text-slate-500 mt-1">
                    {{ pronInfo.simplifiedText }}
                </div>
                <div v-if="pronInfo.adaptedChara" class="text-xs text-slate-400 mt-0.5">
                    ({{ pronInfo.adaptedChara }})
                </div>
            </div>
        </div>

        <!-- Right: Content -->
        <div class="flex-1 px-5 py-3 min-w-0">
            
            <!-- Meanings -->
            <div class="mb-5 text-base leading-relaxed text-slate-700 dark:text-slate-300">
                <div v-if="meaningInfo.bookInfo" class="mb-2 italic text-slate-500 border-l-2 border-slate-200 pl-2 text-sm">
                   —— {{ meaningInfo.bookInfo }}
                </div>
                <div v-for="(m, idx) in meaningInfo.meanings" :key="idx" :class="{'mb-1': idx < meaningInfo.meanings.length-1}">
                    <span v-if="m.isBold" class="font-bold text-slate-900 dark:text-slate-100" v-html="m.text"></span>
                    <span v-else v-html="m.text"></span>
                </div>
                <div v-if="meaningInfo.meanings.length === 0 && !meaningInfo.bookInfo" class="text-slate-400 italic">
                    (暫無釋義)
                </div>
            </div>
            
            <!-- Locations (Cities) -->
            <div class="flex flex-wrap gap-3 mb-3 text-sm">
                <div v-for="loc in processedLocations.cities" :key="loc.key" class="relative">
                    <div 
                        class="inline-flex flex-wrap items-baseline gap-1"
                        :class="{'cursor-pointer hover:opacity-80': loc.note}"
                        @click="loc.note && toggleNote(loc.key)"
                    >
                         <!-- Label -->
                        <span class="font-bold" :style="{ color: loc.color }">{{ loc.label }}:</span>
                        <!-- Value -->
                        <span class="text-slate-800 dark:text-slate-300" :class="{ 'italic': loc.isItalic, 'text-slate-400': loc.isDim }" v-html="loc.value"></span>
                        <!-- Note Indicator -->
                        <span v-if="loc.note" class="text-xs align-top text-accent opacity-70">*</span>
                    </div>
                    
                    <!-- Note Popup -->
                    <transition
                      enter-active-class="transition-all duration-300 ease-out overflow-hidden"
                      enter-from-class="max-h-0 opacity-0"
                      enter-to-class="max-h-40 opacity-100"
                      leave-active-class="transition-all duration-200 ease-in overflow-hidden"
                      leave-from-class="max-h-40 opacity-100"
                      leave-to-class="max-h-0 opacity-0"
                    >
                        <div v-if="activeNote === loc.key" class="w-full min-w-[200px] mt-1 p-2 bg-blue-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs rounded border-l-2 border-blue-400 block whitespace-pre-wrap z-10">
                            {{ loc.note }}
                        </div>
                    </transition>
                </div>
            </div>

            <!-- Foreign (Separated by Divider) -->
            <div v-if="processedLocations.foreign.length > 0" class="pt-3 mt-2 border-t border-dashed border-gray-200 dark:border-slate-700 flex flex-wrap gap-4 text-sm/3">
                <div v-for="loc in processedLocations.foreign" :key="loc.key" class="relative text-slate-500 dark:text-slate-400">
                    <span class="font-bold [text-shadow:_0_0_1px_#FFF7]" :style="{ color: loc.color }">{{ loc.label }}</span>:
                     <span :class="{ 'italic': loc.isItalic }" v-html="loc.value"></span>
                </div>
            </div>

            <!-- Footer: Classification -->
            <div v-if="classification" class="mt-4 flex justify-end">
                <span class="text-xs uppercase tracking-wider text-slate-300 dark:text-slate-600 font-bold bg-slate-50 dark:bg-slate-900 px-2 py-1 rounded">
                    {{ classification }}
                </span>
            </div>
        </div>
    </div>
  </div>
</template>
