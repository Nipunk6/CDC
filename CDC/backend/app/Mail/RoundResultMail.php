<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E4 — a round's result was published: selected / waitlisted, or the regret mail.
 * Always sent as a BCC batch, so `$name` is null there (D89); waitlists have no positions (D90).
 */
class RoundResultMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ?string $name,
        public string $companyName,
        public string $title,
        public string $roundName,
        public string $outcome,
        public ?string $nextRound,
        public bool $addendum = false
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->outcome) {
            'selected' => "Shortlisted: {$this->companyName} — {$this->roundName}",
            'waitlisted' => "Waitlisted: {$this->companyName} — {$this->roundName}",
            default => "Update on your application: {$this->companyName}",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.round-result');
    }
}
