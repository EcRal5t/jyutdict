<script setup>
const props = defineProps({
  chara: {
    type: String,
    required: true
  }
});
</script>

<template>
  <div class="mt-8 bg-slate-50 dark:bg-slate-900/50 p-6 rounded-xl border border-dashed border-slate-300 dark:border-slate-700">
      <h4 class="text-sm font-bold text-slate-500 mb-4 uppercase tracking-wider">快速查詢 (External Links): {{ chara }}</h4>
      
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
          <div class="space-y-2">
              <a :href="`http://www.zdic.net/hans/${chara}`" target="_blank" class="block text-accent hover:underline">漢典網</a>
              <a :href="`http://www.ccamc.co/cjkv.php?cjkv=${chara}`" target="_blank" class="block text-slate-600 dark:text-slate-400 hover:text-accent">古今文字集成</a>
              <a :href="`http://humanum.arts.cuhk.edu.hk/Lexis/lexi-mf/search.php?word=${chara}`" target="_blank" class="block text-slate-600 dark:text-slate-400 hover:text-accent">漢語多功能字庫</a>
              <a :href="`https://zi.tools/zi/${chara}`" target="_blank" class="block text-slate-600 dark:text-slate-400 hover:text-accent">字統网</a>
          </div>
          
          <div class="space-y-2">
              <a :href="`https://jyut.net/query?q=${chara}`" target="_blank" class="block text-accent hover:underline">粵音資料集叢</a>
              <a :href="`https://words.hk/zidin/wan/?q=${chara}`" target="_blank" class="block text-slate-600 dark:text-slate-400 hover:text-accent">粵典</a>
              <a :href="`https://kaifangcidian.com/han/yue/?${chara}`" target="_blank" class="block text-slate-600 dark:text-slate-400 hover:text-accent">開放粵語詞典</a>
          </div>
          
          <div class="space-y-2">
               <a :href="`http://www.zdic.net/zd/yy/yy/${chara}`" target="_blank" class="block text-slate-600 dark:text-slate-400 hover:text-accent">漢典-粵</a>
               <a :href="`https://ytenx.org/zim?dzih=${chara}&dzyen=1&jtkb=1&jtkd=1&jtdt=1&jtgt=1`" target="_blank" class="block text-accent hover:underline">韻典網</a>
               <a :href="`https://hamzau.com/?q=${chara}`" target="_blank" class="block text-slate-600 dark:text-slate-400 hover:text-accent">欽州白話</a>
          </div>
      </div>
  </div>
</template>
