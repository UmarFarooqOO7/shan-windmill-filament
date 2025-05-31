<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CalendarConnectionStatusEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;
    public $isConnected; // boolean
    public $errorMessage; // ?string
    public $isDisconnection; // bool

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(User $user, bool $isConnected, ?string $errorMessage = null, bool $isDisconnection = false)
    {
        $this->user = $user;
        $this->isConnected = $isConnected;
        $this->errorMessage = $errorMessage;
        $this->isDisconnection = $isDisconnection;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        $statusText = $this->isDisconnection ? 'Disconnected' : ($this->isConnected ? 'Connected' : 'Connection Failed');
        return new Envelope(
            subject: 'Google Calendar Status: ' . $this->user->name . ' - ' . $statusText,
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
            view: 'emails.calendar.connection_status',
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
