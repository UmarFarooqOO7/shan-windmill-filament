<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimeEntry extends Model
{
    protected $fillable = [
        'employee_id',
        'clock_in',
        'clock_out',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
