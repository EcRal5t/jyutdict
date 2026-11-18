<script setup>
defineProps({
    label: { type: String, default: '' },
    size: { type: String, default: 'md' } // sm | md
});
</script>

<template>
    <div class="flex flex-col items-center justify-center gap-3 jd-enter-fade" role="status" aria-live="polite">
        <div class="flex items-end" :class="size === 'sm' ? 'gap-1 h-4' : 'gap-1.5 h-7'">
            <span v-for="n in 4" :key="n" class="jd-loader-bar bg-accent dark:bg-red-500"
                :class="size === 'sm' ? 'w-1 h-4' : 'w-1.5 h-7'"
                :style="{ animationDelay: `${(n - 1) * 90}ms` }"></span>
        </div>
        <span v-if="label" class="text-xs tracking-widest text-slate-400">{{ label }}</span>
        <span v-else class="sr-only">載入中</span>
    </div>
</template>

<style scoped>
.jd-loader-bar {
    transform-origin: bottom;
    animation: jd-loader 720ms var(--jd-ease) infinite;
}
</style>
