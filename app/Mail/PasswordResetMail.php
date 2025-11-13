<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $resetCode, public ?string $name = null)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'OnlyFarms - Password Reset Code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset-code',
            with: [
                'resetCode' => $this->resetCode,
                'name' => $this->name,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

