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

class LeadStatusApprovedEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $lead;
    public $approvedStatus;
    public $approvedByUser; // Admin who approved

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Lead $lead, Status $approvedStatus, User $approvedByUser)
    {
        $this->lead = $lead;
        $this->approvedStatus = $approvedStatus;
        $this->approvedByUser = $approvedByUser;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: 'Lead Status Change Approved: ' . $this->lead->plaintiff,
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
            view: 'emails.leads.status_approved',
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
