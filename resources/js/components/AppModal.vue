<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppIcon from './AppIcon.vue';

const props = withDefaults(defineProps<{ open: boolean; title: string; size?: 'md' | 'lg' | 'xl' | '2xl' }>(), { size: 'md' });
const emit = defineEmits<{ close: [] }>();
const { t } = useI18n();
const panel = ref<HTMLElement | null>(null);
const titleId = useId();
let opener: HTMLElement | null = null;

const widths = { md: 'max-w-lg', lg: 'max-w-2xl', xl: 'max-w-4xl', '2xl': 'max-w-5xl' };
const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

function onKey(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        emit('close');
        return;
    }
    // Keep Tab / Shift+Tab inside the dialog.
    if (event.key === 'Tab' && panel.value) {
        const items = [...panel.value.querySelectorAll<HTMLElement>(FOCUSABLE)];
        if (!items.length) return;
        const first = items[0];
        const last = items[items.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }
}

watch(
    () => props.open,
    async (open) => {
        if (open) {
            opener = document.activeElement as HTMLElement | null;
            document.addEventListener('keydown', onKey);
            await nextTick();
            panel.value?.querySelector<HTMLElement>('input, select, textarea, button[type=submit]')?.focus();
        } else {
            document.removeEventListener('keydown', onKey);
            // Return focus to whatever opened the dialog.
            opener?.focus?.();
            opener = null;
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => document.removeEventListener('keydown', onKey));
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-40 overflow-y-auto">
            <div class="fixed inset-0 bg-slate-900/50" @click="emit('close')" />
            <div class="relative flex min-h-full items-start justify-center p-4 sm:pt-16">
                <div
                    ref="panel"
                    class="relative w-full rounded-xl bg-white shadow-2xl dark:bg-slate-900"
                    :class="widths[size]"
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="titleId"
                    data-testid="modal"
                >
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <h2 :id="titleId" class="text-lg font-bold text-slate-900 dark:text-white">{{ title }}</h2>
                        <button type="button" class="rounded-lg p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800" :aria-label="t('common.close')" @click="emit('close')">
                            <AppIcon name="close" />
                        </button>
                    </div>
                    <slot />
                </div>
            </div>
        </div>
    </Teleport>
</template>
