<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { useToastStore } from '@/stores/toast';
import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { errorMessage } from '@/lib/http';
import { api, type Invoice, type InvoicePayload } from '@/lib/api';
import { useListQuery } from '@/composables/useListQuery';
import { useCrud } from '@/composables/useCrud';
import { useFormat } from '@/composables/useFormat';
import AppIcon from '@/components/AppIcon.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import InvoiceFormModal from '@/components/InvoiceFormModal.vue';
import PageHeader from '@/components/PageHeader.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import QueryState from '@/components/QueryState.vue';
import SearchInput from '@/components/SearchInput.vue';
import SortHeader from '@/components/SortHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';

const { t } = useI18n();
const auth = useAuthStore();
const canCreate = computed(() => auth.can('invoices.create'));
const canUpdate = computed(() => auth.can('invoices.update'));
const canDelete = computed(() => auth.can('invoices.delete'));
const canEmail = computed(() => auth.can('invoices.email'));
const canReopen = computed(() => auth.can('invoices.reopen'));
const queryClient = useQueryClient();

const reopen = useMutation({
    mutationFn: (id: number) => api.invoices.reopen(id),
    onSuccess: async () => {
        toast.push(t('invoices.reopened'));
        await queryClient.invalidateQueries({ queryKey: ['invoices'] });
    },
    onError: (error) => toast.push(errorMessage(error) ?? t('common.errorBody'), 'error'),
});
const ui = useUiStore();
const toast = useToastStore();

const sendEmail = useMutation({
    mutationFn: (id: number) => api.invoices.email(id),
    onSuccess: (message) => toast.push(message),
    onError: (error) => toast.push(errorMessage(error) ?? t('common.errorBody'), 'error'),
});
const fmt = useFormat();
const statuses = ['draft', 'paid', 'overdue'] as const;

const { search, params, query, rows, meta, isFiltered, toggleSort } = useListQuery<Invoice>('invoices', api.invoices.list, {
    sort: 'issued_at',
    direction: 'desc',
});

const crud = useCrud<Invoice, InvoicePayload>({
    keys: ['invoices', 'orders', 'dashboard'],
    create: api.invoices.create,
    update: api.invoices.update,
    remove: api.invoices.remove,
});
</script>

<template>
    <div>
        <PageHeader :subtitle="meta ? t('common.totalRecords', { count: fmt.number(meta.total) }) : ''">
            <SearchInput v-model="search" />
            <select v-model="params.status" class="input w-auto" :aria-label="t('invoices.status')" data-testid="status-filter">
                <option value="">{{ t('invoices.status') }}: {{ t('common.all') }}</option>
                <option v-for="s in statuses" :key="s" :value="s">{{ t(`invoices.statuses.${s}`) }}</option>
            </select>
            <button v-if="canCreate" type="button" class="btn-primary" data-testid="create" @click="crud.openCreate()">
                <AppIcon name="plus" :size="18" />
                {{ t('invoices.new') }}
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
                                <SortHeader column="number" :label="t('invoices.number')" :sort="params.sort" :direction="params.direction" @sort="toggleSort" />
                                <SortHeader column="customer" :label="t('invoices.customer')" :sort="params.sort" :direction="params.direction" @sort="toggleSort" />
                                <th>{{ t('invoices.order') }}</th>
                                <SortHeader column="issued_at" :label="t('invoices.issuedAt')" :sort="params.sort" :direction="params.direction" @sort="toggleSort" />
                                <SortHeader column="due_at" :label="t('invoices.dueAt')" :sort="params.sort" :direction="params.direction" @sort="toggleSort" />
                                <SortHeader column="status" :label="t('invoices.status')" :sort="params.sort" :direction="params.direction" @sort="toggleSort" />
                                <SortHeader column="amount" :label="t('invoices.amount')" :sort="params.sort" :direction="params.direction" align="end" @sort="toggleSort" />
                                <th class="text-end">{{ t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="invoice in rows" :key="invoice.id" data-testid="row">
                                <td><span class="ltr-value font-semibold text-slate-900 dark:text-white">{{ invoice.number }}</span></td>
                                <td>{{ invoice.customer?.name }}</td>
                                <td><span class="ltr-value text-slate-500">{{ invoice.order?.number }}</span></td>
                                <td class="whitespace-nowrap">{{ fmt.date(invoice.issued_at) }}</td>
                                <td class="whitespace-nowrap">{{ fmt.date(invoice.due_at) }}</td>
                                <td><StatusBadge kind="invoice" :status="invoice.status" /></td>
                                <td class="text-end font-semibold whitespace-nowrap"><bdi>{{ fmt.money(invoice.amount) }}</bdi></td>
                                <td class="text-end whitespace-nowrap">
                                    <a :href="api.invoices.pdfUrl(invoice.id, ui.locale)" class="inline-flex rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-brand-700 dark:hover:bg-slate-800" :aria-label="t('invoices.pdf')" :title="t('invoices.pdf')" data-testid="pdf">
                                        <AppIcon name="file" :size="18" />
                                    </a>
                                    <button v-if="canEmail" type="button" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-brand-700 disabled:opacity-50 dark:hover:bg-slate-800" :aria-label="t('invoices.email')" :title="t('invoices.email')" :disabled="sendEmail.isPending.value" data-testid="email" @click="sendEmail.mutate(invoice.id)">
                                        <AppIcon name="mail" :size="18" />
                                    </button>
                                    <button v-if="canReopen && invoice.status === 'paid'" type="button" class="rounded-lg p-1.5 text-slate-500 hover:bg-amber-50 hover:text-amber-700 dark:hover:bg-slate-800" :aria-label="t('invoices.reopen')" :title="t('invoices.reopen')" :disabled="reopen.isPending.value" data-testid="reopen" @click="reopen.mutate(invoice.id)">
                                        <AppIcon name="refresh" :size="18" />
                                    </button>
                                    <RouterLink :to="`/invoices/${invoice.id}/print`" class="inline-flex rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-brand-700 dark:hover:bg-slate-800" :aria-label="t('invoices.print')" :title="t('invoices.print')" data-testid="print">
                                        <AppIcon name="printer" :size="18" />
                                    </RouterLink>
                                    <button type="button" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-brand-700 dark:hover:bg-slate-800" :aria-label="t('common.edit')" v-if="canUpdate" data-testid="edit" @click="crud.openEdit(invoice)">
                                        <AppIcon name="edit" :size="18" />
                                    </button>
                                    <button type="button" class="rounded-lg p-1.5 text-slate-500 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-slate-800" :aria-label="t('common.delete')" v-if="canDelete && invoice.status !== 'paid'" data-testid="delete" @click="crud.askDelete(invoice)">
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

        <InvoiceFormModal
            :open="crud.formOpen.value"
            :invoice="crud.editing.value"
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
