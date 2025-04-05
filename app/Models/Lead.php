<?php

namespace App\Models;

use App\Traits\GlobalScopesTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'plaintiff',
        'defendant_first_name',
        'defendant_last_name',
        'address',
        'county',
        'city',
        'state',
        'zip',
        'case_number',
        'setout_date',
        'setout_time',
        'status_id',
        'writ_id',
        'setout_id',
        'lbx',
        'vis_setout',
        'vis_to',
        'notes',
        'time_on',
        'setout_st',
        'setout_en',
        'time_en',
        'locs',
        'amount_cleared',
        'amount_owed'
    ];

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'status_id')->where('type', 'lead');
    }

    public function setoutStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'setout_id')->where('type', 'setout');
    }

    public function writStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'writ_id')->where('type', 'writ');
    }

    public function leadAmounts()
    {
        return $this->hasMany(LeadAmount::class);
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'leads_teams');
    }

    /**
     * Get all status change approval requests for this lead
     */
    public function statusChangeApprovals(): HasMany
    {
        return $this->hasMany(StatusChangeApproval::class);
    }

    /**
     * Get pending status change approval requests for this lead
     */
    public function pendingStatusChangeApprovals(): HasMany
    {
        return $this->statusChangeApprovals()
            ->whereNull('approved_at')
            ->whereNull('rejected_at');
    }
}
