import axios from 'axios';

const apiClient = axios.create({
    baseURL: '/api/v0.9',
    headers: {
        'Content-Type': 'application/json',
    },
});

export default {
    getHeaderInfo() {
        return apiClient.get('/sheet', {
            params: {
                query: '',
                header: 1
            }
        });
    },

    search(params) {
        // legacy params: query, fuzzy, regex, trim, col, b (meaning), limit
        const p = new URLSearchParams();

        if (params.query === '' && !params.isDef) {
            p.append('query', '!');
            p.append('limit', '10');
        } else {
            p.append('query', params.query);

            // logic from legacy: 
            // if alphanumeric and !def -> trim + col check
            // but here we just pass what user selected usually, mimicking performSearch()
            const isAlphaNum = /[a-zA-Z0-9]/.test(params.query);

            if (isAlphaNum && !params.isDef) {
                if (params.isTrim) p.append('trim', '');
                if (params.location) p.append('col', params.location);
            } else {
                if (params.isFuzzy) p.append('fuzzy', '');
            }

            if (params.isDef) p.append('b', '');
            if (params.isRegex) p.append('regex', '');
        }

        return apiClient.get('/sheet', { params: p });
    }
};
