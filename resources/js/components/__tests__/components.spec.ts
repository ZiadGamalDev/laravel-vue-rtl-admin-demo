import { beforeEach, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { i18n } from '@/i18n';
import PaginationBar from '../PaginationBar.vue';
import SortHeader from '../SortHeader.vue';
import StatusBadge from '../StatusBadge.vue';
import QueryState from '../QueryState.vue';

const global = { plugins: [i18n] };

describe('shared components', () => {
    beforeEach(() => {
        localStorage.clear();
        setActivePinia(createPinia());
    });

    it('paginates with localized numbers and disables the first page "previous" button', async () => {
        const wrapper = mount(PaginationBar, {
            props: { meta: { current_page: 1, last_page: 3, total: 25, from: 1, to: 10 } },
            global,
        });
        expect(wrapper.text()).toContain('صفحة ١ من ٣');
        expect(wrapper.text()).toContain('عرض ١–١٠ من ٢٥');
        expect(wrapper.get('[data-testid=page-prev]').attributes('disabled')).toBeDefined();
        await wrapper.get('[data-testid=page-next]').trigger('click');
        expect(wrapper.emitted('page')).toEqual([[2]]);
    });

    it('marks the active sort column for assistive tech', async () => {
        const wrapper = mount(SortHeader, {
            props: { column: 'name', label: 'الاسم', sort: 'name', direction: 'asc' },
            attachTo: document.createElement('tr'),
        });
        expect(wrapper.attributes('aria-sort')).toBe('ascending');
        await wrapper.get('button').trigger('click');
        expect(wrapper.emitted('sort')).toEqual([['name']]);
    });

    it('translates statuses', () => {
        const wrapper = mount(StatusBadge, { props: { kind: 'invoice', status: 'overdue' }, global });
        expect(wrapper.text()).toBe('متأخرة');
    });

    it('shows loading, error and empty states', () => {
        const slots = { default: '<p>rows</p>' };
        expect(mount(QueryState, { props: { loading: true }, slots, global }).find('[data-testid=state-loading]').exists()).toBe(true);
        expect(mount(QueryState, { props: { loading: false, error: new Error('x') }, slots, global }).find('[data-testid=state-error]').exists()).toBe(true);
        expect(mount(QueryState, { props: { loading: false, empty: true }, slots, global }).find('[data-testid=state-empty]').exists()).toBe(true);
        expect(mount(QueryState, { props: { loading: false }, slots, global }).text()).toBe('rows');
    });
});

describe('date range filter', () => {
    function mountFilter(from = '', to = '') {
        return import('../DateRangeFilter.vue').then(({ default: DateRangeFilter }) => {
            const wrapper = mount(DateRangeFilter, {
                props: {
                    from,
                    to,
                    defaultPreset: 'all' as const,
                    'onUpdate:from': (v: string) => wrapper.setProps({ from: v }),
                    'onUpdate:to': (v: string) => wrapper.setProps({ to: v }),
                },
                global,
            });
            return wrapper;
        });
    }

    it('hides the date inputs until a custom range is chosen', async () => {
        const wrapper = await mountFilter();
        expect(wrapper.find('[data-testid=range-from]').exists()).toBe(false);
        await wrapper.get('[data-testid=range-preset]').setValue('custom');
        expect(wrapper.find('[data-testid=range-from]').exists()).toBe(true);
    });

    it('fills a preset relative to today and clears back to the default', async () => {
        const wrapper = await mountFilter();
        await wrapper.get('[data-testid=range-preset]').setValue('7');
        const from = new Date(wrapper.props('from') + 'T00:00:00');
        const to = new Date(wrapper.props('to') + 'T00:00:00');
        expect(Math.round((to.getTime() - from.getTime()) / 86400000)).toBe(6);

        await wrapper.get('[data-testid=range-preset]').setValue('default');
        expect(wrapper.props('from')).toBe('');
        expect(wrapper.props('to')).toBe('');
    });

    it('starts in custom mode when a range is already set', async () => {
        const wrapper = await mountFilter('2026-10-01', '2026-10-15');
        expect((wrapper.get('[data-testid=range-to]').element as HTMLInputElement).min).toBe('2026-10-01');
        await wrapper.get('[data-testid=range-clear]').trigger('click');
        expect(wrapper.props('from')).toBe('');
    });
});
