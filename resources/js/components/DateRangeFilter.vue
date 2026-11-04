<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useFormat } from '@/composables/useFormat';
import { todayIso } from '@/lib/dates';
import AppIcon from './AppIcon.vue';

/**
 * Period picker: presets first, native date inputs only for a custom range
 * (empty native date inputs would show an English "mm/dd/yyyy" mask in the Arabic UI).
 * An empty from/to pair means "the default period" (handled by the caller).
 */
const props = defineProps<{ defaultPreset: 'last30' | 'all' }>();
const from = defineModel<string>('from', { required: true });
const to = defineModel<string>('to', { required: true });
const { t } = useI18n();
const fmt = useFormat();

type Preset = 'default' | '7' | '30' | '90' | 'custom';
const preset = ref<Preset>(from.value || to.value ? 'custom' : 'default');

const options = computed(() => {
    const list: { value: Preset; label: string }[] = [
        { value: 'default', label: props.defaultPreset === 'last30' ? t('common.rangeDefault30', { n: fmt.number(30) }) : t('common.rangeAll') },
        { value: '7', label: t('common.rangeLast7', { n: fmt.number(7) }) },
    ];
    if (props.defaultPreset !== 'last30') list.push({ value: '30', label: t('common.rangeDefault30', { n: fmt.number(30) }) });
    list.push({ value: '90', label: t('common.rangeLast90', { n: fmt.number(90) }) }, { value: 'custom', label: t('common.rangeCustom') });
    return list;
});

function daysAgo(days: number): string {
    const date = new Date();
    date.setDate(date.getDate() - days);
    return todayIso(date);
}

watch(preset, (value) => {
    if (value === 'default') {
        from.value = '';
        to.value = '';
    } else if (value !== 'custom') {
        from.value = daysAgo(Number(value) - 1);
        to.value = todayIso();
    }
});
</script>

<template>
    <div class="flex flex-wrap items-center gap-2" data-testid="date-range">
        <AppIcon name="calendar" :size="18" class="text-slate-500" />
        <select v-model="preset" class="input w-auto" :aria-label="t('common.range')" data-testid="range-preset">
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <template v-if="preset === 'custom'">
            <input v-model="from" type="date" class="input w-40" :max="to || undefined" :aria-label="t('common.from')" data-testid="range-from" />
            <span class="text-slate-500">–</span>
            <input v-model="to" type="date" class="input w-40" :min="from || undefined" :aria-label="t('common.to')" data-testid="range-to" />
            <button
                type="button"
                class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800"
                :aria-label="t('common.clear')"
                data-testid="range-clear"
                @click="preset = 'default'"
            >
                <AppIcon name="close" :size="16" />
            </button>
        </template>
    </div>
</template>
