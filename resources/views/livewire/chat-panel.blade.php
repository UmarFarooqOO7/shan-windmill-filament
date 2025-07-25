<div class="row gy-3 flex-column flex-md-row">
    <div class="col-12 col-md-3 border-end px-0" style="height: 92vh;">
        <div class="d-flex flex-column h-100">

            {{-- Go to Dashboard Button --}}
            <div class="mx-3 d-flex justify-content-between align-items-center">
                <a href="{{ url('/admin') }}" class="btn btn-sm btn-outline-success-app w-100 py-2">
                    <i class="fa fa-arrow-left me-1"></i> Dashboard
                </a>
            </div>
            {{-- Search Bar --}}
            <div class="p-3 border-bottom sticky-top bg-white d-flex gap-2 align-items-center justify-content-between"
                style="z-index: 1;">
                <input type="text" wire:model.live.debounce.250ms="search" class="form-control"
                    placeholder="Search {{ $activeTab === 'teams' ? 'teams' : 'users' }}..." />

                <button class="btn btn-sm btn-outline-success-app" data-bs-toggle="modal"
                    data-bs-target="#newChatModal">
                    <i class="fa fa-plus"></i>
                </button>
            </div>

            {{-- Tabs --}}
            <div class="d-flex">
                <button class="btn w-50 rounded-0 {{ $activeTab === 'teams' ? 'btn-outline-success-app' : '' }}"
                    wire:click="$set('activeTab', 'teams')">
                    <i class="fa fa-users text-success"></i> Teams
                </button>
                <button class="btn w-50 rounded-0 {{ $activeTab === 'users' ? 'btn-outline-success-app' : '' }}"
                    wire:click="$set('activeTab', 'users')">
                    <i class="fa fa-user text-success"></i> Chats
                </button>
            </div>

            {{-- User / Team List --}}
            <div class="flex-grow-1 overflow-y-auto">
                @if ($activeTab === 'users')
                    @forelse($this->users as $user)
                        <div wire:click="selectUser({{ $user->id }})"
                            class="user-container d-flex align-items-center justify-content-between px-3 py-2 border-bottom {{ $selectedUser && $selectedUser->id === $user->id ? 'bg-default-app' : '' }}"
                            style="cursor: pointer; border-top: 1px solid #059669">

                            {{-- Left: Avatar + Name + Message Preview --}}
                            <div class="d-flex align-items-center gap-3" style="width: 80%;">
                                {{-- Avatar + Online Dot --}}
                                <div class="position-relative">
                                    <img src="{{ 'https://ui-avatars.com/api/?name=' . urlencode($user->name) }}"
                                        class="rounded-circle" width="45" height="45">
                                    <span
                                        class="position-absolute bottom-0 end-0 translate-middle p-1 border border-white rounded-circle"
                                        style="background-color: {{ $user->is_online ? '#16a34a' : '#f97316' }}; width: 10px; height: 10px;">
                                    </span>
                                </div>

                                {{-- Name + Latest Message --}}
                                <div class="d-flex flex-column">
                                    <div class="fw-semibold">{{ $user->name }}</div>

                                    @foreach ($user->receivedMessages as $message)
                                        <div class="text-muted small text-truncate" style="max-width: 200px;">
                                            {{ \Illuminate\Support\Str::limit($message->message, 30) }}
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Right: Time + Unread Count --}}
                            <div class="text-end">
                                @if ($user->latestMessageWithAuth)
                                @endif

                                @foreach ($user->receivedMessages as $message)
                                    <div class="text-muted small">
                                        {{ $message->created_at->format('g:i a') }}
                                    </div>
                                @endforeach

                                @if ($user->unread_count > 0)
                                    <span class="badge bg-success rounded-pill">{{ $user->unread_count }}</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-muted text-center p-3">No users found.</div>
                    @endforelse
                @else
                    @forelse($this->teams as $team)
                        <div wire:click="openTeamChat({{ $team->id }})"
                            class="user-container d-flex align-items-center justify-content-between px-3 py-2 border-bottom {{ $selectedChat && $selectedChat->team_id === $team->id ? 'bg-default-app' : '' }}"
                            style="cursor: pointer; border-top: 1px solid #059669">

                            <div class="d-flex flex-column gap-1" style="width: 80%;">
                                <div class="fw-semibold d-flex align-items-center gap-2">
                                    <i class="fa fa-users text-success"></i> {{ $team->name }}
                                </div>

                                @if ($team->latestMessage)
                                    <div class="text-muted small text-truncate" style="max-width: 200px;">
                                        @if ($team->latestMessage->user_id === auth()->id())
                                            You:
                                        @else
                                            {{ $team->latestMessage->user->name }}:
                                        @endif

                                        {{ \Illuminate\Support\Str::limit($team->latestMessage->message, 30) }}
                                    </div>
                                @endif
                            </div>

                            <div class="text-end">
                                @if ($team->latestMessage)
                                    <div class="text-muted small">
                                        {{ $team->latestMessage->created_at->format('g:i a') }}
                                    </div>
                                @endif

                                @if ($team->unread_count > 0)
                                    <span class="badge bg-danger rounded-pill">{{ $team->unread_count }}</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-muted text-center p-3">No teams found.</div>
                    @endforelse

                @endif
            </div>


            <!-- Modal -->
            <div class="modal fade" id="newChatModal" tabindex="-1" aria-labelledby="newChatModalLabel"
                aria-hidden="true" wire:ignore.self>
                <div class="modal-dialog modal-fullscreen-sm-down modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Start New Chat</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                                wire:click="$set('showingModal', false)"></button>
                        </div>
                        <div class="modal-body p-0">

                            {{-- Search --}}
                            <div class="p-3 border-bottom">
                                <input type="text" class="form-control" wire:model.live.debounce.300ms="modalSearch"
                                    placeholder="Search {{ $activeTab === 'teams' ? 'teams' : 'users' }}..." />
                            </div>

                            {{-- List --}}
                            <div class="p-3">
                                @if ($activeTab === 'users')
                                    @forelse ($this->filteredModalUsers as $user)
                                        <div
                                            class="d-flex align-items-center justify-content-between border-bottom py-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <img src="{{ 'https://ui-avatars.com/api/?name=' . urlencode($user->name) }}"
                                                    class="rounded-circle" width="40" height="40">
                                                <div>{{ $user->name }}</div>
                                            </div>
                                            <button wire:click="selectUser({{ $user->id }})"
                                                class="btn btn-sm btn-outline-success-app" data-bs-dismiss="modal"
                                                wire:click="$set('showingModal', false)">Chat</button>
                                        </div>
                                    @empty
                                        <div class="text-muted text-center">No users found.</div>
                                    @endforelse
                                @else
                                    @forelse ($this->filteredModalTeams as $team)
                                        <div
                                            class="d-flex align-items-center justify-content-between border-bottom py-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="fa-solid fa-users text-success"></i>
                                                <div>{{ $team->name }}</div>
                                            </div>
                                            <button wire:click="openTeamChat({{ $team->id }})"
                                                class="btn btn-sm btn-outline-success-app" data-bs-dismiss="modal"
                                                wire:click="$set('showingModal', false)">Group Chat</button>
                                        </div>
                                    @empty
                                        <div class="text-muted text-center">No teams found.</div>
                                    @endforelse
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>


        </div>
    </div>

    <div class="col-12 col-md-9 d-flex flex-column px-0" style="height: 92vh;">
        <div class="d-flex justify-content-between align-items-center border-bottom px-3 py-2 bg-white shadow-sm">

            {{-- chat user/team name + notifications --}}
            <div class="d-flex align-items-center gap-2">
                @if ($selectedChat)
                    <!-- Left: Avatar + Name (User or Team) -->
                    <div class="d-flex align-items-center gap-2">
                        @if ($selectedChat->team_id)
                            <i class="fa fa-users text-success fs-4"></i>
                            <div class="fw-semibold">{{ $selectedChat->team->name }}</div>
                        @elseif (isset($selectedUser))
                            <img src="{{ 'https://ui-avatars.com/api/?name=' . urlencode($selectedUser->name) }}"
                                alt="Avatar" class="rounded-circle" width="40" height="40">
                            <div>
                                <div class="fw-semibold">{{ $selectedUser->name }}</div>
                                <div class="d-flex align-items-center gap-1">
                                    <span class="rounded-circle"
                                        style="width: 8px; height: 8px; background-color: {{ $selectedUser->is_online ? '#22c55e' : '#f97316' }};"></span>
                                    <small class="text-muted">
                                        {{ $selectedUser->is_online ? 'Online' : 'Offline' }}
                                    </small>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <!-- notifi + delete chat  -->
            <div class="d-flex align-items-center gap-2">

                <div class="relative me-2" x-data="{ open: false }">
                    <button class="btn btn-outline-secondary btn-sm position-relative" @click="open = !open"
                        @click.outside="open = false">
                        <i class="fa fa-bell"></i>

                        @if ($unreadCount)
                            <span
                                class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                {{ $unreadCount }}
                            </span>
                        @endif
                    </button>

                    <div x-show="open" x-cloak x-transition
                        class="absolute end-0 mt-2 z-50 bg-white shadow rounded border"
                        style="width: 300px; max-height: 300px; overflow-y: auto;">
                        @if ($unreadCount)
                            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                                <span class="fw-semibold">Notifications</span>
                                <button wire:click.prevent="markAllNotificationsAsRead"
                                    class="btn btn-link btn-sm p-0 text-primary" x-on:click.stop>
                                    Mark all as read
                                </button>
                            </div>
                        @endif

                        @forelse($notifications as $notification)
                            <div class="px-3 py-2 border-bottom small d-flex justify-content-between align-items-start gap-2"
                                style="cursor: pointer;"
                                wire:click.prevent="markNotificationAsRead('{{ $notification->id }}')"
                                x-on:click.stop>
                                <div style="max-width: 80%;">
                                    <div class="fw-semibold mb-1">
                                        {{ $notification->data['sender_name'] ?? 'Unknown' }}
                                    </div>
                                    <div class="text-muted text-truncate small"
                                        title="{{ $notification->data['message'] ?? '📎 File Received' }}">
                                        {{ \Illuminate\Support\Str::limit($notification->data['message'] ?: '📎 File Received', 30) }}
                                    </div>
                                </div>
                                <small class="text-muted text-nowrap">
                                    {{ \Carbon\Carbon::parse($notification->created_at)->diffForHumans() }}
                                </small>
                            </div>
                        @empty
                            <div class="px-3 py-2 text-muted text-center">No new notifications</div>
                        @endforelse
                    </div>
                </div>

                @if ($selectedChat && $messages->isNotEmpty())
                    <div x-data="{ open: false }" class="position-relative me-3">
                        <!-- Three Dots Button -->
                        <button class="btn btn-sm btn-outline-secondary" @click="open = !open"
                            @click.outside="open = false">
                            <i class="fa fa-ellipsis-v"></i>
                        </button>

                        <!-- Dropdown -->
                        <div x-show="open" x-cloak x-transition
                            class="dropdown-menu dropdown-menu-end show shadow position-absolute"
                            style="top: 100%; right: 0; z-index: 1000; display: block; min-width: 180px;">

                            @if ($selectedChat->team_id)
                                <a href="{{ url('admin/teams/' . $selectedChat->team_id) }}" class="dropdown-item">
                                    <i class="fa fa-eye me-1 text-primary"></i> View Team
                                </a>
                            @endif

                            <a href="#" class="dropdown-item text-danger"
                                @click.prevent="
                    Swal.fire({
                        title: 'Are you sure?',
                        text: 'This will permanently delete this chat!',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Yes, delete it!'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            @this.call('deleteChat');
                            open = false;
                        }
                    });
                ">
                                <i class="fa fa-trash me-1"></i> Delete Chat
                            </a>
                        </div>
                    </div>
                @endif

            </div>
        </div>

        <!-- Scrollable Messages -->
        <div id="chat-box" class="overflow-auto flex-grow-1 border-bottom p-3"
            wire:poll.keep-alive.10s="pollNewMessages" x-data="{ previousScrollHeight: 0, loading: false }"
            x-on:scroll.passive="
        if ($el.scrollTop < 50 && @this.hasMoreMessages && !loading) {
            loading = true;
            previousScrollHeight = $el.scrollHeight;
            @this.call('loadMoreMessages').then(() => {
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
                            $attachments = $msg->attachments ? json_decode($msg->attachments, true) : [];
                            $isMine = $msg->user_id === auth()->id();
                        @endphp

                        {{-- Message Bubble --}}
                        <div wire:key="msg-{{ $msg->id }}"
                            class="d-flex mb-2 {{ $msg->user_id === auth()->id() ? 'justify-content-end' : 'justify-content-start' }}">
                            <div class="position-relative px-3 py-2 rounded"
                                style="max-width: 70%; background-color: {{ $msg->user_id === auth()->id() ? '#dcf8c6' : '#f1f0f0' }};"
                                onmouseover="this.querySelector('.delete-btn').classList.remove('d-none')"
                                onmouseout="this.querySelector('.delete-btn').classList.add('d-none')">

                                {{-- Show Sender's Name in Team Chat --}}
                                @if ($selectedChat?->team_id && !$isMine)
                                    <div class="fw-semibold text-primary mb-1" style="font-size: 13px;">
                                        {{ $msg->user->name }}
                                    </div>
                                @endif

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
                                                        class="btn btn-sm btn-outline-success-app mt-1">Open</a>
                                                </div>
                                            </div>
                                        @elseif(in_array($extension, ['doc', 'docx']))
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="fa-solid fa-file-word text-primary fs-3"></i>
                                                <div>
                                                    <div class="fw-semibold">{{ $fileName }}</div>
                                                    <a href="{{ $fileUrl }}" target="_blank"
                                                        class="btn btn-sm btn-outline-success-app mt-1">Open</a>
                                                </div>
                                            </div>
                                        @elseif(in_array($extension, ['xls', 'xlsx', 'csv']))
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="fa-solid fa-file-excel text-success fs-3"></i>
                                                <div>
                                                    <div class="fw-semibold">{{ $fileName }}</div>
                                                    <a href="{{ $fileUrl }}" target="_blank"
                                                        class="btn btn-sm btn-outline-success-app mt-1">Open</a>
                                                </div>
                                            </div>
                                        @elseif($extension === 'mp3' || $extension === 'm4a')
                                            <div x-data="audioPlayer('{{ $fileUrl }}')"
                                                class="d-flex align-items-center gap-2 p-2 bg-white rounded shadow-sm"
                                                style="width: 250px;">
                                                <button @click="toggle()"
                                                    class="btn btn-sm btn-outline-primary rounded-circle"
                                                    style="width: 32px; height: 32px;">
                                                    <i :class="playing ? 'fa-pause' : 'fa-play'" class="fa-solid"></i>
                                                </button>

                                                <div class="flex-grow-1">
                                                    <div class="progress mb-1" style="height: 4px;">
                                                        <div class="progress-bar bg-success" role="progressbar"
                                                            :style="{ width: progress + '%' }"></div>
                                                    </div>
                                                    <div class="d-flex justify-content-between small text-muted"
                                                        style="font-size: 10px;">
                                                        <span x-text="currentTimeDisplay">0:00</span>
                                                        <span x-text="durationDisplay">0:00</span>
                                                    </div>
                                                </div>

                                                <audio x-ref="audio" :src="src"
                                                    preload="metadata"></audio>
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

                                {{-- Footer: Time & Read --}}
                                <div class="d-flex align-items-center justify-content-between gap-3">
                                    <small class="text-muted d-block mt-1" style="font-size: 10px;">
                                        {{ $msg->created_at->format('h:i A') }}
                                    </small>

                                    @if ($isMine)
                                        <div class="text-end text-muted small mt-1">
                                            @if ($msg->is_read)
                                                <i class="fa-solid fa-check-double text-primary"></i>
                                            @else
                                                <i class="fa-solid fa-check"></i>
                                            @endif
                                        </div>
                                    @endif
                                </div>



                                {{-- Delete Button --}}
                                @if ($isMine)
                                    <button x-data
                                        @click.prevent="
                                            Swal.fire({
                                                title: 'Are you sure?',
                                                text: 'You will not be able to recover this message!',
                                                icon: 'warning',
                                                showCancelButton: true,
                                                confirmButtonColor: '#d33',
                                                cancelButtonColor: '#3085d6',
                                                confirmButtonText: 'Yes, delete it!'
                                            }).then((result) => {
                                                if (result.isConfirmed) {
                                                    @this.call('deleteMessage', {{ $msg->id }});
                                                }
                                            });
                                        "
                                        class="btn btn-sm btn-danger btn-circle delete-btn position-absolute top-0 end-0 mt-1 me-1 d-none"
                                        style="padding: 2px 6px; font-size: 10px;" title="Delete">
                                        ×
                                    </button>
                                @endif


                            </div>
                        </div>

                    @empty
                        <div
                            class="d-flex flex-column justify-content-center align-items-center text-muted text-center my-5">
                            <i class="fa-regular fa-paper-plane fs-1 text-secondary mb-3"></i>
                            <h6 class="fw-semibold">No messages yet</h6>
                            <p class="mb-0">Start the conversation by sending a message!</p>
                        </div>
                    @endforelse

                </div>
            @else
                <div class="d-flex flex-column justify-content-center align-items-center text-muted text-center h-100">
                    <i class="fa-regular fa-comments fs-1 mb-3 text-primary"></i>
                    <h5 class="fw-semibold">No chat selected</h5>
                    <p class="mb-0">Please select a user from the left to start chatting.</p>
                </div>
            @endif

        </div>


        @if ($selectedChat)
            <form wire:submit.prevent="sendMessage" class="d-flex flex-column gap-2 p-3"
                enctype="multipart/form-data">

                <div wire:loading wire:target="mediaFiles" class="text-muted small">Uploading...</div>

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
                                    style="width: 13px; height: 13px; font-size: 10px;"
                                    wire:click="removeMedia({{ $index }})"> x
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- 📎 FILE INPUT, EMOJI PICKER & MESSAGE INPUT --}}
                <div x-data="{ show: false }" class="d-flex align-items-center gap-2 w-100 position-relative">

                    {{-- 📎 Attachment --}}
                    <label class="btn btn-outline-secondary btn-sm mb-0">
                        <i class="fa-solid fa-paperclip"></i>
                        <input type="file" wire:model="mediaFiles" multiple hidden>
                    </label>

                    <!-- Emoji Toggle Button -->
                    <button type="button" @click="show = !show" class="btn btn-outline-secondary btn-sm mb-0">
                        😊
                    </button>

                    {{-- 🧠 Text Input --}}
                    <input type="text" wire:model.defer="newMessage" class="form-control"
                        placeholder="Type your message..." />

                    {{-- ✈️ Submit --}}
                    <button wire:loading.attr="disabled" wire:target="mediaFiles" class="btn btn-primary"
                        type="submit">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>

                    <!-- Emoji Picker -->
                    <div x-show="show" @click.outside="show = false"
                        class="position-absolute bottom-100 mb-2 bg-white border rounded shadow p-2 z-10"
                        style="max-height: 200px; overflow-y: auto; width: 250px;">
                        @foreach (['😀', '😁', '😂', '🤣', '😃', '😄', '😅', '😆', '😉', '😊', '😍', '😘', '😗', '😜', '🤪', '😎', '🤩', '🥳', '😏', '😒', '😞', '😔', '😢', '😭', '😤', '😡', '🤔', '🤨', '😐', '😶', '🙄', '😴', '😷', '🤐', '🤯', '😬', '💪', '👏', '🙌', '🙏', '👍', '👎', '👌', '🤝', '🤙', '🖐️', '❤️', '💔', '💖', '💘', '💕', '💞', '🎉', '🎊', '🎁', '🎈', '✨', '💥', '🔥', '🌟', '🐶', '🐱', '🦁', '🐯', '🐰', '🐼', '🐸', '🍎', '🍌', '🍇', '🍓', '🍕', '🍔', '🍟', '🌮', '🍩', '🍪', '🌞', '🌛', '🌍', '🌈', '☔', '⚡'] as $emoji)
                            <button type="button" class="btn btn-light btn-sm m-1"
                                wire:click="appendEmoji('{{ $emoji }}')">
                                {{ $emoji }}
                            </button>
                        @endforeach
                    </div>

                </div>



                {{-- Error and Uploading --}}
                @error('mediaFiles.*')
                    <span class="text-danger small">{{ $message }}</span>
                @enderror
            </form>
        @endif

    </div>

    <audio id="sendSound" src="{{ asset('sounds/send-aud.mp3') }}"></audio>
    <audio id="deleteSound" src="{{ asset('sounds/delete-aud.mp3') }}"></audio>
    <audio id="recSound" src="{{ asset('sounds/rec-audio.mp3') }}"></audio>
</div>

@push('scripts')
    <script>
        function audioPlayer(src) {
            return {
                src,
                playing: false,
                progress: 0,
                currentTimeDisplay: '0:00',
                durationDisplay: '0:00',

                toggle() {
                    const audio = this.$refs.audio;

                    // Pause all other players
                    document.querySelectorAll('audio').forEach(el => {
                        if (el !== audio) {
                            el.pause();
                            el.currentTime = 0;

                            // update play button icons manually
                            const wrapper = el.closest('[x-data]');
                            if (wrapper && wrapper.__x) {
                                wrapper.__x.$data.playing = false;
                                wrapper.__x.$data.progress = 0;
                                wrapper.__x.$data.currentTimeDisplay = '0:00';
                            }
                        }
                    });

                    if (audio.paused) {
                        audio.play();
                        this.playing = true;
                    } else {
                        audio.pause();
                        this.playing = false;
                    }

                    audio.ontimeupdate = () => {
                        this.progress = (audio.currentTime / audio.duration) * 100;
                        this.currentTimeDisplay = this.formatTime(audio.currentTime);
                    };

                    audio.onloadedmetadata = () => {
                        this.durationDisplay = this.formatTime(audio.duration);
                    };

                    audio.onended = () => {
                        this.playing = false;
                        this.progress = 0;
                        this.currentTimeDisplay = '0:00';
                    };
                },

                formatTime(seconds) {
                    const m = Math.floor(seconds / 60);
                    const s = Math.floor(seconds % 60);
                    return `${m}:${s < 10 ? '0' : ''}${s}`;
                }
            };
        }

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
                }, 10);
            });

            Livewire.on('playSendTune', () => {
                setTimeout(() => {
                    scrollToBottom();

                    // 🔊 Play send sound as part of scroll (sender only)
                    const snd = document.getElementById('sendSound');
                    if (snd) snd.play();

                }, 10); // Small delay ensures DOM updates are rendered
            });

            Livewire.on('playDeleteTune', () => {
                // 🔊 Play send sound as part of scroll (sender only)
                const del = document.getElementById('deleteSound');
                if (del) del.play();
            });

            Livewire.on('new-message-received', () => {
                // 🔊 Play send sound as part of scroll (sender only)
                const rec = document.getElementById('recSound');
                if (rec) rec.play();
            });
        });
    </script>
@endpush
