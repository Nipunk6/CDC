<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * P10.2 — to an SCPT member: made SPOC for a job profile (`assigned`), no longer its SPOC (`removed`), or the company's
 * HR contact changed while they are its SPOC (`hr_updated`). Assigned and HR-updated mails carry the current HR details.
 */
class SpocAssignmentMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  'assigned'|'removed'|'hr_updated'  $kind
     * @param  array{name: ?string, designation: ?string, email: ?string, phone: ?string, alt_phone: ?string}  $hr
     */
    public function __construct(
        public string $kind,
        public string $name,
        public string $companyName,
        public string $role,
        public string $cycleName,
        public ?string $deadline,
        public array $hr,
        public string $subjectLine
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.spoc-assignment');
    }
}
