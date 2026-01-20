<script setup>
import { computed, ref } from 'vue';
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

// State for expanding long notes
const expandedRows = ref(new Set());
const toggleNote = (idx) => {
    if (expandedRows.value.has(idx)) {
        expandedRows.value.delete(idx);
    } else {
        expandedRows.value.add(idx);
    }
};

// Groups Logic
const tableRows = computed(() => {
    // 1. Sort data (Optional, assuming API sends reasonable order)
    
    // 2. Process into hierarchical groups first to calculate spans
    const groups = [];
    let lastGroupKey = null;
    let currentGroup = null;

    props.data.forEach(loc => {
        // Group key: City + District
        // Sometimes district is empty, fallback to Division? 
        // User screenshot shows "1897香山" which implies grouping by strict equality of these fields.
        const city = loc.city || '';
        const district = loc.district || '';
        const groupKey = city + '|' + district;

        if (groupKey !== lastGroupKey) {
            currentGroup = {
                displayName: city + (district ? ` ${district}` : ''),
                division: loc.division, // keep if needed
                locations: [],
                totalSenses: 0
            };
            groups.push(currentGroup);
            lastGroupKey = groupKey;
        }

        // Process Senses (Polyphones) for this location
        const jpps = loc['粵拼'] || []; // Array of strings (e.g. "cung4=cung5")
        const ipas = loc['IPA'] || [];
        const notes = loc['注釋'] || [];
        
        // Sometimes arrays are empty, ensure at least one row if it's a valid location?
        // If empty, we show one empty row.
        const count = Math.max(jpps.length, ipas.length, 1);
        const senses = [];

        for (let i = 0; i < count; i++) {
            const rawJpp = jpps[i] || '';
            const rawIpa = ipas[i] || '';
            const note = notes[i] || '';

            // Handle Heteronyms (split by '=')
            const jppParts = rawJpp.split('=').map(s => s.trim()).filter(s => s);
            const ipaParts = rawIpa.split('=').map(s => s.trim()).filter(s => s);
            
            // Map to objects
            // We align jppParts and ipaParts by index if possible
            const prons = jppParts.map((part, pIdx) => {
                const parsed = Jyutping.parse(part);
                return {
                    initial: parsed ? parsed.initial : '',
                    nuclei: parsed ? parsed.nuclei : '',
                    coda: parsed ? parsed.coda : '',
                    tone: parsed ? parsed.tone : '',
                    val: part,
                    ipa: ipaParts[pIdx] || '' // Matching index
                };
            });

            senses.push({
                prons,
                note
            });
        }

        currentGroup.locations.push({
            name: loc.division_adm || loc['地點'] || 'Unknown', // Using division_adm as specific point name based on context? Or "division"? The user mentioned "Location name".
            // Actually, in `DetailView.vue`:
            // division: headerInfo.first
            // city: headerInfo.second
            // district: headerInfo.third
            // color: headerInfo.color
            // The "Location Name" in the user screenshot shows "鶴山沙坪", "台山大江". These are likely `division_adm` or just the `headerInfo` name?
            // The API returns `location` entries. 
            // In `DetailView`, we map `division` -> `first`, `city` -> `second`. 
            // The user screenshot column 1 is "1897香山" (City?). 
            // Column 2 is "鶴山沙坪" (Point?). 
            // Let's use `division_adm` from the API entry itself if available, or construct it.
            // Wait, looking at DetailView map:
            // `charEntry.location` items have `city`, `division`, `district` mapped from `headerInfo`.
            // ORIGINAL DATA has `division_adm`? It was in the comment: `v0.9 had: division_adm`.
            // This new API uses `id` to link to headers.
            // So `loc` has `city` (second), `division` (first), `district` (third).
            // The grouping key `city` + `district` corresponds to "1897香山".
            // The specific point name must be `division`.
            name: loc.division || '地點', 
            color: loc.color || '#999',
            senses: senses,
            totalSenses: senses.length
        });

        currentGroup.totalSenses += senses.length;
    });

    // 3. Flatten to Rows
    const rows = [];
    let globalRowIndex = 0;

    groups.forEach(group => {
        let groupProcessedSenses = 0;
        
        group.locations.forEach((loc, locIdx) => {
            loc.senses.forEach((sense, senseIdx) => {
                const row = {
                    id: globalRowIndex++,
                    
                    // City Column
                    cityData: (locIdx === 0 && senseIdx === 0) ? {
                        name: group.displayName,
                        span: group.totalSenses
                    } : null,

                    // Location Column
                    locData: (senseIdx === 0) ? {
                        name: loc.name,
                        color: loc.color,
                        span: loc.totalSenses
                    } : null,

                    // Content
                    prons: sense.prons,
                    note: sense.note,
                    
                    // Flags
                    isGroupStart: (locIdx === 0 && senseIdx === 0),
                    isLocStart: (senseIdx === 0)
                };
                rows.push(row);
            });
        });
    });

    return rows;
});

// Check if note is long
const isLongNote = (note) => {
    return note && note.length > 15;
};

</script>

