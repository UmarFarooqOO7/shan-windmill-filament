<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CalendarSyncErrorEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $errorMessage;
    public $userId;
    public $eventId;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(string $errorMessage, int $userId = null, int $eventId = null)
    {
        $this->errorMessage = $errorMessage;
        $this->userId = $userId;
        $this->eventId = $eventId;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: 'Google Calendar Sync Error',
        );
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content()
    {
        return new Content(
            view: 'emails.calendar.sync_error',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments()
    {
        return [];
    }
}
