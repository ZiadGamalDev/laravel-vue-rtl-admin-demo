/**
 * Only same-app paths are allowed after login. "//host" and "/\host" are
 * protocol-relative URLs that browsers resolve to another origin.
 */
export function safeRedirect(value: unknown, fallback = '/'): string {
    if (typeof value !== 'string' || !value.startsWith('/')) return fallback;
    if (value.startsWith('//') || value.startsWith('/\\')) return fallback;
    return value;
}
