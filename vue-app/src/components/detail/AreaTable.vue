<script setup>
import { computed } from 'vue';
import { darkenColor } from '@/utils/formatters.js';
import { Jyutping } from '@/utils/jyutping.js';

const props = defineProps({
  data: {
    type: Object, 
    required: true
  },
  showIPA: {
    type: Boolean,
    default: false
  }
});

// Process data to parse Jyutping and IPA
const rows = computed(() => {
    return props.data.map(row => {
        const safeSplit = (val) => (val || '').split('=');
        
        // Use Raw JPP if available for better parsing
        // If not, revert to split fields (fallback)
        const jpps = safeSplit(row.jpp || row.粵拼); // Handle mapped key
        
        // If jpps exist, parse them.
        // Fallback to pre-split fields if jpp is missing (though we added it to API)
        const initials = safeSplit(row.initial);
        const nucleis = safeSplit(row.nuclei);
        const codas = safeSplit(row.coda);
        const tones = safeSplit(row.tone);
        const ipas = safeSplit(row.ipa);

        // Map JPPs to items
        // Use jpps length if available
        const count = Math.max(jpps.length, initials.length, ipas.length);
        
        const items = [];
        for (let i = 0; i < count; i++) {
            let parsed = null;
            if (jpps[i]) {
                parsed = Jyutping.parse(jpps[i]);
            }
            
            // Construct item
            items.push({
                initial: parsed ? parsed.initial : (initials[i] || ''),
                nuclei: parsed ? parsed.nuclei : (nucleis[i] || ''),
                coda: parsed ? parsed.coda : (codas[i] || ''),
                tone: parsed ? parsed.tone : (tones[i] || ''),
                ipa: ipas[i] || '',
                raw: jpps[i] || ''
            });
        }
        
        return {
            ...row,
            items
        };
    });
});

</script>

<template>
  <div v-if="rows && rows.length > 0" class="mb-1">
      
      <!-- Minimal Header -->
      <div v-if="false" class="flex justify-between items-center border-b border-slate-100 dark:border-slate-800 px-1 py-0.5">
      </div>
      
      <!-- Dense Table -->
      <table class="w-full text-xs text-left border-collapse">
          <thead class="bg-gray-50 dark:bg-slate-900 border-b border-gray-200 dark:border-slate-800">
              <tr>
                  <th class="py-1 px-2 font-bold text-slate-700 dark:text-slate-300 w-[20%]">
                      {{ rows[0].city }}{{ rows[0].division_adm ? `(${rows[0].division_adm})` : '' }}
                  </th>
                  <th class="py-1 px-2 w-[35%]"></th>
                  <th class="py-1 px-2"></th>
              </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
              <tr v-for="(row, idx) in rows" :key="idx" class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                  <!-- Color & Location -->
                  <td class="py-0.5 px-2 align-middle border-r border-[#f0f0f0] dark:border-slate-800">
                      <div class="flex items-center gap-1.5 whitespace-nowrap overflow-hidden text-ellipsis">
                          <span v-if="row.color && row.color !== '#000000'" :style="{ color: darkenColor(row.color, 0.88) }" class="min-w-[10px] text-[10px] leading-none">█</span>
                          <span v-if="row.district" class="font-normal text-slate-600 dark:text-slate-400 leading-none">{{ row.district }}</span>
                      </div>
                  </td>

                  <!-- Pronunciation -->
                  <td class="py-0.5 px-2 align-middle border-r border-[#f0f0f0] dark:border-slate-800">
                      <div class="flex flex-wrap items-center gap-x-2">
                           <div v-for="(item, i) in row.items" :key="i" class="flex items-baseline gap-x-1">
                               <!-- Colored Jyutping -->
                               <span class="font-mono text-[13px] leading-none tracking-tight">
                                   <span class="text-[#D32913] dark:text-red-400">{{ item.initial }}</span><span class="text-emerald-700 dark:text-emerald-400">{{ item.nuclei }}</span><span class="text-emerald-700 dark:text-emerald-400">{{ item.coda }}</span><span class="text-amber-600 dark:text-amber-400">{{ item.tone }}</span>
                               </span>
                               
                               <!-- IPA -->
                               <span v-if="props.showIPA && item.ipa" class="font-sans text-[11px] text-slate-400 dark:text-slate-500 leading-none">
                                   [{{ item.ipa }}]
                               </span>
                           </div>
                      </div>
                  </td>

                  <!-- Note -->
                  <td class="py-0.5 px-2 text-slate-400 text-[10px] align-middle leading-tight whitespace-nowrap overflow-hidden text-ellipsis">
                      {{ row.note }}
                  </td>
              </tr>
          </tbody>
      </table>
  </div>
</template>
