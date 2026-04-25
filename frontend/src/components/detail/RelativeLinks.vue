<script setup>
const props = defineProps({
  chara: {
    type: String,
    required: true
  }
});
</script>

<template>
  <div class="bg-slate-50 dark:bg-slate-900/50 p-4 rounded-lg border border-slate-200 dark:border-slate-800">
      <h4 class="text-xs font-bold text-slate-500 mb-3 uppercase tracking-wider flex items-center gap-2">
          <span>快速查詢</span>
          <span class="bg-gray-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 px-1.5 py-0.5 rounded text-[10px]">{{ chara }}</span>
      </h4>
      
      <!-- Flex container for ALL links, wrapping naturally -->
      <div class="flex flex-wrap gap-1.5">
          <!-- External Sites -->
          <a :href="`http://www.zdic.net/hans/${chara}`" target="_blank" class="link-tag">漢典</a>
          <a :href="`http://www.ccamc.co/cjkv.php?cjkv=${chara}`" target="_blank" class="link-tag">古今文字集成</a>
          <a :href="`http://humanum.arts.cuhk.edu.hk/Lexis/lexi-mf/search.php?word=${chara}`" target="_blank" class="link-tag">漢語多功能字庫</a>
          <a :href="`https://zi.tools/zi/${chara}`" target="_blank" class="link-tag">字統</a>

          <!-- Cantonese Specific -->
          <a :href="`https://jyut.net/query?q=${chara}`" target="_blank" class="link-tag link-tag-cantonese">粵音資料集叢</a>
          <a :href="`https://words.hk/zidin/wan/?q=${chara}`" target="_blank" class="link-tag link-tag-cantonese">粵典</a>
          <a :href="`https://kaifangcidian.com/han/yue/?${chara}`" target="_blank" class="link-tag link-tag-cantonese">開放粵語詞典</a>
          <a :href="`https://jyutjyu.com/word/${chara}`" target="_blank" class="link-tag link-tag-jyutjyucicung">粵語辭叢</a>
          
          <!-- Others -->
          <a :href="`http://www.zdic.net/zd/yy/yy/${chara}`" target="_blank" class="link-tag">漢典-粵</a>
          <a :href="`http://www.zdic.net/zd/yy/ph/${chara}`" target="_blank" class="link-tag">漢典-平</a>
          <a :href="`https://ytenx.org/zim?dzih=${chara}&dzyen=1&jtkb=1&jtkd=1&jtdt=1&jtgt=1`" target="_blank" class="link-tag text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/20 border-purple-100 dark:border-purple-800/30">韻典</a>
          <a :href="`https://hamzau.com/?q=${chara}`" target="_blank" class="link-tag">欽州白話</a>
      </div>
  </div>
</template>

<style scoped>
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
</style>
