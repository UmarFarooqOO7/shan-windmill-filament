<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Team;
use App\Models\TeamMessageRead;
use App\Notifications\NewChatMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;

class ChatPanel extends Component
{
    use WithFileUploads;


    public $selectedUser = null;
    public $selectedChat = null;
    public $isTeamChat = null;

    public $messages = [];
    public $perPage = 10;
    public $page = 1;
    public $hasMoreMessages = true;

    public $activeTab = 'teams';
    public $newMessage = '';
    public $search = '';
    public $mediaFiles = [];
    public bool $showingModal = false;
    public string $modalSearch = '';

    public $notifications = [];
    public $unreadCount = 0;

    public function loadUnreadNotifications()
    {
        $user = auth()->user();

        $this->notifications = $user->unreadNotifications()->latest()->whereNotNull('data->chat_type')->limit(5)->get();

        $this->unreadCount =
            // $this->selectedChat ? $user->unreadNotifications->where('data.chat_id', $this->selectedChat->id)->count()
            $user->unreadNotifications()->whereNotNull('data->chat_type')->count();
    }

    public function markAllNotificationsAsRead()
    {
        auth()->user()->unreadNotifications->markAsRead();
        $this->loadUnreadNotifications();
    }

    public function markNotificationAsRead($id)
    {
        $notification = auth()->user()->unreadNotifications()->where('id', $id)->first();

        if ($notification) {
            $data = $notification->data;
            $notification->markAsRead();

            // Auto-open chat on click
            if (isset($data['chat_type']) && isset($data['chat_id'])) {
                if ($data['chat_type'] === 'team') {
                    $this->activeTab = "teams";
                    $chat = Chat::find($data['chat_id']);
                    if ($chat && $chat->team_id) {
                        $this->openTeamChat($chat->team_id);
                    }
                } elseif ($data['chat_type'] === 'user' && isset($data['sender_id'])) {
                    $this->activeTab = "users";
                    $this->selectUser($data['sender_id']);
                }
            }
        }
        // $this->loadUnreadNotifications();

    }


    // modal open and users show with search

