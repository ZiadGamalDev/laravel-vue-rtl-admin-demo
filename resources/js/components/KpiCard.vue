<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useFormat } from '@/composables/useFormat';
import AppIcon from './AppIcon.vue';

const props = defineProps<{
    label: string;
    value: string;
    icon: string;
    change?: number | null;
    hint?: string;
    tone?: 'brand' | 'sky' | 'violet' | 'rose';
}>();

const { t } = useI18n();
const fmt = useFormat();

const tones = {
    brand: 'bg-brand-50 text-brand-700 dark:bg-brand-700/20 dark:text-brand-100',
    sky: 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
    violet: 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300',
    rose: 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300',
};

const changeText = computed(() =>
    props.change == null ? '' : fmt.number(props.change / 100, { style: 'percent', maximumFractionDigits: 1, signDisplay: 'exceptZero' }),
);
</script>

<template>
    <div class="card p-5" data-testid="kpi-card">
        <div class="flex items-start justify-between gap-3">
            <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ label }}</p>
            <span class="grid size-10 place-items-center rounded-lg" :class="tones[tone ?? 'brand']"><AppIcon :name="icon" /></span>
        </div>
        <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><bdi>{{ value }}</bdi></p>
        <p class="mt-1 flex items-center gap-1.5 text-xs text-slate-500">
            <template v-if="change != null">
                <span
                    class="inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 font-semibold"
                    :class="change >= 0 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10' : 'bg-rose-50 text-rose-700 dark:bg-rose-500/10'"
                >
                    <AppIcon :name="change >= 0 ? 'trendUp' : 'trendDown'" :size="14" />
                    <bdi>{{ changeText }}</bdi>
                </span>
                <span>{{ t('dashboard.vsPrevious') }}</span>
            </template>
            <span v-else-if="hint">{{ hint }}</span>
        </p>
    </div>
</template>
