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
                        class="block px-4 py-2 rounded-md transition-colors hover:bg-[#E8E8DD]"
                        :class="{ 'bg-[#E8E8DD] text-[#d32913]': currentArticleId === article.id, 'text-gray-600 dark:text-gray-400 dark:hover:text-gray-900': currentArticleId !== article.id }">
                        {{ article.title }}
                    </router-link>
                </template>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 pb-12 article-content">
            <div v-if="currentArticle" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 md:p-8">
                <h1 class="text-3xl font-serif font-bold text-gray-900 dark:text-gray-100 mb-6">{{ currentArticle.title
                    }}</h1>

                <template v-for="(block, index) in currentArticle.blocks" :key="index">

                    <!-- HTML Block -->
                    <div v-if="block.type === 'html'" v-html="block.content"
                        class="mb-6 prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 init-content">
                    </div>

                    <!-- Phonology Table Block -->
                    <div v-else-if="block.type === 'phonology_table'" class="mb-8 overflow-x-auto">
                        <table
                            class="phonology-table w-full border-collapse border border-stone-400 dark:border-stone-600 font-serif text-center">
                            <caption v-if="block.caption"
                                class="font-bold text-lg mb-2 text-stone-700 dark:text-stone-300 caption-top">
                                {{ block.caption }}
                            </caption>
                            <tbody>
                                <tr v-for="(row, rIndex) in block.rows" :key="rIndex"
                                    class="border-b border-stone-300 dark:border-stone-700 hover:bg-amber-50 dark:hover:bg-stone-700/50 w-auto">
                                    <template v-for="(cell, cIndex) in row.cells" :key="cIndex">
                                        <component :is="cell.tag || 'td'" :rowspan="cell.rowspan"
                                            :colspan="cell.colspan" :style="{ width: cell.width }"
                                            class="p-2 border-r border-stone-300 dark:border-stone-700 last:border-0 align-middle"
                                            :class="[
                                                cell.className || '',
                                                (cell.tag === 'th') ? 'bg-stone-100 dark:bg-stone-900 font-bold text-stone-800 dark:text-stone-200 border-b-2 border-stone-400 dark:border-stone-600 whitespace-nowrap' : 'text-stone-900 dark:text-stone-100'
                                            ]">
                                            <div v-html="cell.content"></div>
                                        </component>
                                    </template>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </template>

            </div>
        </main>
    </div>
</template>

<style>
/* Global styles for article content to match PHP styling */
.article-content .init-content p {
    text-indent: 2em;
    margin-bottom: 1em;
    line-height: 2em;
}

.article-content .init-content b {
    color: #d32913;
    font-weight: bold;
}

.article-content .init-content i {
    color: darkslategrey;
    font-style: italic;
}

.article-content .init-content a {
    color: #2563eb;
    /* blue-600 */
    text-decoration: underline;
}

/* Table Styles */
.phonology-table th {
    font-size: 1.2em;
    line-height: 2em;
}

.phonology-table td.A {
    background-color: rgb(193 227 255);
}

.dark .phonology-table td.A {
    background-color: rgb(30 60 90);
    /* Dark mode equivalent */
}

.phonology-table td.B {
    background-color: rgb(173 251 158);
}

.dark .phonology-table td.B {
    background-color: rgb(30 70 30);
    /* Dark mode equivalent */
}

/* Character Column Styles */
.phonology-table .head {
    font-weight: bold;
    font-size: 1.5em;
    color: #d32913;
    line-height: 1.5em;
    display: inline-block;
}

.dark .phonology-table .head {
    color: #ff6b6b;
    /* Lighter red for dark mode */
}

.phonology-table i>.head {
    color: darkslategrey;
    /* Lighter red for dark mode */
}

.dark .phonology-table i>.head {
    color: slategrey;
    /* Lighter red for dark mode */
}

.phonology-table .sub {
    /* Originally empty or just context for small */
}

.phonology-table .sub small {
    font-size: 0.8em;
    color: #4b5563;
    /* gray-600 */
}

.dark .phonology-table .sub small {
    color: #9ca3af;
    /* gray-400 */
}

.phonology-table i {
    color: darkslategrey;
    font-style: italic;
}

.dark .phonology-table i {
    color: #94a3b8;
    /* slate-400 */
}

/* Meanings */
.phonology-table .mean {
    text-align: left;
    padding: 1ex;
    line-height: 1.2em;
}
</style>
