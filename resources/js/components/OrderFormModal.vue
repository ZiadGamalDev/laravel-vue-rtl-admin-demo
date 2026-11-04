<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useQuery } from '@tanstack/vue-query';
import { api, type Order, type OrderItem, type OrderPayload } from '@/lib/api';
import type { ValidationErrors } from '@/lib/http';
import { useFormat } from '@/composables/useFormat';
import { todayIso } from '@/lib/dates';
import AppIcon from './AppIcon.vue';
import AppModal from './AppModal.vue';
import FormField from './FormField.vue';

const props = defineProps<{ open: boolean; order: Order | null; errors: ValidationErrors; formError: string | null; busy: boolean }>();
const emit = defineEmits<{ close: []; submit: [OrderPayload] }>();
const { t } = useI18n();
const fmt = useFormat();

const statuses = ['pending', 'processing', 'completed', 'cancelled'] as const;

const customers = useQuery({
    queryKey: ['customers', 'options'],
    queryFn: () => api.customers.list({ per_page: 100, sort: 'name', direction: 'asc' }),
    enabled: computed(() => props.open),
    staleTime: 60_000,
});

// Editing needs the order's items, which the list endpoint does not include.
const details = useQuery({
    queryKey: computed(() => ['orders', 'detail', props.order?.id]),
    queryFn: () => api.orders.get(props.order!.id),
    enabled: computed(() => props.open && !!props.order),
});

type ItemRow = { product_name: string; quantity: number | string; unit_price: number | string };
const form = reactive<{ customer_id: number | null; status: Order['status']; ordered_at: string; notes: string; items: ItemRow[] }>({
    customer_id: null,
    status: 'pending',
    ordered_at: todayIso(),
    notes: '',
    items: [],
});

function blankItem(): ItemRow {
    return { product_name: '', quantity: 1, unit_price: '' };
}

function fill(order: Order | null, items?: OrderItem[]) {
    form.customer_id = order?.customer_id ?? null;
    form.status = order?.status ?? 'pending';
    form.ordered_at = order?.ordered_at ?? todayIso();
    form.notes = order?.notes ?? '';
    form.items = items?.length ? items.map((i) => ({ product_name: i.product_name, quantity: i.quantity, unit_price: i.unit_price })) : [blankItem()];
}

watch(
    () => [props.open, props.order] as const,
    ([open, order]) => open && fill(order, details.data.value?.id === order?.id ? details.data.value?.items : undefined),
    { immediate: true },
);
watch(
    () => details.data.value,
    (value) => value && props.open && props.order?.id === value.id && fill(value, value.items),
);

const lineTotal = (item: ItemRow) => (Number(item.quantity) || 0) * (Number(item.unit_price) || 0);
const total = computed(() => form.items.reduce((sum, item) => sum + lineTotal(item), 0));

const itemError = (index: number, field: string) => props.errors[`items.${index}.${field}`];

// Mirrors the API: an order with a paid invoice keeps its customer, date and items,
// and its status can only move forward (never cancelled).
const paidLocked = computed(() => props.order?.invoice?.status === 'paid');
const allowedStatuses = computed(() => {
    if (!paidLocked.value || !props.order) return statuses;
    const flow = ['pending', 'processing', 'completed'] as const;
    return flow.slice(flow.indexOf(props.order.status as (typeof flow)[number]));
});

function submit() {
    emit('submit', {
        customer_id: form.customer_id,
        status: form.status,
        ordered_at: form.ordered_at,
        notes: form.notes || null,
        items: form.items.map((i) => ({
            product_name: i.product_name,
            quantity: i.quantity === '' ? (null as unknown as number) : Number(i.quantity),
            unit_price: i.unit_price === '' ? (null as unknown as number) : Number(i.unit_price),
        })),
    });
}
</script>

