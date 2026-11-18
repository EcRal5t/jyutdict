import { ref } from 'vue';

// 全站共用的“当前是否夜间主题”响应式状态：跟随 <html class="dark"> 变化（App.vue 负责切换）。
export const isDarkTheme = ref(
    typeof document !== 'undefined' && document.documentElement.classList.contains('dark')
);

if (typeof MutationObserver !== 'undefined' && typeof document !== 'undefined') {
    new MutationObserver(() => {
        isDarkTheme.value = document.documentElement.classList.contains('dark');
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
}
