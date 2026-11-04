<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import AppIcon from './AppIcon.vue';

/**
 * Loading, error and empty states shared by every data view.
 * Renders the default slot only when data is available.
 */
defineProps<{
    loading: boolean;
    error?: unknown;
    empty?: boolean;
    emptyText?: string;
}>();
defineEmits<{ retry: [] }>();

const { t } = useI18n();
</script>

<template>
    <div v-if="loading" class="flex flex-col items-center justify-center gap-3 py-16 text-slate-500" role="status" data-testid="state-loading">
        <span class="size-8 animate-spin rounded-full border-4 border-slate-200 border-t-brand-600" />
        <span class="text-sm">{{ t('common.loading') }}</span>
    </div>
    <div v-else-if="error" class="flex flex-col items-center justify-center gap-2 py-16 text-center" role="alert" data-testid="state-error">
        <span class="grid size-12 place-items-center rounded-full bg-rose-50 text-rose-600 dark:bg-rose-500/10"><AppIcon name="alert" /></span>
        <p class="font-bold text-slate-800 dark:text-slate-100">{{ t('common.errorTitle') }}</p>
        <p class="text-sm text-slate-500">{{ t('common.errorBody') }}</p>
        <button type="button" class="btn-secondary mt-2" @click="$emit('retry')">
            <AppIcon name="refresh" :size="16" />
            {{ t('common.retry') }}
        </button>
    </div>
    <div v-else-if="empty" class="flex flex-col items-center justify-center gap-2 py-16 text-center" data-testid="state-empty">
        <span class="grid size-12 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800"><AppIcon name="inbox" /></span>
        <p class="font-bold text-slate-800 dark:text-slate-100">{{ t('common.emptyTitle') }}</p>
        <p class="text-sm text-slate-500">{{ emptyText ?? t('common.emptyBody') }}</p>
        <slot name="empty-action" />
    </div>
    <slot v-else />
</template>
