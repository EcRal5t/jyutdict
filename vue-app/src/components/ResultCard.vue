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
    let r = 0, g = 0, b = 0, count = 0;
    
    props.headerInfo.cities.forEach(info => {
        const val = props.rowData[info.col];
        if (val && val !== '_' && val !== '?' && info.color) { // Valid data present
           // Parse hex
           const hex = info.color.startsWith('#') ? info.color.slice(1) : info.color;
           if (hex.length === 6) {
               r += parseInt(hex.substring(0,2), 16);
               g += parseInt(hex.substring(2,4), 16);
               b += parseInt(hex.substring(4,6), 16);
               count++;
           }
        }
    });

    if (count === 0) return 'transparent';
    
    r = Math.round(r / count);
    g = Math.round(g / count);
    b = Math.round(b / count);
    
    return `rgb(${r}, ${g}, ${b})`;
});

const processedLocations = computed(() => {
    let cellNotes = {};
    try {
        const notesString = props.rowData['附'] || '{}';
        // Note: Using a safer parser or library would be better, but sticking to logic
        const jsonStr = notesString.replace(/\n/g, '\\n').replace(/\t/g, '\\t').replace(/'/g, '"');
        cellNotes = JSON.parse(jsonStr);
    } catch(e) { /* Error handling */ }

    const cities = [];
    props.headerInfo.cities.forEach(info => {
        const key = info.col;
        const value = props.rowData[key] ? String(props.rowData[key]).trim() : '';
        if (!value) return;

        const color = info.color ? darkenColor(info.color, 0.88) : '#000'; // Using new darken algorithm
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
        
        const color = info.color ? darkenColor(info.color, 0.88) : '#000';
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
        <div class="md:w-32 bg-gray-50 dark:bg-slate-900/50 flex flex-row md:flex-col items-center md:justify-center p-4 gap-4 md:gap-2 border-b md:border-b-0 md:border-r border-gray-100 dark:border-slate-700">
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
        <div class="flex-1 p-5 md:p-6 min-w-0">
            
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
                        <span v-if="loc.note" class="text-[10px] align-top text-accent opacity-70">*</span>
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
            <div v-if="processedLocations.foreign.length > 0" class="pt-3 mt-2 border-t border-dashed border-gray-200 dark:border-slate-700 flex flex-wrap gap-4 text-xs">
                <div v-for="loc in processedLocations.foreign" :key="loc.key" class="relative text-slate-500 dark:text-slate-400">
                    <span class="font-bold" :style="{ color: loc.color }">{{ loc.label }}</span>:
                     <span :class="{ 'italic': loc.isItalic }" v-html="loc.value"></span>
                </div>
            </div>

            <!-- Footer: Classification -->
            <div v-if="classification" class="mt-4 flex justify-end">
                <span class="text-[10px] uppercase tracking-wider text-slate-300 dark:text-slate-600 font-bold bg-slate-50 dark:bg-slate-900 px-2 py-1 rounded">
                    {{ classification }}
                </span>
            </div>
        </div>
    </div>
  </div>
</template>
