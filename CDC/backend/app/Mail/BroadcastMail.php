<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;

/**
 * Admin-written broadcast (notice published, stage email, survey published; Superset parity S7). Same portal layout
 * as PortalNoticeMail, plus an optional attachment from the private disk. Sent in BCC batches, never personalised (D89).
 */
class BroadcastMail extends PortalNoticeMail
{
    /**
     * @param  list<string>  $lines  plain-text paragraphs (rich text is flattened first, D76)
     */
    public function __construct(
        string $subjectLine,
        string $headline,
        array $lines = [],
        ?string $actionUrl = null,
        ?string $actionLabel = null,
        public ?string $attachmentPath = null,
        public ?string $attachmentName = null
    ) {
        parent::__construct($subjectLine, 'Dear Student,', $headline, $lines, $actionUrl, $actionLabel);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.broadcast');
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        if (! $this->attachmentPath) {
            return [];
        }

        return [Attachment::fromStorageDisk('local', $this->attachmentPath)->as($this->attachmentName ?: basename($this->attachmentPath))];
    }

    /**
     * Rich text (HTML) → plain paragraphs for the mail body, the same flattening as event descriptions (D76).
     *
     * @return list<string>
     */
    public static function paragraphs(?string $html): array
    {
        if (! $html) {
            return [];
        }
        $text = html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />', '</li>'], "\n", $html)), ENT_QUOTES | ENT_HTML5);

        return array_values(array_filter(array_map('trim', preg_split('/\n+/', $text) ?: []), fn ($line) => $line !== ''));
    }
}
