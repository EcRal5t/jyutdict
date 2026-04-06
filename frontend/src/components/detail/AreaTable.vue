<script setup>
import { computed, ref, reactive, onMounted } from 'vue';
import { Jyutping } from '@/utils/jyutping.js';
import articlesApi from '@/api/articles.js';
import LocationArticleModal from '@/components/LocationArticleModal.vue';

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

// ===== 地点文章相关 =====
const articleLocationSet = ref(new Set()) // 存储有文章的地点名称
const modalSource = ref('')
const modalLocationName = ref('')
const showModal = ref(false)

// 加载有文章的地点列表
const loadArticleLocations = async () => {
    try {
        const res = await articlesApi.getArticleList()
        const articles = res.data.articles || []
        const set = new Set()
        articles.forEach(a => {
            // AreaTable 展示的是 area 类型的地点
            if (a.location_source === 'area') {
                set.add(a.location_name)
            }
        })
        articleLocationSet.value = set
    } catch (e) {
        // 静默失败，不影响主功能
    }
}

// 检查某地点是否有文章
const hasArticle = (cityName, districtName) => {
    const name = cityName + (districtName || '')
    return articleLocationSet.value.has(name)
}

// 点击地点名称
const openArticleModal = (cityName, districtName) => {
    const name = cityName + (districtName || '')
    if (!articleLocationSet.value.has(name)) return
    modalSource.value = 'area'
    modalLocationName.value = name
    showModal.value = true
}

const closeModal = () => {
    showModal.value = false
}

onMounted(() => {
    loadArticleLocations()
})

// State for expanding long notes (using Set for ID tracking)
// We use CSS hover for visual expansion, but maybe user wants it to stick?
// "Mouse hover ... mutations". The user wants gradient/animation.
// We will use CSS transitions on max-height.


// Groups Logic
const tableRows = computed(() => {
    const groups = [];
    let lastGroupKey = null;
    let currentGroup = null;

    props.data.forEach(loc => {
        const city = loc.city || '';
        const district = loc.district || '';
        const groupKey = city + '|' + district;

        if (groupKey !== lastGroupKey) {
            currentGroup = {
                cityName: city,
                districtName: district,
                division: loc.division,
                locations: [],
                totalSenses: 0
            };
            groups.push(currentGroup);
            lastGroupKey = groupKey;
        }

        const jpps = loc['粵拼'] || [];
        const ipas = loc['IPA'] || [];
        const notes = loc['注釋'] || [];
        const altGroups = loc['又音組'] || [];

        // 根据 alt_group 分组读音
        // 如果多个读音有相同的 alt_group（非 null），它们是又音，需要用 · 连接
        // 如果 alt_group 不同或为 null，它们是多音，需要分行显示
        const senseMap = new Map(); // alt_group -> { prons: [], note: '' }
        const nullGroupProns = []; // alt_group 为 null 的读音

        for (let i = 0; i < jpps.length; i++) {
            const rawJpp = jpps[i] || '';
            const rawIpa = ipas[i] || '';
            const note = notes[i] || '';
            const altGroup = altGroups[i];

            const parsed = Jyutping.parse(rawJpp);
            const pronObj = {
                initial: parsed ? parsed.initial : '',
                nuclei: parsed ? parsed.nuclei : '',
                coda: parsed ? parsed.coda : '',
                tone: parsed ? parsed.tone : '',
                val: rawJpp,
                ipa: rawIpa
            };

            if (altGroup !== null && altGroup !== undefined) {
                // 有又音组标记
                const key = String(altGroup);
                if (!senseMap.has(key)) {
                    senseMap.set(key, { prons: [], note: note });
                }
                senseMap.get(key).prons.push(pronObj);
            } else {
                // 没有又音组标记，独立读音
                nullGroupProns.push({
                    prons: [pronObj],
                    note: note
                });
            }
        }

        const senses = [];

        // 添加有又音组标记的读音
        senseMap.forEach((sense) => {
            senses.push({
                prons: sense.prons,
                note: sense.note,
                isAltPron: sense.prons.length > 1 // 标记是否为又音
            });
        });

        // 添加独立的读音
        nullGroupProns.forEach(sense => {
            senses.push({
                prons: sense.prons,
                note: sense.note,
                isAltPron: false
            });
        });

        currentGroup.locations.push({
            name: loc.division || '地點',
            color: loc.color || '#999999',
            senses: senses,
            totalSenses: senses.length
        });

        currentGroup.totalSenses += senses.length;
    });

    const rows = [];
    let globalRowIndex = 0;

    groups.forEach(group => {
        group.locations.forEach((loc, locIdx) => {
            loc.senses.forEach((sense, senseIdx) => {
                const row = {
                    id: globalRowIndex++,
                    
                    // City Column
                    cityData: (locIdx === 0 && senseIdx === 0) ? {
                        cityName: group.cityName,
                        districtName: group.districtName,
                        span: group.totalSenses
                    } : null,

                    // Location Column
                    locData: (senseIdx === 0) ? {
                        name: loc.name,
                        color: getColors(loc.color),
                        span: loc.totalSenses
                    } : null,
                    prons: sense.prons,
                    note: sense.note,
                };
                rows.push(row);
            });
        });
    });

    return rows;
});

const getColors = (colorStr) => {
    if (!colorStr) return [];
    // User: "API returns comma separated #7F7F7F,#FFFFFF". Ignore #000000.
    // colorStr = colorStr + ",#FF7300";
    return colorStr.split(',')
        .map(c => c.trim())
        .filter(c => c && c !== '#000000');
};

