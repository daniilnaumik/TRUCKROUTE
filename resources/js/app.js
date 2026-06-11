import './bootstrap';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import router from './router';
import App from './App.vue';

const pinia = createPinia();
const app = createApp(App);

app.use(pinia);
app.use(router);

window.addEventListener('app:forbidden', (event) => {
    if (router.currentRoute.value.name === 'forbidden') return;
    router.push({
        name: 'forbidden',
        query: {
            from: event.detail?.from || router.currentRoute.value.fullPath,
            message: event.detail?.message || undefined,
        },
    });
});

app.mount('#app');
