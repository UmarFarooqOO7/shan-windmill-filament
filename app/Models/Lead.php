<?php

namespace App\Models;

use App\Traits\GlobalScopesTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Builder;

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
        'status',
        'writ',
        'setout',
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

    public function leadAmounts()
    {
        return $this->hasMany(LeadAmount::class);
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'leads_teams');
    }
}
