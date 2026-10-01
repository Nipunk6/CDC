<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E8 — the CDC changed something on a student's profile (admin edit or a branch-change decision).
 */
class StudentProfileUpdatedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $lines  student-facing summary lines
     */
    public function __construct(
        public string $name,
        public string $headline,
        public array $lines,
        public string $subjectLine
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.student-profile-updated');
    }
}
