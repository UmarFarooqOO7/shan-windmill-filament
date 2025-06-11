<div class="row">
    <div class="col-md-3 border-end" style="height: 80vh; overflow-y: auto;">
        <h5>Users</h5>
        @foreach ($users as $user)
            <div wire:click="selectUser({{ $user->id }})"
                class="py-2 px-3 {{ $selectedUser && $selectedUser->id === $user->id ? 'bg-primary text-white' : '' }}"
                style="cursor: pointer;">
                {{ $user->name }}
            </div>
        @endforeach
    </div>

    <div class="col-md-9 d-flex flex-column" style="height: 80vh;">
        <div class="flex-grow-1 overflow-auto border-bottom p-3">
            @if ($selectedChat)
                <h5>Chat with {{ $selectedUser->name }}</h5>
                <div wire:poll.3s>
                    @forelse($messages as $msg)
                        <div class="my-2">
                            <strong>{{ $msg->user_id === auth()->id() ? 'You' : $msg->user->name }}:</strong>
                            {{ $msg->message }}
                        </div>
                    @empty
                        <div class="text-muted">No messages yet.</div>
                    @endforelse
                </div>
            @else
                <div class="text-muted">Select a user to chat with.</div>
            @endif
        </div>

        @if ($selectedChat)
            <form wire:submit.prevent="sendMessage" class="d-flex p-3">
                <input type="text" wire:model.defer="newMessage" class="form-control me-2"
                    placeholder="Type a message..." />
                <button class="btn btn-primary" type="submit">Send</button>
            </form>
        @endif
    </div>
</div>
