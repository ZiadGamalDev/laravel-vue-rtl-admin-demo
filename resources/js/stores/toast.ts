import { defineStore } from 'pinia';
import { ref } from 'vue';

export interface Toast {
    id: number;
    message: string;
    tone: 'success' | 'error';
}

export const useToastStore = defineStore('toast', () => {
    const toasts = ref<Toast[]>([]);
    let next = 1;

    function push(message: string, tone: Toast['tone'] = 'success', timeout = 3500): void {
        const id = next++;
        toasts.value.push({ id, message, tone });
        setTimeout(() => dismiss(id), timeout);
    }

    function dismiss(id: number): void {
        toasts.value = toasts.value.filter((t) => t.id !== id);
    }

    return { toasts, push, dismiss };
});
