<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { api, type ErrorEvent } from '@/lib/api';
import { appConfig } from '@/lib/config';
import { useListQuery } from '@/composables/useListQuery';
import { useFormat } from '@/composables/useFormat';
import { useToastStore } from '@/stores/toast';
import AppIcon from '@/components/AppIcon.vue';
import PageHeader from '@/components/PageHeader.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import QueryState from '@/components/QueryState.vue';
import SearchInput from '@/components/SearchInput.vue';
import SortHeader from '@/components/SortHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';

const { t } = useI18n();
const fmt = useFormat();
const router = useRouter();
const toast = useToastStore();
const queryClient = useQueryClient();
const canTrigger = appConfig.env === 'local';

const { search, params, query, rows, meta, isFiltered, toggleSort } = useListQuery<ErrorEvent>('error-events', api.errors.list, {
    sort: 'last_seen_at',
    direction: 'desc',
});
params.status = 'unresolved';

const tabs = [
    { value: 'unresolved', label: 'errors.unresolved' },
    { value: 'resolved', label: 'errors.resolved' },
    { value: '', label: 'common.all' },
];

const resolve = useMutation({
    mutationFn: (event: ErrorEvent) => api.errors.resolve(event.id, !event.resolved_at),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['error-events'] }),
});

// The trigger endpoint fails with a 500 on purpose: that is the error being recorded.
const trigger = useMutation({
    mutationFn: () => api.errors.trigger().catch(() => undefined),
    onSuccess: async () => {
        toast.push(t('errors.triggered'));
        await queryClient.invalidateQueries({ queryKey: ['error-events'] });
    },
});

function open(event: ErrorEvent) {
    router.push({ name: 'error-detail', params: { id: event.id } });
}
</script>

<template>
    <div>
        <PageHeader :subtitle="t('errors.subtitle')">
            <SearchInput v-model="search" />
            <button v-if="canTrigger" type="button" class="btn-danger" :disabled="trigger.isPending.value" data-testid="trigger-error" @click="trigger.mutate()">
                <AppIcon name="bug" :size="18" />
                {{ t('errors.trigger') }}
            </button>
        </PageHeader>

        <div class="mb-4 inline-flex rounded-lg border border-slate-200 bg-white p-1 dark:border-slate-700 dark:bg-slate-900" role="tablist">
            <button
                v-for="tab in tabs"
                :key="tab.value"
                type="button"
                role="tab"
                class="rounded-md px-4 py-1.5 text-sm font-semibold transition"
                :class="params.status === tab.value ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
                :aria-selected="params.status === tab.value"
                :data-testid="`tab-${tab.value || 'all'}`"
                @click="params.status = tab.value"
            >
                {{ t(tab.label) }}
            </button>
        </div>

        <div class="card overflow-hidden">
            <QueryState
                :loading="query.isPending.value"
                :error="query.error.value"
                :empty="rows.length === 0"
                :empty-text="isFiltered && search ? t('common.emptySearch') : undefined"
                @retry="query.refetch()"
            >
                <div class="overflow-x-auto" :class="{ 'opacity-60': query.isPlaceholderData.value }">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="w-1/2">{{ t('errors.message') }}</th>
                                <SortHeader column="count" :label="t('errors.count')" :sort="params.sort" :direction="params.direction" @sort="toggleSort" />
                                <SortHeader column="first_seen_at" :label="t('errors.firstSeen')" :sort="params.sort" :direction="params.direction" @sort="toggleSort" />
                                <SortHeader column="last_seen_at" :label="t('errors.lastSeen')" :sort="params.sort" :direction="params.direction" @sort="toggleSort" />
                                <th>{{ t('errors.status') }}</th>
                                <th class="text-end">{{ t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="event in rows" :key="event.id" class="cursor-pointer" data-testid="row" @click="open(event)">
                                <td class="max-w-0">
                                    <RouterLink
                                        :to="{ name: 'error-detail', params: { id: event.id } }"
                                        class="align-page-start block truncate font-semibold text-slate-900 hover:underline focus-visible:underline dark:text-white"
                                        dir="auto"
                                        :title="event.message"
                                        data-testid="error-link"
                                        @click.stop
                                    >
                                        {{ event.message }}
                                    </RouterLink>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        <span class="ltr-value">
                                            <span class="font-semibold text-rose-600">{{ event.exception_class.split('\\').pop() }}</span>
                                            · {{ event.file }}:{{ event.line }}
                                        </span>
                                    </p>
                                </td>
                                <td>
                                    <span class="inline-flex min-w-8 justify-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                        {{ fmt.number(event.count) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap" :title="fmt.dateTime(event.first_seen_at)">{{ fmt.relative(event.first_seen_at) }}</td>
                                <td class="whitespace-nowrap" :title="fmt.dateTime(event.last_seen_at)">{{ fmt.relative(event.last_seen_at) }}</td>
                                <td><StatusBadge kind="error" :status="event.resolved_at ? 'resolved' : 'unresolved'" /></td>
                                <td class="text-end whitespace-nowrap">
                                    <button
                                        type="button"
                                        class="btn-secondary px-2.5 py-1 text-xs"
                                        :data-testid="event.resolved_at ? 'reopen' : 'resolve'"
                                        @click.stop="resolve.mutate(event)"
                                    >
                                        <AppIcon :name="event.resolved_at ? 'refresh' : 'check'" :size="14" />
                                        {{ event.resolved_at ? t('errors.reopen') : t('errors.resolve') }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <PaginationBar v-if="meta" :meta="meta" @page="params.page = $event" />
            </QueryState>
        </div>
    </div>
</template>
