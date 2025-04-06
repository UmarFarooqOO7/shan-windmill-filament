<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'type', 'requires_approval'];
    
    protected $casts = [
        'requires_approval' => 'boolean',
    ];

    public function leads()
    {
        return $this->hasMany(Lead::class,'status','id');
    }
    
    /**
     * Check if this status requires admin approval
     */
    public function requiresApproval(): bool
    {
        return $this->requires_approval === true;
    }
}