</script>

<template>
  <div v-if="tableRows.length > 0" class="mb-4 bg-white/50 dark:bg-slate-800/50 rounded lg:rounded-lg overflow-hidden text-md border border-slate-200 dark:border-slate-700">
      <table class="w-full text-left border-collapse text-sm">
          <thead class="bg-gray-100/80 dark:bg-slate-900/80 border-b border-gray-200 dark:border-slate-700 backdrop-blur-sm">
              <tr>
                  <th class="py-1 px-2 font-bold text-slate-700 dark:text-slate-300 w-[35%] border-r border-slate-200 dark:border-slate-700 whitespace-nowrap text-center">
                      地點
                  </th>
                  <th class="py-1 px-2 w-[30%] border-r border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 whitespace-nowrap text-center">
                      讀音
                  </th>
                  <th class="py-1 px-2 text-slate-700 dark:text-slate-300 whitespace-nowrap text-center">
                      備註
                  </th>
              </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
              <tr v-for="row in tableRows" :key="row.id" class="hover:bg-white/60 dark:hover:bg-slate-700/40 transition-colors group">
                  
                  <!-- Location Cell -->
                  <td v-if="row.locData" 
                      :rowspan="row.locData.span" 
                      class="relative py-0.5 px-2 align-middle border-r border-slate-200 dark:border-slate-700 bg-white/40 dark:bg-slate-800/40"
                  >
                      <!-- Absolute Color Blocks on LEFT (Horizontal Stack) -->
                      <div class="absolute left-0 top-0 bottom-0 flex flex-row items-center h-full z-0 opacity-80">
                          <div v-for="(col, cIdx) in row.locData.color" 
                               :key="cIdx"
                               class="h-full w-2 border-r border-white/20"
                               :style="{ backgroundColor: col }"
                          ></div>
                      </div>

                      <!-- Centered Text -->
                      <div class="relative z-10 w-full text-center leading-tight px-2 drop-shadow-sm shadow-black"
                           :class="{ 'cursor-pointer': hasArticle(row.cityData.cityName, row.cityData.districtName) }"
                           @click="openArticleModal(row.cityData.cityName, row.cityData.districtName)">
                          <span class="text-base text-slate-800 dark:text-slate-200"
                                :class="{ 'underline decoration-accent/40 decoration-1 underline-offset-2': hasArticle(row.cityData.cityName, row.cityData.districtName) }">
                              {{ row.cityData.cityName }}
                          </span>
                          <span v-if="row.cityData.districtName"
                                class="text-sm text-neutral-700 dark:text-neutral-300 p-0.5"
                                :class="{ 'underline decoration-accent/40 decoration-1 underline-offset-2': hasArticle(row.cityData.cityName, row.cityData.districtName) }">
                              {{ row.cityData.districtName }}
                          </span>
                      </div>
                  </td>

                  <!-- Pronunciation -->
                  <td class="py-0.5 px-4 align-middle border-r border-slate-200 dark:border-slate-700 relative"
                      :colspan="(!row.note) ? 2 : 1"
                  >
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                            <template v-for="(pron, pIdx) in row.prons" :key="pIdx">
                                <div class="flex items-baseline gap-x-0.5">
                                    <span v-if="pIdx > 0" class="text-slate-300 text-xs"> · </span>
                                    
                                    <span class="font-mono text-md leading-snug tracking-tight">
                                        <span class="text-[#CC2014] dark:text-red-400">{{ pron.initial }}</span><span class="text-emerald-800 dark:text-emerald-400">{{ pron.nuclei }}</span><span class="text-emerald-800 dark:text-emerald-400">{{ pron.coda }}</span><span class="text-amber-700 dark:text-amber-500">{{ pron.tone }}</span>
                                    </span>
                                    
                                    <span v-if="showIPA && pron.ipa" class="font-sans text-slate-500 dark:text-slate-500 ml-0.5">
                                        {{ pron.ipa }}
                                    </span>
                                </div>
                            </template>
                        </div>
                  </td>

                  <!-- Note -->
                  <td v-if="row.note" 
                      class="py-0.5 px-2 text-slate-500 dark:text-slate-400 align-middle leading-tight max-w-[150px] relative group/note"
                  >
                      <!-- 
                        Using max-height transition. 
                        Default: max-h-[1.5em] (roughly 1 line, depends on line-height).
                        Hover: max-h-[200px].
                      -->
                      <div class="relative overflow-hidden transition-all duration-500 ease-in-out max-h-[1.4rem] group-hover/note:max-h-[20rem]">
                          <div class="">
                            {{ row.note }}
                          </div>
                      </div>
                      
                      <!-- Gradient overlay that fades out on hover -->
                      <div v-if="row.note.length > 8" 
                           class="absolute right-0 top-0 bottom-0 w-8 bg-gradient-to-l from-white dark:from-slate-800 to-transparent pointer-events-none transition-opacity duration-300 group-hover/note:opacity-0">
                      </div>
                  </td>

              </tr>
          </tbody>
      </table>
  </div>

  <!-- 地点文章弹窗 -->
  <Teleport to="body">
      <LocationArticleModal v-if="showModal"
          :source="modalSource"
          :location-name="modalLocationName"
          @close="closeModal" />
  </Teleport>
</template>
