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

class LeadStatusApprovalRequestEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $lead;
    public $requestedStatus;
    public $currentStatus;
    public $requestedByUser;
    public $reason;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Lead $lead, Status $requestedStatus, Status $currentStatus = null, User $requestedByUser, $reason = null)
    {
        $this->lead = $lead;
        $this->requestedStatus = $requestedStatus;
        $this->currentStatus = $currentStatus;
        $this->requestedByUser = $requestedByUser;
        $this->reason = $reason;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: 'Approval Required for Lead Status Change: ' . $this->lead->plaintiff,
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
            view: 'emails.leads.status_approval_request',
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
