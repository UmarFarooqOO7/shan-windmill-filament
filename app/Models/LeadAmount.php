<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadAmount extends Model
{
    // Define the table associated with the LeadAmount model (if it's not the default 'lead_amounts')
    protected $table = 'lead_amounts';

    // Define the fillable properties, which are the columns that can be mass-assigned
    protected $fillable = [
        'lead_id',
        'amount_cleared',
        'amount_owed'
    ];

    // Define the relationship between LeadAmount and Lead (if any)
    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}
