<script setup lang="ts">
import { computed, useId } from 'vue';

const props = defineProps<{ label: string; error?: string | string[] | null; required?: boolean }>();

const id = useId();
const message = computed(() => (Array.isArray(props.error) ? props.error[0] : props.error) || null);

// Spread onto the control: links the label, marks it invalid and points to the error text
// so screen readers announce the message with the field.
const field = computed(() => ({
    id,
    'aria-invalid': message.value ? true : undefined,
    'aria-describedby': message.value ? `${id}-error` : undefined,
    'aria-required': props.required ? true : undefined,
}));
</script>

<template>
    <div>
        <label :for="id" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">
            {{ label }}<span v-if="required" class="ms-0.5 text-rose-600" aria-hidden="true">*</span>
        </label>
        <slot :field="field" :invalid="!!message" />
        <p v-if="message" :id="`${id}-error`" class="mt-1.5 text-sm text-rose-600 dark:text-rose-400" role="alert" data-testid="field-error">
            {{ message }}
        </p>
    </div>
</template>
