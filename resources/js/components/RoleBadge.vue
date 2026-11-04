<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { Role } from '@/stores/auth';

defineProps<{ role: Role }>();
const { t } = useI18n();

const tones: Record<Role, string> = {
    admin: 'bg-violet-50 text-violet-700 ring-violet-200 dark:bg-violet-500/10 dark:text-violet-300 dark:ring-violet-500/30',
    staff: 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/30',
    viewer: 'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-slate-500/10 dark:text-slate-300 dark:ring-slate-500/30',
};
</script>

<template>
    <span class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap ring-1 ring-inset" :class="tones[role]" :data-role="role">
        {{ t(`roles.${role}`) }}
    </span>
</template>
