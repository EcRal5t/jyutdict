import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import seoBuild from './scripts/seo-build.js'

// https://vitejs.dev/config/
export default defineConfig({
    plugins: [
        vue(),
        seoBuild(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./src', import.meta.url))
        }
    },
    server: {
        proxy: {
            '/api': {
                target: 'http://localhost:8888',
                changeOrigin: true
            },
            '/img': {
                target: 'http://localhost:8888',
                changeOrigin: true
            }
        }
    }
})
