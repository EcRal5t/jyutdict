<script setup>
import { ref, computed } from 'vue';
import { useRoute } from 'vue-router';
import articlesData from '../data/articles.json';

const route = useRoute();
const currentArticleId = computed(() => route.params.id || articlesData[0].id);

const currentArticle = computed(() => {
    return articlesData.find(a => a.id === currentArticleId.value) || articlesData[0];
});
</script>

<template>
    <div class="flex flex-col md:flex-row min-h-screen pt-16 container mx-auto px-4 gap-8">
        <!-- Sidebar -->
        <aside class="w-full md:w-64 flex-shrink-0">
            <nav class="space-y-2 sticky top-20">
                <h2 class="text-xl font-bold mb-4 text-gray-800 dark:text-gray-200 border-b pb-2">紀文</h2>
                <template v-for="article in articlesData" :key="article.id">
                    <router-link :to="{ name: 'article', params: { id: article.id } }"
                        class="block px-4 py-2 rounded-md transition-colors hover:bg-gray-100 dark:hover:bg-gray-700"
                        :class="{ 'bg-blue-100 text-blue-700 dark:bg-gray-700 dark:text-blue-300': currentArticleId === article.id, 'text-gray-600 dark:text-gray-400': currentArticleId !== article.id }">
                        {{ article.title }}
                    </router-link>
                </template>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 pb-12">
            <div v-if="currentArticle" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 md:p-8">
                <h1 class="text-3xl font-serif font-bold text-gray-900 dark:text-gray-100 mb-2">{{ currentArticle.title
                }}</h1>
                <p v-if="currentArticle.intro"
                    class="text-gray-600 dark:text-gray-400 mb-8 italic border-l-4 border-gray-300 pl-4">
                    {{ currentArticle.intro }}
                </p>

                <div v-if="currentArticle.table && currentArticle.table.rows && currentArticle.table.rows.length"
                    class="overflow-x-auto">
                    <table
                        class="w-full border-collapse border border-stone-400 dark:border-stone-600 font-serif text-center article-table">
                        <caption class="font-bold text-lg mb-2 text-stone-700 dark:text-stone-300">{{
                            currentArticle.table.caption }}</caption>
                        <thead>
                            <tr
                                class="bg-stone-100 dark:bg-stone-900 border-b-2 border-stone-400 dark:border-stone-600">
                                <th v-for="(header, index) in currentArticle.table.headers" :key="index"
                                    class="p-2 font-bold text-stone-800 dark:text-stone-200 border-r border-stone-300 dark:border-stone-700 last:border-0"
                                    v-html="header"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, rowIndex) in currentArticle.table.rows" :key="rowIndex"
                                class="hover:bg-amber-50/50 dark:hover:bg-stone-800/50 border-b border-stone-300 dark:border-stone-700 text-stone-900 dark:text-stone-100">
                                <td v-for="(cell, cIndex) in row.cells" :key="cIndex"
                                    :rowspan="cell.rowspan > 1 ? cell.rowspan : undefined"
                                    :colspan="cell.colspan > 1 ? cell.colspan : undefined"
                                    :class="[cell.class, 'p-2 border-r border-stone-300 dark:border-stone-700']"
                                    v-html="cell.content">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="text-center py-10 text-gray-500">
                    Data migration for this article is pending.
                </div>

                <div class="mt-8 text-sm text-gray-500 text-center border-t pt-4">
                    <p>Data sourced from legacy Jyutdict articles.</p>
                </div>
            </div>
        </main>
    </div>
</template>

<style>
/* Global styles for article table content injected via v-html */
.article-table .A {
    background-color: rgb(219 234 254);
}

.dark .article-table .A {
    background-color: rgba(30, 58, 138, 0.5);
}

.article-table .B {
    background-color: rgb(220 252 231);
}

.dark .article-table .B {
    background-color: rgba(20, 83, 45, 0.5);
}

.article-table .head {
    font-weight: bold;
    font-size: 1.125rem;
    color: #d32913;
}

.dark .article-table .head {
    color: #f87171;
}

.article-table .sub {
    font-size: 0.75rem;
    color: #6b7280;
    margin-left: 0.25rem;
}

.article-table .mean {
    text-align: left;
    font-size: 0.875rem;
    line-height: 1.625;
    padding: 0.5rem;
}
</style>
