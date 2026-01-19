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

    <!-- Fanwan Table (Legacy Style) -->
    <div v-if="isFanwan" class="border border-slate-300 dark:border-slate-600 mb-4 bg-white dark:bg-slate-800 text-sm">
        <table class="w-full border-collapse">
             <!-- No header in legacy? Or just rows? Legacy usually has headers. -->
             <!-- Assuming standard headers based on view.class.php if I could see it. -->
             <!-- Let's follow the user's "Restore original effect" instruction. -->
             <!-- Standard header: Name | Yunbu-Siuwan | Pronunciation | [Empty/Note] -->
             <thead class="bg-slate-100 dark:bg-slate-900 border-b border-slate-300 dark:border-slate-600 font-bold text-slate-700 dark:text-slate-300">
                 <tr>
                     <td class="p-2 border-r border-slate-300 dark:border-slate-600 w-24 text-center">分韻</td>
                     <td class="p-2 border-r border-slate-300 dark:border-slate-600">韻部 - 小韻</td>
                     <td class="p-2 border-r border-slate-300 dark:border-slate-600">聲 - 韻 - 調</td>
                     <td class="p-2">備註</td>
                 </tr>
             </thead>
             <tbody>
                 <template v-for="(row, idx) in props.data" :key="idx">
                     <tr class="border-b border-slate-200 dark:border-slate-700">
                         <td rowspan="2" class="p-2 border-r border-slate-300 dark:border-slate-600 text-center font-serif font-bold text-lg align-middle bg-slate-50 dark:bg-slate-800/50">
                             {{ row.name }}
                         </td>
                         <td class="p-2 border-r border-slate-300 dark:border-slate-600 font-serif">
                             {{ row.yunbu }} - {{ row.siuwan }}
                         </td>
                         <td class="p-2 border-r border-slate-300 dark:border-slate-600 font-mono text-base">
                             <!-- Colored Components -->
                             <span class="text-[#D32913] dark:text-red-400">{{ row.initial }}</span><span class="text-emerald-700 dark:text-emerald-400">{{ row.nuclei }}</span><span class="text-emerald-700 dark:text-emerald-400">{{ row.coda }}</span><span class="text-amber-600 dark:text-amber-400">{{ row.tone }}</span>
                             <span class="text-slate-400 mx-1">/</span>
                             {{ row.initial_ch }}{{ row.final_ch }}{{ row.tone_ch }}
                         </td>
                         <td class="p-2 text-xs text-slate-500">
                             <!-- Placeholder/Note -->
                         </td>
                     </tr>
                     <tr class="border-b border-slate-300 dark:border-slate-600">
                         <td colspan="3" class="p-2 text-slate-700 dark:text-slate-300 font-serif bg-slate-50/50 dark:bg-slate-800/30">
                             {{ row.meaning }}
                         </td>
                     </tr>
                 </template>
             </tbody>
        </table>
    </div>

    <!-- Jingwaa Table (Legacy Style) -->
    <div v-if="isJingwaa" class="border border-slate-300 dark:border-slate-600 mb-4 bg-white dark:bg-slate-800 text-sm">
        <table class="w-full border-collapse text-left">
            <thead class="bg-slate-100 dark:bg-slate-900 border-b border-slate-300 dark:border-slate-600 font-bold text-slate-700 dark:text-slate-300">
                 <tr>
                    <td class="p-2 border-r border-slate-300 dark:border-slate-600 w-24 text-center">英華</td>
                    <td class="p-2 border-r border-slate-300 dark:border-slate-600">葉碼</td>
                    <td class="p-2 border-r border-slate-300 dark:border-slate-600">筆畫</td>
                    <td class="p-2 border-r border-slate-300 dark:border-slate-600">原標音</td>
                    <td class="p-2"></td>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                <tr v-for="(row, idx) in props.data" :key="idx" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                    <td class="p-2 border-r border-slate-300 dark:border-slate-600 text-center font-bold">英華</td>
                    <td class="p-2 border-r border-slate-300 dark:border-slate-600 font-mono">{{ row.page }}</td>
                    <td class="p-2 border-r border-slate-300 dark:border-slate-600">
                        {{ row.radical_stroke }}({{ row.radical }})+{{ row.extra_stroke }}
                    </td>
                    <td class="p-2 border-r border-slate-300 dark:border-slate-600 font-serif text-lg">
                        {{ row.pronunciation }}
                    </td>
                    <td class="p-2 font-mono text-accent">
                        <!-- We can try to parse row.pronunciation if needed, but legacy likely just showed it. -->
                        <!-- If user wants colors, we can try, but for now stick to simple display. -->
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

  </div>
</template>