<template>
  <div v-if="tableRows.length > 0" class="mb-4 bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden text-sm">
      <table class="w-full text-left border-collapse">
          <thead class="bg-gray-100 dark:bg-slate-900 border-b border-gray-300 dark:border-slate-600">
              <tr>
                  <th class="py-2 px-2 font-bold text-slate-700 dark:text-slate-300 w-[30%] border-r border-slate-200 dark:border-slate-700 whitespace-nowrap">
                      地點
                  </th>
                  <th class="py-2 px-2 w-[25%] border-r border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 whitespace-nowrap">
                      讀音
                  </th>
                  <th class="py-2 px-2 text-slate-700 dark:text-slate-300 whitespace-nowrap">
                      備註
                  </th>
              </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
              <tr v-for="row in tableRows" :key="row.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                  
                  <!-- City/Group Column -->
                  <!-- Note: Effectively merged with Location Column logic-wise in user's request description they said "Location and Color... merged into one cell" for the split rows. 
                       But they also have the "1897香山" header. 
                       In the image: 
                       Column 1: "1897香山" (Spans many rows)
                       Column 2: "鶴山沙坪" (Spans its senses), "开平..." etc.
                       Wait, the user image has a header "地点" that seems to span Col 1 and Col 2 visually? No, standard table.
                       Let's stick to: Col 1 = Group (City), Col 2 = Point (Division) ??
                       Actually, the image shows:
                       Header: 地點 (Points to first column)
                       Column 1 Content: "1897香山" -> "Name"
                       BUT next to it are colored blocks "鹤山...", "开平...".
                       Ah, the image table has:
                       [Header: 地點] [Header: 讀音] [Header: 備註]
                       Row 1: "1897香山" (Gray BG, spans all 3 cols? or just a header row?)
                       Row 2: [Red Block] 鹤山... | cung4... | Note
                       It seems "1897香山" is a Section Header Row!
                       User said: "Representing location row header color block needs redesign...".  
                       "Merged Header: For polyphones... the line head (Place Name and Color) needs to be merged".
                       
                       Re-reading image carefully:
                       There is a row "1897香山" spanning the whole table width? No, it's just the first row. 
                       It has "zuung3" in the second col.
                       So "1897香山" is just another point!
                       
                       BUT, the user request text says:
                       "id is 16 is Guangzhou... id 17 is Fictional..."
                       Currently `locations` have `city` (Guangzhou) and `division` (District/Point).
                       
                       If I look at my generated rows:
                       I have `cityData` and `locData`.
                       If I want to match the image structure more closely: 
                       The image shows names like "台山大江", "台山斗山". These are specific points.
                       The "1897香山" row seems to be a point too.
                       
                       The "Group" concept I implemented (City+District) corresponds to the "1897香山" level? 
                       No, "1897香山" is likely a `division` name.
                       
                       Let's assume the standard structure:
                       Col 1: Location Name (merged for senses).
                       Col 2: Pronunciation (merged with Note for empty note).
                       Col 3: Note.
                       
                       I will use `cityData` to group visually if needed, but let's stick to the flattening.
                       Wait, mapping `division` -> `locData.name` is the key.
                       And `color` -> `locData.color`.
                       
                       What about the "1897香山" grouping? The previous code had it.
                       I will create a "Section Header" row if the group name changes?
                       The user image shows "1897香山" as a row with data "zuung3". 
                       So it's just a location.
                       
                       Okay, I will DROP the "City" column and just show the "Location" column. 
                       The "City" info might be redundant or part of the name.
                       In `DetailView.vue`, `division` = `headerInfo.first`.
                       Let's just use `loc.division` (which is mapped to `headerInfo.first`) as the name.
                       And `loc.city` is `headerInfo.second`.
                       Usually `headerInfo.first` is the Display Name.
                  -->
                  
                  <!-- Location Cell (Merged) -->
                  <td v-if="row.locData" 
                      :rowspan="row.locData.span" 
                      class="py-1 px-2 align-middle border-r border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 relative group-hover:bg-slate-50 dark:group-hover:bg-slate-700/30"
                  >
                      <div class="flex items-center gap-2 h-full">
                          <!-- Color Bar Design -->
                          <div class="w-1.5 self-stretch rounded-full" 
                               :style="{ backgroundColor: row.locData.color }">
                          </div>
                          <span class="text-slate-700 dark:text-slate-300 text-sm leading-tight">
                              {{ row.cityData.name }}
                          </span>
                      </div>
                  </td>

                  <!-- Pronunciation -->
                  <td class="py-1 px-2 align-middle border-r border-slate-200 dark:border-slate-700"
                      :colspan="(!row.note) ? 2 : 1"
                  >
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <template v-for="(pron, pIdx) in row.prons" :key="pIdx">
                                <div class="flex items-baseline gap-x-1">
                                    <!-- Separator for Heteronyms -->
                                    <span v-if="pIdx > 0" class="text-slate-400 font-light">/</span>
                                    
                                    <!-- Jyutping -->
                                    <span class="font-mono text-[15px] leading-none tracking-tight">
                                        <span class="text-[#D32913] dark:text-red-400 font-medium">{{ pron.initial }}</span><span class="text-emerald-700 dark:text-emerald-400 font-medium">{{ pron.nuclei }}</span><span class="text-emerald-700 dark:text-emerald-400 font-medium">{{ pron.coda }}</span><span class="text-amber-600 dark:text-amber-400 font-medium">{{ pron.tone }}</span>
                                    </span>
                                    
                                    <!-- IPA -->
                                    <span v-if="showIPA && pron.ipa" class="font-sans text-[12px] text-slate-500 dark:text-slate-400">
                                        [{{ pron.ipa }}]
                                    </span>
                                </div>
                            </template>
                        </div>
                  </td>

                  <!-- Note -->
                  <td v-if="row.note" class="py-1 px-2 text-slate-600 dark:text-slate-400 text-xs align-middle leading-tight">
                      <div @click="toggleNote(row.id)" 
                           class="cursor-pointer hover:text-slate-900 dark:hover:text-slate-200 transition-colors"
                           :class="{ 'line-clamp-1': !expandedRows.has(row.id) }">
                          {{ row.note }}
                      </div>
                  </td>

              </tr>
          </tbody>
      </table>
  </div>
</template>
