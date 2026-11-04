<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import { useQuery } from '@tanstack/vue-query';
import { api } from '@/lib/api';
import { useFormat } from '@/composables/useFormat';
import { useUiStore } from '@/stores/ui';
import AppIcon from '@/components/AppIcon.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import QueryState from '@/components/QueryState.vue';
import StatusBadge from '@/components/StatusBadge.vue';

const { t } = useI18n();
const ui = useUiStore();
const fmt = useFormat();
const route = useRoute();

const query = useQuery({
    queryKey: computed(() => ['invoices', 'detail', String(route.params.id)]),
    queryFn: () => api.invoices.get(String(route.params.id)),
});
const invoice = computed(() => query.data.value);
const items = computed(() => invoice.value?.order?.items ?? []);

function print() {
    window.print();
}
</script>

<template>
    <div class="print-page min-h-screen bg-slate-200 py-8 dark:bg-slate-950">
        <!-- Toolbar: hidden when printing -->
        <div class="no-print mx-auto mb-4 flex max-w-[210mm] flex-wrap items-center justify-between gap-2 px-4 sm:px-0">
            <RouterLink to="/invoices" class="btn-secondary">
                <AppIcon name="arrow" :size="16" class="rotate-180" />
                {{ t('print.back') }}
            </RouterLink>
            <div class="flex items-center gap-2">
                <LocaleSwitcher />
                <a v-if="invoice" :href="api.invoices.emailPreviewUrl(invoice.id, ui.locale)" target="_blank" rel="noopener" class="btn-secondary" data-testid="email-preview">
                    <AppIcon name="mail" :size="18" />
                    {{ t('invoices.emailPreview') }}
                </a>
                <a v-if="invoice" :href="api.invoices.pdfUrl(invoice.id, ui.locale)" class="btn-secondary" data-testid="pdf-button">
                    <AppIcon name="file" :size="18" />
                    {{ t('invoices.pdf') }}
                </a>
                <button type="button" class="btn-primary" :disabled="!invoice" data-testid="print-button" @click="print">
                    <AppIcon name="printer" :size="18" />
                    {{ t('print.printNow') }}
                </button>
            </div>
        </div>

        <QueryState :loading="query.isPending.value" :error="query.error.value" @retry="query.refetch()">
            <article
                v-if="invoice"
                class="invoice-sheet mx-auto flex min-h-[297mm] max-w-[210mm] flex-col bg-white p-[14mm] text-sm text-slate-800 shadow-xl"
                data-testid="invoice-sheet"
            >
                <header class="flex items-start justify-between gap-6 border-b-2 border-brand-600 pb-6">
                    <div>
                        <div class="mb-3 grid size-12 place-items-center rounded-xl bg-brand-600 text-2xl font-bold text-white">د</div>
                        <p class="text-lg font-bold">{{ t('print.companyName') }}</p>
                        <p class="text-slate-500">{{ t('print.companyLine') }}</p>
                    </div>
                    <div class="text-end">
                        <h1 class="text-3xl font-bold text-brand-700">{{ t('print.invoice') }}</h1>
                        <p class="mt-1 text-base font-semibold"><span class="ltr-value">{{ invoice.number }}</span></p>
                        <div class="mt-2"><StatusBadge kind="invoice" :status="invoice.status" /></div>
                    </div>
                </header>

                <section class="grid grid-cols-2 gap-6 py-6">
                    <div>
                        <h2 class="mb-2 text-xs font-bold text-slate-500">{{ t('print.billTo') }}</h2>
                        <p class="text-base font-bold">{{ invoice.customer?.name }}</p>
                        <p v-if="invoice.customer?.city">{{ invoice.customer.city }}</p>
                        <p><span class="ltr-value">{{ invoice.customer?.email }}</span></p>
                        <p v-if="invoice.customer?.phone"><span class="ltr-value">{{ invoice.customer.phone }}</span></p>
                    </div>
                    <dl class="grid grid-cols-2 content-start gap-x-4 gap-y-2">
                        <dt class="text-slate-500">{{ t('invoices.issuedAt') }}</dt>
                        <dd class="font-semibold">{{ fmt.date(invoice.issued_at) }}</dd>
                        <dt class="text-slate-500">{{ t('invoices.dueAt') }}</dt>
                        <dd class="font-semibold">{{ fmt.date(invoice.due_at) }}</dd>
                        <dt class="text-slate-500">{{ t('invoices.order') }}</dt>
                        <dd class="font-semibold"><span class="ltr-value">{{ invoice.order?.number }}</span></dd>
                        <dt class="text-slate-500">{{ t('orders.date') }}</dt>
                        <dd class="font-semibold">{{ fmt.date(invoice.order?.ordered_at) }}</dd>
                    </dl>
                </section>

                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-slate-100 text-xs text-slate-600">
                            <th class="w-10 p-2.5 text-start">#</th>
                            <th class="p-2.5 text-start">{{ t('orders.product') }}</th>
                            <th class="w-20 p-2.5 text-center">{{ t('orders.quantity') }}</th>
                            <th class="w-32 p-2.5 text-end">{{ t('orders.unitPrice') }}</th>
                            <th class="w-32 p-2.5 text-end">{{ t('orders.lineTotal') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, index) in items" :key="index" class="border-b border-slate-200">
                            <td class="p-2.5 text-slate-500">{{ fmt.number(index + 1) }}</td>
                            <td class="p-2.5"><bdi>{{ item.product_name }}</bdi></td>
                            <td class="p-2.5 text-center">{{ fmt.number(item.quantity) }}</td>
                            <td class="p-2.5 text-end whitespace-nowrap"><bdi>{{ fmt.money(item.unit_price) }}</bdi></td>
                            <td class="p-2.5 text-end font-semibold whitespace-nowrap"><bdi>{{ fmt.money(item.line_total ?? 0) }}</bdi></td>
                        </tr>
                    </tbody>
                </table>

                <div class="mt-4 flex justify-end">
                    <dl class="w-72 space-y-2">
                        <div class="flex justify-between">
                            <dt class="text-slate-500">{{ t('print.subtotal') }}</dt>
                            <dd><bdi>{{ fmt.money(invoice.amount) }}</bdi></dd>
                        </div>
                        <div class="flex justify-between border-t-2 border-slate-800 pt-2 text-lg font-bold">
                            <dt>{{ t('print.total') }}</dt>
                            <dd data-testid="invoice-total"><bdi>{{ fmt.money(invoice.amount) }}</bdi></dd>
                        </div>
                    </dl>
                </div>

                <p v-if="invoice.notes" class="mt-6 rounded-lg bg-slate-50 p-3"><bdi>{{ invoice.notes }}</bdi></p>

                <footer class="mt-auto border-t border-slate-200 pt-4 text-center text-xs text-slate-500">
                    <p class="font-semibold text-slate-700">{{ t('print.thanks') }}</p>
                    <p class="mt-1">{{ t('print.demoNote') }}</p>
                </footer>
            </article>
        </QueryState>
    </div>
</template>