<template>
    <AppModal :open="open" :title="order ? `${t('orders.editTitle')} ${order.number}` : t('orders.new')" size="2xl" @close="emit('close')">
        <form novalidate @submit.prevent="submit">
            <div class="space-y-5 p-5">
                <p v-if="paidLocked" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-500/30" data-testid="paid-lock">
                    {{ t('orders.paidLocked') }}
                </p>
                <p v-if="formError" class="alert-error" role="alert" data-testid="form-error">
                    {{ formError }}
                </p>
                <div class="grid gap-4 md:grid-cols-3">
                    <FormField v-slot="{ field, invalid }" :label="t('orders.customer')" :error="errors.customer_id" required>
                        <select v-bind="field" v-model="form.customer_id" class="input" :class="{ 'input-invalid': invalid }" name="customer_id" :disabled="paidLocked">
                            <option :value="null">{{ t('orders.selectCustomer') }}</option>
                            <option v-for="c in customers.data.value?.data ?? []" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </FormField>
                    <FormField v-slot="{ field, invalid }" :label="t('orders.status')" :error="errors.status" required>
                        <select v-bind="field" v-model="form.status" class="input" :class="{ 'input-invalid': invalid }" name="status">
                            <option v-for="s in allowedStatuses" :key="s" :value="s">{{ t(`orders.statuses.${s}`) }}</option>
                        </select>
                    </FormField>
                    <FormField v-slot="{ field, invalid }" :label="t('orders.date')" :error="errors.ordered_at" required>
                        <input v-bind="field" v-model="form.ordered_at" type="date" class="input" :class="{ 'input-invalid': invalid }" name="ordered_at" :disabled="paidLocked" />
                    </FormField>
                </div>

                <fieldset>
                    <legend class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ t('orders.items') }}<span class="ms-0.5 text-rose-600">*</span></legend>
                    <p v-if="errors.items" class="mb-2 text-sm text-rose-600" role="alert" data-testid="field-error">{{ errors.items[0] }}</p>
                    <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>{{ t('orders.product') }}</th>
                                    <th class="w-44">{{ t('orders.quantity') }}</th>
                                    <th class="w-44">{{ t('orders.unitPrice') }}</th>
                                    <th class="w-32 text-end">{{ t('orders.lineTotal') }}</th>
                                    <th class="w-10"><span class="sr-only">{{ t('common.actions') }}</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in form.items" :key="index" data-testid="item-row">
                                    <td class="align-top">
                                        <input v-model="item.product_name" class="input" :class="{ 'input-invalid': itemError(index, 'product_name') }" :name="`items.${index}.product_name`" :disabled="paidLocked" :aria-invalid="itemError(index, 'product_name') ? true : undefined" :aria-describedby="itemError(index, 'product_name') ? `item-${index}-product_name-error` : undefined" dir="auto" :aria-label="t('orders.product')" />
                                        <p v-if="itemError(index, 'product_name')" :id="`item-${index}-product_name-error`" class="mt-1 text-xs text-rose-600 dark:text-rose-400" data-testid="field-error">{{ itemError(index, 'product_name')?.[0] }}</p>
                                    </td>
                                    <td class="align-top">
                                        <input v-model="item.quantity" type="number" min="1" inputmode="numeric" class="input" :class="{ 'input-invalid': itemError(index, 'quantity') }" :name="`items.${index}.quantity`" :disabled="paidLocked" :aria-invalid="itemError(index, 'quantity') ? true : undefined" :aria-describedby="itemError(index, 'quantity') ? `item-${index}-quantity-error` : undefined" :aria-label="t('orders.quantity')" />
                                        <p v-if="itemError(index, 'quantity')" :id="`item-${index}-quantity-error`" class="mt-1 text-xs text-rose-600 dark:text-rose-400" data-testid="field-error">{{ itemError(index, 'quantity')?.[0] }}</p>
                                    </td>
                                    <td class="align-top">
                                        <input v-model="item.unit_price" type="number" min="0" step="0.01" inputmode="decimal" class="input" :class="{ 'input-invalid': itemError(index, 'unit_price') }" :name="`items.${index}.unit_price`" :disabled="paidLocked" :aria-invalid="itemError(index, 'unit_price') ? true : undefined" :aria-describedby="itemError(index, 'unit_price') ? `item-${index}-unit_price-error` : undefined" :aria-label="t('orders.unitPrice')" />
                                        <p v-if="itemError(index, 'unit_price')" :id="`item-${index}-unit_price-error`" class="mt-1 text-xs text-rose-600 dark:text-rose-400" data-testid="field-error">{{ itemError(index, 'unit_price')?.[0] }}</p>
                                    </td>
                                    <td class="pt-5 text-end align-top font-semibold whitespace-nowrap"><bdi>{{ fmt.money(lineTotal(item)) }}</bdi></td>
                                    <td class="align-top">
                                        <button
                                            type="button"
                                            class="mt-1 rounded-lg p-1.5 text-slate-500 hover:bg-rose-50 hover:text-rose-600 disabled:opacity-40"
                                            :disabled="form.items.length === 1 || paidLocked"
                                            :aria-label="t('orders.removeItem')"
                                            @click="form.items.splice(index, 1)"
                                        >
                                            <AppIcon name="trash" :size="18" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3">
                                        <button type="button" class="btn-secondary px-3 py-1.5" :disabled="paidLocked" data-testid="add-item" @click="form.items.push(blankItem())">
                                            <AppIcon name="plus" :size="16" />
                                            {{ t('orders.addItem') }}
                                        </button>
                                    </td>
                                    <td class="text-end text-base font-bold whitespace-nowrap text-slate-900 dark:text-white" data-testid="order-total">
                                        <bdi>{{ fmt.money(total) }}</bdi>
                                    </td>
                                    <td />
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </fieldset>

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
