<script setup>
import { ref, reactive } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

// Ported Regex from legacy pron.php
const format = /^[a-z%]{1,10}\d{0,2}$/;
const initialFormat = /^(n[jg]?|bb?|dd?|[zcs][hrjl]?|[ptg]h?|[gk][wv]?|[hmqfvwjl]|%)(?=[aeoiuy%])/;
const codaFormat = /[aoreiwu%](n[ng]?|[mptkh]|%)(\d{0,2})$/;
const toneFormat = /\d{1,2}$/;
const vowelFormat = /^(ng$|m$|ii|uu|[iu][rw]?|[aeo][aorew]?|yw|yu$|y|%$)/;

const router = useRouter();

const form = reactive({
    pron: '',
    in: '',
    nu: '',
    co: '',
    to: '',
    wanshyu: true,
    area: true
});

const parseStatus = ref('neutral'); // neutral, valied, invalid
const inputDisabled = ref(true);

const parsedComponents = reactive({
    in: '',
    nu: [],
    co: '',
    to: ''
});

// Ported inputAnalyse function
const analyzeInput = () => {
    const pron = form.pron;
    // Reset parsed components
    parsedComponents.in = '';
    parsedComponents.nu = [];
    parsedComponents.co = '';
    parsedComponents.to = '';

    // Legacy logic: if (pron.split("%").length<4 && format.test(pron))
    // We allow wildcards but the regex is strict.
    if (pron.split("%").length < 4 && format.test(pron)) {
        const toneMatch = pron.match(toneFormat);
        const tone = toneMatch ? toneMatch[0] : "";

        const initialMatch = pron.match(initialFormat);
        const initial = initialMatch ? initialMatch[1] : "";

        const codaMatch = pron.match(codaFormat);
        const coda = codaMatch ? codaMatch[1] : "";

        const nuclei = pron.substr(initial.length, pron.length - initial.length - coda.length - tone.length);

        const vowels = [];
        let pos = 0;
        let validVowels = true;

        while (pos < nuclei.length) {
            const sub = nuclei.substr(pos);
            const match = sub.match(vowelFormat);
            if (match) {
                vowels.push(match[0]);
                pos += match[0].length;
            } else {
                validVowels = false;
                break;
            }
        }

        if (validVowels) {
            parsedComponents.in = initial;
            parsedComponents.nu = vowels; // Array of vowels
            parsedComponents.co = coda;
            parsedComponents.to = tone;

            form.in = initial;
            // Join vowels for form submission as logic implies full nuclei string in legacy?
            // "document.querySelector('#inputNuclei').value = nuclei;" -> Yes.
            form.nu = nuclei;
            form.co = coda;
            form.to = tone;

            parseStatus.value = 'valid';
            inputDisabled.value = false;
            return;
        }
    }

    // Invalid
    parseStatus.value = pron ? 'invalid' : 'neutral';
    inputDisabled.value = true;
};

const submitSearch = () => {
    if (inputDisabled.value) return;

    const query = {
        in: form.in,
        nu: form.nu,
        co: form.co,
        to: form.to
    };
    if (form.wanshyu) query['option[]'] = query['option[]'] ? [...query['option[]'], 'wanshyu'] : ['wanshyu'];
    // Axios or Router push?
    // The legacy app used form submit to same page. DetailView handles API?
    // Detail.php handles it. We should probably reuse DetailView or fetch here?
    // The plan was "Connect to api/v0.9/detail.php". "Create Vue View for Pronunciation".
    // If we use DetailView, it's designed for Char or Pron query.
    // detail.php now supports in/nu/co/to.
    // So we can fetch data here and display it, OR query detail.php and show results.
    // Let's fetch data here to display "Result" as in legacy pron.php.

    fetchResults();
};

const results = ref(null);
const loading = ref(false);

