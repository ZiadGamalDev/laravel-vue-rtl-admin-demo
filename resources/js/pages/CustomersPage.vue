<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import { api, type Customer, type CustomerPayload } from '@/lib/api';
import { useListQuery } from '@/composables/useListQuery';
import { useCrud } from '@/composables/useCrud';
import { useFormat } from '@/composables/useFormat';
import AppIcon from '@/components/AppIcon.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import CustomerFormModal from '@/components/CustomerFormModal.vue';
import PageHeader from '@/components/PageHeader.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import QueryState from '@/components/QueryState.vue';
import SearchInput from '@/components/SearchInput.vue';
import SortHeader from '@/components/SortHeader.vue';

const { t } = useI18n();
const auth = useAuthStore();
const canCreate = computed(() => auth.can('customers.create'));
const canUpdate = computed(() => auth.can('customers.update'));
const canDelete = computed(() => auth.can('customers.delete'));
const fmt = useFormat();

const list = useListQuery<Customer>('customers', api.customers.list, { sort: 'created_at', direction: 'desc' });
const { search, params, query, rows, meta, isFiltered, toggleSort } = list;

const crud = useCrud<Customer, CustomerPayload>({
    keys: ['customers', 'dashboard'],
    create: api.customers.create,
    update: api.customers.update,
    remove: api.customers.remove,
});
</script>

<template>
    <div>
        <PageHeader :subtitle="meta ? t('common.totalRecords', { count: fmt.number(meta.total) }) : ''">
            <SearchInput v-model="search" />
            <button v-if="canCreate" type="button" class="btn-primary" data-testid="create" @click="crud.openCreate()">
                <AppIcon name="plus" :size="18" />
                {{ t('customers.new') }}
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
                                <SortHeader column="name" :label="t('customers.name')" :sort="params.sort" :direction="params.direction" @sort="toggleSort" />
                                <SortHeader column="email" :label="t('customers.email')" :sort="params.sort" :direction="params.direction" @sort="toggleSort" />
                                <th>{{ t('customers.phone') }}</th>
                                <SortHeader column="city" :label="t('customers.city')" :sort="params.sort" :direction="params.direction" @sort="toggleSort" />
                                <SortHeader column="orders_count" :label="t('customers.orders')" :sort="params.sort" :direction="params.direction" @sort="toggleSort" />
                                <SortHeader column="created_at" :label="t('common.createdAt')" :sort="params.sort" :direction="params.direction" @sort="toggleSort" />
                                <th v-if="canUpdate || canDelete" class="text-end">{{ t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="customer in rows" :key="customer.id" data-testid="row">
                                <td class="font-semibold text-slate-900 dark:text-white">{{ customer.name }}</td>
                                <td><span class="ltr-value">{{ customer.email }}</span></td>
                                <td class="whitespace-nowrap"><span v-if="customer.phone" class="ltr-value">{{ customer.phone }}</span></td>
                                <td>{{ customer.city }}</td>
                                <td>{{ fmt.number(customer.orders_count ?? 0) }}</td>
                                <td class="whitespace-nowrap">{{ fmt.date(customer.created_at) }}</td>
                                <td v-if="canUpdate || canDelete" class="text-end whitespace-nowrap">
                                    <button type="button" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-brand-700 dark:hover:bg-slate-800" :aria-label="t('common.edit')" v-if="canUpdate" data-testid="edit" @click="crud.openEdit(customer)">
                                        <AppIcon name="edit" :size="18" />
                                    </button>
                                    <button type="button" class="rounded-lg p-1.5 text-slate-500 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-slate-800" :aria-label="t('common.delete')" v-if="canDelete" data-testid="delete" @click="crud.askDelete(customer)">
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

        <CustomerFormModal
            :open="crud.formOpen.value"
            :customer="crud.editing.value"
            :errors="crud.errors.value"
            :form-error="crud.formError.value"
            :busy="crud.save.isPending.value"
            @close="crud.closeForm()"
            @submit="crud.save.mutate($event)"
        />
        <ConfirmDialog
            :open="!!crud.deleting.value"
            :name="crud.deleting.value?.name ?? ''"
            :busy="crud.destroy.isPending.value"
            :error="crud.deleteError.value"
            @cancel="crud.deleting.value = null"
            @confirm="crud.deleting.value && crud.destroy.mutate(crud.deleting.value)"
        />
    </div>
</template>
