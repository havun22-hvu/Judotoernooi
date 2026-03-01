<?php

namespace App\Mail;

use App\Models\ToernooiBetaling;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FactuurMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ToernooiBetaling $betaling,
        public string $pdfPath,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Factuur {$this->betaling->factuurnummer} - JudoToernooi",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.factuur',
            with: [
                'betaling' => $this->betaling,
                'organisator' => $this->betaling->organisator,
                'toernooi' => $this->betaling->toernooi,
                'factuurnummer' => $this->betaling->factuurnummer,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->pdfPath)
                ->as($this->betaling->factuurnummer . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
