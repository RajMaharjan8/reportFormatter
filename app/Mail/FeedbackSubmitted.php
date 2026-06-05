<?php

namespace App\Mail;

use App\Models\Feedback;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FeedbackSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Feedback $feedback) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New feedback from '.($this->feedback->user?->name ?? 'a user'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.feedback',
        );
    }

    /**
     * Attach any screenshots the user added.
     *
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return array_map(
            fn (string $path): Attachment => Attachment::fromStorageDisk('public', $path),
            $this->feedback->images ?? [],
        );
    }
}
