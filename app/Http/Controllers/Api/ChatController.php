<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Team;
use App\Models\TeamMessageRead;
use App\Notifications\NewChatMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    public function chattedTeams(Request $request)
    {
        $authId = auth()->id();
        $search = $request->query('search');

        $teams = Team::query()
            ->whereHas('messages')
            ->whereHas('members', fn($q) => $q->where('users.id', $authId))
            ->when($search, fn($q) => $q->where('name', 'like', '%' . $search . '%'))
            ->with([
                'messages' => fn($q) => $q->latest()->take(1),
                'messages.user:id,name,email',
            ])
            ->withCount([
                'messages as unread_count' => function ($q) use ($authId) {
                    $q->where('user_id', '!=', $authId)
                        ->whereDoesntHave('readers', fn($q) => $q->where('user_id', $authId));
                }
            ])
            ->orderByDesc('unread_count')
            ->get();

        return $this->success($teams,' chats Teams');
    }

    // public function chattedUsers(Request $request)
    // {
    //     $authId = auth()->id();
    //     $search = $request->query('search');

    //     $users = User::query()
    //         ->where('id', '!=', $authId)
    //         ->whereHas('chats', function ($q) use ($authId) {
    //             $q->whereHas('users', fn($q) => $q->where('users.id', $authId));
    //         })
    //         ->when($search, fn($q) => $q->where('name', 'like', '%' . $search . '%'))
    //         ->withCount([
    //             'receivedMessages as unread_count' => function ($q) use ($authId) {
    //                 $q->where('is_read', false)
    //                     ->whereHas('chat.users', fn($q) => $q->where('users.id', $authId));
    //             }
    //         ])
    //         ->with([
    //             'receivedMessages' => function ($q) use ($authId) {
    //                 $q->where('is_read', false)
    //                     ->latest()
    //                     ->take(1)
    //                     ->whereHas('chat.users', fn($q) => $q->where('users.id', $authId));
    //             }
    //         ])
    //         ->orderByDesc('unread_count')
    //         ->orderBy('name')
    //         ->get();

    //     return $this->success($users, 'Chats users');
    // }

    // public function getAllUsers(Request $request)
    // {
    //     $users = User::where('id', '!=', Auth::id())
    //         ->when($request->name, function ($query) use ($request) {
    //             $query->where('name', 'like', '%' . $request->name . '%');
    //         })
    //         ->get();

    //     return $this->success(['users' => $users]);
        
    // }

    // public function getAllTeams(Request $request)
    // {
    //     $teams = Team::when($request->name, function ($query) use ($request) {
    //             $query->where('name', 'like', '%' . $request->name . '%');
    //         })
    //         ->get();

    //     return $this->success(['teams' => $teams]);
    // }

    // public function teamChat(Request $request, $teamId)
    // {
    //     $authId = auth()->id();

    //     $chat = Chat::firstOrCreate(['team_id' => $teamId]);

    //     $messages = $chat->messages()
    //         ->with(['user', 'readers'])
    //         ->where(function ($q) use ($authId) {
    //             $q->whereNull('deleted_by')
    //             ->orWhereJsonDoesntContain('deleted_by', $authId);
    //         })
    //         ->orderBy('created_at')
    //         ->get();

    //     return $this->success([
    //         'chat_id' => $chat->id,
    //         'messages' => $messages,
    //     ]);
    // }

    // public function userChat(Request $request, $userId)
    // {
    //     $authId = auth()->id();

    //     // Find or create private chat between both users
    //     $chat = Chat::where('is_group', false)
    //         ->whereHas('users', fn($q) => $q->where('user_id', $authId))
    //         ->whereHas('users', fn($q) => $q->where('user_id', $userId))
    //         ->withCount('users')
    //         ->get()
    //         ->filter(fn($chat) => $chat->users_count == 2)
    //         ->first();

    //     if (!$chat) {
    //         $chat = Chat::create(['is_group' => false]);
    //         $chat->users()->attach([$authId, $userId]);
    //     }

    //     $messages = $chat->messages()
    //         ->with('user')
    //         ->where(function ($q) use ($authId) {
    //             $q->whereNull('deleted_by')
    //             ->orWhereJsonDoesntContain('deleted_by', $authId);
    //         })
    //         ->orderBy('created_at')
    //         ->get();

    //     // Mark unread as read
    //     $chat->messages()
    //         ->where('user_id', '!=', $authId)
    //         ->where('is_read', false)
    //         ->update(['is_read' => true]);

    //     return $this->success([
    //         'chat_id' => $chat->id,
    //         'messages' => $messages,
    //     ]);
    // }

    // public function sendMessage(Request $request, $chatId)
    // {
    //     $request->validate([
    //         'message' => 'nullable|string',
    //         'media_files.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx,mp4,avi,txt,zip|max:20480' // 20MB limit per file
    //     ]);

    //     $chat = Chat::with(['team.members', 'users'])->findOrFail($chatId);

    //     if (!$request->message && !$request->hasFile('media_files')) {
    //         return $this->error('Empty message or file required', 422);
    //     }

    //     $filePaths = [];

    //     if ($request->hasFile('media_files')) {
    //         foreach ($request->file('media_files') as $file) {
    //             $originalName = $file->getClientOriginalName();
    //             $path = $file->storeAs('chat_attachments', $originalName, 'public');
    //             $filePaths[] = $path;
    //         }
    //     }

    //     $message = $chat->messages()->create([
    //         'user_id' => Auth::id(),
    //         'message' => $request->message ?? '',
    //         'attachments' => count($filePaths) ? json_encode($filePaths) : null,
    //     ]);

    //     // 🔔 Notifications
    //     if ($chat->team_id) {
    //         foreach ($chat->team->members as $user) {
    //             if ($user->id !== Auth::id()) {
    //                 $user->notify(new NewChatMessage($message, Auth::user(), 'team'));
    //             }
    //         }
    //     } else {
    //         foreach ($chat->users as $user) {
    //             if ($user->id !== Auth::id()) {
    //                 $user->notify(new NewChatMessage($message, Auth::user(), 'user'));
    //             }
    //         }
    //     }

    //     return $this->success([
    //         'message' => 'Message sent successfully',
    //         'data' => $message->load('user')
    //     ]);
    // }

    // public function deleteMessage($id)
    // {
    //     $message = Message::findOrFail($id);

    //     if ($message->user_id !== Auth::id()) {
    //         return response()->json(['error' => 'Unauthorized'], 403);
    //     }

    //     // Delete attachments
    //     if ($message->attachments) {
    //         $attachments = json_decode($message->attachments, true);

    //         foreach ($attachments as $filePath) {
    //             if ($filePath && Storage::disk('public')->exists($filePath)) {
    //                 Storage::disk('public')->delete($filePath);
    //             }
    //         }
    //     }

    //     $message->delete();

    //     r$this->success([], 'Message deleted');
    // }

    // public function deleteChat($chatId)
    // {
    //     $chat = Chat::findOrFail($chatId);

    //     // Soft-delete messages by adding user ID to `deleted_by` column
    //     DB::table('messages')
    //         ->where('chat_id', $chat->id)
    //         ->where(function ($query) {
    //             $query->whereNull('deleted_by')
    //                 ->orWhereJsonDoesntContain('deleted_by', Auth::id());
    //         })
    //         ->update([
    //             'deleted_by' => DB::raw("JSON_ARRAY_APPEND(IFNULL(deleted_by, JSON_ARRAY()), '$', " . Auth::id() . ")")
    //         ]);

    //     return response()->json(['success' => true, 'message' => 'Chat deleted for you']);
    // }

}
