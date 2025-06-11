<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Chat;
use App\Models\Message;

class ChatPanel extends Component
{
    public $users;
    public $selectedUser = null;
    public $selectedChat = null;
    public $messages = [];
    public $newMessage = '';

    public function mount()
    {
        $this->users = User::where('id', '!=', Auth::id())->get();
    }

    public function selectUser($userId)
    {
        $this->selectedUser = User::findOrFail($userId);

        // Find or create one-to-one chat
        $chat = Chat::where('is_group', false)
            ->whereHas('users', fn($q) => $q->where('user_id', Auth::id()))
            ->whereHas('users', fn($q) => $q->where('user_id', $userId))
            ->withCount('users')
            ->get()
            ->filter(fn($chat) => $chat->users_count == 2)
            ->first();

        if (!$chat) {
            $chat = Chat::create(['is_group' => false]);
            $chat->users()->attach([Auth::id(), $userId]);
        }

        $this->selectedChat = $chat;
        $this->loadMessages();
    }

    public function loadMessages()
    {
        if ($this->selectedChat) {
            $this->messages = $this->selectedChat
                ->messages()
                ->with('user')
                ->orderBy('created_at')
                ->get();
        }
    }

    public function sendMessage()
    {
        if (!$this->newMessage || !$this->selectedChat) return;

        $this->selectedChat->messages()->create([
            'user_id' => Auth::id(),
            'message' => $this->newMessage,
        ]);

        $this->newMessage = '';
        $this->loadMessages(); // refresh
    }

    public function render()
    {
        return view('livewire.chat-panel');
    }
}
