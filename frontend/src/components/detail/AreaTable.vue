<script setup>
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
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
  },
  compact: {
    type: Boolean,
    default: false
  }
});

const modalLocationName = ref('')
const showModal = ref(false)
const hoveredLocationKey = ref(null)
const popoverPlacement = ref('left')
const articleState = reactive({})
let hideTimer = null

const getArticleKey = (location) => `location:${location.articleName}`

const updatePopoverPlacement = (trigger) => {
    const anchor = trigger?.parentElement
    if (!anchor) return

    const rect = anchor.getBoundingClientRect()
    const requiredSpace = 348
    const viewportPadding = 16
    const leftSpace = rect.left - viewportPadding
    const rightSpace = window.innerWidth - rect.right - viewportPadding

    if (leftSpace >= requiredSpace) {
        popoverPlacement.value = 'left'
    } else if (rightSpace >= requiredSpace) {
        popoverPlacement.value = 'right'
    } else {
        popoverPlacement.value = 'below'
    }
}

const showLocationDetails = async (location, event = null) => {
    if (hideTimer) clearTimeout(hideTimer)
    if (event?.currentTarget) updatePopoverPlacement(event.currentTarget)
    hoveredLocationKey.value = location.key

    const articleKey = getArticleKey(location)
    if (articleState[articleKey]) return
    articleState[articleKey] = 'loading'
    try {
        articleState[articleKey] = await articlesApi.checkArticle(location.articleName)
            ? 'available'
            : 'missing'
    } catch (e) {
        articleState[articleKey] = 'missing'
    }
}

const scheduleHideLocationDetails = () => {
    if (hideTimer) clearTimeout(hideTimer)
    hideTimer = setTimeout(() => {
        hoveredLocationKey.value = null
    }, 500)
}

const openArticleModal = (location) => {
    if (articleState[getArticleKey(location)] !== 'available') return
    modalLocationName.value = location.articleName
    showModal.value = true
}

const closeModal = () => {
    showModal.value = false
}

