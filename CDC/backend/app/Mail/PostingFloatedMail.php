<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E2 — a posting the student is eligible for has been floated. Sent in BCC batches, so it is not personalised (D89).
 */
class PostingFloatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $companyName,
        public string $title,
        public string $typeLabel,
        public string $deadline,
        public string $url
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "New opening: {$this->companyName} — {$this->title}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.posting-floated');
    }
}
