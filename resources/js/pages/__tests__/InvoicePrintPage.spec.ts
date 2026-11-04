import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { createMemoryHistory, createRouter } from 'vue-router';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { i18n } from '@/i18n';
import InvoicePrintPage from '../InvoicePrintPage.vue';

vi.mock('@/lib/api', () => ({
    api: {
        invoices: {
            pdfUrl: (id: number, locale: string) => `/documents/invoices/${id}.pdf?lang=${locale}`,
            emailPreviewUrl: (id: number, locale: string) => `/documents/invoices/${id}/email?lang=${locale}`,
            get: vi.fn().mockResolvedValue({
                id: 7,
                number: 'INV-01007',
                order_id: 3,
                status: 'paid',
                amount: 57.5,
                issued_at: '2026-10-01',
                due_at: '2026-10-15',
                notes: null,
                customer: { id: 1, name: 'ليلى منصور', email: 'layla@example.com', phone: '555-0101', city: 'عمّان' },
                order: {
                    id: 3,
                    number: 'ORD-10003',
                    ordered_at: '2026-09-30',
                    total: 57.5,
                    items: [
                        { product_name: 'قهوة عربية', quantity: 2, unit_price: 18.5, line_total: 37 },
                        { product_name: 'Notebook', quantity: 1, unit_price: 20.5, line_total: 20.5 },
                    ],
                },
            }),
        },
    },
}));

async function render() {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/invoices', component: { template: '<div />' } },
            { path: '/invoices/:id/print', component: InvoicePrintPage },
        ],
    });
    await router.push('/invoices/7/print');
    const wrapper = mount(InvoicePrintPage, {
        global: { plugins: [i18n, router, [VueQueryPlugin, { queryClient: new QueryClient() }]] },
    });
    await flushPromises();
    return wrapper;
}

describe('invoice print page', () => {
    beforeEach(() => {
        localStorage.clear();
        setActivePinia(createPinia());
    });

    it('renders the invoice in Arabic with Arabic-Indic digits', async () => {
        const wrapper = await render();
        const sheet = wrapper.get('[data-testid=invoice-sheet]');

        expect(sheet.text()).toContain('فاتورة');
        expect(sheet.text()).toContain('INV-01007');
        expect(sheet.text()).toContain('ليلى منصور');
        expect(sheet.findAll('tbody tr')).toHaveLength(2);
        expect(wrapper.get('[data-testid=invoice-total]').text()).toContain('٥٧٫٥٠');
        expect(document.documentElement.dir).toBe('rtl');
        expect(wrapper.get('[data-testid=pdf-button]').attributes('href')).toBe('/documents/invoices/7.pdf?lang=ar');
    });

    it('prints with the browser dialog', async () => {
        const print = vi.spyOn(window, 'print').mockImplementation(() => {});
        const wrapper = await render();

        await wrapper.get('[data-testid=print-button]').trigger('click');

        expect(print).toHaveBeenCalledOnce();
    });
});
