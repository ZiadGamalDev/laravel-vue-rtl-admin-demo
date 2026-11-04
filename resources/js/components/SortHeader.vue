<script setup lang="ts">
import AppIcon from './AppIcon.vue';

defineProps<{ column: string; label: string; sort: string; direction: 'asc' | 'desc'; align?: 'start' | 'end' }>();
const emit = defineEmits<{ sort: [string] }>();
</script>

<template>
    <th :class="align === 'end' ? 'text-end' : ''" :aria-sort="sort === column ? (direction === 'asc' ? 'ascending' : 'descending') : 'none'">
        <button
            type="button"
            class="inline-flex items-center gap-1 hover:text-slate-800 dark:hover:text-slate-200"
            :class="{ 'text-slate-800 dark:text-slate-100': sort === column }"
            :data-testid="`sort-${column}`"
            @click="emit('sort', column)"
        >
            {{ label }}
            <AppIcon :name="sort !== column ? 'sort' : direction === 'asc' ? 'sortAsc' : 'sortDesc'" :size="14" />
        </button>
    </th>
</template>
