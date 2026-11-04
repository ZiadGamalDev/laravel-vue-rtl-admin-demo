import { describe, expect, it } from 'vitest';
import { defineComponent, h, nextTick, ref } from 'vue';
import { mount } from '@vue/test-utils';
import { i18n } from '@/i18n';
import FormField from '../FormField.vue';
import AppModal from '../AppModal.vue';

describe('FormField', () => {
    it('links the control to its label and error message', () => {
        const wrapper = mount(FormField, {
            props: { label: 'البريد', error: ['حقل البريد مطلوب.'], required: true },
            slots: { default: `<template #default="{ field }"><input v-bind="field" /></template>` },
        });
        const input = wrapper.get('input');
        const label = wrapper.get('label');
        const error = wrapper.get('[data-testid=field-error]');

        expect(label.attributes('for')).toBe(input.attributes('id'));
        expect(input.attributes('aria-invalid')).toBe('true');
        expect(input.attributes('aria-required')).toBe('true');
        expect(input.attributes('aria-describedby')).toBe(error.attributes('id'));
    });

    it('drops the error wiring when there is no error', () => {
        const wrapper = mount(FormField, {
            props: { label: 'الاسم' },
            slots: { default: `<template #default="{ field }"><input v-bind="field" /></template>` },
        });
        expect(wrapper.get('input').attributes('aria-invalid')).toBeUndefined();
        expect(wrapper.get('input').attributes('aria-describedby')).toBeUndefined();
    });
});

describe('AppModal', () => {
    const Host = defineComponent({
        setup() {
            const open = ref(false);
            return () =>
                h('div', [
                    h('button', { id: 'opener', onClick: () => (open.value = true) }, 'open'),
                    h(AppModal, { open: open.value, title: 'عنوان', onClose: () => (open.value = false) }, () => [
                        h('input', { id: 'first' }),
                        h('button', { id: 'last', type: 'submit' }, 'save'),
                    ]),
                ]);
        },
    });

    it('is labelled by its title, traps Tab and returns focus to the opener on Escape', async () => {
        const wrapper = mount(Host, { attachTo: document.body, global: { plugins: [i18n] } });
        const opener = wrapper.get('#opener').element as HTMLButtonElement;
        opener.focus();
        await wrapper.get('#opener').trigger('click');
        await nextTick();
        await nextTick();

        const dialog = document.querySelector('[role=dialog]')!;
        expect(document.getElementById(dialog.getAttribute('aria-labelledby')!)?.textContent).toBe('عنوان');
        expect(document.activeElement?.id).toBe('first');

        (document.getElementById('last') as HTMLElement).focus();
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', cancelable: true }));
        // Tab from the last control wraps to the first focusable (the close button).
        expect(dialog.contains(document.activeElement)).toBe(true);
        expect(document.activeElement?.id).not.toBe('last');

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await nextTick();
        await nextTick();
        expect(document.querySelector('[role=dialog]')).toBeNull();
        expect(document.activeElement).toBe(opener);
        wrapper.unmount();
    });
});
