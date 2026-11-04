@php
    use App\Support\Money;
    $rtl = app()->getLocale() === 'ar';
    $align = $rtl ? 'right' : 'left';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('invoice.mail_subject', ['number' => $invoice->number, 'company' => __('invoice.company')]) }}</title>
</head>
{{-- Email clients ignore most CSS: inline styles, tables for layout, explicit dir on every block. --}}
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Tahoma,Arial,sans-serif;color:#1e293b;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 0;">
    <tr><td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" dir="{{ $rtl ? 'rtl' : 'ltr' }}" style="max-width:600px;width:100%;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;">
            <tr>
                <td style="background:#0f766e;color:#ffffff;padding:20px 28px;border-radius:12px 12px 0 0;text-align:{{ $align }};">
                    <div style="font-size:20px;font-weight:bold;">{{ __('invoice.company') }}</div>
                    <div style="font-size:13px;opacity:.85;">{{ __('invoice.title') }} <span dir="ltr">{{ $invoice->number }}</span></div>
                </td>
            </tr>
            <tr>
                <td style="padding:24px 28px;text-align:{{ $align }};font-size:15px;line-height:1.8;">
                    <p style="margin:0 0 12px;">{{ __('invoice.mail_greeting', ['name' => $invoice->customer?->name]) }}</p>
                    <p style="margin:0 0 20px;">
                        {!! __('invoice.mail_body', [
                            'number' => '<bdi dir="ltr">'.e($invoice->number).'</bdi>',
                            'amount' => '<strong><bdi>'.e(Money::format($invoice->amount)).'</bdi></strong>',
                            'due' => e(Money::date($invoice->due_at)),
                        ]) !!}
                    </p>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:8px;">
                        <tr>
                            <td style="padding:10px 14px;color:#64748b;text-align:{{ $align }};">{{ __('invoice.issued_at') }}</td>
                            <td style="padding:10px 14px;text-align:{{ $rtl ? 'left' : 'right' }};">{{ Money::date($invoice->issued_at) }}</td>
                        </tr>
                        <tr>
                            <td style="padding:10px 14px;color:#64748b;border-top:1px solid #e2e8f0;text-align:{{ $align }};">{{ __('invoice.due_at') }}</td>
                            <td style="padding:10px 14px;border-top:1px solid #e2e8f0;text-align:{{ $rtl ? 'left' : 'right' }};">{{ Money::date($invoice->due_at) }}</td>
                        </tr>
                        <tr>
                            <td style="padding:10px 14px;font-weight:bold;border-top:1px solid #e2e8f0;text-align:{{ $align }};">{{ __('invoice.total') }}</td>
                            <td style="padding:10px 14px;font-weight:bold;border-top:1px solid #e2e8f0;text-align:{{ $rtl ? 'left' : 'right' }};"><bdi>{{ Money::format($invoice->amount) }}</bdi></td>
                        </tr>
                    </table>
                    <p style="margin:20px 0 0;">{{ __('invoice.thanks') }}</p>
                </td>
            </tr>
            <tr>
                <td style="padding:14px 28px;border-top:1px solid #e2e8f0;color:#64748b;font-size:12px;text-align:center;">{{ __('invoice.mail_footer') }}</td>
            </tr>
        </table>
    </td></tr>
</table>
</body>
</html>
