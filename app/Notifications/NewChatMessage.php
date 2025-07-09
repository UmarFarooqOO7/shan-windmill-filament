<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;
use App\Models\Message;
use App\Models\User;

class NewChatMessage extends Notification
{
    // use Queueable;

    protected Message $message;
    protected User $sender;
    protected string $chatType;

    public function __construct(Message $message, User $sender, string $chatType = 'user')
    {
        $this->message = $message;
        $this->sender = $sender;
        $this->chatType = $chatType;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'chat_type'   => $this->chatType,
            'message_id'  => $this->message->id,
            'message'     => $this->message->message,
            'sender_id'   => $this->sender->id,
            'sender_name' => $this->sender->name,
            'chat_id'     => $this->message->chat_id,
            'created_at'  => now(),
        ];
    }

    public function toArray($notifiable): array
    {
        return $this->toDatabase($notifiable); // For broadcast, API fallback etc.
    }
}
