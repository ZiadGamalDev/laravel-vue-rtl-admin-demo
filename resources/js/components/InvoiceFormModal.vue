<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useQuery } from '@tanstack/vue-query';
import { api, type Invoice, type InvoicePayload } from '@/lib/api';
import type { ValidationErrors } from '@/lib/http';
import { useFormat } from '@/composables/useFormat';
import { todayIso } from '@/lib/dates';
import AppModal from './AppModal.vue';
import FormField from './FormField.vue';

const props = defineProps<{ open: boolean; invoice: Invoice | null; errors: ValidationErrors; formError: string | null; busy: boolean }>();
const emit = defineEmits<{ close: []; submit: [InvoicePayload] }>();
const { t } = useI18n();
const fmt = useFormat();

const statuses = ['draft', 'paid', 'overdue'] as const;

const orders = useQuery({
    queryKey: ['orders', 'without-invoice'],
    queryFn: () => api.orders.list({ without_invoice: 1, per_page: 100, sort: 'ordered_at', direction: 'desc' }),
    enabled: computed(() => props.open),
});

function addDays(iso: string, days: number): string {
    const [y, m, d] = iso.split('-').map(Number);
    return todayIso(new Date(y, m - 1, d + days));
}

const form = reactive<{ order_id: number | null; status: Invoice['status']; issued_at: string; due_at: string; notes: string }>({
    order_id: null,
    status: 'draft',
    issued_at: todayIso(),
    due_at: addDays(todayIso(), 14),
    notes: '',
});

watch(
    () => [props.open, props.invoice] as const,
    ([open, invoice]) => {
        if (!open) return;
        form.order_id = invoice?.order_id ?? null;
        form.status = invoice?.status ?? 'draft';
        form.issued_at = invoice?.issued_at ?? todayIso();
        form.due_at = invoice?.due_at ?? addDays(todayIso(), 14);
        form.notes = invoice?.notes ?? '';
    },
    { immediate: true },
);

// The invoice's own order is not in the "without invoice" list, so add it back when editing.
const orderOptions = computed(() => {
    const list = (orders.data.value?.data ?? []).map((o) => ({ id: o.id, number: o.number, customer: o.customer?.name ?? '', total: o.total }));
    const current = props.invoice?.order;
    if (current && !list.some((o) => o.id === current.id)) {
        list.unshift({ id: current.id, number: current.number, customer: props.invoice?.customer?.name ?? '', total: current.total });
    }
    return list;
});

const selected = computed(() => orderOptions.value.find((o) => o.id === form.order_id));
// Mirrors the API: an invoice never changes order; a paid one only accepts notes.
const editing = computed(() => !!props.invoice);
const paidLocked = computed(() => props.invoice?.status === 'paid');

function submit() {
    emit('submit', { ...form, notes: form.notes || null });
}
</script>

<template>
    <AppModal :open="open" :title="invoice ? `${t('invoices.editTitle')} ${invoice.number}` : t('invoices.new')" size="lg" @close="emit('close')">
        <form novalidate @submit.prevent="submit">
            <div class="space-y-4 p-5">
                <p v-if="paidLocked" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-500/30" data-testid="paid-lock">
                    {{ t('invoices.paidLocked') }}
                </p>
                <p v-if="formError" class="alert-error" role="alert" data-testid="form-error">
                    {{ formError }}
                </p>
                <FormField v-slot="{ field, invalid }" :label="t('invoices.order')" :error="errors.order_id" required>
                    <select v-bind="field" v-model="form.order_id" class="input" :class="{ 'input-invalid': invalid }" name="order_id" :disabled="editing">
                        <option :value="null">{{ t('invoices.selectOrder') }}</option>
                        <option v-for="o in orderOptions" :key="o.id" :value="o.id">{{ o.number }} — {{ o.customer }} — {{ fmt.money(o.total) }}</option>
                    </select>
                </FormField>
                <div v-if="selected" class="flex flex-wrap gap-x-6 gap-y-1 rounded-lg bg-slate-50 p-3 text-sm dark:bg-slate-800" data-testid="selected-order">
                    <span><span class="text-slate-500">{{ t('invoices.customer') }}:</span> {{ selected.customer }}</span>
                    <span><span class="text-slate-500">{{ t('invoices.amount') }}:</span> <bdi class="font-semibold">{{ fmt.money(selected.total) }}</bdi></span>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <FormField v-slot="{ field, invalid }" :label="t('invoices.status')" :error="errors.status" required>
                        <select v-bind="field" v-model="form.status" class="input" :class="{ 'input-invalid': invalid }" name="status" :disabled="paidLocked">
                            <option v-for="s in statuses" :key="s" :value="s">{{ t(`invoices.statuses.${s}`) }}</option>
                        </select>
                    </FormField>
                    <FormField v-slot="{ field, invalid }" :label="t('invoices.issuedAt')" :error="errors.issued_at" required>
                        <input v-bind="field" v-model="form.issued_at" type="date" class="input" :class="{ 'input-invalid': invalid }" name="issued_at" :disabled="paidLocked" />
                    </FormField>
                    <FormField v-slot="{ field, invalid }" :label="t('invoices.dueAt')" :error="errors.due_at" required>
                        <input v-bind="field" v-model="form.due_at" type="date" class="input" :class="{ 'input-invalid': invalid }" name="due_at" :disabled="paidLocked" />
                    </FormField>
                </div>
                <FormField v-slot="{ field, invalid }" :label="t('common.notes')" :error="errors.notes">
                    <textarea v-bind="field" v-model="form.notes" rows="2" class="input" :class="{ 'input-invalid': invalid }" name="notes" dir="auto" />
                </FormField>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                <button type="button" class="btn-secondary" @click="emit('close')">{{ t('common.cancel') }}</button>
                <button type="submit" class="btn-primary" :disabled="busy" data-testid="form-submit">{{ t('common.save') }}</button>
            </div>
        </form>
    </AppModal>
</template>
