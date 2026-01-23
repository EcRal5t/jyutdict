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
  <div v-if="props.data && props.data.length > 0" class="mb-6 page-break-inside-avoid">
    
    <!-- Kuangyon Header -->
    <div v-if="isKuangyon" class="pl-2 border-l-2 border-slate-300 dark:border-slate-600 mb-2 text-slate-700 dark:text-slate-300 text-base">
        <div v-for="(row, idx) in props.data" :key="idx" class="mb-0.5 flex gap-2">
             <span>{{ row['聲母'] }}</span>
             <span>{{ row['攝'] }}</span>
             <span>{{ row['韻'] }}</span>
             <span>{{ row['等'] }}</span>
             <span>{{ row['呼'] }}</span>
             <span>{{ row['聲調'] }}</span>
             <span class="opacity-50 text-sm">/ {{ row['轉寫'] }}</span>
        </div>
    </div>

    <!-- Fanwan Table -->
    <div v-if="isFanwan" class="border border-slate-300 dark:border-slate-600 mb-2 bg-white dark:bg-slate-800 text-xs rounded-lg overflow-hidden shadow-sm">
        <table class="w-full border-collapse text-center">
             <thead class="bg-gray-50 dark:bg-slate-900/50 border-b border-slate-300 dark:border-slate-600 font-bold text-slate-700 dark:text-slate-300">
                 <tr>
                     <td class="p-1 text-base border-r border-slate-300 dark:border-slate-600 w-20">分韻</td>
                     <td class="p-1 border-r border-slate-300 dark:border-slate-600">韻部 - 小韻</td>
                     <td class="p-1 border-r border-slate-300 dark:border-slate-600">聲 - 韻 - 調</td>
                 </tr>
             </thead>
             <tbody>
                 <template v-for="(row, idx) in props.data" :key="idx">
                     <tr class="border-b border-slate-200 dark:border-slate-700">
                         <td rowspan="2" class="p-1 border-r border-slate-300 dark:border-slate-600 text-base align-middle bg-gray-50/30 dark:bg-slate-800/30  font-mono">
                             <!-- Colored Components -->
                            <span class="text-[#D32913] dark:text-red-400">{{ row['聲母'] }}</span>
                            <span class="text-emerald-700 dark:text-emerald-400">{{ row['韻核'] }}</span>
                            <span class="text-emerald-700 dark:text-emerald-400">{{ row['韻尾'] }}</span>
                            <span class="text-amber-600 dark:text-amber-400">{{ row['聲調'] }}</span>
                         </td>
                         <td class="p-1 border-r border-slate-300 dark:border-slate-600 text-sm">
                             {{ row['韻部'] }} - {{ row['小韻'] }}
                         </td>
                         <td class="p-1 border-r border-slate-300 dark:border-slate-600 text-sm">
                             
                             <span class="text-slate-600 dark:text-slate-400">{{ row['聲字'] }}{{ row['韻字'] }}{{ row['調類'] }}</span>
                         </td>
                     </tr>
                     <tr class="border-b border-slate-300 dark:border-slate-600">
                         <td colspan="3" class="p-1 text-slate-700 dark:text-slate-300 bg-orange-50/30 dark:bg-amber-900/10 italic leading-tight">
                             {{ row['義'] }}
                         </td>
                     </tr>
                 </template>
             </tbody>
        </table>
    </div>

    <!-- Jingwaa Table -->
    <div v-if="isJingwaa" class="border border-slate-300 dark:border-slate-600 mb-2 bg-white dark:bg-slate-800 text-xs rounded-lg overflow-hidden shadow-sm">
        <table class="w-full border-collapse text-center">
            <thead class="bg-gray-50 dark:bg-slate-900/50 border-b border-slate-300 dark:border-slate-600 font-bold text-slate-700 dark:text-slate-300">
                 <tr>
                    <td class="p-1 text-base border-r border-slate-300 dark:border-slate-600 w-20">英華</td>
                    <td class="p-1 border-r border-slate-300 dark:border-slate-600">葉碼</td>
                    <td class="p-1 border-r border-slate-300 dark:border-slate-600">筆畫</td>
                    <td class="p-1 border-r border-slate-300 dark:border-slate-600">原標音</td>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                <tr v-for="(row, idx) in props.data" :key="idx" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 ">
                    <td class="p-1 border-r border-slate-300 dark:border-slate-600 font-mono text-base">
                        <span class="text-[#D32913] dark:text-red-400"> {{ row['聲母'] }}</span>
                        <span class="text-emerald-700 dark:text-emerald-400">{{ row['韻核'] }}{{ row['韻尾'] }}</span>
                        <span class="text-amber-600 dark:text-amber-400">{{ row['聲調'] }}</span>
                    </td>
                    <td class="p-1 border-r border-slate-300 dark:border-slate-600">{{ row['頁'] }} . {{ row['序'] }}</td>
                    <td class="p-1 border-r border-slate-300 dark:border-slate-600">
                        {{ row['部首'] }}({{ row['部首筆畫'] }})+{{ row['部外筆畫'] }}
                    </td>
                    <td class="p-1 border-r border-slate-300 dark:border-slate-600 text-base">
                        {{ row['音'] }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

  </div>
</template>
