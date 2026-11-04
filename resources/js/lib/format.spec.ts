import { describe, expect, it } from 'vitest';
import { formatDate, formatMoney, formatNumber, formatRelative, intlLocale } from './format';

describe('format helpers', () => {
    it('builds locale tags with the chosen numbering system', () => {
        expect(intlLocale('ar', 'arab')).toBe('ar-EG-u-nu-arab');
        expect(intlLocale('ar', 'latn')).toBe('ar-EG-u-nu-latn');
        expect(intlLocale('en', 'arab')).toBe('en-US');
    });

    it('renders Arabic-Indic digits only when asked', () => {
        expect(formatNumber(1250, intlLocale('ar', 'arab'))).toBe('١٬٢٥٠');
        expect(formatNumber(1250, intlLocale('ar', 'latn'))).toBe('1,250');
    });

    it('formats money with the currency', () => {
        expect(formatMoney(99.5, 'en-US', 'USD')).toBe('$99.50');
        const arabic = formatMoney(99.5, intlLocale('ar', 'arab'), 'USD');
        expect(arabic).toContain('٩٩٫٥٠');
        expect(arabic).toContain('$');
        expect(arabic).not.toContain('US');
    });

    it('treats date-only strings as local dates', () => {
        expect(formatDate('2026-11-01', 'en-US')).toBe('Nov 1, 2026');
        expect(formatDate('2026-11-01', intlLocale('ar', 'latn'))).toContain('نوفمبر');
    });

    it('formats relative times', () => {
        const now = new Date('2026-11-01T12:00:00Z');
        expect(formatRelative('2026-11-01T09:00:00Z', 'en-US', now)).toBe('3 hours ago');
    });
});
