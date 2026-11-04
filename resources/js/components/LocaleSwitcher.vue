<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { useUiStore } from '@/stores/ui';
import AppIcon from './AppIcon.vue';

withDefaults(defineProps<{ showDigits?: boolean }>(), { showDigits: true });

const ui = useUiStore();
const { t } = useI18n();
</script>

<template>
    <div class="flex items-center gap-2">
        <div
            v-if="showDigits && ui.locale === 'ar'"
            class="flex items-center rounded-lg border border-slate-200 bg-white p-0.5 text-xs dark:border-slate-700 dark:bg-slate-800"
            role="group"
            :aria-label="t('nav.digits')"
        >
            <button
                v-for="option in (['arab', 'latn'] as const)"
                :key="option"
                type="button"
                class="rounded-md px-2 py-1 font-semibold transition"
                :class="ui.digits === option ? 'bg-brand-600 text-white' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400'"
                :aria-pressed="ui.digits === option"
                :data-testid="`digits-${option}`"
                @click="ui.setDigits(option)"
            >
                {{ option === 'arab' ? t('nav.digitsArab') : t('nav.digitsLatn') }}
            </button>
        </div>
        <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
            data-testid="locale-toggle"
            @click="ui.toggleLocale()"
        >
            <AppIcon name="globe" :size="16" />
            <span :lang="ui.locale === 'ar' ? 'en' : 'ar'">{{ t('nav.language') }}</span>
        </button>
    </div>
</template>
