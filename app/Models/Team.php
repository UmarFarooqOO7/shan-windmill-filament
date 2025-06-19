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
    
    public function chat()
    {
        return $this->hasOne(Chat::class, 'team_id');
    }
}
