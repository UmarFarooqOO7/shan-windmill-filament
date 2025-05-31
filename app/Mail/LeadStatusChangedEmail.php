<?php

namespace App\Mail;

use App\Models\Lead;
use App\Models\User;
use App\Models\Status;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeadStatusChangedEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $lead;
    public $newStatus;
    public $previousStatus;
    public $changedByUser;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Lead $lead, Status $newStatus = null, Status $previousStatus = null, User $changedByUser)
    {
        $this->lead = $lead;
        $this->newStatus = $newStatus;
        $this->previousStatus = $previousStatus;
        $this->changedByUser = $changedByUser;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: 'Lead Status Updated: ' . $this->lead->plaintiff,
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
            view: 'emails.leads.status_changed',
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
