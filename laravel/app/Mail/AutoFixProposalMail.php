<?php

namespace App\Mail;

use App\Models\AutofixProposal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AutoFixProposalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AutofixProposal $proposal
    ) {}

    public function envelope(): Envelope
    {
        $short = \Illuminate\Support\Str::limit($this->proposal->exception_message, 50);

        return new Envelope(
            subject: "[AutoFix] {$this->proposal->exception_class}: {$short}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.autofix-proposal',
        );
    }
}
