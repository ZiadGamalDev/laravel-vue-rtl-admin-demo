import { defineStore } from 'pinia';
import { ref } from 'vue';
import { csrf, http } from '@/lib/http';

export type Role = 'admin' | 'staff' | 'viewer';

export interface User {
    id: number;
    name: string;
    email: string;
    role: Role;
    can?: Record<string, boolean>;
}

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null);
    const loaded = ref(false);

    async function fetchUser(): Promise<User | null> {
        try {
            const { data } = await http.get<{ data: User }>('/api/user');
            user.value = data.data;
        } catch {
            user.value = null;
        }
        loaded.value = true;
        return user.value;
    }

    async function ensureLoaded(): Promise<void> {
        if (!loaded.value) await fetchUser();
    }

    async function login(credentials: { email: string; password: string; remember: boolean }): Promise<void> {
        await csrf();
        const { data } = await http.post<{ data: User }>('/api/login', credentials);
        user.value = data.data;
        loaded.value = true;
    }

    async function logout(): Promise<void> {
        try {
            await http.post('/api/logout');
        } finally {
            user.value = null;
        }
    }

    function clear(): void {
        user.value = null;
    }

    /** UI hint only: the API enforces the same rules with policies. */
    function can(ability: string): boolean {
        return user.value?.can?.[ability] === true;
    }

    return { user, loaded, fetchUser, ensureLoaded, login, logout, clear, can };
});
