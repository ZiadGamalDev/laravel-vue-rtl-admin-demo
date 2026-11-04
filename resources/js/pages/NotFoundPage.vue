<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import AppIcon from '@/components/AppIcon.vue';

const { t } = useI18n();
</script>

<template>
    <div class="card flex flex-col items-center gap-3 px-6 py-16 text-center" data-testid="not-found">
        <span class="grid size-12 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800"><AppIcon name="alert" /></span>
        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ t('notFound.title') }}</h2>
        <RouterLink to="/" class="btn-primary">{{ t('notFound.back') }}</RouterLink>
    </div>
</template>