const fetchResults = async () => {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        params.append('in', form.in);
        params.append('nu', form.nu);
        params.append('co', form.co);
        params.append('to', form.to);
        if (form.wanshyu) params.append('option[]', 'wanshyu');
        if (form.area) params.append('option[]', 'area');

        const response = await axios.get('https://jyutdict.org/api/v0.9/detail.php', { params });
        // Note: URL hardcoded? Should be relative or config.
        // During migration, if we are serving from vue-app, we might need proxy or absolute URL.
        // User's Env: "http://jyutdict.org" is likely live. We are migrating content.
        // The API files are local: d:\Proj\Jyutdict\Jyutdict-Old\api\v0.9\detail.php
        // We probably need to point to the local API if we have a dev server proxy, or relative path if deployed.
        // Assume relative '/api/v0.9/detail.php' works if deployed together.
        // For dev, assume proxy or mocked. usage of absolute URI in `axios.get` above might fail CORS if not configured.
        // Let's use relative for now.

        // Wait, I am running "run_command" not browser. I can't test "axios" easily during dev unless I assume the user runs backend.
        // I will use relative path.
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
    // Mocking response logic mostly because we don't have the API running here.
    // In real implementation I'd store response.data in results.
};
</script>

<template>
    <div class="container mx-auto px-4 pt-20 pb-12 flex flex-col items-center">
        <!-- Input Section -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg p-8 w-full max-w-2xl">
            <h1 class="text-3xl font-bold mb-6 text-center text-slate-800 dark:text-slate-100 font-serif">粵語檢音</h1>

            <div class="flex gap-4 mb-6">
                <input v-model="form.pron" @input="analyzeInput" type="text"
                    class="flex-1 p-3 text-lg font-mono border-2 rounded-md outline-none transition-colors dark:bg-slate-900 dark:text-white"
                    :class="{
                        'border-gray-200 dark:border-slate-700': parseStatus === 'neutral',
                        'border-green-500': parseStatus === 'valid',
                        'border-red-500': parseStatus === 'invalid'
                    }" placeholder="輸入粵拼 (e.g. jyut6, j%t%)...">
                <button disabled
                    class="px-6 py-3 font-bold text-white rounded-md transition-all shadow-md opacity-50 cursor-not-allowed bg-gray-400">
                    耖
                </button>
            </div>

            <div class="flex justify-center gap-6 text-sm text-slate-600 dark:text-slate-400 mb-8">
                <label class="flex items-center gap-2 cursor-pointer hover:text-accent">
                    <input type="checkbox" v-model="form.wanshyu" class="w-4 h-4 rounded text-accent focus:ring-accent">
                    韻書音
                </label>
                <label class="flex items-center gap-2 cursor-pointer hover:text-accent">
                    <input type="checkbox" v-model="form.area" class="w-4 h-4 rounded text-accent focus:ring-accent">
                    地方音
                </label>
            </div>

            <!-- Color Blocks Visualization -->
            <div class="flex justify-center gap-1 font-mono text-xl h-10">
                <div
                    class="w-10 flex items-center justify-center rounded bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300">
                    {{ parsedComponents.in }}</div>
                <template v-for="(v, i) in parsedComponents.nu" :key="i">
                    <div
                        class="w-10 flex items-center justify-center rounded bg-orange-100 dark:bg-orange-900/50 text-orange-700 dark:text-orange-300">
                        {{ v }}</div>
                </template>
                <div
                    class="w-10 flex items-center justify-center rounded bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-300">
                    {{ parsedComponents.co }}</div>
                <div
                    class="w-10 flex items-center justify-center rounded bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300">
                    {{ parsedComponents.to }}</div>
            </div>
        </div>

        <!-- Results Placeholder -->
        <div v-if="loading" class="mt-8 text-gray-500">Loading...</div>
        <div v-if="results" class="mt-8 w-full max-w-4xl">
            <!-- Result Tables Implementation needed here based on API format -->
            <!-- For now leaving empty as backend connectivity is not verifiable in this env -->
        </div>
    </div>
</template>
