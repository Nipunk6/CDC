<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FormEditedByAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, array{old: string, new: string}>  $changedFields
     */
    public function __construct(
        public string $formType,
        public string $title,
        public array $changedFields
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('%s Updated by Admin: %s', $this->formType, $this->title),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.form-edited-by-admin',
        );
    }
}
