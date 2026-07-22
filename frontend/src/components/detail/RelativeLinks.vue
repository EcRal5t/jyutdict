<script setup>
import { computed } from 'vue';

const props = defineProps({
  chara: {
    type: String,
    required: true
  },
  compact: {
    type: Boolean,
    default: false
  },
  compactLabel: {
    type: String,
    default: '快速查詢'
  }
});

const links = computed(() => [
  { label: '漢典', href: `http://www.zdic.net/hans/${props.chara}` },
  { label: '古今文字集成', href: `http://www.ccamc.co/cjkv.php?cjkv=${props.chara}` },
  { label: '漢語多功能字庫', href: `http://humanum.arts.cuhk.edu.hk/Lexis/lexi-mf/search.php?word=${props.chara}` },
  { label: '字統', href: `https://zi.tools/zi/${props.chara}` },
  { label: '粵音資料集叢', href: `https://jyut.net/query?q=${props.chara}`, className: 'link-tag-cantonese' },
  { label: '粵典', href: `https://words.hk/zidin/wan/?q=${props.chara}`, className: 'link-tag-cantonese' },
  { label: '開放粵語詞典', href: `https://kaifangcidian.com/han/yue/?${props.chara}`, className: 'link-tag-cantonese' },
  { label: '粵語辭叢', href: `https://jyutjyu.com/word/${props.chara}`, className: 'link-tag-jyutjyucicung' },
  { label: '漢典-粵', href: `http://www.zdic.net/zd/yy/yy/${props.chara}` },
  { label: '漢典-平', href: `http://www.zdic.net/zd/yy/ph/${props.chara}` },
  { label: '韻典', href: `https://ytenx.org/zim?dzih=${props.chara}&dzyen=1&jtkb=1&jtkd=1&jtdt=1&jtgt=1`, className: 'link-tag-rime' },
  { label: '欽州白話', href: `https://hamzau.com/?q=${props.chara}` }
]);
</script>

<template>
  <details v-if="compact" class="quick-links-compact relative">
      <summary class="cursor-pointer select-none list-none px-1.5 py-0.5 text-[10px] leading-none font-bold text-slate-500 hover:text-accent border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800"
          :title="compactLabel === '查' ? '快速查詢' : undefined">
          {{ compactLabel }}
      </summary>
      <div class="absolute right-0 top-full z-50 mt-1 w-72 max-w-[calc(100vw-2rem)] bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-2 shadow-[4px_4px_0_rgba(0,0,0,0.12)]">
          <div class="flex flex-wrap gap-1">
              <a v-for="link in links" :key="link.label" :href="link.href" target="_blank"
                  class="link-tag" :class="link.className">{{ link.label }}</a>
          </div>
      </div>
  </details>

  <div v-else class="bg-slate-50 dark:bg-slate-900/50 p-4 rounded-lg border border-slate-200 dark:border-slate-800">
      <h4 class="text-xs font-bold text-slate-500 mb-3 uppercase tracking-wider flex items-center gap-2">
          <span>快速查詢</span>
          <span class="bg-gray-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 px-1.5 py-0.5 rounded text-[10px]">{{ chara }}</span>
      </h4>
      <div class="flex flex-wrap gap-1.5">
          <a v-for="link in links" :key="link.label" :href="link.href" target="_blank"
              class="link-tag" :class="link.className">{{ link.label }}</a>
      </div>
  </div>
</template>

<style scoped>
.quick-links-compact summary::-webkit-details-marker {
    display: none;
}

.link-tag {
    @apply inline-flex items-center px-2 py-1.5 rounded text-xs font-medium transition-all duration-200
           bg-white dark:bg-slate-800
           border border-slate-200 dark:border-slate-700
           text-slate-600 dark:text-slate-300
           hover:border-accent hover:text-accent dark:hover:border-red-500 dark:hover:text-red-400
           hover:shadow-sm hover:-translate-y-0.5 active:translate-y-0;
}

.link-tag-cantonese {
    @apply bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 border-blue-100 dark:border-blue-800/30;
}

.link-tag-jyutjyucicung {
    @apply bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-300 border-yellow-100 dark:border-yellow-800/30;
}

.link-tag-rime {
    @apply text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/20 border-purple-100 dark:border-purple-800/30;
}
</style>
