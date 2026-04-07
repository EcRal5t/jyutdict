<script setup>
import { ref, computed, watchEffect, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import aboutPagesData from '../data/about_pages.json';
import { marked } from 'marked';

const route = useRoute();
const currentIdentifier = computed(() => route.params.id || aboutPagesData[0].id);

const currentPage = computed(() => {
    return aboutPagesData.find(p => p.id === currentIdentifier.value) || aboutPagesData[0];
});

const markdownContent = ref('');
const loading = ref(false);
const error = ref(null);

watchEffect(async () => {
    if (!currentPage.value) return;

    loading.value = true;
    error.value = null;

    try {
        // Dynamic import of markdown file as raw string
        // Note: Vite requires known paths or glob for dynamic imports.
        // Since we have a specific directory, we can use a switch or mapping if the files are few,
        // or use `import.meta.glob` if we want to be generic.
        // Given the user wants to add files manually to json, we should probably map them.
        // But for simplicity/extensibility with Vite, import.meta.glob is best.

        const modules = import.meta.glob('../data/markdown/*.md', { query: '?raw', import: 'default' });
        const filePath = `../data/markdown/${currentPage.value.file}`;

        if (modules[filePath]) {
            const rawContent = await modules[filePath]();
            // Fix image paths: ./img/ or img/ -> /img/
            // The php version had ./img/, Vue public folder is at root so /img/ is correct.
            const contentWithFixedPaths = rawContent.replace(/(src=["']|\]\()(?:\.\/)?img\//g, '$1/img/');
            markdownContent.value = marked(contentWithFixedPaths);
        } else {
            error.value = 'Article not found';
        }
    } catch (e) {
        console.error(e);
        error.value = 'Failed to load content';
    } finally {
        loading.value = false;
    }

    // 处理页内锚点滚动（hash history 模式下 URL 格式为 /#/about#fjb）
    await nextTick();
    const hash = window.location.hash;
    const anchorMatch = hash.match(/#([^/]+)$/);
    if (anchorMatch) {
        const anchorId = anchorMatch[1];
        const element = document.getElementById(anchorId);
        if (element) {
            element.scrollIntoView({ behavior: 'smooth' });
        }
    }
});
</script>

<template>
    <div class="flex flex-col md:flex-row min-h-screen pt-16 container mx-auto px-4 gap-8">
        <!-- Sidebar -->
        <aside class="w-full md:w-64 flex-shrink-0">
            <nav class="space-y-1 sticky top-20">
                <h2 class="text-xl font-bold mb-4 text-gray-800 dark:text-gray-200 border-b-2 border-slate-200 dark:border-slate-700 pb-2">説明</h2>
                <template v-for="page in aboutPagesData" :key="page.id">
                    <router-link :to="{ name: 'about', params: { id: page.id } }"
                        class="block px-4 py-2.5 rounded-none transition-all border-l-4 hover:translate-x-1 hover:shadow-sm"
                        :class="currentIdentifier === page.id ? 'border-l-accent bg-accent/5 text-accent' : 'border-l-transparent text-gray-600 dark:text-gray-400 dark:hover:text-gray-100 hover:bg-slate-50 dark:hover:bg-slate-800 hover:border-l-slate-300 dark:hover:border-l-slate-600'">
                        {{ page.title }}
                    </router-link>
                </template>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 pb-12 article-content">
            <div v-if="loading" class="text-center py-8">
                <div class="animate-spin inline-block w-8 h-8 border-4 border-current border-t-transparent text-gray-400 rounded-full"
                    role="status" aria-label="loading">
                    <span class="sr-only">Loading...</span>
                </div>
            </div>

            <div v-else-if="error" class="bg-red-50 dark:bg-red-900/10 border-l-4 border-red-500 text-red-700 dark:text-red-400 px-4 py-3 rounded-none"
                role="alert">
                <span class="block sm:inline">{{ error }}</span>
            </div>

            <div v-else-if="currentPage" class="bg-white dark:bg-gray-800 rounded-none shadow-[6px_6px_0_rgba(0,0,0,0.06)] dark:shadow-[6px_6px_0_rgba(0,0,0,0.3)] p-6 md:p-8 border border-slate-100 dark:border-slate-700">
                <!-- Content -->
                <div v-html="markdownContent"
                    class="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 init-content">
                </div>
            </div>
        </main>
    </div>
</template>

<style>
/* Reusing ArticleView styles */
.article-content .init-content p {
    text-indent: 2em;
    margin-bottom: 1em;
    line-height: 2em;
}

.article-content .init-content .cite>p {
    line-height: 1em;
}

.article-content .init-content b,
.article-content .init-content strong {
    color: #d32913;
    font-weight: bold;
}

.article-content .init-content i,
.article-content .init-content em {
    color: darkslategrey;
    font-style: italic;
}

.article-content .init-content a {
    color: #2563eb;
    text-decoration: underline;
}

.article-content .init-content h1,
.article-content .init-content h2,
.article-content .init-content h3 {
    font-family: serif;
    font-weight: bold;
    margin-top: 1.5em;
    margin-bottom: 0.5em;
    color: inherit;
}

.article-content .init-content h1 {
    font-size: 1.875rem;
    line-height: 2.25rem;
}

.article-content .init-content h2 {
    font-size: 1.5rem;
    line-height: 2rem;
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 0.5rem;
}

.article-content .init-content h3 {
    font-size: 1.25rem;
    line-height: 1.75rem;
}

.article-content .init-content blockquote {
    border-left: 4px solid #e5e7eb;
    padding-left: 1rem;
    color: #4b5563;
    font-style: italic;
    margin-top: 1rem;
    margin-bottom: 1rem;
}

.article-content .init-content img {
    max-width: 100%;
    height: auto;
    margin: 1rem auto;
    border-radius: 0.375rem;
}

/* Dark mode adjustments applied via parents .dark class normally, 
   but here we rely on base prose styles + specific overrides */
.dark .article-content .init-content i,
.dark .article-content .init-content em {
    color: #94a3b8;
}

.dark .article-content .init-content blockquote {
    border-left-color: #374151;
    color: #9ca3af;
}

.cite {
    border-left: 5px solid rgb(211, 41, 19);
    padding: 0 0 5px 20px;
    color: rgb(96, 96, 96);
}

.dark .cite {
    color: rgb(160, 160, 160);
}

ul {
    margin-left: 2em;
    margin-bottom: 1em;
}
ol {
    margin-left: 2em;
    margin-bottom: 1em;
}

code {
    background: oklch(0.67 0.07 31.65 / 0.18);
    padding: 0 0.2em;
    border-radius: 0.3em;
    color: oklch(0.60 0.16 35);
    font-family: monospace;
}
</style>
