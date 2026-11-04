import axios, { AxiosError } from 'axios';

export const http = axios.create({
    baseURL: '/',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

let currentLocale = 'ar';

/** The server localizes validation messages from this header. */
export function setRequestLocale(locale: string): void {
    currentLocale = locale;
}

http.interceptors.request.use((config) => {
    config.headers.set('X-Locale', currentLocale);
    return config;
});

let unauthorizedHandler: (() => void) | null = null;

export function onUnauthorized(handler: () => void): void {
    unauthorizedHandler = handler;
}

http.interceptors.response.use(
    (response) => response,
    (error: AxiosError) => {
        if (error.response?.status === 401 && unauthorizedHandler) {
            unauthorizedHandler();
        }
        return Promise.reject(error);
    },
);

export type ValidationErrors = Record<string, string[]>;

export function validationErrors(error: unknown): ValidationErrors | null {
    if (error instanceof AxiosError && error.response?.status === 422) {
        return (error.response.data as { errors?: ValidationErrors }).errors ?? {};
    }
    return null;
}

export function errorMessage(error: unknown): string | null {
    if (error instanceof AxiosError) {
        return (error.response?.data as { message?: string } | undefined)?.message ?? error.message;
    }
    return error instanceof Error ? error.message : null;
}

export async function csrf(): Promise<void> {
    await http.get('/sanctum/csrf-cookie');
}
