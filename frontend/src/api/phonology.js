import axios from 'axios'

const client = axios.create({
    baseURL: '/api/v1.0/phonology',
    timeout: 60000,
})

export default {
    getLocations() {
        return client.get('', { params: { catalog: 2 } })
    },
    getReport(areaId) {
        return client.get('', {
            params: { area_id: areaId },
            responseType: 'json',
        })
    },
}
