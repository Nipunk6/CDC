<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Invitation for a new CDC staff member: their role, what it lets them do, and the link to set a password.
 * After signing in they complete their own details (name, mobile, designation).
 */
class StaffInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public const SUBJECT = 'You have been given access to the IIT ISM CDC Admin Portal';

    /**
     * @param  list<array{group: string, items: list<array{label: string, level: string}>}>  $permissions
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $invitedBy,
        public string $roleName,
        public ?string $roleDescription,
        public bool $fullAccess,
        public array $permissions,
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
        return new Content(view: 'emails.staff-invitation');
    }
}
