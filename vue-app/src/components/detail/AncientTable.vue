<script setup>
import { computed } from 'vue';

const props = defineProps({
  data: {
    type: Object,
    required: true
  }
});

// data is an array of entries for a specific book (Ancient > [Book Array])
// Wait, the API structure is tricky:
// entry["ancient"] is an array of arrays representing books.
// entry["ancient"][0] is array of pronunciation entries for that book.
// So this component should probably take ONE book's data (array of entries) and render it.

const bookName = computed(() => {
    // Determine book name from first entry (if available)
    // The API seems to put "__name" in the 0th element sometimes? 
    // Or in the entry itself?
    // Let's look at detail.php again. 
    // $allPron = ["__name"=>$eachWanshyu['name']]; ... array_push($entriesInAncient, $allPron);
    // Be careful: if search by Pronunciation, structure is different!
    // If search by Character (wanshyu loop):
    // $entry[$key["name"]] = "廣韻"; ... array_push($entries, $entry); array_push($entriesInAncient, $entries);
    // So for Chara search, props.data is an ARRAY of objects. Each object has "name" property.
    if (Array.isArray(props.data) && props.data.length > 0) {
        // Find property that holds name. "書名" or "name" depending on ASCII.
        // We set ascii=1 so keys are "name".
        return props.data[0].name || props.data[0]['書名'];
    }
    return '';
});

const isFanwan = computed(() => bookName.value === '分韻');
const isJingwaa = computed(() => bookName.value === '英華');
const isKuangyon = computed(() => bookName.value === '廣韻');

</script>

<template>
  <div v-if="props.data && props.data.length > 0" class="mb-6">
    
    <!-- Kuangyon Header -->
    <div v-if="isKuangyon" class="text-lg font-bold mb-2 p-2 bg-slate-100 dark:bg-slate-700/50 rounded">
         廣韻
    </div>
    <div v-if="isKuangyon" class="pl-4 border-l-2 border-slate-300 dark:border-slate-600 mb-4 text-slate-700 dark:text-slate-300 font-mono">
        <div v-for="(row, idx) in props.data" :key="idx" class="mb-1">
             {{ row.initial }}{{ row.rime_class }}{{ row.rime }}{{ row.division_cha }}{{ row.rounding }}{{ row.tone }}{{ row.transliteration }}
        </div>
    </div>

    <!-- Fanwan Table -->
    <table v-if="isFanwan" class="w-full text-sm text-left border-collapse bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden shadow-sm">
        <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700">
            <tr>
                <th class="p-3 font-semibold text-slate-600 dark:text-slate-400 w-[15%]">分韻</th>
                <th class="p-3 font-semibold text-slate-600 dark:text-slate-400 w-[25%]">韻部 - 小韻</th>
                <th class="p-3 font-semibold text-slate-600 dark:text-slate-400 w-[30%]">聲 - 韻 - 調</th>
                <th class="p-3 font-semibold text-slate-600 dark:text-slate-400"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
            <template v-for="(row, idx) in props.data" :key="idx">
                <tr>
                    <td class="p-3 text-slate-800 dark:text-slate-200">{{ row.name }}</td>
                    <td class="p-3 text-slate-600 dark:text-slate-400">{{ row.yunbu }} - {{ row.siuwan }}</td>
                    <td class="p-3 text-slate-600 dark:text-slate-400">{{ row.initial_ch }}-{{ row.final_ch }}-{{ row.tone_ch }}</td>
                    <td rowspan="2" class="p-3 font-mono text-accent">
                        <!-- Legacy code uses Jyutping parser to display colored text -->
                        <!-- Accessing jpp raw? The API returns many fields but not raw jpp usually unless requested? -->
                        <!-- Actually FanwanData returns 'meaning' etc. -->
                        <!-- Let's assume we don't have color logic yet for this specific column without raw data -->
                        <!-- Wait, view.class says $data->getJpp(). If API sends it... -->
                        <!-- Looking at Detail PHP: K-V mapping excludes JPP for Fanwan? -->
                        <!-- Valid keys: name... meaning, siuwan, yunbu, initial_ch... -->
                        <!-- It seems API does NOT return JPP for Fanwan? Check lines 130-143 of index.php... no. -->
                        <!-- Wait, line 56 of detail.php defines Keys. "jpp" is NOT in the key map! -->
                        <!-- So the API might NOT be returning the field needed for coloring? -->
                        <!-- Ah, legacy uses Views which access Data objects having direct DB access. API is serialized. -->
                        <!-- If API doesn't expose it, I can't render it. -->
                        <!-- I should check the JSON response in browser once I can. -->
                    </td>
                </tr>
                <tr>
                    <td class="p-3 text-slate-800 dark:text-slate-200">{{ row.name }}</td>
                    <td colspan="3" class="p-3 text-slate-600 dark:text-slate-400">{{ row.meaning }}</td>
                </tr>
            </template>
        </tbody>
    </table>

    <!-- Jingwaa Table -->
    <table v-if="isJingwaa" class="w-full text-sm text-left border-collapse bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden shadow-sm">
        <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700">
             <tr>
                <th class="p-3 font-semibold text-slate-600 dark:text-slate-400">英華</th>
                <th class="p-3 font-semibold text-slate-600 dark:text-slate-400">葉碼</th>
                <th class="p-3 font-semibold text-slate-600 dark:text-slate-400">筆畫</th>
                <th class="p-3 font-semibold text-slate-600 dark:text-slate-400">原標音</th>
                <th class="p-3 font-semibold text-slate-600 dark:text-slate-400"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
            <tr v-for="(row, idx) in props.data" :key="idx">
                <td class="p-3 text-slate-800 dark:text-slate-200">英華</td>
                <td class="p-3 text-slate-600 dark:text-slate-400">{{ row.page }}</td>
                <td class="p-3 text-slate-600 dark:text-slate-400">
                    {{ row.radical_stroke }}({{ row.radical }})+{{ row.extra_stroke }}
                </td>
                <td class="p-3 font-serif" :class="{'opacity-50': false /* logic for lastOrder comparison? */ }">
                    {{ row.pronunciation }}
                </td>
                <td class="p-3 font-mono text-accent">
                    <!-- Same issue with JPP color -->
                </td>
            </tr>
        </tbody>
    </table>

  </div>
</template>
