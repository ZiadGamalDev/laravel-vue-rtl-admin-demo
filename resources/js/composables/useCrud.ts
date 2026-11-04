import { ref } from 'vue';
import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { useI18n } from 'vue-i18n';
import { AxiosError } from 'axios';
import { errorMessage, validationErrors, type ValidationErrors } from '@/lib/http';
import { useToastStore } from '@/stores/toast';

/**
 * Modal + delete-confirm state for a resource page, with save/delete
 * mutations that surface server validation errors and refresh lists.
 */
export function useCrud<T extends { id: number }, Payload>(options: {
    keys: string[];
    create: (payload: Payload) => Promise<T>;
    update: (id: number, payload: Payload) => Promise<T>;
    remove: (id: number) => Promise<void>;
}) {
    const { t } = useI18n();
    const toast = useToastStore();
    const queryClient = useQueryClient();

    const formOpen = ref(false);
    const editing = ref<T | null>(null);
    const errors = ref<ValidationErrors>({});
    const formError = ref<string | null>(null);

    const deleting = ref<T | null>(null);
    const deleteError = ref<string | null>(null);

    const invalidate = () => Promise.all(options.keys.map((key) => queryClient.invalidateQueries({ queryKey: [key] })));

    function openCreate(): void {
        editing.value = null;
        errors.value = {};
        formError.value = null;
        formOpen.value = true;
    }

    function openEdit(row: T): void {
        editing.value = row;
        errors.value = {};
        formError.value = null;
        formOpen.value = true;
    }

    function closeForm(): void {
        formOpen.value = false;
    }

    const save = useMutation({
        mutationFn: (payload: Payload) => (editing.value ? options.update(editing.value.id, payload) : options.create(payload)),
        onMutate: () => {
            errors.value = {};
            formError.value = null;
        },
        onSuccess: async () => {
            formOpen.value = false;
            toast.push(t('common.saved'));
            await invalidate();
        },
        onError: (error) => {
            const fields = validationErrors(error);
            if (fields) {
                errors.value = fields;
                formError.value = t('common.fixErrors');
            } else {
                formError.value = errorMessage(error) ?? t('common.errorBody');
            }
        },
    });

    const destroy = useMutation({
        mutationFn: (row: T) => options.remove(row.id),
        onMutate: () => (deleteError.value = null),
        onSuccess: async () => {
            deleting.value = null;
            toast.push(t('common.deleted'));
            await invalidate();
        },
        onError: (error) => {
            deleteError.value =
                error instanceof AxiosError && error.response?.status === 409 ? errorMessage(error) : t('common.errorBody');
        },
    });

    function askDelete(row: T): void {
        deleteError.value = null;
        deleting.value = row;
    }

    return { formOpen, editing, errors, formError, openCreate, openEdit, closeForm, save, deleting, deleteError, askDelete, destroy };
}
