import { createI18n } from 'vue-i18n';
import ar from './ar';
import en from './en';

export type MessageSchema = typeof ar;
export type AppLocale = 'ar' | 'en';

export const i18n = createI18n({
    legacy: false,
    locale: 'ar' as AppLocale,
    fallbackLocale: 'en' as AppLocale,
    messages: { ar, en },
});

export function setI18nLocale(locale: AppLocale): void {
    i18n.global.locale.value = locale;
}

export function currentI18nLocale(): AppLocale {
    return i18n.global.locale.value as AppLocale;
}
