<?php

namespace App\Mail;

use App\Models\ClubUitnodiging;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClubUitnodigingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ClubUitnodiging $uitnodiging
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Uitnodiging {$this->uitnodiging->toernooi->naam}",
        );
    }

    public function content(): Content
    {
        $club = $this->uitnodiging->club;

        return new Content(
            view: 'emails.club-uitnodiging',
            with: [
                'uitnodiging' => $this->uitnodiging,
                'toernooi' => $this->uitnodiging->toernooi,
                'club' => $club,
                'portalUrl' => $club->getPortalUrl($this->uitnodiging->toernooi),
                'pincode' => $club->pincode,
            ],
        );
    }
}
