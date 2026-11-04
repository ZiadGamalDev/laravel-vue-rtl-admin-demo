export type Locale = 'ar' | 'en';
export type Digits = 'arab' | 'latn';

/** BCP 47 tag used for every Intl formatter in the app. */
export function intlLocale(locale: Locale, digits: Digits): string {
    return locale === 'ar' ? `ar-EG-u-nu-${digits}` : 'en-US';
}

export function formatNumber(value: number, tag: string, options: Intl.NumberFormatOptions = {}): string {
    return new Intl.NumberFormat(tag, options).format(value);
}

export function formatMoney(value: number, tag: string, currency: string): string {
    const parts = new Intl.NumberFormat(tag, {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
        minimumFractionDigits: 2,
    }).formatToParts(value);
    // Arabic locales print "US$", which the bidi algorithm shows as "$US" next to
    // Arabic digits. Use the plain narrow symbol ("$") everywhere instead.
    const symbol = narrowSymbol(currency);
    return parts.map((part) => (part.type === 'currency' ? symbol : part.value)).join('');
}

function narrowSymbol(currency: string): string {
    const part = new Intl.NumberFormat('en-US', { style: 'currency', currency, currencyDisplay: 'narrowSymbol' })
        .formatToParts(0)
        .find((p) => p.type === 'currency');
    return part?.value ?? currency;
}

export function formatDate(value: string | Date | null | undefined, tag: string, withTime = false): string {
    if (!value) return '';
    const date = typeof value === 'string' ? parseDate(value) : value;
    return new Intl.DateTimeFormat(tag, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
    }).format(date);
}

export function formatRelative(value: string, tag: string, now: Date = new Date()): string {
    const diffSeconds = Math.round((parseDate(value).getTime() - now.getTime()) / 1000);
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['year', 31536000],
        ['month', 2592000],
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];
    const rtf = new Intl.RelativeTimeFormat(tag, { numeric: 'auto' });
    for (const [unit, seconds] of units) {
        if (Math.abs(diffSeconds) >= seconds) {
            return rtf.format(Math.round(diffSeconds / seconds), unit);
        }
    }
    return rtf.format(diffSeconds, 'second');
}

/** Date-only strings (YYYY-MM-DD) are treated as local dates, not UTC midnight. */
export function parseDate(value: string): Date {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
    if (match) return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    return new Date(value);
}
