<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Team extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'team_id'];

    // Relationship with users (Many-to-Many)
    public function members()
    {
        return $this->belongsToMany(User::class, 'team_members');
    }

    // Relationship to parent team
    public function parent()
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    // Relationship to sub-teams
    public function subTeams()
    {
        return $this->hasMany(Team::class, 'team_id');
    }

    public function leads()
    {
        return $this->belongsToMany(Lead::class, 'leads_teams');
    }

    public function scopeForUser(Builder $query, User $user, $type = 'all')
    {
        if ($user->role === 'master') {
            return $query; // Master gets all teams
        }

        return $query->where(function ($q) use ($user, $type) {
            if ($type === 'own' || $type === 'all') {
                // Get teams where the user is a direct member
                $q->whereHas('members', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            }

            if ($type === 'parent' || $type === 'all') {
                // Get parent teams where the user is a member(they are a member of a team that is a parent of this team)
                $q->orWhereHas('parent.members', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            }
        });
    }
}
