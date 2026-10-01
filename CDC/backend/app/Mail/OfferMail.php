<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E5 — final result: an offer.
 */
class OfferMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $companyName,
        public string $title,
        public string $offerLabel,
        public ?string $compensation,
        public ?string $blockNote
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Congratulations! Offer from {$this->companyName}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.offer');
    }
}
