import { beforeEach, describe, expect, it } from 'vitest';
import { nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { useUiStore } from './ui';
import { currentI18nLocale } from '@/i18n';

describe('ui store', () => {
    beforeEach(() => {
        localStorage.clear();
        setActivePinia(createPinia());
    });

    it('defaults to Arabic, right-to-left and Arabic-Indic digits', () => {
        const ui = useUiStore();
        expect(ui.locale).toBe('ar');
        expect(document.documentElement.dir).toBe('rtl');
        expect(document.documentElement.lang).toBe('ar');
        expect(ui.tag).toBe('ar-EG-u-nu-arab');
    });

    it('toggles to English and persists the choice', async () => {
        const ui = useUiStore();
        ui.toggleLocale();
        await nextTick();
        expect(document.documentElement.dir).toBe('ltr');
        expect(currentI18nLocale()).toBe('en');
        expect(localStorage.getItem('locale')).toBe('en');

        setActivePinia(createPinia());
        expect(useUiStore().locale).toBe('en');
    });

    it('persists the digits preference', async () => {
        const ui = useUiStore();
        ui.setDigits('latn');
        await nextTick();
        expect(localStorage.getItem('digits')).toBe('latn');
        expect(ui.tag).toBe('ar-EG-u-nu-latn');
    });

    it('toggles dark mode on the root element and persists it', async () => {
        const ui = useUiStore();
        ui.toggleDark();
        await nextTick();
        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(localStorage.getItem('theme')).toBe('dark');
        ui.toggleDark();
        await nextTick();
        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });
});
