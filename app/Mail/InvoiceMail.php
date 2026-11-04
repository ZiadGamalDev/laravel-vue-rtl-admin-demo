<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Services\InvoicePdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The invoice email: RTL-safe HTML body plus the PDF attached. */
class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('invoice.mail_subject', ['number' => $this->invoice->number, 'company' => __('invoice.company')]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.invoice', with: ['invoice' => $this->invoice->loadMissing('customer')]);
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        $pdf = app(InvoicePdf::class);

        return [
            Attachment::fromData(fn () => $pdf->render($this->invoice), $pdf->filename($this->invoice))->withMime('application/pdf'),
        ];
    }
}
