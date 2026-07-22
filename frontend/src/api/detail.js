import axios from 'axios';

const apiClient = axios.create({
    baseURL: '/api/v1.0',
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

    // Get character details; areas is an optional list of i_area_list ids for location mode.
    getCharacterDetail(chara, areas = []) {
        return apiClient.get('/detail', {
            params: {
                chara,
                ...(areas.length ? { areas: areas.join(',') } : {})
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
