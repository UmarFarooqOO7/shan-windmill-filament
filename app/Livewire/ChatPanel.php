<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;

class ChatPanel extends Component
{
    use WithFileUploads;


    public $selectedUser = null;
    public $selectedChat = null;
    public $messages = [];
    public $newMessage = '';
    public $search = '';
    public $mediaFiles = [];


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
        // Dispatch scroll event
        $this->dispatch('scrollToBottom');
    }

    #[Computed]
    public function users()
    {
        return User::query()
            ->where('id', '!=', Auth::id())
            ->when($this->search, fn($q) =>
            $q->where('name', 'like', '%' . $this->search . '%'))
            ->orderBy('name')
            ->get();
    }

    public function loadMessages()
    {
        if ($this->selectedChat) {
            $this->messages = $this->selectedChat
                ->messages()
                ->with('user')
                ->orderBy('created_at')
                // not get deleted messages
                ->where(function ($query) {
                    $query->whereNull('deleted_by')
                        ->orWhereJsonDoesntContain('deleted_by', Auth::id());
                })
                ->get();
        }
    }

    public function removeMedia($index)
    {
        unset($this->mediaFiles[$index]);
        $this->mediaFiles = array_values($this->mediaFiles); // reindex
    }

    public function sendMessage()
    {
        if (empty($this->newMessage) && count($this->mediaFiles) === 0) {
            return;
        }

        $filePaths = [];

        foreach ($this->mediaFiles as $file) {
            $originalName = $file->getClientOriginalName(); // e.g. invoice_march.pdf
            $path = $file->storeAs('chat_attachments', $originalName, 'public');
            $filePaths[] = $path; // store only relative path, not full URL
        }


        $this->selectedChat->messages()->create([
            'user_id' => Auth::id(),
            'message' => $this->newMessage ?? '',
            'attachment' => count($filePaths) ? json_encode($filePaths) : null,
        ]);


        $this->newMessage = '';
        $this->mediaFiles = [];
        $this->loadMessages(); // refresh
        // Dispatch scroll event
        $this->dispatch('scrollToBottom');
    }

    public function deleteMessage($id)
    {
        $message = Message::find($id);

        if ($message && $message->user_id === auth()->id()) {

            // Delete attached files from storage
            if ($message->attachment) {
                $attachments = json_decode($message->attachment, true);

                foreach ($attachments as $filePath) {
                    if ($filePath && Storage::disk('public')->exists($filePath)) {
                        Storage::disk('public')->delete($filePath);
                    }
                }
            }

            $message->delete();
            $this->loadMessages(); // Refresh messages after deletion
        }
    }

    public function deleteChat()
    {
        Message::where('chat_id', $this->selectedChat->id)
            ->where(function ($query) {
                $query->whereNull('deleted_by')
                    ->orWhereJsonDoesntContain('deleted_by', auth()->id());
            })
            ->update([
                'deleted_by' => DB::raw("JSON_ARRAY_APPEND(IFNULL(deleted_by, JSON_ARRAY()), '$', " . auth()->id() . ")")
            ]);

        $this->selectedChat = null;
        $this->selectedUser = null;
    }



    public function render()
    {
        return view('livewire.chat-panel');
    }
}
