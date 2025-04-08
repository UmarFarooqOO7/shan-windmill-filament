<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusChangeApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'requested_by',
        'approved_by',
        'status_type',
        'from_status_id',
        'to_status_id',
        'reason',
        'approved_at',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /**
     * Get the lead this approval request is for
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get the user who requested the status change
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Get the user who approved the status change
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the original status
     */
    public function fromStatus(): BelongsTo
    {
        // Don't filter by status type since fromStatus and toStatus can be of different types
        return $this->belongsTo(Status::class, 'from_status_id');
    }

    /**
     * Get the requested status
     */
    public function toStatus(): BelongsTo
    {
        // Don't filter by status type since fromStatus and toStatus can be of different types
        return $this->belongsTo(Status::class, 'to_status_id');
    }

    /**
     * Check if this approval request is pending
     */
    public function isPending(): bool
    {
        return $this->approved_at === null && $this->rejected_at === null;
    }

    /**
     * Check if this approval request is approved
     */
    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * Check if this approval request is rejected
     */
    public function isRejected(): bool
    {
        return $this->rejected_at !== null;
    }
}
