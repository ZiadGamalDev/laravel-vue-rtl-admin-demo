import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

declare module 'vue-router' {
    interface RouteMeta {
        guest?: boolean;
        bare?: boolean;
        title?: string;
        ability?: string;
    }
}

export const routes: RouteRecordRaw[] = [
    { path: '/login', name: 'login', component: () => import('@/pages/LoginPage.vue'), meta: { guest: true, bare: true } },
    { path: '/', name: 'dashboard', component: () => import('@/pages/DashboardPage.vue'), meta: { title: 'nav.dashboard' } },
    { path: '/customers', name: 'customers', component: () => import('@/pages/CustomersPage.vue'), meta: { title: 'nav.customers' } },
    { path: '/orders', name: 'orders', component: () => import('@/pages/OrdersPage.vue'), meta: { title: 'nav.orders' } },
    { path: '/invoices', name: 'invoices', component: () => import('@/pages/InvoicesPage.vue'), meta: { title: 'nav.invoices' } },
    { path: '/invoices/:id/print', name: 'invoice-print', component: () => import('@/pages/InvoicePrintPage.vue'), meta: { bare: true } },
    { path: '/errors', name: 'errors', component: () => import('@/pages/ErrorsPage.vue'), meta: { title: 'nav.errors', ability: 'errors.view' } },
    { path: '/errors/:id', name: 'error-detail', component: () => import('@/pages/ErrorDetailPage.vue'), meta: { title: 'nav.errors', ability: 'errors.view' } },
    { path: '/users', name: 'users', component: () => import('@/pages/UsersPage.vue'), meta: { title: 'nav.users', ability: 'users.manage' } },
    { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/pages/NotFoundPage.vue') },
];

export const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();
    await auth.ensureLoaded();

    if (to.meta.guest) {
        return auth.user ? { name: 'dashboard' } : true;
    }
    if (!auth.user) {
        return { name: 'login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : {} };
    }
    // Pages outside the user's role fall back to the dashboard (the API would answer 403 anyway).
    if (to.meta.ability && !auth.can(to.meta.ability)) {
        return { name: 'dashboard' };
    }
    return true;
});
