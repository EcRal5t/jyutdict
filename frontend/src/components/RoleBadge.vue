<script setup>
import { computed } from 'vue'

const props = defineProps({
    role: { type: String, required: true },
    /** 是否显示编纂者负责的地点（可选） */
    locations: { type: Array, default: () => [] },
})

const label = computed(() => {
    const map = { owner: '站長', admin: '管理員', editor: '編纂者' }
    return map[props.role] || null
})

const badgeClass = computed(() => {
    const map = {
        owner: 'bg-red-50 text-red-700 border-red-200/60 dark:bg-red-500/10 dark:text-red-300 dark:border-red-500/20 dark:shadow-[0_0_8px_rgba(239,68,68,0.15)]',
        admin: 'bg-blue-50 text-blue-700 border-blue-200/60 dark:bg-blue-500/10 dark:text-blue-300 dark:border-blue-500/20 dark:shadow-[0_0_8px_rgba(59,130,246,0.15)]',
        editor: 'bg-emerald-50 text-emerald-700 border-emerald-200/60 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/20 dark:shadow-[0_0_8px_rgba(16,185,129,0.15)]',
    }
    return map[props.role] || ''
})
</script>

<template>
    <span v-if="label" class="inline-flex items-center justify-center text-[10px] px-2 py-0.5 rounded-none border font-semibold tracking-wide transition-all shadow-sm hover:shadow-md hover:-translate-y-0.5" :class="badgeClass">
        {{ label }}
    </span>
</template>
