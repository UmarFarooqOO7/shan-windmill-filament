<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = ['chat_id', 'user_id', 'message', 'is_read', 'attachments','is_tune_rec'];

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function readers()
    {
        return $this->belongsToMany(User::class, 'team_message_reads')
            ->withTimestamps();
    }
}
