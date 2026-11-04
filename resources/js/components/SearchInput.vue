<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import AppIcon from './AppIcon.vue';

const model = defineModel<string>({ required: true });
const { t } = useI18n();
</script>

<template>
    <div class="relative w-full sm:w-72">
        <AppIcon name="search" :size="18" class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-slate-500" />
        <input v-model="model" type="search" class="input ps-10" :placeholder="t('common.searchPlaceholder')" :aria-label="t('common.search')" data-testid="search" />
    </div>
</template>
