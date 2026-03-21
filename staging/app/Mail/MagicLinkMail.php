<?php

namespace App\Mail;

use App\Models\MagicLinkToken;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public MagicLinkToken $token) {}

    public function envelope(): Envelope
    {
        $subject = $this->token->type === 'register'
            ? __('Bevestig je registratie - JudoToernooi')
            : __('Wachtwoord resetten - JudoToernooi');

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $route = $this->token->type === 'register'
            ? route('register.verify', $this->token->token)
            : route('password.magic-reset', $this->token->token);

        return new Content(
            markdown: 'emails.magic-link',
            with: [
                'url' => $route,
                'type' => $this->token->type,
                'name' => $this->token->metadata['name'] ?? null,
                'expiresIn' => 15,
            ],
        );
    }
}
