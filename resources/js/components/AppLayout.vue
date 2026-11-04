<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { useQueryClient } from '@tanstack/vue-query';
import AppIcon from './AppIcon.vue';
import LocaleSwitcher from './LocaleSwitcher.vue';
import ToastHost from './ToastHost.vue';
import RoleBadge from './RoleBadge.vue';

const { t } = useI18n();
const auth = useAuthStore();
const ui = useUiStore();
const route = useRoute();
const router = useRouter();
const queryClient = useQueryClient();
const menuOpen = ref(false);

const allLinks = [
    { to: '/', name: 'dashboard', label: 'nav.dashboard', icon: 'home' },
    { to: '/customers', name: 'customers', label: 'nav.customers', icon: 'users' },
    { to: '/orders', name: 'orders', label: 'nav.orders', icon: 'cart' },
    { to: '/invoices', name: 'invoices', label: 'nav.invoices', icon: 'invoice' },
    { to: '/errors', name: 'errors', label: 'nav.errors', icon: 'bug', ability: 'errors.view' },
    { to: '/users', name: 'users', label: 'nav.users', icon: 'shield', ability: 'users.manage' },
];
const links = computed(() => allLinks.filter((link) => !link.ability || auth.can(link.ability)));

const title = computed(() => (route.meta.title ? t(route.meta.title) : ''));
const isActive = (name: string) => route.name === name || (name === 'errors' && route.name === 'error-detail');

watch(() => route.fullPath, () => (menuOpen.value = false));

async function logout() {
    await auth.logout();
    queryClient.clear();
    await router.push({ name: 'login' });
}
</script>

<template>
    <div class="min-h-screen bg-slate-50 dark:bg-slate-950">
        <a href="#main" class="sr-only z-50 rounded-lg bg-brand-700 px-4 py-2 font-semibold text-white focus:not-sr-only focus:fixed focus:start-4 focus:top-4">
            {{ t('nav.skip') }}
        </a>
        <!-- Sidebar sits on the inline-start edge: right in Arabic, left in English. -->
        <aside
            class="fixed inset-y-0 start-0 z-30 flex w-64 flex-col border-e border-slate-200 bg-white transition-transform lg:translate-x-0 dark:border-slate-800 dark:bg-slate-900"
            :class="menuOpen ? 'translate-x-0' : ui.isRtl ? 'invisible translate-x-full lg:visible' : 'invisible -translate-x-full lg:visible'"
        >
            <div class="flex h-16 items-center gap-3 border-b border-slate-200 px-5 dark:border-slate-800">
                <div class="grid size-9 place-items-center rounded-lg bg-brand-600 text-lg font-bold text-white">د</div>
                <div class="min-w-0">
                    <p class="truncate font-bold text-slate-900 dark:text-white">{{ t('app.name') }}</p>
                    <p class="truncate text-xs text-slate-500">{{ t('app.demoBadge') }}</p>
                </div>
            </div>
            <nav class="flex-1 space-y-1 p-3">
                <RouterLink
                    v-for="link in links"
                    :key="link.name"
                    :to="link.to"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition"
                    :class="
                        isActive(link.name)
                            ? 'bg-brand-50 text-brand-700 dark:bg-brand-700/20 dark:text-brand-100'
                            : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
                    "
                    :data-testid="`nav-${link.name}`"
                >
                    <AppIcon :name="link.icon" />
                    <span>{{ t(link.label) }}</span>
                </RouterLink>
            </nav>
            <div class="border-t border-slate-200 p-3 dark:border-slate-800">
                <div class="mb-2 px-3">
                    <p class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                        <span class="truncate">{{ auth.user?.name }}</span>
                        <RoleBadge v-if="auth.user" :role="auth.user.role" />
                    </p>
                    <p class="text-xs text-slate-500"><span class="ltr-value">{{ auth.user?.email }}</span></p>
                </div>
                <button
                    type="button"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                    data-testid="logout"
                    @click="logout"
                >
                    <AppIcon name="logout" />
                    <span>{{ t('nav.logout') }}</span>
                </button>
            </div>
        </aside>

        <div v-if="menuOpen" class="fixed inset-0 z-20 bg-slate-900/40 lg:hidden" @click="menuOpen = false" />

        <div class="lg:ps-64">
            <header
                class="sticky top-0 z-10 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6 dark:border-slate-800 dark:bg-slate-900/90"
            >
                <button
                    type="button"
                    class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden dark:text-slate-300"
                    :aria-label="t('nav.menu')"
                    :aria-expanded="menuOpen"
                    @click="menuOpen = true"
                >
                    <AppIcon name="menu" />
                </button>
                <h1 class="truncate text-lg font-bold text-slate-900 dark:text-white">{{ title }}</h1>
                <div class="ms-auto flex items-center gap-2">
                    <slot name="toolbar" />
                    <button
                        type="button"
                        class="rounded-lg border border-slate-200 bg-white p-1.5 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                        :aria-label="t('nav.theme')"
                        :aria-pressed="ui.dark"
                        :title="t('nav.theme')"
                        data-testid="theme-toggle"
                        @click="ui.toggleDark()"
                    >
                        <AppIcon :name="ui.dark ? 'sun' : 'moon'" :size="18" />
                    </button>
                    <LocaleSwitcher />
                </div>
            </header>
            <main id="main" tabindex="-1" class="mx-auto max-w-7xl p-4 outline-none sm:p-6">
                <slot />
            </main>
        </div>
        <ToastHost />
    </div>
</template>
