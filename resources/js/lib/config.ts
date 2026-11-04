export interface AppConfig {
    name: string;
    env: string;
    currency: string;
    demo: { password: string; accounts: { email: string; role: 'admin' | 'staff' | 'viewer' }[] };
}

declare global {
    interface Window {
        __APP__?: Partial<AppConfig>;
    }
}

export const appConfig: AppConfig = {
    name: 'Admin',
    env: 'production',
    currency: 'USD',
    demo: {
        password: 'password',
        accounts: [
            { email: 'demo@example.com', role: 'admin' },
            { email: 'staff@example.com', role: 'staff' },
            { email: 'viewer@example.com', role: 'viewer' },
        ],
    },
    ...(typeof window !== 'undefined' ? window.__APP__ : {}),
};
