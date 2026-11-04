<script setup lang="ts">
import { computed } from 'vue';

/**
 * Small inline icon set (24x24, stroke based). Icons that point in a reading
 * direction are listed in `directional` and get mirrored in RTL.
 */
const paths: Record<string, string> = {
    home: 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
    users: 'M16 19v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1M9 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7m13 9v-1a4 4 0 0 0-3-3.87M16 3.13a3.5 3.5 0 0 1 0 6.75',
    cart: 'M3 4h2l2.4 10.2a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.76L20 8H6.2M9 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2m8 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2',
    invoice: 'M7 3h7l5 5v12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1m7 0v5h5M9 13h6m-6 4h6',
    bug: 'M8 9V7a4 4 0 0 1 8 0v2m-9 0h10v5a5 5 0 0 1-10 0zM3 13h4m10 0h4M4 7l3 2m13-2-3 2M4 19l3-2m13 2-3-2',
    logout: 'M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h3m5-12 4 4-4 4m4-4H8',
    plus: 'M12 5v14m-7-7h14',
    search: 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14m10 3-5-5',
    edit: 'M4 20h4L19 9l-4-4L4 16zm9-13 4 4',
    trash: 'M4 7h16m-10 4v6m4-6v6M6 7l1 13h10l1-13M9 7V4h6v3',
    chevron: 'm9 6 6 6-6 6',
    arrow: 'M5 12h14m-6-6 6 6-6 6',
    sortAsc: 'm8 15 4-4 4 4',
    sortDesc: 'm8 10 4 4 4-4',
    sort: 'm8 9 4-4 4 4m-8 6 4 4 4-4',
    close: 'M6 6l12 12M18 6 6 18',
    printer: 'M7 9V3h10v6M7 17H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2M7 14h10v7H7z',
    globe: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18m-9-9h18M12 3c2.5 2.5 3.5 5.5 3.5 9s-1 6.5-3.5 9c-2.5-2.5-3.5-5.5-3.5-9s1-6.5 3.5-9',
    alert: 'M12 9v4m0 4h.01M10.3 4.2 2.6 17.5A2 2 0 0 0 4.3 20.5h15.4a2 2 0 0 0 1.7-3L13.7 4.2a2 2 0 0 0-3.4 0',
    inbox: 'M3 13h5l1.5 3h5L16 13h5M5 5h14l2 8v6a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-6z',
    check: 'm5 12 5 5 9-10',
    refresh: 'M20 11a8 8 0 0 0-14.9-3M4 4v4h4m-4 5a8 8 0 0 0 14.9 3M20 20v-4h-4',
    trendUp: 'm3 17 6-6 4 4 8-8m0 0h-5m5 0v5',
    trendDown: 'm3 7 6 6 4-4 8 8m0 0h-5m5 0v-5',
    moon: 'M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5',
    sun: 'M12 17a5 5 0 1 0 0-10 5 5 0 0 0 0 10m0-15v2m0 16v2M4.2 4.2l1.4 1.4m12.8 12.8 1.4 1.4M2 12h2m16 0h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4',
    download: 'M12 4v11m-5-5 5 5 5-5M5 20h14',
    menu: 'M4 6h16M4 12h16M4 18h16',
    shield: 'M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6zm-3 9 2 2 4-4',
    mail: 'M4 6h16v12H4zm0 0 8 7 8-7',
    file: 'M7 3h7l5 5v12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1m7 0v5h5M9 14h6m-6 3h4',
    calendar: 'M7 3v3m10-3v3M4 8h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1',
};

const directional = new Set(['chevron', 'arrow', 'logout', 'trendUp', 'trendDown']);

const props = withDefaults(defineProps<{ name: string; size?: number }>(), { size: 20 });

const d = computed(() => paths[props.name] ?? '');
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        :class="{ 'flip-rtl': directional.has(name) }"
        class="shrink-0"
    >
        <path :d="d" />
    </svg>
</template>