    #[Computed]
    public function filteredModalUsers()
    {
        return User::query()
            ->where('id', '!=', Auth::id())
            ->when($this->modalSearch, function ($query) {
                $query->where('name', 'like', '%' . $this->modalSearch . '%');
            })
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function filteredModalTeams()
    {
        return Team::query()
            ->whereHas('members', fn($q) => $q->where('users.id', auth()->id()))
            ->when($this->modalSearch, fn($q) => $q->where('name', 'like', '%' . $this->modalSearch . '%'))
            ->get();
    }
    public function updatedShowingModal($val)
    {
        if (!$val) {
            $this->modalSearch = '';
        }
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


        $this->page = 1;
        $this->perPage = 10;
        $this->loadMessages();
        // Dispatch scroll event
        $this->dispatch('scrollToBottom');
    }

    public function openTeamChat($teamId)
    {
        $this->selectedChat = Chat::firstOrCreate([
            'team_id' => $teamId,
        ]);

        $this->selectedUser = null;
        $this->page = 1;
        $this->loadMessages();
        $this->dispatch('scrollToBottom');
    }

    public function chatNotificationsRead()
    {
        if ($this->selectedChat) {
            // ✅ Mark related notifications as read
            auth()->user()
                ->unreadNotifications()
                ->where('data->chat_id', $this->selectedChat->id)
                ->update(['read_at' => now()]);
        }
    }

    #[Computed]
    public function users()
    {
        $authId = auth()->id();

        return User::query()
            ->where('id', '!=', $authId)
            ->whereHas('chats', function ($q) use ($authId) {
                $q->whereHas('users', fn($q) => $q->where('users.id', $authId));
            })
            ->when(
                $this->search,
                fn($q) =>
                $q->where('name', 'like', '%' . $this->search . '%')
            )
            ->withCount([
                'receivedMessages as unread_count' => function ($q) use ($authId) {
                    $q->where('is_read', false)
                        ->whereHas('chat.users', fn($q) => $q->where('users.id', $authId));
                }
            ])
            ->with([
                'receivedMessages' => function ($q) use ($authId) {
                    $q->where('is_read', false)->latest()->take(1)
                        ->whereHas('chat.users', fn($q) => $q->where('users.id', $authId));
                }
            ])
            ->orderByDesc('unread_count')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function teams()
    {
        $authId = auth()->id();
        // mark messages as read for team chats
        if ($this->selectedChat && $this->selectedChat->team_id) {
            $unreadIds = $this->messages
                ->filter(fn($msg) => $msg->user_id !== $authId)
                ->filter(fn($msg) => !$msg->readers->contains($authId))
                ->pluck('id');

            if ($unreadIds->isNotEmpty()) {
                $now = now();
                $data = $unreadIds->map(fn($id) => [
                    'message_id' => $id,
                    'user_id' => $authId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->toArray();

                TeamMessageRead::insertOrIgnore($data);
            }
        }

        return Team::whereHas('members', fn($q) => $q->where('users.id', $authId))
            ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->whereHas('messages') // ✅ this now works!
            ->with(['messages' => fn($q) => $q->latest()->take(1)])
            ->withCount([
                'messages as unread_count' => function ($q) use ($authId) {
                    $q->where('user_id', '!=', $authId)
                        ->whereDoesntHave('readers', fn($q) => $q->where('user_id', $authId))
                        ->when($this->selectedChat?->team_id, function ($q, $selectedTeamId) {
                            $q->whereHas(
                                'chat',
                                fn($chat) =>
                                $chat->where('team_id', '!=', $selectedTeamId)
                            );
                        });
                }
            ])
            ->with('latestMessage.user')
            ->get();
    }


    public function loadMessages($mode = 'initial') // mode: 'initial', 'scroll', 'poll'
    {
        if (!$this->selectedChat) return;

        $query = $this->selectedChat
            ->messages()
            ->with('user')
            ->where(function ($q) {
                $q->whereNull('deleted_by')
                    ->orWhereJsonDoesntContain('deleted_by', Auth::id());
            });

        if ($mode === 'poll') {
            // Load new messages after the last
            $lastId = $this->messages->last()->id ?? 0;

            $newMessages = $query
                ->where('id', '>', $lastId)
                ->orderBy('created_at')
                ->get();

            $this->messages = $this->messages->merge($newMessages);

            if ($newMessages->isNotEmpty()) {
                $this->dispatch('scrollToBottom');
            }
        } else {

            if ($this->selectedChat->team_id) {
                $query = $query->with('readers');
            }

            // Scroll or initial load
            $messages = $query
                ->orderByDesc('created_at')
                ->skip(($this->page - 1) * $this->perPage)
                ->take($this->perPage)
                ->get()
                ->reverse(); // Maintain old → new order

            if ($mode === 'scroll') {
                $this->messages = $messages->merge($this->messages); // Prepend
            } else {
                $this->messages = $messages; // Initial load
            }

            // Has more?
            $total = $this->selectedChat->messages()->count();
            $this->hasMoreMessages = $total > $this->page * $this->perPage;
        }

        // Mark unread as read (only for user chat)
        if ($this->selectedChat->team_id === null) {
            $this->selectedChat->messages()
                ->where('user_id', '!=', Auth::id())
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }
    }

    public function checkAndPlayNewMessageTone()
    {
        // Get latest message in this chat not sent by current user
        $latest = Message::where('user_id', '!=', auth()->id())
            ->latest()
            ->first();

        if ($latest && !$latest->is_tune_rec) {
            // 🔔 Trigger frontend audio
            $this->dispatch('new-message-received');

            // ✅ Set flag so next time it doesn't play again
            $latest->update(['is_tune_rec' => true]);
        }
    }


    public function pollNewMessages()
    {
        auth()->user()->update(['last_seen_at' => now()]);
        
        if (!$this->selectedChat) return;
        $this->loadMessages('poll');
    }

    public function loadMoreMessages()
    {
        if (!$this->selectedChat || !$this->hasMoreMessages) return;

        $this->page++;
        $this->loadMessages('scroll');
    }

    public function removeMedia($index)
    {
        unset($this->mediaFiles[$index]);
        $this->mediaFiles = array_values($this->mediaFiles); // reindex
    }

    public function appendEmoji($emoji)
    {
        $this->newMessage .= $emoji;
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


        $message =  $this->selectedChat->messages()->create([
            'user_id' => Auth::id(),
            'message' => $this->newMessage ?? '',
            'attachments' => count($filePaths) ? json_encode($filePaths) : null,
        ]);

        // Notify users (Team or One-to-One)
        if ($this->selectedChat->team_id) {
            // 🔔 TEAM CHAT
            $members = $this->selectedChat->team->members;

            foreach ($members as $user) {
                if ($user->id !== Auth::id()) {
                    $user->notify(new NewChatMessage($message, Auth::user(), 'team'));
                }
            }
        } else {
            // 👤 ONE-TO-ONE CHAT
            foreach ($this->selectedChat->users as $user) {
                if ($user->id !== Auth::id()) {
                    $user->notify(new NewChatMessage($message, Auth::user(), 'user'));
                }
            }
        }


        $this->newMessage = '';
        $this->mediaFiles = [];
        // $this->loadMessages(); // refresh
        // Append new message manually (no need to reload all)
        $this->messages->push($message->load('user'));
        // Dispatch scroll event
        $this->dispatch('playSendTune');
    }

    public function markChatNotificationsAsRead()
    {
        if ($this->selectedChat) {
            $chatId = $this->selectedChat->id;

            auth()->user()
                ->unreadNotifications()
                ->where('data->chat_id', $chatId)
                ->update(['read_at' => now()]);
        }
    }


    public function deleteMessage($id)
    {
        $message = Message::find($id);

        if ($message && $message->user_id === auth()->id()) {

            // Delete attached files from storage
            if ($message->attachments) {
                $attachments = json_decode($message->attachments, true);

                foreach ($attachments as $filePath) {
                    if ($filePath && Storage::disk('public')->exists($filePath)) {
                        Storage::disk('public')->delete($filePath);
                    }
                }
            }

            $message->delete();
            $this->messages = $this->messages->filter(fn ($msg) => $msg->id !== $id);
            // $this->loadMessages(); // Refresh messages after deletion
            $this->dispatch('playDeleteTune');
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
        $this->dispatch('playDeleteTune');
    }

    public function render()
    {
        $this->chatNotificationsRead();
        $this->loadUnreadNotifications();
        // 🔔 Play tone if latest received message has not played
        $this->checkAndPlayNewMessageTone();
        return view('livewire.chat-panel');
    }
}
