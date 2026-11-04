<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { api } from '@/lib/api';
import { useFormat } from '@/composables/useFormat';
import AppIcon from '@/components/AppIcon.vue';
import QueryState from '@/components/QueryState.vue';
import StatusBadge from '@/components/StatusBadge.vue';

const { t } = useI18n();
const fmt = useFormat();
const route = useRoute();
const queryClient = useQueryClient();
const id = computed(() => String(route.params.id));

const query = useQuery({
    queryKey: computed(() => ['error-events', 'detail', id.value]),
    queryFn: () => api.errors.get(id.value),
});
const event = computed(() => query.data.value);

const toggle = useMutation({
    mutationFn: () => api.errors.resolve(event.value!.id, !event.value!.resolved_at),
    onSuccess: async (updated) => {
        queryClient.setQueryData(['error-events', 'detail', id.value], updated);
        await queryClient.invalidateQueries({ queryKey: ['error-events'] });
    },
});

const requestLine = computed(() => {
    if (!event.value?.url) return '—';
    return `${event.value.method ?? ''} ${event.value.url}`.trim();
});

/** Frames from the app itself are highlighted; vendor frames are dimmed. */
const isAppFrame = (file: string | null) => !!file && !file.startsWith('vendor/');
</script>

<template>
    <div>
        <RouterLink to="/errors" class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:underline dark:text-brand-100">
            <AppIcon name="arrow" :size="16" class="rotate-180" />
            {{ t('errors.back') }}
        </RouterLink>

        <QueryState :loading="query.isPending.value" :error="query.error.value" @retry="query.refetch()">
            <div v-if="event" class="space-y-5">
                <section class="card p-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-rose-600"><span class="ltr-value">{{ event.exception_class }}</span></p>
                            <h2 class="mt-1 text-lg font-bold break-words text-slate-900 dark:text-white" data-testid="error-message"><bdi>{{ event.message }}</bdi></h2>
                            <p class="mt-1 text-sm text-slate-500"><span class="ltr-value">{{ event.file }}:{{ event.line }}</span></p>
                        </div>
                        <div class="flex items-center gap-3">
                            <StatusBadge kind="error" :status="event.resolved_at ? 'resolved' : 'unresolved'" />
                            <button type="button" class="btn-primary" :disabled="toggle.isPending.value" data-testid="toggle-resolved" @click="toggle.mutate()">
                                <AppIcon :name="event.resolved_at ? 'refresh' : 'check'" :size="18" />
                                {{ event.resolved_at ? t('errors.reopen') : t('errors.resolve') }}
                            </button>
                        </div>
                    </div>
                    <dl class="mt-5 grid gap-4 border-t border-slate-100 pt-4 text-sm sm:grid-cols-2 lg:grid-cols-4 dark:border-slate-800">
                        <div>
                            <dt class="text-slate-500">{{ t('errors.count') }}</dt>
                            <dd class="font-semibold">{{ t('errors.occurrences', { count: fmt.number(event.count) }) }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">{{ t('errors.firstSeen') }}</dt>
                            <dd class="font-semibold">{{ fmt.dateTime(event.first_seen_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">{{ t('errors.lastSeen') }}</dt>
                            <dd class="font-semibold">{{ fmt.dateTime(event.last_seen_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">{{ t('errors.request') }}</dt>
                            <dd class="font-semibold"><span class="ltr-value">{{ requestLine }}</span></dd>
                        </div>
                        <div class="sm:col-span-2 lg:col-span-4">
                            <dt class="text-slate-500">{{ t('errors.fingerprint') }}</dt>
                            <dd class="font-mono text-xs"><span class="ltr-value">{{ event.fingerprint }}</span></dd>
                        </div>
                    </dl>
                </section>

                <section class="card overflow-hidden">
                    <h3 class="border-b border-slate-200 px-5 py-3 font-bold text-slate-900 dark:border-slate-800 dark:text-white">{{ t('errors.stackTrace') }}</h3>
                    <!-- Code is always left-to-right, even in the Arabic UI. -->
                    <ol dir="ltr" class="divide-y divide-slate-100 font-mono text-xs dark:divide-slate-800" data-testid="stack-trace">
                        <li v-for="(frame, index) in event.trace" :key="index" class="flex gap-3 px-5 py-2.5" :class="isAppFrame(frame.file) ? 'bg-amber-50/60 dark:bg-amber-500/5' : 'text-slate-500'">
                            <span class="w-6 shrink-0 text-end text-slate-500">{{ index }}</span>
                            <div class="min-w-0">
                                <p class="break-all font-semibold text-slate-800 dark:text-slate-200">{{ frame.function }}</p>
                                <p v-if="frame.file" class="break-all">{{ frame.file }}:{{ frame.line }}</p>
                            </div>
                        </li>
                    </ol>
                </section>
            </div>
        </QueryState>
    </div>
</template>
