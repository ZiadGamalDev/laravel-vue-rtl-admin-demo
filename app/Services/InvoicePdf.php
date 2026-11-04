<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;

/**
 * Server-side invoice PDF. mPDF is used because it shapes Arabic (joined letters,
 * right-to-left runs, mixed Latin numbers) correctly; it embeds a subset of the
 * XB Riyaz font (SIL OFL, bundled with mPDF) for Arabic text.
 */
class InvoicePdf
{
    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing(['customer', 'order.items']);
        $rtl = App::getLocale() === 'ar';

        $tempDir = storage_path('framework/cache/mpdf');
        File::ensureDirectoryExists($tempDir);

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_top' => 16,
            'margin_bottom' => 16,
            'margin_left' => 16,
            'margin_right' => 16,
            'default_font' => 'xbriyaz',
            'directionality' => $rtl ? 'rtl' : 'ltr',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'tempDir' => $tempDir,
        ]);
        $mpdf->SetTitle(__('invoice.title').' '.$invoice->number);
        $mpdf->SetCreator(config('app.name'));
        $mpdf->WriteHTML(view('pdf.invoice', ['invoice' => $invoice, 'rtl' => $rtl])->render());

        return $mpdf->Output('', 'S');
    }

    public function filename(Invoice $invoice): string
    {
        return $invoice->number.'.pdf';
    }
}
