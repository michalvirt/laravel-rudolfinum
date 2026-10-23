<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A plain text email with a subject and a body that were already rendered from a template.
 */
class EmailNotificationMail extends Mailable
{
    public function __construct(public string $subjectLine, public string $body) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(text: 'mail.email-notification');
    }
}
