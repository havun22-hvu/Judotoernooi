<?php

namespace App\Mail;

use App\Models\AutofixProposal;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class AutoFixProposalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AutofixProposal $proposal,
        public ?Collection $attempts = null
    ) {}

    public function envelope(): Envelope
    {
        $short = Str::limit($this->proposal->exception_message, 50);

        return new Envelope(
            subject: "[AutoFix MISLUKT] {$this->proposal->exception_class}: {$short}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.autofix-proposal',
        );
    }
}
