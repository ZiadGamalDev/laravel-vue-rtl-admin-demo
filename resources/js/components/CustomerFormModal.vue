<script setup lang="ts">
import { reactive, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Customer, CustomerPayload } from '@/lib/api';
import type { ValidationErrors } from '@/lib/http';
import AppModal from './AppModal.vue';
import FormField from './FormField.vue';

const props = defineProps<{ open: boolean; customer: Customer | null; errors: ValidationErrors; formError: string | null; busy: boolean }>();
const emit = defineEmits<{ close: []; submit: [CustomerPayload] }>();
const { t } = useI18n();

const form = reactive<CustomerPayload>({ name: '', email: '', phone: '', city: '', notes: '' });

watch(
    () => [props.open, props.customer] as const,
    ([open, customer]) => {
        if (!open) return;
        form.name = customer?.name ?? '';
        form.email = customer?.email ?? '';
        form.phone = customer?.phone ?? '';
        form.city = customer?.city ?? '';
        form.notes = customer?.notes ?? '';
    },
    { immediate: true },
);

function submit() {
    emit('submit', { ...form, phone: form.phone || null, city: form.city || null, notes: form.notes || null });
}
</script>

<template>
    <AppModal :open="open" :title="customer ? t('customers.editTitle') : t('customers.new')" @close="emit('close')">
        <form novalidate @submit.prevent="submit">
            <div class="space-y-4 p-5">
                <p v-if="formError" class="alert-error" role="alert" data-testid="form-error">
                    {{ formError }}
                </p>
                <FormField v-slot="{ field, invalid }" :label="t('customers.name')" :error="errors.name" required>
                    <input v-bind="field" v-model="form.name" class="input" :class="{ 'input-invalid': invalid }" name="name" dir="auto" />
                </FormField>
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField v-slot="{ field, invalid }" :label="t('customers.email')" :error="errors.email" required>
                        <input v-bind="field" v-model="form.email" type="email" dir="ltr" class="input text-start" :class="{ 'input-invalid': invalid }" name="email" />
                    </FormField>
                    <FormField v-slot="{ field, invalid }" :label="t('customers.phone')" :error="errors.phone">
                        <input v-bind="field" v-model="form.phone" type="tel" dir="ltr" class="input text-start" :class="{ 'input-invalid': invalid }" name="phone" />
                    </FormField>
                </div>
                <FormField v-slot="{ field, invalid }" :label="t('customers.city')" :error="errors.city">
                    <input v-bind="field" v-model="form.city" class="input" :class="{ 'input-invalid': invalid }" name="city" dir="auto" />
                </FormField>
                <FormField v-slot="{ field, invalid }" :label="t('customers.notes')" :error="errors.notes">
                    <textarea v-bind="field" v-model="form.notes" rows="3" class="input" :class="{ 'input-invalid': invalid }" name="notes" dir="auto" />
                </FormField>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                <button type="button" class="btn-secondary" @click="emit('close')">{{ t('common.cancel') }}</button>
                <button type="submit" class="btn-primary" :disabled="busy" data-testid="form-submit">{{ t('common.save') }}</button>
            </div>
        </form>
    </AppModal>
</template>
