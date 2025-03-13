<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamMember extends Model
{
    use HasFactory;

    // Fillable fields
    protected $fillable = [
        'team_id',
        'user_id',
        'role',
    ];

    /**
     * Get the team that owns the TeamMember.
     */
    public function team()
    {
        return $this->belongsTo(Team::class); // Each TeamMember belongs to one Team
    }

    /**
     * Get the user that is a member of the team.
     */
    public function user()
    {
        return $this->belongsTo(User::class); // Each TeamMember belongs to one User
    }
}
