<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadImage extends Model
{
    use HasFactory;

    protected $fillable = ['lead_id', 'file_path', 'file_name'];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}

