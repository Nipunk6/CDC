<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E6 — event announced. Sent in BCC batches, so it is not personalised (D89).
 */
class EventAnnouncedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $title,
        public string $typeLabel,
        public string $when,
        public ?string $companyName,
        public ?string $venue,
        public ?string $meetingLink,
        public ?string $description
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->typeLabel}: {$this->title}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.event-announced');
    }
}
