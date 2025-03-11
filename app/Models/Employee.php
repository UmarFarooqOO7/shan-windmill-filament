<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'name',
        'email',
        'comp_time',
        'vacation_time',
        'sick_time',
        'comp_time_accrual_rate',
        'vacation_time_accrual_rate',
        'sick_time_accrual_rate',
    ];

    public function timeEntries()
    {
        return $this->hasMany(TimeEntry::class);
    }
}
