import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import seoCatalog from 'virtual:jyutdict-seo'
import { resolveSeo, updateSeo } from './utils/seo'

router.afterEach((to, from, failure) => {
    if (!failure) updateSeo(resolveSeo(to, seoCatalog))
})

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

app.mount('#app')
