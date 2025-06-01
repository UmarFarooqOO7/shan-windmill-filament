<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadAmount extends Model
{
    // Define the table associated with the LeadAmount model (if it's not the default 'lead_amounts')
    protected $table = 'lead_amounts';

    // Define the fillable properties, which are the columns that can be mass-assigned
    protected $fillable = [
        'lead_id',
        'amount_cleared',
        'amount_owed',
        'payment_date',
        'description'
    ];

    // Define the casts for the properties
    protected $casts = [
        'payment_date' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->payment_date) {
                $model->payment_date = now();
            }
        });
    }

    // Define the relationship between LeadAmount and Lead (if any)
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
