import axios from 'axios';

const apiClient = axios.create({
    baseURL: '/api/v0.9',
    headers: {
        'Content-Type': 'application/json',
    },
});

export default {
    // Get character details
    // params: chara (string), ascii (bool, optional)
    getCharacterDetail(chara) {
        return apiClient.get('/detail', {
            params: {
                chara,
                // We usually want standard keys (chinese), but let's check what the frontend prefers.
                // Legacy uses chinese keys by default.
                // To make it easier for Vue, maybe ASCII keys are better? 
                // API line 56/58: if ascii=1, keys are "name", "initial"... 
                // if ascii=0, keys are "書名", "聲母"...
                // ASCII keys are definitely safer for JS code.
                ascii: 1
            }
        });
    },

    // Get pronunciation details (reverse lookup?)
    getPronunciationDetail(pron) {
        return apiClient.get('/detail', {
            params: {
                pron,
                ascii: 1
            }
        });
    }
};
