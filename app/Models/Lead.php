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

    // Add a computed property for sorting by team names
    public function getSortableTeamNamesAttribute()
    {
        return $this->teams->pluck('name')->sort()->join(',');
    }

    // Add a scope to join team names for sorting
    public function scopeWithTeamNames(Builder $query): Builder
    {
        return $query->addSelect(['sortable_team_names' => function($query) {
            $query->selectRaw('GROUP_CONCAT(teams.name ORDER BY teams.name ASC SEPARATOR \',\')')
                ->from('teams')
                ->join('leads_teams', 'teams.id', '=', 'leads_teams.team_id')
                ->whereColumn('leads.id', 'leads_teams.lead_id');
        }]);
    }

    public function leadAmounts()
    {
        return $this->hasMany(LeadAmount::class);
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'leads_teams');
    }
}
