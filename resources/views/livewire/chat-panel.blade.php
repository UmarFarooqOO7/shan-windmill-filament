<div class="row">
    <div class="col-md-3 border-end px-0" style="height: 90vh;">
        <div class="d-flex flex-column h-100">

            {{-- Search Bar --}}
            <div class="p-3 border-bottom sticky-top bg-white d-flex gap-2 align-items-center justify-content-between"
                style="z-index: 1;">
                <input type="text" wire:model.live.debounce.250ms="search" class="form-control"
                    placeholder="Search users..." />

                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#newChatModal">
                    <i class="fa fa-plus"></i>
                </button>
            </div>

            <!-- Modal -->
            <div class="modal fade" id="newChatModal" tabindex="-1" aria-labelledby="newChatModalLabel"
                aria-hidden="true" wire:ignore.self>
                <div class="modal-dialog modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Start New Chat</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                                wire:click="$set('showingModal', false)"></button>
                        </div>
                        <div class="modal-body">
                            <input type="text" class="form-control mb-3" wire:model.live.debounce.300ms="modalSearch"
                                placeholder="Search users..." />

                            @foreach ($this->allUsers as $user)
                                <div class="d-flex align-items-center justify-content-between border-bottom py-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="{{ 'https://ui-avatars.com/api/?name=' . urlencode($user->name) }}"
                                            class="rounded-circle" width="40" height="40">
                                        <div>{{ $user->name }}</div>
                                    </div>
                                    <button wire:click="selectUser({{ $user->id }})" class="btn btn-sm btn-primary"
                                        data-bs-dismiss="modal" wire:click="$set('showingModal', false)">Chat</button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>



            {{-- Scrollable User List --}}
            <div class="flex-grow-1 overflow-y-auto">
                @forelse($this->users as $user)
                    <div wire:click="selectUser({{ $user->id }})"
                        class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom {{ $selectedUser && $selectedUser->id === $user->id ? 'bg-primary text-white' : 'bg-light' }}"
                        style="cursor: pointer;">

                        <div class="d-flex align-items-center gap-2">
                            {{-- User Avatar --}}
                            <img src="{{ 'https://ui-avatars.com/api/?name=' . urlencode($user->name) }}" alt="avatar"
                                class="rounded-circle" width="40" height="40">

                            {{-- User Name --}}
                            <div class="fw-semibold">{{ $user->name }}</div>
                        </div>

                        {{-- Unread Badge --}}
                        @if ($user->unread_count > 0)
                            <span class="badge bg-danger rounded-pill">{{ $user->unread_count }}</span>
                        @endif
                    </div>
                @empty
                    <div class="text-muted text-center p-3">No users found.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-md-9 d-flex flex-column px-0" style="height: 90vh;">
        @if ($selectedChat)
            <div class="d-flex justify-content-between align-items-center border-bottom px-3 py-2 bg-white shadow-sm">
                <!-- Left: User Avatar + Name -->
                <div class="d-flex align-items-center gap-2">
                    <img src="{{ 'https://ui-avatars.com/api/?name=' . urlencode($selectedUser->name) }}" alt="Avatar"
                        class="rounded-circle" width="40" height="40">
                    <div class="fw-semibold">{{ $selectedUser->name }}</div>
                </div>

                <!-- Right: Dropdown -->
                <div class="dropdown" wire:ignore>
                    <a class="btn btn-light btn-sm dropdown-toggle" href="#" role="button" id="dropdownMenuLink"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        Options
                    </a>

                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuLink">
                        <li>
                            @if ($messages->isNotEmpty())
                                <a class="dropdown-item text-danger" href="#" wire:click.prevent="deleteChat">
                                    Delete Chat
                                </a>
                            @endif
                        </li>

                        {{-- <li><a class="dropdown-item text-muted" href="#">Mute</a></li> --}}
                    </ul>
                </div>
            </div>
        @endif

        <div id="chat-box" class="overflow-auto flex-grow-1 border-bottom p-3"
            wire:poll.keep-alive.5000ms="pollNewMessages" x-data="{ previousScrollHeight: 0, loading: false }"
            x-on:scroll.passive="
        if ($el.scrollTop < 50 && @this.hasMoreMessages && !loading) {
            loading = true;
            previousScrollHeight = $el.scrollHeight;
            @this.call('loadMoreMessages').then(() => {
                // After Livewire updates DOM
                $nextTick(() => {
                    const newScrollHeight = $el.scrollHeight;
                    $el.scrollTop = newScrollHeight - previousScrollHeight - 20;
                    loading = false;
                });
            });
        }
    ">

            @if ($selectedChat)
                <div>
                    @forelse($messages as $msg)
                        @php
                            $attachments = $msg->attachment ? json_decode($msg->attachment, true) : [];
                        @endphp

                        {{-- Message Bubble --}}
                        <div
                            class="d-flex mb-2 {{ $msg->user_id === auth()->id() ? 'justify-content-end' : 'justify-content-start' }}">
                            <div class="position-relative px-3 py-2 rounded"
                                style="max-width: 70%; background-color: {{ $msg->user_id === auth()->id() ? '#dcf8c6' : '#f1f0f0' }};"
                                onmouseover="this.querySelector('.delete-btn').classList.remove('d-none')"
                                onmouseout="this.querySelector('.delete-btn').classList.add('d-none')">

                                {{-- Message Text --}}
                                @if ($msg->message)
                                    <div class="mb-2">
                                        {{ $msg->message }}
                                    </div>
                                @endif

                                {{-- Files Block --}}
                                @foreach ($attachments as $file)
                                    @php
                                        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                        $fileUrl = asset('storage/' . $file);
                                        $fileName = basename($file);
                                    @endphp

                                    <div class="p-2 mb-2 rounded shadow-sm bg-white"
                                        style="background-color: #f8f9fa; max-width: 300px;">
                                        @if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                                            <a href="{{ $fileUrl }}" target="_blank">
                                                <img src="{{ $fileUrl }}" class="img-fluid rounded"
                                                    style="max-height: 200px;" />
                                            </a>
                                        @elseif($extension === 'pdf')
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="fa-solid fa-file-pdf text-danger fs-3"></i>
                                                <div>
                                                    <div class="fw-semibold">{{ $fileName }}</div>
                                                    <a href="{{ $fileUrl }}" target="_blank"
                                                        class="btn btn-sm btn-outline-primary mt-1">Open</a>
                                                </div>
                                            </div>
                                        @elseif(in_array($extension, ['doc', 'docx']))
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="fa-solid fa-file-word text-primary fs-3"></i>
                                                <div>
                                                    <div class="fw-semibold">{{ $fileName }}</div>
                                                    <a href="{{ $fileUrl }}" target="_blank"
                                                        class="btn btn-sm btn-outline-primary mt-1">Open</a>
                                                </div>
                                            </div>
                                        @elseif(in_array($extension, ['xls', 'xlsx', 'csv']))
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="fa-solid fa-file-excel text-success fs-3"></i>
                                                <div>
                                                    <div class="fw-semibold">{{ $fileName }}</div>
                                                    <a href="{{ $fileUrl }}" target="_blank"
                                                        class="btn btn-sm btn-outline-success mt-1">Open</a>
                                                </div>
                                            </div>
                                        @else
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="fa-solid fa-file text-secondary fs-3"></i>
                                                <div>
                                                    <div class="fw-semibold">{{ $fileName }}</div>
                                                    <a href="{{ $fileUrl }}" target="_blank"
                                                        class="btn btn-sm btn-outline-secondary mt-1">Open</a>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach

                                <div class="d-flex align-items-center justify-content-between gap-3">
                                    {{-- Time --}}
                                    <small class="text-muted d-block mt-1" style="font-size: 10px;">
                                        {{ $msg->created_at->format('h:i A') }}
                                    </small>

                                    {{-- double / single tick --}}
                                    @if ($msg->user_id === auth()->id())
                                        <div class="text-end text-muted small mt-1">
                                            @if ($msg->is_read)
                                                <i class="fa-solid fa-check-double text-primary"></i>
                                                {{-- Read (✓✓ blue) --}}
                                            @else
                                                <i class="fa-solid fa-check"></i> {{-- Sent (✓) --}}
                                            @endif
                                        </div>
                                    @endif
                                </div>


                                {{-- Delete Button --}}
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
                <div class="text-muted text-center">Select a user to chat with.</div>
            @endif
        </div>


        @if ($selectedChat)
            <form wire:submit.prevent="sendMessage" class="d-flex flex-column gap-2 p-3"
                enctype="multipart/form-data">

                {{-- PREVIEW MULTIPLE FILES --}}
                @if ($mediaFiles && count($mediaFiles))
                    <div class="d-flex gap-2 flex-wrap px-3 pb-2">
                        @foreach ($mediaFiles as $index => $file)
                            @php
                                $mime = $file->getMimeType();
                                $originalName = $file->getClientOriginalName();
                                $shortName =
                                    strlen($originalName) > 15
                                        ? substr($originalName, 0, 10) .
                                            '...' .
                                            pathinfo($originalName, PATHINFO_EXTENSION)
                                        : $originalName;

                                if (Str::startsWith($mime, 'image')) {
                                    $icon = null;
                                } elseif (Str::contains($mime, 'pdf')) {
                                    $icon = 'fa-file-pdf text-danger';
                                } elseif (Str::contains($mime, ['msword', 'wordprocessingml'])) {
                                    $icon = 'fa-file-word text-primary';
                                } elseif (Str::contains($mime, ['excel', 'spreadsheetml'])) {
                                    $icon = 'fa-file-excel text-success';
                                } elseif (Str::contains($mime, 'csv')) {
                                    $icon = 'fa-file-csv text-info';
                                } else {
                                    $icon = 'fa-file text-secondary';
                                }
                            @endphp

                            <div class="position-relative text-center" style="width: 80px;">
                                @if ($icon)
                                    <div class="bg-light border rounded d-flex align-items-center justify-content-center"
                                        style="height: 60px;">
                                        <i class="fa-solid {{ $icon }} fs-3"></i>
                                    </div>
                                @else
                                    <img src="{{ $file->temporaryUrl() }}" class="img-thumbnail"
                                        style="width: 100%; height: 60px; object-fit: cover;">
                                @endif

                                <!-- File name below -->
                                <small class="d-block text-truncate mt-1" title="{{ $originalName }}"
                                    style="font-size: 11px;">
                                    {{ $shortName }}
                                </small>

                                <!-- Close button -->
                                <button type="button"
                                    class="btn btn-sm btn-close position-absolute top-0 end-0 bg-dark text-white rounded-circle p-1"
                                    style="width: 14px; height: 14px; font-size: 11px;"
                                    wire:click="removeMedia({{ $index }})"> x
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif


                {{-- File Upload Input --}}
                <div class="d-flex align-items-center gap-2">
                    <label class="btn btn-outline-secondary btn-sm mb-0">
                        <i class="fa-solid fa-paperclip"></i>
                        <input type="file" wire:model="mediaFiles" multiple hidden>
                    </label>

                    <input type="text" wire:model="newMessage" class="form-control me-2"
                        placeholder="Type your message..." />

                    <button class="btn btn-primary" type="submit">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>

                {{-- Error and Uploading --}}
                <div wire:loading wire:target="mediaFiles" class="text-muted small">Uploading...</div>
                @error('mediaFiles.*')
                    <span class="text-danger small">{{ $message }}</span>
                @enderror
            </form>
        @endif

    </div>

    <audio id="sendSound" src="{{ asset('sounds/send-aud.mp3') }}"></audio>
    <audio id="receiveSound" src="{{ asset('sounds/rec-aud.mp3') }}"></audio>
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

                    // 🔊 Play send sound as part of scroll (sender only)
                    const snd = document.getElementById('sendSound');
                    if (snd) snd.play();

                }, 10); // Small delay ensures DOM updates are rendered
            });
        });

        // document.addEventListener('livewire:initialized', () => {
        //     Livewire.on('playReceiveSound', () => {
        //         const sound = document.getElementById('receiveSound');
        //         if (sound) sound.play();
        //     });
        // });
    </script>
@endpush
