<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewOrderNotification extends Mailable
{
    use Queueable, SerializesModels;

    public string $url;
    public string $userName;

    public function __construct(string $url, string $userName)
    {
        $this->url      = $url;
        $this->userName = $userName;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Order Created',
            to: [
                new Address(
                    config('mail.to.address'),
                    config('mail.to.name')
                )
            ]
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.new-number-notification',
            with: [
                'url'      => $this->url,
                'userName' => $this->userName,  
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}