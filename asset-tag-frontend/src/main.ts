import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import './main.css'
import api from './services/api'

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.config.globalProperties.$api = api
app.mount('#app')