const tableRows = computed(() => {
    const rows = [];
    let globalRowIndex = 0;
    props.data.forEach((loc, locIndex) => {
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

        const articleName = `${loc.city || ''}${loc.district || ''}`;
        senses.forEach((sense, senseIndex) => {
            rows.push({
                id: globalRowIndex++,
                locData: senseIndex === 0 ? {
                    key: loc.id ?? `${articleName}-${locIndex}`,
                    articleName,
                    cityName: loc.city || '',
                    districtName: loc.district || '',
                    detailedName: loc.detailedName || '',
                    sheetInfo: loc.sheetInfo || '',
                    color: getColors(loc.color || '#999999'),
                    span: senses.length
                } : null,
                prons: sense.prons,
                note: sense.note,
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

onBeforeUnmount(() => {
    if (hideTimer) clearTimeout(hideTimer)
})

</script>

<template>
  <div v-if="tableRows.length > 0"
      class="bg-white/50 dark:bg-slate-800/50 rounded lg:rounded-lg overflow-visible text-md border border-slate-200 dark:border-slate-700"
      :class="compact ? 'mb-0' : 'mb-4'">
      <table class="w-full text-left border-collapse text-sm" :class="{ 'table-fixed': !compact }">
          <thead class="bg-gray-100/80 dark:bg-slate-900/80 border-b border-gray-200 dark:border-slate-700 backdrop-blur-sm">
              <tr>
                  <th class="py-1 px-2 font-bold text-slate-700 dark:text-slate-300 border-r border-slate-200 dark:border-slate-700 whitespace-nowrap text-center"
                      :class="compact ? 'w-[34%] md:w-[22%] xl:w-[18%]' : 'w-[36%]'">
                      地點
                  </th>
                  <th class="py-1 px-2 border-r border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 whitespace-nowrap text-center"
                      :class="compact ? 'w-[32%] md:w-[28%]' : 'w-[38%]'">
                      讀音
                  </th>
                  <th class="relative py-1 px-2 text-slate-700 dark:text-slate-300 whitespace-nowrap text-center"
                      :class="compact ? '' : 'w-[26%]'">
                      備註
                      <span class="absolute right-1 top-1/2 -translate-y-1/2">
                          <slot name="actions"></slot>
                      </span>
                  </th>
              </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
              <tr v-for="row in tableRows" :key="row.id"
                  class="hover:bg-white/60 dark:hover:bg-slate-700/40 transition-colors group"
                  :class="{ 'relative z-[80]': row.locData && hoveredLocationKey === row.locData.key }">
                  
                  <!-- Location Cell -->
                  <td v-if="row.locData" 
                      :rowspan="row.locData.span" 
                      class="relative py-0.5 px-2 align-middle border-r border-slate-200 dark:border-slate-700 bg-white/40 dark:bg-slate-800/40"
                      :class="hoveredLocationKey === row.locData.key ? 'z-[90]' : 'z-0'"
                  >
                      <!-- Absolute Color Blocks on LEFT (Horizontal Stack) -->
                      <div class="absolute left-0 top-0 bottom-0 flex flex-row items-center h-full z-0 opacity-80">
                          <div v-for="(col, cIdx) in row.locData.color" 
                               :key="cIdx"
                               class="h-full w-2 border-r border-white/20"
                               :style="{ backgroundColor: col }"
                          ></div>
                      </div>

                          <!-- 地名及按需加载的二级信息卡 -->
                          <div class="relative z-10 w-full text-center leading-tight px-2 drop-shadow-sm shadow-black">
                              <button type="button" class="inline-block"
                              @mouseenter="showLocationDetails(row.locData, $event)" @mouseleave="scheduleHideLocationDetails"
                              @focus="showLocationDetails(row.locData, $event)" @blur="scheduleHideLocationDetails"
                              @click="showLocationDetails(row.locData, $event)" aria-label="顯示地點詳細信息">
                              <span class="text-base text-slate-800 dark:text-slate-200 cursor-help">
                                  {{ row.locData.cityName }}
                              </span>
                              <span v-if="row.locData.districtName"
                                    class="text-sm text-neutral-700 dark:text-neutral-300 p-0.5 cursor-help">
                                  {{ row.locData.districtName }}
                              </span>
                          </button>

                          <Transition name="location-card">
                              <div v-if="hoveredLocationKey === row.locData.key"
                                  class="location-popover absolute z-[100] pointer-events-auto w-80 max-w-[calc(100vw-2rem)] border-2 border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 p-4 text-left shadow-[6px_6px_0_rgba(0,0,0,0.12)] dark:shadow-[6px_6px_0_rgba(0,0,0,0.35)]"
                                  :class="`location-popover--${popoverPlacement}`"
                                  @mouseenter="showLocationDetails(row.locData)"
                                  @mouseleave="scheduleHideLocationDetails">
                                  <div class="font-bold text-slate-900 dark:text-slate-100 mb-2">
                                      {{ row.locData.detailedName || row.locData.articleName }}
                                  </div>
                                  <div class="min-h-16 border-l-4 border-accent/70 bg-slate-50 dark:bg-slate-900/60 px-3 py-2 text-xs leading-relaxed whitespace-pre-wrap text-slate-600 dark:text-slate-300">{{ row.locData.sheetInfo || '暫無地點詳細信息' }}</div>
                                  <div class="flex gap-2 mt-3">
                                      <button type="button"
                                          class="px-3 py-1.5 text-xs font-bold border transition-colors"
                                          :disabled="articleState[getArticleKey(row.locData)] !== 'available'"
                                          :class="articleState[getArticleKey(row.locData)] === 'available'
                                              ? 'border-accent text-accent hover:bg-accent hover:text-white'
                                              : 'border-slate-200 dark:border-slate-700 text-slate-400 cursor-not-allowed opacity-70'"
                                          @click="openArticleModal(row.locData)">
                                          地點介紹
                                      </button>
                                      <button type="button" disabled title="音系內容尚未開放"
                                          class="px-3 py-1.5 text-xs font-bold border border-slate-200 dark:border-slate-700 text-slate-400 cursor-not-allowed opacity-70">
                                          音系
                                      </button>
                                  </div>
                              </div>
                          </Transition>
                      </div>
                  </td>

                  <!-- Pronunciation -->
                  <td class="py-0.5 px-2 sm:px-4 align-middle border-r border-slate-200 dark:border-slate-700 relative"
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
                      class="py-0.5 px-2 text-slate-500 dark:text-slate-400 align-middle leading-tight relative group/note"
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
          :location-name="modalLocationName"
          @close="closeModal" />
  </Teleport>
</template>

<style scoped>
.location-popover {
    top: 50%;
    transform: translateY(-50%);
}

.location-popover--left {
    right: calc(100% + 0.75rem);
}

.location-popover--right {
    left: calc(100% + 0.75rem);
}

.location-popover--below {
    left: 0;
    top: calc(100% + 0.75rem);
    transform: none;
}

.location-card-enter-active,
.location-card-leave-active {
    transition: opacity 150ms ease, transform 150ms ease;
}

.location-card-enter-from.location-popover--left,
.location-card-leave-to.location-popover--left {
    opacity: 0;
    transform: translate(-0.35rem, -50%);
}

.location-card-enter-from.location-popover--right,
.location-card-leave-to.location-popover--right {
    opacity: 0;
    transform: translate(0.35rem, -50%);
}

.location-card-enter-from.location-popover--below,
.location-card-leave-to.location-popover--below {
    opacity: 0;
    transform: translateY(-0.35rem);
}
</style>
