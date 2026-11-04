import '@fontsource/cairo/400.css';
import '@fontsource/cairo/600.css';
import '@fontsource/cairo/700.css';
import '../css/app.css';

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { VueQueryPlugin, QueryClient } from '@tanstack/vue-query';
import App from './App.vue';
import { i18n } from './i18n';
import { router } from './router';
import { onUnauthorized } from './lib/http';
import { useAuthStore } from './stores/auth';
import { useUiStore } from './stores/ui';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: { retry: 1, refetchOnWindowFocus: false, staleTime: 30_000 },
    },
});

const app = createApp(App);
app.use(createPinia());
app.use(i18n);
app.use(VueQueryPlugin, { queryClient });

useUiStore();

onUnauthorized(() => {
    useAuthStore().clear();
    queryClient.clear();
    if (router.currentRoute.value.name !== 'login') {
        router.push({ name: 'login' });
    }
});

app.use(router);
app.mount('#app');
