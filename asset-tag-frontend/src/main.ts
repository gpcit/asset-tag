import { createApp } from 'vue'
import { createPinia } from 'pinia'
import axios, { Axios } from 'axios'
import App from './App.vue'
import router from './router'
import './main.css'

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(axios)

app.mount('#app')
