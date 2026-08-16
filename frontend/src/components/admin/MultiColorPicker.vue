<script setup>
import { computed } from 'vue';
import { parseAreaColors } from '@/utils/areaColor.js';

const model = defineModel({ type: String, default: '#CCCCCC' });

const colorList = computed({
    get() {
        return parseAreaColors(model.value, '#CCCCCC');
    },
    set(newColors) {
        if (!newColors || newColors.length === 0) {
            model.value = '#CCCCCC';
        } else {
            model.value = newColors.map(c => c.toUpperCase()).join(',');
        }
    }
});

const updateColor = (index, value) => {
    const list = [...colorList.value];
    list[index] = (value || '#CCCCCC').toUpperCase();
    colorList.value = list;
};

const addColor = () => {
    const list = [...colorList.value];
    // 默认新颜色稍微偏移或使用预设
    const lastColor = list[list.length - 1] || '#CCCCCC';
    list.push(lastColor);
    colorList.value = list;
};

const removeColor = (index) => {
    if (colorList.value.length <= 1) return;
    const list = colorList.value.filter((_, i) => i !== index);
    colorList.value = list;
};
</script>

<template>
    <div class="flex flex-col gap-1.5 p-2 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-600 dark:text-slate-400">
                地點着色 ({{ colorList.length }} 色)
            </span>
            <button
                type="button"
                @click="addColor"
                class="text-xs px-2 py-0.5 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:border-accent hover:text-accent font-medium transition-colors"
                title="新增一個著色"
            >
                + 加色
            </button>
        </div>

        <!-- 颜色条预览 -->
        <div class="flex h-2 w-full overflow-hidden border border-black/10 dark:border-white/10">
            <div
                v-for="(c, idx) in colorList"
                :key="idx"
                class="h-full flex-1 transition-colors"
                :style="{ backgroundColor: c }"
            ></div>
        </div>

        <!-- 颜色拾取列表 -->
        <div class="flex flex-wrap items-center gap-1.5 pt-1">
            <div
                v-for="(col, idx) in colorList"
                :key="idx"
                class="flex items-center gap-1 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-1.5 py-1 text-xs"
            >
                <input
                    type="color"
                    :value="col"
                    @input="updateColor(idx, $event.target.value)"
                    class="w-5 h-5 cursor-pointer border-0 p-0 bg-transparent"
                    :title="`點擊選擇第 ${idx + 1} 個顏色`"
                />
                <span class="font-mono text-[11px] select-all">{{ col }}</span>
                <button
                    v-if="colorList.length > 1"
                    type="button"
                    @click="removeColor(idx)"
                    class="ml-1 text-slate-400 hover:text-red-600 font-bold px-1 text-xs"
                    title="移除此顏色"
                >
                    ×
                </button>
            </div>
        </div>
    </div>
</template>
