<script setup>
import { useRouter } from 'vue-router'
import { ref } from 'vue'

const router = useRouter()
const inputChara = ref('')

const goToDetail = () => {
    const char = inputChara.value.trim();
    if (char) {
        router.push({ path: '/detail', query: { chara: char } })
    }
}
</script>

<template>
  <main class="flex-1 bg-[#F4F4EE] dark:bg-slate-900 flex flex-col items-center justify-center p-6">
    <!-- Hero Section -->
    <div class="text-center max-w-4xl mx-auto space-y-8 animate-fade-in-up">
      <div class="space-y-4">
        <h1 class="text-6xl md:text-7xl font-extrabold tracking-tight text-slate-900 dark:text-white">
          <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-indigo-600">
            Jyutdict
          </span>
          <span class="text-slate-800 dark:text-slate-200">Vue</span>
        </h1>
      </div>

      <!-- Search Section -->
      <div class="space-y-4">
          <div class="flex flex-col sm:flex-row gap-4 justify-center max-w-2xl mx-auto">
              <input 
                  v-model="inputChara"
                  @keypress.enter="goToDetail"
                  type="text" 
                  placeholder="輸入查詢..." 
                  class="flex-1 p-4 rounded-lg border border-gray-200 dark:border-slate-700 bg-white/90 dark:bg-slate-800/90 backdrop-blur shadow-sm focus:ring-2 focus:ring-accent/50 focus:border-accent outline-none text-lg transition-all dark:text-white"
              >
              <button 
                  @click="goToDetail"
                  class="bg-accent hover:bg-red-700 text-white px-8 py-4 rounded-lg font-bold shadow-lg hover:shadow-xl hover:-translate-y-0.5 active:translate-y-0 transition-all duration-200"
              >
                  通表
              </button>
              <button 
                  @click="router.push({ path: '/sheet', query: inputChara ? { q: inputChara } : {} })"
                  class="bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-gray-200 dark:border-slate-700 px-8 py-4 rounded-lg font-bold shadow hover:shadow-md hover:-translate-y-0.5 active:translate-y-0 transition-all duration-200 whitespace-nowrap hidden sm:block"
              >
                  粵表
              </button>
          </div>
          <!-- Mobile only secondary button -->
          <button 
              @click="router.push({ path: '/sheet', query: inputChara ? { q: inputChara } : {} })"
              class="sm:hidden w-full bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-gray-200 dark:border-slate-700 px-8 py-4 rounded-lg font-bold shadow hover:shadow-md hover:-translate-y-0.5 active:translate-y-0 transition-all duration-200"
          >
              前往泛粵表
          </button>
      </div>
    </div>
  </main>
</template>

<style scoped>
.animate-fade-in-up {
  animation: fadeInUp 0.8s ease-out;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
