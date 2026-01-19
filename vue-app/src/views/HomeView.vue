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
  <main class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 flex flex-col items-center justify-center p-6">
    <!-- Hero Section -->
    <div class="text-center max-w-4xl mx-auto space-y-8 animate-fade-in-up">
      <div class="space-y-4">
        <h1 class="text-6xl md:text-7xl font-extrabold tracking-tight text-slate-900">
          <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-indigo-600">
            Jyutdict
          </span>
          <span class="text-slate-800">Vue</span>
        </h1>
        <p class="text-xl md:text-2xl text-slate-600 font-light max-w-2xl mx-auto leading-relaxed">
          The modern, high-performance dictionary for Cantonese linguistics.
          <br>
          <span class="text-blue-500 font-medium">Fast. Responsive. Beautiful.</span>
        </p>
      </div>

      <!-- Search Section -->
      <div class="space-y-4">
          <div class="flex flex-col sm:flex-row gap-4 justify-center max-w-2xl mx-auto">
              <input 
                  v-model="inputChara"
                  @keypress.enter="goToDetail"
                  type="text" 
                  placeholder="輸入單字查詢 (Enter a character)..." 
                  class="flex-1 p-4 rounded-xl border border-gray-200 dark:border-slate-700 bg-white/90 dark:bg-slate-800/90 backdrop-blur shadow-sm focus:ring-2 focus:ring-accent/50 focus:border-accent outline-none text-lg transition-all dark:text-white"
              >
              <button 
                  @click="goToDetail"
                  class="bg-accent hover:bg-red-700 text-white px-8 py-4 rounded-xl font-bold shadow-lg hover:shadow-xl hover:-translate-y-0.5 active:translate-y-0 transition-all duration-200"
              >
                  查字
              </button>
              <button 
                  @click="router.push({ path: '/sheet', query: inputChara ? { q: inputChara } : {} })"
                  class="bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-gray-200 dark:border-slate-700 px-8 py-4 rounded-xl font-bold shadow hover:shadow-md hover:-translate-y-0.5 active:translate-y-0 transition-all duration-200 whitespace-nowrap hidden sm:block"
              >
                  泛粵表
              </button>
          </div>
          <!-- Mobile only secondary button -->
          <button 
              @click="router.push({ path: '/sheet', query: inputChara ? { q: inputChara } : {} })"
              class="sm:hidden w-full bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-gray-200 dark:border-slate-700 px-8 py-4 rounded-xl font-bold shadow hover:shadow-md hover:-translate-y-0.5 active:translate-y-0 transition-all duration-200"
          >
              前往泛粵表
          </button>
      </div>
    </div>

    <!-- Features Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-20 max-w-6xl w-full">
      <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition-shadow">
        <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mb-6 text-blue-600">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
          </svg>
        </div>
        <h3 class="text-xl font-bold text-slate-800 mb-2">詳細音韻</h3>
        <p class="text-slate-500 leading-relaxed">
          收錄粵語發音的詳盡資料，包含詳細的地理變體。
        </p>
      </div>

      <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition-shadow">
        <div class="w-12 h-12 bg-indigo-100 rounded-xl flex items-center justify-center mb-6 text-indigo-600">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <h3 class="text-xl font-bold text-slate-800 mb-2">多地口音</h3>
        <p class="text-slate-500 leading-relaxed">
          探索包括廣州、香港、澳門在內的各地口音。
        </p>
      </div>

      <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition-shadow">
        <div class="w-12 h-12 bg-sky-100 rounded-xl flex items-center justify-center mb-6 text-sky-600">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
          </svg>
        </div>
        <h3 class="text-xl font-bold text-slate-800 mb-2">極速響應</h3>
        <p class="text-slate-500 leading-relaxed">
          由 Vue 3 和 Vite 驅動，帶來即時、流暢的查詢體驗。
        </p>
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
