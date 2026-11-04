import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useAuthStore } from './auth';

describe('auth store abilities', () => {
    beforeEach(() => setActivePinia(createPinia()));

    it('denies everything when signed out', () => {
        expect(useAuthStore().can('orders.create')).toBe(false);
    });

    it('reads abilities from the signed-in user and treats unknown ones as denied', () => {
        const auth = useAuthStore();
        auth.user = { id: 1, name: 'x', email: 'x@example.com', role: 'staff', can: { 'orders.create': true, 'orders.delete': false } };

        expect(auth.can('orders.create')).toBe(true);
        expect(auth.can('orders.delete')).toBe(false);
        expect(auth.can('users.manage')).toBe(false);
    });
});
