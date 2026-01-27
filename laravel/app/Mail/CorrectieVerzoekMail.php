<?php

namespace App\Mail;

use App\Models\Club;
use App\Models\Toernooi;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class CorrectieVerzoekMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Toernooi $toernooi,
        public Club $club,
        public Collection $judokas
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Actie vereist: Corrigeer judoka gegevens - {$this->toernooi->naam}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.correctie-verzoek',
            with: [
                'toernooi' => $this->toernooi,
                'club' => $this->club,
                'judokas' => $this->judokas,
                'portalUrl' => $this->club->getPortalUrl($this->toernooi),
                'pincode' => $this->club->pincode,
            ],
        );
    }
}
