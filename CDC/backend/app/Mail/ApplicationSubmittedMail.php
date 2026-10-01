<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E3 — application confirmation.
 */
class ApplicationSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $companyName,
        public string $title,
        public string $resumeLabel,
        public bool $unverifiedResume,
        public string $deadline,
        public string $url
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Application received: {$this->companyName} — {$this->title}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.application-submitted');
    }
}
