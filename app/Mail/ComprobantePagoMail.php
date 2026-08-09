<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ComprobantePagoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly array $documento, private readonly string $pdf) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Comprobante de pago {$this->documento['comprobanteNumero']} - AUTOFIX");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.comprobante-pago', with: ['documento' => $this->documento]);
    }

    public function attachments(): array
    {
        $numero = preg_replace('/[^A-Za-z0-9._-]/', '-', $this->documento['comprobanteNumero']);

        return [Attachment::fromData(fn () => $this->pdf, "comprobante-{$numero}.pdf")->withMime('application/pdf')];
    }
}
