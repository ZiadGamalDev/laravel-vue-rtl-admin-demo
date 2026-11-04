<script setup lang="ts">
import { computed, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import { useQuery } from '@tanstack/vue-query';
import { api } from '@/lib/api';
import { useFormat } from '@/composables/useFormat';
import KpiCard from '@/components/KpiCard.vue';
import OrderValueChart from '@/components/OrderValueChart.vue';
import QueryState from '@/components/QueryState.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import DateRangeFilter from '@/components/DateRangeFilter.vue';

const { t } = useI18n();
const fmt = useFormat();

// Empty range = last 30 days. The API needs both ends, so a half-filled range is ignored.
const range = reactive({ from: '', to: '' });
const params = computed(() => (range.from && range.to && range.from <= range.to ? { from: range.from, to: range.to } : {}));
const custom = computed(() => 'from' in params.value);

const query = useQuery({
    queryKey: computed(() => ['dashboard', params.value]),
    queryFn: () => api.dashboard(params.value),
    placeholderData: (previous) => previous,
});

const data = computed(() => query.data.value);
</script>

<template>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500" data-testid="period">
            <template v-if="data">{{ t('dashboard.rangeSubtitle', { from: fmt.date(data.period.from), to: fmt.date(data.period.to) }) }}</template>
        </p>
        <DateRangeFilter v-model:from="range.from" v-model:to="range.to" default-preset="last30" />
    </div>
    <QueryState :loading="query.isPending.value" :error="query.error.value" @retry="query.refetch()">
        <div v-if="data" class="space-y-6" :class="{ 'opacity-60': query.isPlaceholderData.value }">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard
                    :label="custom ? t('dashboard.orderValueRange') : t('dashboard.orderValue30', { n: fmt.number(30) })"
                    :value="fmt.money(data.kpis.order_value.value)"
                    :change="data.kpis.order_value.change"
                    icon="trendUp"
                    tone="brand"
                />
                <KpiCard
                    :label="custom ? t('dashboard.ordersRange') : t('dashboard.orders30', { n: fmt.number(30) })"
                    :value="fmt.number(data.kpis.orders.value)"
                    :change="data.kpis.orders.change"
                    icon="cart"
                    tone="sky"
                />
                <KpiCard
                    :label="t('dashboard.customers')"
                    :value="fmt.number(data.kpis.customers.value)"
                    icon="users"
                    tone="violet"
                    :hint="t('dashboard.newCustomers', { count: fmt.number(data.kpis.customers.new) })"
                />
                <KpiCard
                    :label="t('dashboard.overdue')"
                    :value="fmt.number(data.kpis.overdue_invoices.value)"
                    icon="invoice"
                    tone="rose"
                    :hint="t('dashboard.overdueAmount', { amount: fmt.money(data.kpis.overdue_invoices.amount) })"
                />
            </div>

            <section class="card p-5">
                <div class="mb-4">
                    <h2 class="font-bold text-slate-900 dark:text-white" :title="t('dashboard.orderValueNote')">{{ t('dashboard.chartTitle') }}</h2>
                    <p class="text-sm text-slate-500">
                        {{ custom ? t('dashboard.rangeSubtitle', { from: fmt.date(data.period.from), to: fmt.date(data.period.to) }) : t('dashboard.chartSubtitle', { n: fmt.number(30) }) }}
                    </p>
                </div>
                <OrderValueChart :points="data.chart" />
            </section>

            <section class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="font-bold text-slate-900 dark:text-white">{{ t('dashboard.recentOrders') }}</h2>
                    <RouterLink to="/orders" class="text-sm font-semibold text-brand-700 hover:underline dark:text-brand-100">
                        {{ t('dashboard.viewAll') }}
                    </RouterLink>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>{{ t('orders.number') }}</th>
                                <th>{{ t('orders.customer') }}</th>
                                <th>{{ t('orders.date') }}</th>
                                <th>{{ t('orders.items') }}</th>
                                <th>{{ t('orders.status') }}</th>
                                <th class="text-end">{{ t('orders.total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="order in data.recent_orders" :key="order.id">
                                <td><span class="ltr-value font-semibold">{{ order.number }}</span></td>
                                <td>{{ order.customer }}</td>
                                <td class="whitespace-nowrap">{{ fmt.date(order.ordered_at) }}</td>
                                <td>{{ fmt.number(order.items_count) }}</td>
                                <td><StatusBadge kind="order" :status="order.status" /></td>
                                <td class="text-end font-semibold whitespace-nowrap"><bdi>{{ fmt.money(order.total) }}</bdi></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </QueryState>
</template>
