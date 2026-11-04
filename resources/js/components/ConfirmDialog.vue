<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import AppModal from './AppModal.vue';

defineProps<{ open: boolean; name: string; busy?: boolean; error?: string | null }>();
const emit = defineEmits<{ confirm: []; cancel: [] }>();
const { t } = useI18n();
</script>

<template>
    <AppModal :open="open" :title="t('common.confirmTitle')" @close="emit('cancel')">
        <div class="space-y-3 p-5">
            <p class="text-slate-700 dark:text-slate-200" data-testid="confirm-body">{{ t('common.confirmBody', { name }) }}</p>
            <p v-if="error" class="alert-error" role="alert">{{ error }}</p>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-800">
            <button type="button" class="btn-secondary" @click="emit('cancel')">{{ t('common.cancel') }}</button>
            <button type="submit" class="btn-danger" :disabled="busy" data-testid="confirm-delete" @click="emit('confirm')">
                {{ t('common.delete') }}
            </button>
        </div>
    </AppModal>
</template>
