<script setup>
defineProps({
    editing: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
    embedded: { type: Boolean, default: false },
})

defineEmits(['submit', 'cancel'])

const model = defineModel({ type: Object, required: true })
</script>

<template>
    <form
        @submit.prevent="$emit('submit')"
        class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 p-4 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700"
        :class="embedded ? 'm-2 border-l-4 border-l-accent shadow-[3px_3px_0_rgba(211,41,19,0.12)]' : 'mb-4'"
    >
        <input v-model.trim="model.sheetname" :disabled="editing" required placeholder="sheetname" class="p-2 border dark:bg-slate-900" />
        <input v-model.trim="model.first" placeholder="一级分类" class="p-2 border dark:bg-slate-900" />
        <input v-model.trim="model.second" placeholder="二级地名" class="p-2 border dark:bg-slate-900" />
        <input v-model.trim="model.third" placeholder="三级地名" class="p-2 border dark:bg-slate-900" />
        <input v-model.trim="model.detailed_name" placeholder="完整地點" class="p-2 border dark:bg-slate-900 sm:col-span-2" />
        <textarea v-model.trim="model.sheet_author" rows="2" placeholder="字表作者／署名" class="p-2 border dark:bg-slate-900 sm:col-span-2"></textarea>
        <input v-model.number="model.longitude" type="number" step="any" required placeholder="经度" class="p-2 border dark:bg-slate-900" />
        <input v-model.number="model.latitude" type="number" step="any" required placeholder="纬度" class="p-2 border dark:bg-slate-900" />
        <label class="flex items-center gap-2 p-2 border"><input v-model="model.color" type="color" /> {{ model.color }}</label>
        <label v-if="editing" class="flex items-center gap-2 p-2"><input v-model="model.is_visible" type="checkbox" /> 對外顯示</label>
        <div class="flex gap-2">
            <button :disabled="saving" class="bg-accent text-white px-4 py-2 text-sm disabled:opacity-50">儲存</button>
            <button type="button" @click="$emit('cancel')" class="px-4 py-2 text-sm border">取消</button>
        </div>
    </form>
</template>
