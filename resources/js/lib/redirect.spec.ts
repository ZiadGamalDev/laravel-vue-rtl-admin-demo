import { describe, expect, it } from 'vitest';
import { safeRedirect } from './redirect';

describe('safeRedirect', () => {
    it('keeps in-app paths', () => {
        expect(safeRedirect('/orders?page=2')).toBe('/orders?page=2');
    });

    it.each([undefined, null, 'orders', 'https://evil.example', '//evil.example', '/\\evil.example', ['/orders']])(
        'falls back for %s',
        (value) => {
            expect(safeRedirect(value)).toBe('/');
        },
    );
});
