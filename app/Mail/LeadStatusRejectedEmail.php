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

class LeadStatusRejectedEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $lead;
    public $rejectedStatus; // The status that was requested but rejected
    public $rejectedByUser; // Admin who rejected
    public $rejectionReason;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Lead $lead, Status $rejectedStatus, User $rejectedByUser, string $rejectionReason)
    {
        $this->lead = $lead;
        $this->rejectedStatus = $rejectedStatus;
        $this->rejectedByUser = $rejectedByUser;
        $this->rejectionReason = $rejectionReason;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: 'Lead Status Change Rejected: ' . $this->lead->plaintiff,
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
            view: 'emails.leads.status_rejected',
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
