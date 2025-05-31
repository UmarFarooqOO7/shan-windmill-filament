<?php

namespace App\Mail;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventUpdatedEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $event;
    // You might want to pass old values as well for comparison
    // public $originalData;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Event $event /*, array $originalData = []*/)
    {
        $this->event = $event;
        // $this->originalData = $originalData;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: 'Event Updated: ' . $this->event->title,
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
            view: 'emails.events.updated',
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
