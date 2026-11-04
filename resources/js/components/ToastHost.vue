<script setup lang="ts">
import { useToastStore } from '@/stores/toast';
import AppIcon from './AppIcon.vue';

const store = useToastStore();
</script>

<template>
    <div class="pointer-events-none fixed bottom-4 end-4 z-50 flex w-80 flex-col gap-2" aria-live="polite">
        <div
            v-for="toast in store.toasts"
            :key="toast.id"
            class="pointer-events-auto flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-semibold text-white shadow-lg"
            :class="toast.tone === 'success' ? 'bg-emerald-700' : 'bg-rose-600'"
            role="status"
        >
            <AppIcon :name="toast.tone === 'success' ? 'check' : 'alert'" :size="18" />
            <span class="flex-1">{{ toast.message }}</span>
            <button type="button" class="opacity-80 hover:opacity-100" @click="store.dismiss(toast.id)"><AppIcon name="close" :size="16" /></button>
        </div>
    </div>
</template>
