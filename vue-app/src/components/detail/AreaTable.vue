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
  <div v-if="rows && rows.length > 0" class="mb-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
      
      <!-- Header per City Group -->
      <div class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 px-2 py-1 flex justify-between items-center">
          <div class="font-bold text-slate-700 dark:text-slate-300 text-xs">
              {{ rows[0].city }} 
              <span v-if="rows[0].division_adm" class="text-[9px] font-normal text-slate-400 ml-1 border border-slate-200 dark:border-slate-700 rounded px-1">{{ rows[0].division_adm }}</span>
          </div>
      </div>

      <table class="w-full text-xs text-left">
          <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
              <tr v-for="(row, idx) in rows" :key="idx" class="group hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                  <!-- Color & Location (Sub-district) -->
                  <td class="p-1.5 w-[20%] align-top border-r border-transparent">
                      <div class="flex items-start gap-1">
                          <span v-if="row.color && row.color !== '#000000'" :style="{ color: darkenColor(row.color, 0.88) }" class="min-w-3 text-xs leading-none">█</span>
                          <span v-if="row.district" class="font-medium text-slate-600 dark:text-slate-300 break-words leading-tight">{{ row.district }}</span>
                          <span v-else class="text-slate-400 italic"> - </span>
                      </div>
                  </td>

                  <!-- Pronunciation -->
                  <td class="p-1.5 align-top">
                      <div class="flex flex-col gap-0.5">
                           <div v-for="(item, i) in row.items" :key="i" class="flex flex-wrap items-baseline gap-x-2">
                               <!-- Colored Jyutping -->
                               <span class="font-mono text-[13px] leading-tight">
                                   <!-- Colors: Initial=Red, Nuclei=Green, Coda=Green, Tone=Yellow -->
                                   <span class="text-[#D32913] dark:text-red-400">{{ item.initial }}</span><span class="text-emerald-700 dark:text-emerald-400">{{ item.nuclei }}</span><span class="text-emerald-700 dark:text-emerald-400">{{ item.coda }}</span><span class="text-amber-600 dark:text-amber-400">{{ item.tone }}</span>
                               </span>
                               
                               <!-- IPA -->
                               <span v-if="props.showIPA && item.ipa" class="font-sans text-[11px] text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 px-1 rounded-sm leading-none ml-1">
                                   [{{ item.ipa }}]
                               </span>
                           </div>
                      </div>
                  </td>

                  <!-- Note -->
                  <td class="p-1.5 text-slate-500 text-[11px] align-top w-[35%] leading-tight border-l border-slate-50 dark:border-slate-800/50">
                      {{ row.note }}
                  </td>
              </tr>
          </tbody>
      </table>
  </div>
</template>
