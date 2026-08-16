<script setup>
import { computed } from 'vue';
import { parseAreaColors } from '@/utils/areaColor.js';

const props = defineProps({
    color: {
        type: [String, Array],
        default: '#999999'
    },
    badgeClass: {
        type: String,
        default: 'w-3 h-3'
    },
    title: {
        type: String,
        default: ''
    }
});

const colors = computed(() => {
    if (Array.isArray(props.color)) {
        return props.color.length ? props.color : ['#999999'];
    }
    return parseAreaColors(props.color);
});
</script>

<template>
    <span
        class="inline-flex overflow-hidden border border-black/15 dark:border-white/20 shrink-0 align-middle leading-none"
        :class="badgeClass"
        :title="title || colors.join(', ')"
        aria-hidden="true"
    >
        <span
            v-for="(col, index) in colors"
            :key="index"
            class="h-full flex-1"
            :style="{ backgroundColor: col }"
        ></span>
    </span>
</template>
