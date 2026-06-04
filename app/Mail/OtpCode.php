<?php

namespace App\Mail;

use App\Models\Otp;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpCode extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public string $purpose,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->purpose === Otp::PURPOSE_PASSWORD_RESET
            ? 'Your password reset code'
            : 'Verify your email address';

        return new Envelope(subject: $subject.' — '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.otp',
            with: [
                'code' => $this->code,
                'isReset' => $this->purpose === Otp::PURPOSE_PASSWORD_RESET,
                'minutes' => Otp::EXPIRY_MINUTES,
            ],
        );
    }
}
