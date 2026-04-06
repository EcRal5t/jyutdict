<script setup>
import { computed } from 'vue'

const props = defineProps({
    role: { type: String, required: true },
    /** 是否显示编纂者负责的地点（可选） */
    locations: { type: Array, default: () => [] },
})

const label = computed(() => {
    const map = { owner: '站长', admin: '管理员', editor: '编纂者' }
    return map[props.role] || null
})

const badgeClass = computed(() => {
    const map = {
        owner: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 border-red-200 dark:border-red-800',
        admin: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 border-blue-200 dark:border-blue-800',
        editor: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 border-green-200 dark:border-green-800',
    }
    return map[props.role] || ''
})
</script>

<template>
    <span v-if="label" class="inline-flex items-center gap-0.5 text-[10px] px-1.5 py-0.5 rounded border font-medium" :class="badgeClass">
        {{ label }}
    </span>
</template>
