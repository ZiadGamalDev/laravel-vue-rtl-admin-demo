<script setup lang="ts">
defineProps<{ subtitle?: string }>();
</script>

<template>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500" data-testid="page-subtitle">{{ subtitle }}</p>
        <div class="flex flex-wrap items-center gap-2">
            <slot />
        </div>
    </div>
</template>
