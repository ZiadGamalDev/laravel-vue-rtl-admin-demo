<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { useFormat } from '@/composables/useFormat';
import AppIcon from './AppIcon.vue';

defineProps<{ meta: { current_page: number; last_page: number; total: number; from: number | null; to: number | null } }>();
const emit = defineEmits<{ page: [number] }>();
const { t } = useI18n();
const fmt = useFormat();
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 text-sm dark:border-slate-800" data-testid="pagination">
        <p class="text-slate-500">
            {{ t('common.showing', { from: fmt.number(meta.from ?? 0), to: fmt.number(meta.to ?? 0), total: fmt.number(meta.total) }) }}
        </p>
        <div class="flex items-center gap-2">
            <button
                type="button"
                class="btn-secondary px-2.5 py-1.5"
                :disabled="meta.current_page <= 1"
                :aria-label="t('common.previous')"
                data-testid="page-prev"
                @click="emit('page', meta.current_page - 1)"
            >
                <AppIcon name="chevron" :size="16" class="rotate-180" />
                <span class="hidden sm:inline">{{ t('common.previous') }}</span>
            </button>
            <span class="px-1 text-slate-600 dark:text-slate-300">
                {{ t('common.page', { page: fmt.number(meta.current_page), pages: fmt.number(meta.last_page) }) }}
            </span>
            <button
                type="button"
                class="btn-secondary px-2.5 py-1.5"
                :disabled="meta.current_page >= meta.last_page"
                :aria-label="t('common.next')"
                data-testid="page-next"
                @click="emit('page', meta.current_page + 1)"
            >
                <span class="hidden sm:inline">{{ t('common.next') }}</span>
                <AppIcon name="chevron" :size="16" />
            </button>
        </div>
    </div>
</template>
