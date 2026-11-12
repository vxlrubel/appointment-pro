import PxSelect from '../../shared/PxSelect.vue'
import PxInput from '../../shared/PxInput.vue'
import PxCurrency from '../../shared/PxCurrency.vue'
import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import { startAdminCacheSync } from './api'

const app = createApp(App)
app.onUnmount(startAdminCacheSync())

app.component('PxSelect', PxSelect)
app.component('PxInput', PxInput)
app.component('PxCurrency', PxCurrency)
app.use(createPinia())
app.use(router)

app.mount('#appointment-pro')
