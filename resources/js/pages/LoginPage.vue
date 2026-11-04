<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { AxiosError } from 'axios';
import { useAuthStore } from '@/stores/auth';
import { safeRedirect } from '@/lib/redirect';
import { appConfig } from '@/lib/config';
import { errorMessage, validationErrors, type ValidationErrors } from '@/lib/http';
import FormField from '@/components/FormField.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import AppIcon from '@/components/AppIcon.vue';

const { t } = useI18n();
const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const form = reactive({ email: '', password: '', remember: false });
const errors = ref<ValidationErrors>({});
const banner = ref<string | null>(null);
const busy = ref(false);

function signInAs(email: string) {
    form.email = email;
    form.password = appConfig.demo.password;
    submit();
}

async function submit() {
    busy.value = true;
    errors.value = {};
    banner.value = null;
    try {
        await auth.login(form);
        await router.push(safeRedirect(route.query.redirect));
    } catch (error) {
        const fields = validationErrors(error);
        if (fields) {
            errors.value = fields;
        } else if (error instanceof AxiosError && error.response?.status === 429) {
            banner.value = errorMessage(error);
        } else {
            banner.value = t('common.errorBody');
        }
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-100 dark:bg-slate-950">
        <div class="flex justify-end p-4">
            <LocaleSwitcher :show-digits="false" />
        </div>
        <div class="flex flex-1 items-center justify-center px-4 pb-16">
            <div class="grid w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-slate-200 md:grid-cols-2 dark:bg-slate-900 dark:ring-slate-800">
                <div class="flex flex-col justify-between bg-brand-700 p-8 text-white">
                    <div>
                        <div class="mb-6 grid size-12 place-items-center rounded-xl bg-white/15 text-2xl font-bold">د</div>
                        <h2 class="text-2xl font-bold">{{ t('app.name') }}</h2>
                        <p class="mt-2 text-brand-100">{{ t('app.tagline') }}</p>
                    </div>
                    <div class="mt-10 rounded-xl bg-white/10 p-4 ring-1 ring-white/20" data-testid="demo-credentials">
                        <p class="flex items-center gap-2 font-bold">
                            <AppIcon name="alert" :size="18" />
                            {{ t('auth.demoTitle') }}
                        </p>
                        <p class="mt-1 text-sm text-brand-100">{{ t('auth.demoHint') }}</p>
                        <p class="mt-3 text-sm font-semibold">{{ t('auth.demoAccounts') }}</p>
                        <ul class="mt-2 space-y-2 text-sm">
                            <li v-for="account in appConfig.demo.accounts" :key="account.email" class="flex items-center justify-between gap-3 rounded-lg bg-white/10 px-3 py-2">
                                <span class="min-w-0">
                                    <span class="block font-bold">{{ t(`roles.${account.role}`) }}</span>
                                    <span class="ltr-value text-brand-100">{{ account.email }}</span>
                                </span>
                                <button
                                    type="button"
                                    class="shrink-0 rounded-lg bg-white px-3 py-1.5 text-sm font-semibold text-brand-700 hover:bg-brand-50 disabled:opacity-60"
                                    :disabled="busy"
                                    :data-testid="`login-as-${account.role}`"
                                    @click="signInAs(account.email)"
                                >
                                    {{ t('auth.signInAs') }}
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>

                <form class="space-y-5 p-8" novalidate @submit.prevent="submit">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ t('auth.title') }}</h1>
                        <p class="mt-1 text-sm text-slate-500">{{ t('auth.subtitle') }}</p>
                    </div>

                    <div v-if="banner" class="alert-error" role="alert" data-testid="login-banner">
                        {{ banner }}
                    </div>

                    <FormField v-slot="{ field, invalid }" :label="t('auth.email')" :error="errors.email">
                        <input
                            v-bind="field"
                            v-model="form.email"
                            type="email"
                            dir="ltr"
                            autocomplete="username"
                            class="input text-start"
                            :class="{ 'input-invalid': invalid }"
                            name="email"
                        />
                    </FormField>

                    <FormField v-slot="{ field, invalid }" :label="t('auth.password')" :error="errors.password">
                        <input
                            v-bind="field"
                            v-model="form.password"
                            type="password"
                            dir="ltr"
                            autocomplete="current-password"
                            class="input text-start"
                            :class="{ 'input-invalid': invalid }"
                            name="password"
                        />
                    </FormField>

                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input v-model="form.remember" type="checkbox" class="size-4 rounded border-slate-300 text-brand-600" />
                        {{ t('auth.remember') }}
                    </label>

                    <button type="submit" class="btn-primary w-full justify-center" :disabled="busy" data-testid="login-submit">
                        {{ busy ? t('common.loading') : t('auth.submit') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
