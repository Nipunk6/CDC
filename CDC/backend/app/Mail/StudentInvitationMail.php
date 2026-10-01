<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StudentInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public const SUBJECT = 'Your IIT ISM CDC Placement Portal account';

    public function __construct(
        public string $name,
        public string $rollNo,
        public string $setPasswordUrl,
        public string $loginUrl
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: self::SUBJECT);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.student-invitation');
    }
}
