<script setup>
import { computed } from 'vue';
import { darkenColor } from '@/utils/formatters.js';
import { Jyutping } from '@/utils/jyutping.js';

const props = defineProps({
  data: {
    type: Array, 
    required: true
  },
  showIPA: {
    type: Boolean,
    default: false
  }
});

// Helper to parse individual row items
const processRow = (row) => {
    // New API structure: arrays are in row['粵拼'], row['IPA'] etc.
    const jpps = row['粵拼'] || [];
    const ipas = row['IPA'] || [];
    const notes = row['注釋'] || [];
    
    const count = Math.max(jpps.length, ipas.length);
    const items = [];
    
    for (let i = 0; i < count; i++) {
        const rawJpp = jpps[i] || '';
        let parsed = null;
        if (rawJpp) {
            parsed = Jyutping.parse(rawJpp);
        }
        
        items.push({
            initial: parsed ? parsed.initial : '',
            nuclei: parsed ? parsed.nuclei : '',
            coda: parsed ? parsed.coda : '',
            tone: parsed ? parsed.tone : '',
            ipa: ipas[i] || '',
            raw: rawJpp
        });
    }

    // Join notes if multiple? Or just pick first? 
    // In table view, maybe we join them or show specific note per pron?
    // Current template shows `row.note` (one string).
    // Let's join them for now.
    const combinedNote = notes.filter(n => n).join('; ');

    return { ...row, items, note: combinedNote };
};

// Group Data by Location Key (City + District)
const groups = computed(() => {
    const g = [];
    let lastKey = null;
    let currentGroup = null;

    props.data.forEach(rawRow => {
        const row = processRow(rawRow);
        // Unique key for location group
        const key = `${row.city}-${row.district}`;

        if (key !== lastKey) {
            currentGroup = {
                city: row.city,
                district: row.district,
                division: row.division_adm,
                color: row.color,
                rows: []
            };
            g.push(currentGroup);
            lastKey = key;
        }
        currentGroup.rows.push(row);
    });
    return g;
});

</script>

<template>
  <div v-if="groups && groups.length > 0" class="mb-1 bg-white dark:bg-slate-800 rounded shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
      <!-- Dense Table -->
      <table class="w-full text-xs text-left border-collapse">
          <thead class="bg-gray-100 dark:bg-slate-900 border-b border-gray-300 dark:border-slate-600">
              <tr>
                  <!-- Combined header for Location -->
                  <th class="py-1 px-2 font-bold text-slate-700 dark:text-slate-300 w-[25%] border-r border-slate-200 dark:border-slate-700">
                      地點
                  </th>
                  <th class="py-1 px-2 w-[40%] border-r border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300">
                      讀音
                  </th>
                  <th class="py-1 px-2 text-slate-700 dark:text-slate-300">
                      備註
                  </th>
              </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
              <template v-for="(group, gIdx) in groups" :key="gIdx">
                  <tr v-for="(row, rIdx) in group.rows" :key="rIdx" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                      
                      <!-- Location Cell (Merged with Rowspan) -->
                      <td v-if="rIdx === 0" 
                          :rowspan="group.rows.length" 
                          class="py-1 px-2 align-middle border-r border-slate-200 dark:border-slate-700 bg-gray-50/50 dark:bg-slate-800/50"
                      >
                          <div class="flex items-center gap-1.5 break-all">
                              <!-- Color Indicator (Merged) -->
                              <span v-if="group.color && group.color !== '#000000'" :style="{ color: darkenColor(group.color, 0.88) }" class="min-w-[10px] text-sm leading-none">█</span>
                              
                              <!-- City + District -->
                              <span class="font-bold text-slate-700 dark:text-slate-300 leading-tight">
                                  {{ group.city }}{{ group.district }}
                              </span>
                          </div>
                      </td>

                      <!-- Pronunciation -->
                      <td class="py-1 px-2 align-middle border-r border-slate-200 dark:border-slate-700">
                          <div class="flex flex-wrap items-center gap-x-2">
                               <div v-for="(item, i) in row.items" :key="i" class="flex items-baseline gap-x-1">
                                   <!-- Colored Jyutping -->
                                   <span class="font-mono text-[13px] leading-none tracking-tight">
                                       <span class="text-[#D32913] dark:text-red-400">{{ item.initial }}</span><span class="text-emerald-700 dark:text-emerald-400">{{ item.nuclei }}</span><span class="text-emerald-700 dark:text-emerald-400">{{ item.coda }}</span><span class="text-amber-600 dark:text-amber-400">{{ item.tone }}</span>
                                   </span>
                                   
                                   <!-- IPA (Always render if showIPA, debugging if empty) -->
                                   <span v-if="props.showIPA" class="font-sans text-[11px] text-slate-500 dark:text-slate-400 leading-none">
                                       <span v-if="item.ipa">[{{ item.ipa }}]</span>
                                       <!-- Optional: Show placeholder if IPA is missing but requested? No, cleaner to show nothing if empty. -->
                                   </span>
                               </div>
                          </div>
                      </td>

                      <!-- Note -->
                      <td class="py-1 px-2 text-slate-500 text-[11px] align-middle leading-tight whitespace-nowrap overflow-hidden text-ellipsis">
                          {{ row.note }}
                      </td>
                  </tr>
              </template>
          </tbody>
      </table>
  </div>
</template>
