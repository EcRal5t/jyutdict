import axios from 'axios';

const apiClient = axios.create({
    baseURL: '/api/1.0',
    headers: {
        'Content-Type': 'application/json',
    },
});

export default {
    // Get location list (header)
    getLocationList() {
        return apiClient.get('/detail', {
            params: {
                chara: ''
            }
        });
    },

    // Get character details
    // params: chara (string)
    getCharacterDetail(chara) {
        return apiClient.get('/detail', {
            params: {
                chara
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
