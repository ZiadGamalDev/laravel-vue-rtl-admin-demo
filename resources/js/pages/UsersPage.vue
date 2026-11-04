<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { api } from '@/lib/api';
import { errorMessage } from '@/lib/http';
import { useAuthStore, type Role } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';
import { useFormat } from '@/composables/useFormat';
import AppIcon from '@/components/AppIcon.vue';
import PageHeader from '@/components/PageHeader.vue';
import QueryState from '@/components/QueryState.vue';
import RoleBadge from '@/components/RoleBadge.vue';

const { t } = useI18n();
const fmt = useFormat();
const auth = useAuthStore();
const toast = useToastStore();
const queryClient = useQueryClient();

const roles: Role[] = ['admin', 'staff', 'viewer'];

// Mirrors app/Policies; shown so a reviewer can see the rules at a glance.
const matrix: { key: string; roles: Role[] }[] = [
    { key: 'read', roles: ['admin', 'staff', 'viewer'] },
    { key: 'write', roles: ['admin', 'staff'] },
    { key: 'email', roles: ['admin', 'staff'] },
    { key: 'delete', roles: ['admin'] },
    { key: 'errors', roles: ['admin'] },
    { key: 'users', roles: ['admin'] },
];

const query = useQuery({ queryKey: ['users'], queryFn: api.users.list });

const change = useMutation({
    mutationFn: ({ id, role }: { id: number; role: Role }) => api.users.updateRole(id, role),
    onSuccess: async () => {
        toast.push(t('users.roleChanged'));
        await queryClient.invalidateQueries({ queryKey: ['users'] });
    },
    onError: async (error) => {
        toast.push(errorMessage(error) ?? t('common.errorBody'), 'error');
        await queryClient.invalidateQueries({ queryKey: ['users'] });
    },
});

function onRoleChange(id: number, event: Event) {
    change.mutate({ id, role: (event.target as HTMLSelectElement).value as Role });
}
</script>

<template>
    <div class="space-y-6">
        <PageHeader :subtitle="t('users.subtitle')" />

        <div class="card overflow-hidden">
            <QueryState :loading="query.isPending.value" :error="query.error.value" :empty="query.data.value?.length === 0" @retry="query.refetch()">
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>{{ t('users.name') }}</th>
                                <th>{{ t('users.email') }}</th>
                                <th>{{ t('users.role') }}</th>
                                <th>{{ t('users.since') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in query.data.value" :key="user.id" data-testid="row">
                                <td class="font-semibold text-slate-900 dark:text-white">
                                    {{ user.name }}
                                    <span v-if="user.id === auth.user?.id" class="ms-1 text-xs font-normal text-slate-500">({{ t('users.you') }})</span>
                                </td>
                                <td><span class="ltr-value">{{ user.email }}</span></td>
                                <td>
                                    <RoleBadge v-if="user.id === auth.user?.id" :role="user.role" />
                                    <select
                                        v-else
                                        :value="user.role"
                                        class="input w-36"
                                        :aria-label="`${t('users.role')}: ${user.name}`"
                                        :disabled="change.isPending.value"
                                        data-testid="role-select"
                                        @change="onRoleChange(user.id, $event)"
                                    >
                                        <option v-for="role in roles" :key="role" :value="role">{{ t(`roles.${role}`) }}</option>
                                    </select>
                                </td>
                                <td class="whitespace-nowrap">{{ fmt.date(user.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </QueryState>
        </div>

        <section class="card overflow-hidden" data-testid="role-matrix">
            <h2 class="border-b border-slate-200 px-5 py-4 font-bold text-slate-900 dark:border-slate-800 dark:text-white">{{ t('users.matrixTitle') }}</h2>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ t('users.permission') }}</th>
                            <th v-for="role in roles" :key="role" class="text-center"><RoleBadge :role="role" /></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in matrix" :key="row.key">
                            <td>{{ t(`users.perms.${row.key}`) }}</td>
                            <td v-for="role in roles" :key="role" class="text-center">
                                <span v-if="row.roles.includes(role)" class="inline-flex text-emerald-700 dark:text-emerald-400" :title="t('users.allowed')">
                                    <AppIcon name="check" :size="18" /><span class="sr-only">{{ t('users.allowed') }}</span>
                                </span>
                                <span v-else class="inline-flex text-slate-500" :title="t('users.denied')">
                                    <AppIcon name="close" :size="16" /><span class="sr-only">{{ t('users.denied') }}</span>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
