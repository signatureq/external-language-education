<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

class ApplicationReceived extends Mailable
{
    public function __construct(public Application $application) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Заявка с сайта External № '.$this->application->reference);
    }

    public function headers(): Headers
    {
        return new Headers(messageId: 'application-'.$this->application->reference.'@externlangschool.ru');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.application-received');
    }
}
