import { describe, expect, it } from 'vitest';
import ar from './ar';
import en from './en';

function keys(obj: Record<string, unknown>, prefix = ''): string[] {
    return Object.entries(obj).flatMap(([key, value]) =>
        value && typeof value === 'object'
            ? keys(value as Record<string, unknown>, `${prefix}${key}.`)
            : [`${prefix}${key}`],
    );
}

describe('translations', () => {
    it('have the same keys in Arabic and English', () => {
        expect(keys(en).sort()).toEqual(keys(ar).sort());
    });

    it('have no empty strings', () => {
        const values = [ar, en].flatMap((m) => keys(m).map((k) => k.split('.').reduce<any>((o, p) => o[p], m)));
        expect(values.every((v) => typeof v === 'string' && v.trim().length > 0)).toBe(true);
    });
});
