<script setup lang="ts">
import { computed, watchEffect } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/components/AppLayout.vue';

const route = useRoute();
const { t } = useI18n();

watchEffect(() => {
    const page = route.meta.title ? t(route.meta.title) : route.name === 'login' ? t('auth.title') : '';
    document.title = page ? `${page} · ${t('app.name')}` : t('app.name');
});
const ready = computed(() => route.matched.length > 0);
const bare = computed(() => route.meta.bare === true);
</script>

<template>
    <template v-if="ready">
        <RouterView v-if="bare" />
        <AppLayout v-else>
            <RouterView />
        </AppLayout>
    </template>
</template>
