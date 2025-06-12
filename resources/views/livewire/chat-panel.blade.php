<div class="row">
    <div class="col-md-3 border-end p-0" style="height: 80vh;">
        <div class="d-flex flex-column h-100">

            {{-- Search Bar --}}
            <div class="p-3 border-bottom sticky-top bg-white" style="z-index: 1;">
                <input type="text" wire:model.live.debounce.250ms="search" class="form-control"
                    placeholder="Search users..." />
            </div>

            {{-- Scrollable User List --}}
            <div class="flex-grow-1 overflow-y-auto">
                @forelse($this->users as $user)
                    <div wire:click="selectUser({{ $user->id }})"
                        class="d-flex align-items-center gap-2 px-3 py-2 border-bottom {{ $selectedUser && $selectedUser->id === $user->id ? 'bg-primary text-white' : 'bg-light' }}"
                        style="cursor: pointer;">

                        {{-- User Image --}}
                        <img src="{{ 'https://ui-avatars.com/api/?name=' . urlencode($user->name) }}"
                            alt="avatar" class="rounded-circle" width="40" height="40">

                        {{-- User Name --}}
                        <div class="fw-semibold">{{ $user->name }}</div>
                    </div>
                @empty
                    <div class="text-muted text-center p-3">No users found.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-md-9 d-flex flex-column" style="height: 80vh;">

        @if ($selectedChat)
            <h5 class="mb-3">Chat with {{ $selectedUser->name }}</h5>
        @endif

        <div class="flex-grow-1 overflow-auto border-bottom p-3" id="chat-box">
            @if ($selectedChat)
                <div wire:poll="loadMessages">
                    @forelse($messages as $msg)
                        <div
                            class="d-flex mb-2 {{ $msg->user_id === auth()->id() ? 'justify-content-end' : 'justify-content-start' }}">
                            <div class="position-relative px-3 py-2 rounded"
                                style="max-width: 70%; background-color: {{ $msg->user_id === auth()->id() ? '#dcf8c6' : '#f1f0f0' }};"
                                onmouseover="this.querySelector('.delete-btn').classList.remove('d-none')"
                                onmouseout="this.querySelector('.delete-btn').classList.add('d-none')">

                                <span>{{ $msg->message }}</span>
                                <small class="text-muted d-block mt-1" style="font-size: 10px;">
                                    {{ $msg->created_at->format('h:i A') }}
                                </small>

                                @if ($msg->user_id === auth()->id())
                                    <button wire:click="deleteMessage({{ $msg->id }})"
                                        class="btn btn-sm btn-danger btn-circle delete-btn position-absolute top-0 end-0 mt-1 me-1 d-none"
                                        style="padding: 2px 6px; font-size: 10px;" title="Delete">
                                        ×
                                    </button>
                                @endif
                            </div>
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


@push('scripts')
    <script>
        function scrollToBottom() {
            const chatBox = document.getElementById('chat-box');
            if (chatBox) {
                console.log('start');
                chatBox.scrollTop = chatBox.scrollHeight;
            }
        }

        document.addEventListener('livewire:initialized', () => {
            Livewire.on('scrollToBottom', () => {
                setTimeout(() => {
                    scrollToBottom();
                }, 10); // Small delay ensures DOM updates are rendered
            });

        });
    </script>
@endpush
