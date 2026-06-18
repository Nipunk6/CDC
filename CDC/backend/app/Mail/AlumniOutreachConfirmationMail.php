<?php

namespace App\Mail;

use App\Models\AlumniOutreachSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AlumniOutreachConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly AlumniOutreachSubmission $submission
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Thank you for registering with IIT (ISM) CDC Alumni Network',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.alumni-outreach-confirmation',
        );
    }
}
