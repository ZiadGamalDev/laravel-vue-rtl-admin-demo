<script setup lang="ts">
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import {
    CategoryScale,
    Chart as ChartJS,
    Filler,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip,
    type ChartData,
    type ChartOptions,
} from 'chart.js';
import { useI18n } from 'vue-i18n';
import { useUiStore } from '@/stores/ui';
import { useFormat } from '@/composables/useFormat';
import { formatDate, parseDate } from '@/lib/format';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Filler, Tooltip);

const props = defineProps<{ points: { date: string; order_value: number }[] }>();

const ui = useUiStore();
const fmt = useFormat();

const { t } = useI18n();

// Screen-reader summary of the canvas: period, total and best day.
const summary = computed(() => {
    if (!props.points.length) return '';
    const total = props.points.reduce((sum, p) => sum + p.order_value, 0);
    const best = props.points.reduce((a, b) => (b.order_value > a.order_value ? b : a));
    return t('dashboard.chartSummary', {
        from: formatDate(props.points[0].date, ui.tag),
        to: formatDate(props.points[props.points.length - 1].date, ui.tag),
        total: fmt.money(total),
        best: formatDate(best.date, ui.tag),
        bestValue: fmt.money(best.order_value),
    });
});

const reducedMotion = typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

function shortDate(value: string): string {
    return new Intl.DateTimeFormat(ui.tag, { day: 'numeric', month: 'short' }).format(parseDate(value));
}

const data = computed<ChartData<'line'>>(() => ({
    labels: props.points.map((p) => p.date),
    datasets: [
        {
            data: props.points.map((p) => p.order_value),
            borderColor: '#0d9488',
            backgroundColor: 'rgba(13, 148, 136, 0.12)',
            fill: true,
            tension: 0.35,
            pointRadius: 0,
            pointHoverRadius: 5,
            borderWidth: 2.5,
        },
    ],
}));

// In RTL the time axis runs right-to-left and the value axis moves to the right edge.
const options = computed<ChartOptions<'line'>>(() => {
    const grid = ui.dark ? 'rgba(148, 163, 184, 0.15)' : 'rgba(148, 163, 184, 0.25)';
    const tick = ui.dark ? '#94a3b8' : '#64748b';
    const font = { family: 'Cairo, sans-serif', size: 12 };
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: reducedMotion ? false : undefined,
        interaction: { mode: 'index', intersect: false },
        locale: ui.tag,
        scales: {
            x: {
                reverse: ui.isRtl,
                grid: { display: false },
                ticks: {
                    color: tick,
                    font,
                    maxRotation: 0,
                    autoSkip: true,
                    maxTicksLimit: 8,
                    callback(_value, index) {
                        return shortDate(props.points[index]?.date ?? '');
                    },
                },
            },
            y: {
                position: ui.isRtl ? 'right' : 'left',
                beginAtZero: true,
                grid: { color: grid },
                border: { display: false },
                ticks: {
                    color: tick,
                    font,
                    maxTicksLimit: 6,
                    callback: (value) => fmt.number(Number(value), { notation: 'compact' }),
                },
            },
        },
        plugins: {
            legend: { display: false },
            tooltip: {
                rtl: ui.isRtl,
                textDirection: ui.dir,
                titleFont: font,
                bodyFont: font,
                callbacks: {
                    title: (items) => formatDate(String(items[0]?.label ?? ''), ui.tag),
                    label: (item) => fmt.money(Number(item.raw)),
                },
            },
        },
    };
});
</script>

<template>
    <div class="h-72" data-testid="order-value-chart" role="img" :aria-label="summary">
        <Line :key="`${ui.locale}-${ui.digits}-${ui.dark}`" :data="data" :options="options" />
    </div>
</template>
