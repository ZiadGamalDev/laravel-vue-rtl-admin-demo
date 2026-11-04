<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import { api, type Order, type OrderPayload } from '@/lib/api';
import { useListQuery } from '@/composables/useListQuery';
import { useCrud } from '@/composables/useCrud';
import { useFormat } from '@/composables/useFormat';
import AppIcon from '@/components/AppIcon.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import OrderFormModal from '@/components/OrderFormModal.vue';
import PageHeader from '@/components/PageHeader.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import QueryState from '@/components/QueryState.vue';
import SearchInput from '@/components/SearchInput.vue';
import SortHeader from '@/components/SortHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import DateRangeFilter from '@/components/DateRangeFilter.vue';
import { computed } from 'vue';

const { t } = useI18n();
const auth = useAuthStore();
const canCreate = computed(() => auth.can('orders.create'));
const canUpdate = computed(() => auth.can('orders.update'));
const canDelete = computed(() => auth.can('orders.delete'));
const fmt = useFormat();
const statuses = ['pending', 'processing', 'completed', 'cancelled'] as const;

const { search, params, query, rows, meta, isFiltered, toggleSort } = useListQuery<Order>('orders', api.orders.list, {
    sort: 'ordered_at',
    direction: 'desc',
});

// The export uses the same filters as the list; the browser downloads it with the session cookie.
const exportUrl = computed(() => {
    const query = new URLSearchParams();
    for (const key of ['search', 'status', 'from', 'to'] as const) {
        if (params[key]) query.set(key, String(params[key]));
    }
    const qs = query.toString();
    return `/api/orders/export${qs ? `?${qs}` : ''}`;
});

const crud = useCrud<Order, OrderPayload>({
    keys: ['orders', 'dashboard', 'customers', 'invoices'],
    create: api.orders.create,
    update: api.orders.update,
    remove: api.orders.remove,
});
</script>

<template>
    <div>
        <PageHeader :subtitle="meta ? t('common.totalRecords', { count: fmt.number(meta.total) }) : ''">
            <SearchInput v-model="search" />
            <select v-model="params.status" class="input w-auto" :aria-label="t('orders.status')" data-testid="status-filter">
                <option value="">{{ t('orders.status') }}: {{ t('common.all') }}</option>
                <option v-for="s in statuses" :key="s" :value="s">{{ t(`orders.statuses.${s}`) }}</option>
            </select>
            <DateRangeFilter v-model:from="params.from!" v-model:to="params.to!" default-preset="all" />
            <a :href="exportUrl" class="btn-secondary" download data-testid="export">
                <AppIcon name="download" :size="18" />
                {{ t('common.export') }}
            </a>
            <button v-if="canCreate" type="button" class="btn-primary" data-testid="create" @click="crud.openCreate()">
                <AppIcon name="plus" :size="18" />
                {{ t('orders.new') }}
            </button>
        </PageHeader>

        <div class="card overflow-hidden">
            <QueryState
                :loading="query.isPending.value"
                :error="query.error.value"
                :empty="rows.length === 0"
                :empty-text="isFiltered ? t('common.emptySearch') : undefined"
                @retry="query.refetch()"
            >
                <div class="overflow-x-auto" :class="{ 'opacity-60': query.isPlaceholderData.value }">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <SortHeader column="number" :label="t('orders.number')" :sort="params.sort" :direction="params.direction" @sort="toggleSort" />
                                <SortHeader column="customer" :label="t('orders.customer')" :sort="params.sort" :direction="params.direction" @sort="toggleSort" />
                                <SortHeader column="ordered_at" :label="t('orders.date')" :sort="params.sort" :direction="params.direction" @sort="toggleSort" />
                                <th>{{ t('orders.items') }}</th>
                                <SortHeader column="status" :label="t('orders.status')" :sort="params.sort" :direction="params.direction" @sort="toggleSort" />
                                <th>{{ t('invoices.number') }}</th>
                                <SortHeader column="total" :label="t('orders.total')" :sort="params.sort" :direction="params.direction" align="end" @sort="toggleSort" />
                                <th v-if="canUpdate || canDelete" class="text-end">{{ t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="order in rows" :key="order.id" data-testid="row">
                                <td><span class="ltr-value font-semibold text-slate-900 dark:text-white">{{ order.number }}</span></td>
                                <td>{{ order.customer?.name }}</td>
                                <td class="whitespace-nowrap">{{ fmt.date(order.ordered_at) }}</td>
                                <td>{{ fmt.number(order.items_count ?? 0) }}</td>
                                <td><StatusBadge kind="order" :status="order.status" /></td>
                                <td class="whitespace-nowrap">
                                    <RouterLink v-if="order.invoice" :to="`/invoices/${order.invoice.id}/print`" class="ltr-value text-brand-700 hover:underline dark:text-brand-100">
                                        {{ order.invoice.number }}
                                    </RouterLink>
                                    <span v-else class="text-slate-500">—</span>
                                </td>
                                <td class="text-end font-semibold whitespace-nowrap"><bdi>{{ fmt.money(order.total) }}</bdi></td>
                                <td v-if="canUpdate || canDelete" class="text-end whitespace-nowrap">
                                    <button type="button" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-brand-700 dark:hover:bg-slate-800" :aria-label="t('common.edit')" v-if="canUpdate" data-testid="edit" @click="crud.openEdit(order)">
                                        <AppIcon name="edit" :size="18" />
                                    </button>
                                    <button type="button" class="rounded-lg p-1.5 text-slate-500 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-slate-800" :aria-label="t('common.delete')" v-if="canDelete" data-testid="delete" @click="crud.askDelete(order)">
                                        <AppIcon name="trash" :size="18" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <PaginationBar v-if="meta" :meta="meta" @page="params.page = $event" />
            </QueryState>
        </div>

        <OrderFormModal
            :open="crud.formOpen.value"
            :order="crud.editing.value"
            :errors="crud.errors.value"
            :form-error="crud.formError.value"
            :busy="crud.save.isPending.value"
            @close="crud.closeForm()"
            @submit="crud.save.mutate($event)"
        />
        <ConfirmDialog
            :open="!!crud.deleting.value"
            :name="crud.deleting.value?.number ?? ''"
            :busy="crud.destroy.isPending.value"
            :error="crud.deleteError.value"
            @cancel="crud.deleting.value = null"
            @confirm="crud.deleting.value && crud.destroy.mutate(crud.deleting.value)"
        />
    </div>
</template>
