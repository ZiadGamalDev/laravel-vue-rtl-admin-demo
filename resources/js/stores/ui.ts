import { defineStore } from 'pinia';
import { computed, ref, watch } from 'vue';
import { setI18nLocale } from '@/i18n';
import { setRequestLocale } from '@/lib/http';
import { intlLocale, type Digits, type Locale } from '@/lib/format';

function read(key: string): string | null {
    try {
        return localStorage.getItem(key);
    } catch {
        return null;
    }
}

function write(key: string, value: string): void {
    try {
        localStorage.setItem(key, value);
    } catch {
        /* storage may be unavailable (private mode); the setting just won't persist */
    }
}

export const useUiStore = defineStore('ui', () => {
    const locale = ref<Locale>(read('locale') === 'en' ? 'en' : 'ar');
    const digits = ref<Digits>(read('digits') === 'latn' ? 'latn' : 'arab');
    const dark = ref<boolean>(read('theme') === 'dark');

    const dir = computed(() => (locale.value === 'ar' ? 'rtl' : 'ltr'));
    const isRtl = computed(() => dir.value === 'rtl');
    const tag = computed(() => intlLocale(locale.value, digits.value));

    function apply(): void {
        setI18nLocale(locale.value);
        setRequestLocale(locale.value);
        document.documentElement.lang = locale.value;
        document.documentElement.dir = dir.value;
        document.documentElement.classList.toggle('dark', dark.value);
    }

    watch(locale, (value) => write('locale', value));
    watch(digits, (value) => write('digits', value));
    watch(dark, (value) => write('theme', value ? 'dark' : 'light'));
    watch([locale, dark], apply, { immediate: true });

    function toggleLocale(): void {
        locale.value = locale.value === 'ar' ? 'en' : 'ar';
    }

    function setDigits(value: Digits): void {
        digits.value = value;
    }

    function toggleDark(): void {
        dark.value = !dark.value;
    }

    return { locale, digits, dark, dir, isRtl, tag, toggleLocale, setDigits, toggleDark };
});
