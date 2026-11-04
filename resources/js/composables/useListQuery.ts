import { computed, onScopeDispose, reactive, ref, watch, type Ref } from 'vue';
import { keepPreviousData, useQuery } from '@tanstack/vue-query';
import type { ListParams, Paginated } from '@/lib/api';

/**
 * Server-side list state: search (debounced), sort, filters and pagination,
 * wired to TanStack Query. Changing anything but the page resets to page 1.
 */
export function useListQuery<T>(
    key: string,
    fetcher: (params: ListParams) => Promise<Paginated<T>>,
    defaults: { sort: string; direction: 'asc' | 'desc'; per_page?: number },
) {
    const search = ref('');
    const params = reactive<Required<Pick<ListParams, 'page' | 'per_page' | 'sort' | 'direction'>> & ListParams>({
        page: 1,
        per_page: defaults.per_page ?? 10,
        sort: defaults.sort,
        direction: defaults.direction,
        search: '',
        status: '',
        from: '',
        to: '',
    });

    let timer: ReturnType<typeof setTimeout> | undefined;
    watch(search, (value) => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            params.search = value.trim();
            params.page = 1;
        }, 300);
    });

    onScopeDispose(() => clearTimeout(timer));

    watch(
        () => [params.status, params.from, params.to, params.per_page],
        () => (params.page = 1),
    );

    const query = useQuery({
        queryKey: computed(() => [key, { ...params }]),
        queryFn: () => {
            const request = { ...params };
            // An inverted range would only produce a validation error; ignore its end until it is fixed.
            if (request.from && request.to && request.from > request.to) request.to = '';
            return fetcher(request);
        },
        placeholderData: keepPreviousData,
    });

    // Deleting the last row of the last page leaves the user on a page that no longer
    // exists (the API answers with an empty page); step back to the real last page.
    watch(
        () => query.data.value?.meta,
        (meta) => {
            if (meta && meta.total > 0 && meta.current_page > meta.last_page) params.page = meta.last_page;
        },
    );

    function toggleSort(column: string): void {
        if (params.sort === column) {
            params.direction = params.direction === 'asc' ? 'desc' : 'asc';
        } else {
            params.sort = column;
            params.direction = 'asc';
        }
        params.page = 1;
    }

    const rows = computed(() => query.data.value?.data ?? []) as Ref<T[]>;
    const meta = computed(() => query.data.value?.meta);
    const isFiltered = computed(() => !!(params.search || params.status || params.from || params.to));

    return { search, params, query, rows, meta, isFiltered, toggleSort };
}
