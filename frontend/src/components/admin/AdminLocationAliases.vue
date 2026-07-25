<script setup>
import { computed, onMounted, ref } from 'vue'
import adminApi from '@/api/admin.js'
import articlesApi from '@/api/articles.js'

const groups = ref([])
const candidates = ref([])
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const editingCanonical = ref('')
const search = ref('')
const form = ref({ canonical_name: '', aliases: [] })

const filteredCandidates = computed(() => {
    const needle = search.value.trim().toLowerCase()
    if (!needle) return candidates.value
    return candidates.value.filter(item => item.name.toLowerCase().includes(needle))
})

const sourceLabel = (source) => source.type === 'common' ? '通表' : '粵表'

const load = async () => {
    loading.value = true
    error.value = ''
    try {
        const res = await adminApi.getLocationAliases()
        groups.value = res.data.groups || []
        candidates.value = res.data.candidates || []
    } catch (e) {
        error.value = e.response?.data?.error || e.message || '載入文章地點失敗'
    } finally {
        loading.value = false
    }
}

const reset = () => {
    editingCanonical.value = ''
    form.value = { canonical_name: '', aliases: [] }
}

const edit = (group) => {
    editingCanonical.value = group.name
    form.value = {
        canonical_name: group.name,
        aliases: [...group.aliases],
    }
    window.scrollTo({ top: 0, behavior: 'smooth' })
}

const submit = async () => {
    saving.value = true
    error.value = ''
    try {
        if (editingCanonical.value) {
            await adminApi.updateLocationAliasGroup(
                editingCanonical.value,
                form.value.canonical_name,
                form.value.aliases,
            )
        } else {
            await adminApi.createLocationAliasGroup(form.value.canonical_name, form.value.aliases)
        }
        articlesApi.clearArticleLocationCache()
        reset()
        await load()
    } catch (e) {
        error.value = e.response?.data?.error || '儲存失敗'
    } finally {
        saving.value = false
    }
}

const remove = async (group) => {
    const typed = prompt(`刪除「${group.name}」的全部別名關係；文章與權限不會刪除。請輸入抽象地點名確認：`)
    if (typed !== group.name) return
    try {
        await adminApi.deleteLocationAliasGroup(group.name, typed)
        articlesApi.clearArticleLocationCache()
        if (editingCanonical.value === group.name) reset()
        await load()
    } catch (e) {
        error.value = e.response?.data?.error || '刪除失敗'
    }
}

onMounted(load)
</script>

<template>
    <section class="space-y-5">
        <div class="border border-slate-200 bg-white/80 p-4 dark:border-slate-700 dark:bg-slate-800/80">
            <h2 class="mb-3 border-l-4 border-accent pl-3 text-sm font-bold">
                {{ editingCanonical ? '修改抽象地點' : '新增抽象地點' }}
            </h2>
            <p class="mb-3 text-xs text-slate-500">
                抽象名用於文章、版本與權限；兩份字表仍顯示各自的原始地名。
            </p>
            <form class="grid grid-cols-1 gap-3 lg:grid-cols-2" @submit.prevent="submit">
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-500">抽象地點名</label>
                    <input v-model.trim="form.canonical_name" required maxlength="100"
                        placeholder="在此輸入統合用地名"
                        class="w-full border-2 border-slate-200 p-2 text-sm dark:border-slate-700 dark:bg-slate-900" />
                    <input v-model.trim="search" placeholder="篩選來源地名…"
                        class="w-full border border-slate-200 p-2 text-xs dark:border-slate-700 dark:bg-slate-900" />
                </div>
                <div>
                    <label class="mb-2 block text-xs font-bold text-slate-500">來源別名（可多選）</label>
                    <select v-model="form.aliases" multiple required
                        class="h-44 w-full border-2 border-slate-200 p-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                        <option v-for="candidate in filteredCandidates" :key="candidate.name" :value="candidate.name">
                            {{ candidate.name }} · {{ candidate.sources.map(sourceLabel).join('/') }}
                        </option>
                    </select>
                </div>
                <div class="flex gap-2 lg:col-span-2">
                    <button :disabled="saving || !form.aliases.length"
                        class="bg-accent px-4 py-2 text-sm font-bold text-white disabled:opacity-50">
                        {{ saving ? '儲存中…' : '儲存' }}
                    </button>
                    <button v-if="editingCanonical" type="button" class="border px-4 py-2 text-sm" @click="reset">取消</button>
                </div>
            </form>
        </div>

        <p v-if="error" class="border-l-4 border-red-500 bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-300">{{ error }}</p>
        <div v-if="loading" class="py-8 text-center text-sm text-slate-400">載入中…</div>
        <div v-else class="space-y-3">
            <article v-for="group in groups" :key="group.name"
                class="border border-slate-200 bg-white/80 p-4 dark:border-slate-700 dark:bg-slate-800/80">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-slate-100">{{ group.name }}</h3>
                        <p class="mt-1 text-xs text-slate-500">別名：{{ group.aliases.join('、') }}</p>
                        <div class="mt-2 flex flex-wrap gap-1">
                            <span v-for="source in group.sources" :key="`${source.type}-${source.id}`"
                                class="border border-slate-200 px-2 py-0.5 text-[11px] text-slate-500 dark:border-slate-700">
                                {{ sourceLabel(source) }} · {{ source.display_name }}
                            </span>
                        </div>
                        <p class="mt-2 text-xs" :class="group.has_article ? 'text-emerald-600' : 'text-amber-600'">
                            {{ group.has_article ? '已有文章' : '尚無文章' }} · {{ group.editor_count }} 位編纂者
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button class="border border-accent px-3 py-1.5 text-xs text-accent" @click="edit(group)">修改</button>
                        <button class="border border-red-300 px-3 py-1.5 text-xs text-red-500" @click="remove(group)">刪除關係</button>
                    </div>
                </div>
            </article>
            <p v-if="groups.length === 0" class="border-2 border-dashed border-slate-200 p-8 text-center text-sm text-slate-400 dark:border-slate-700">
                尚未建立抽象地點。
            </p>
        </div>
    </section>
</template>
