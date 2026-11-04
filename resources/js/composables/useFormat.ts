import { useUiStore } from '@/stores/ui';
import { appConfig } from '@/lib/config';
import { formatDate, formatMoney, formatNumber, formatRelative } from '@/lib/format';

export function useFormat() {
    const ui = useUiStore();
    return {
        number: (value: number, options?: Intl.NumberFormatOptions) => formatNumber(value, ui.tag, options),
        money: (value: number) => formatMoney(value, ui.tag, appConfig.currency),
        date: (value: string | null | undefined) => formatDate(value, ui.tag),
        dateTime: (value: string | null | undefined) => formatDate(value, ui.tag, true),
        relative: (value: string) => formatRelative(value, ui.tag),
    };
}
