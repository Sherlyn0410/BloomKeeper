@php
    $conversation ??= null;
    $initialsOf = fn ($person) => Str::of($person->name)->explode(' ')->filter()->take(2)->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->join('');
    $roleTones = [
        'admin' => 'bg-[#d5e4e1] text-[#285443]',
        'staff' => 'bg-[#fff0d5] text-[#a76513]',
        'supplier' => 'bg-[#e1e9f8] text-[#3d5a99]',
    ];
@endphp

<x-workspace.layout
    title="Messages"
    description="Chat directly with admins, staff, and suppliers."
>
    <section class="bk-card overflow-hidden p-0 sm:p-0">
        <div class="grid h-[calc(100vh-15rem)] min-h-[28rem] lg:grid-cols-[19rem_minmax(0,1fr)]">
            {{-- Conversation list: hidden on phones while a chat is open --}}
            <aside @class(['min-h-0 flex-col border-[#dce9e6] lg:flex lg:border-r', 'hidden' => $conversation, 'flex' => ! $conversation])>
                <form method="POST" action="{{ route('messages.start') }}" class="flex gap-2 border-b border-[#dce9e6] p-4">
                    @csrf
                    <label for="new-message-user" class="sr-only">New message to</label>
                    <select id="new-message-user" name="user_id" required class="bk-input min-w-0 flex-1">
                        <option value="">New message to…</option>
                        @foreach ($contacts->groupBy('role') as $role => $people)
                            <optgroup label="{{ ucfirst($role) }}">
                                @foreach ($people as $person)
                                    <option value="{{ $person->id }}">{{ $person->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <button class="bk-btn bk-btn-primary shrink-0">Start</button>
                </form>

                <nav class="min-h-0 flex-1 overflow-y-auto" aria-label="Conversations">
                    @forelse ($conversations as $item)
                        @php
                            $isOpen = $conversation?->id === $item['conversation']->id;
                            $latest = $item['conversation']->latestMessage;
                        @endphp
                        <a
                            href="{{ route('messages.show', $item['conversation']) }}"
                            @if ($isOpen) aria-current="page" @endif
                            @class([
                                'flex items-start gap-3 border-b border-[#eef4f2] px-4 py-3 transition',
                                'bg-[#e3f1ee]' => $isOpen,
                                'hover:bg-[#f2f8f6]' => ! $isOpen,
                            ])
                        >
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#285443] text-xs font-bold text-white">{{ $initialsOf($item['other']) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center justify-between gap-2">
                                    <span @class(['truncate text-sm text-[#1c2e28]', 'font-bold' => $item['unread'] > 0, 'font-semibold' => $item['unread'] === 0])>{{ $item['other']->name }}</span>
                                    <span class="shrink-0 text-[11px] text-[#8aa39c]">{{ $item['conversation']->last_message_at?->timezone('Asia/Kuala_Lumpur')->format('d M') }}</span>
                                </span>
                                <span class="mt-0.5 flex items-center justify-between gap-2">
                                    <span @class(['truncate text-xs', 'font-semibold text-[#203d37]' => $item['unread'] > 0, 'text-[#607a72]' => $item['unread'] === 0])>
                                        {{ $latest?->user_id === auth()->id() ? 'You: ' : '' }}{{ $latest?->preview() }}
                                    </span>
                                    @if ($item['unread'] > 0)
                                        <span class="shrink-0 rounded-full bg-[#b84331] px-1.5 text-[11px] font-bold text-white">{{ $item['unread'] }}</span>
                                    @endif
                                </span>
                            </span>
                        </a>
                    @empty
                        <p class="p-6 text-center text-sm text-[#607a72]">No conversations yet. Choose someone above to start one.</p>
                    @endforelse
                </nav>
            </aside>

            {{-- Open chat --}}
            @if ($conversation)
                <div class="flex min-h-0 flex-col">
                    <header class="flex items-center gap-3 border-b border-[#dce9e6] px-4 py-3">
                        <a href="{{ route('messages.index') }}" class="flex size-8 items-center justify-center rounded-full text-[#285443] hover:bg-[#e3f1ee] lg:hidden" aria-label="Back to conversations">
                            <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" /></svg>
                        </a>
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#285443] text-xs font-bold text-white">{{ $initialsOf($otherUser) }}</span>
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-[#1c2e28]">{{ $otherUser->name }}</p>
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $roleTones[$otherUser->role] ?? 'bg-[#dce9e6] text-[#49665f]' }}">{{ ucfirst($otherUser->role) }}</span>
                        </div>
                    </header>

                    <div
                        id="chat-log"
                        class="min-h-0 flex-1 space-y-2 overflow-y-auto bg-[#f4faf8] px-4 py-4"
                        data-poll-url="{{ route('messages.poll', $conversation) }}"
                        data-last-id="{{ $messages->last()?->id ?? 0 }}"
                        aria-live="polite"
                    >
                        @forelse ($messages as $message)
                            @php $chat = $message->toChatArray(auth()->user()); @endphp
                            <div @class(['flex', 'justify-end' => $chat['mine']]) data-message-id="{{ $chat['id'] }}">
                                <div @class([
                                    'max-w-[80%] rounded-2xl px-3.5 py-2 text-sm',
                                    'rounded-br-md bg-[#285443] text-white' => $chat['mine'],
                                    'rounded-bl-md bg-white text-[#1c2e28] shadow-sm' => ! $chat['mine'],
                                ])>
                                    @if ($chat['image_url'])
                                        <a href="{{ $chat['image_url'] }}" target="_blank" rel="noopener" class="mb-1 block">
                                            <img src="{{ $chat['image_url'] }}" alt="Photo from {{ $chat['mine'] ? 'you' : $otherUser->name }}" loading="lazy" class="max-h-64 max-w-full rounded-xl object-cover">
                                        </a>
                                    @endif
                                    @if ($chat['body'])
                                        <p class="whitespace-pre-wrap break-words">{{ $chat['body'] }}</p>
                                    @endif
                                    <p @class(['mt-1 text-right text-[10px]', 'text-white/70' => $chat['mine'], 'text-[#8aa39c]' => ! $chat['mine']])>{{ $chat['time'] }}</p>
                                </div>
                            </div>
                        @empty
                            <p id="chat-empty" class="pt-10 text-center text-sm text-[#607a72]">Say hello to {{ $otherUser->name }}.</p>
                        @endforelse
                    </div>

                    <form id="chat-form" method="POST" action="{{ route('messages.store', $conversation) }}" enctype="multipart/form-data" class="border-t border-[#dce9e6] p-3">
                        @csrf
                        <div id="chat-attachment" class="mb-2 hidden items-center gap-2 rounded-xl bg-[#edf7f5] px-3 py-1.5 text-xs text-[#285443]">
                            <svg class="size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M1 5.25A2.25 2.25 0 0 1 3.25 3h13.5A2.25 2.25 0 0 1 19 5.25v9.5A2.25 2.25 0 0 1 16.75 17H3.25A2.25 2.25 0 0 1 1 14.75v-9.5Zm1.5 5.81v3.69c0 .414.336.75.75.75h13.5a.75.75 0 0 0 .75-.75v-2.69l-2.22-2.219a.75.75 0 0 0-1.06 0l-1.91 1.909.47.47a.75.75 0 1 1-1.06 1.06L6.53 8.091a.75.75 0 0 0-1.06 0L2.5 11.06ZM12 7a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z" clip-rule="evenodd" /></svg>
                            <span id="chat-attachment-name" class="min-w-0 flex-1 truncate"></span>
                            <button type="button" id="chat-attachment-remove" class="cursor-pointer font-semibold underline underline-offset-2">Remove</button>
                        </div>
                        <div class="flex items-end gap-2">
                            <label for="chat-image" class="flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-xl text-[#285443] transition hover:bg-[#e3f1ee] focus-within:ring-2 focus-within:ring-[#285443]/40" title="Attach a photo">
                                <span class="sr-only">Attach a photo</span>
                                <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M1 5.25A2.25 2.25 0 0 1 3.25 3h13.5A2.25 2.25 0 0 1 19 5.25v9.5A2.25 2.25 0 0 1 16.75 17H3.25A2.25 2.25 0 0 1 1 14.75v-9.5Zm1.5 5.81v3.69c0 .414.336.75.75.75h13.5a.75.75 0 0 0 .75-.75v-2.69l-2.22-2.219a.75.75 0 0 0-1.06 0l-1.91 1.909.47.47a.75.75 0 1 1-1.06 1.06L6.53 8.091a.75.75 0 0 0-1.06 0L2.5 11.06ZM12 7a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z" clip-rule="evenodd" /></svg>
                                <input id="chat-image" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="sr-only">
                            </label>
                            <label for="chat-body" class="sr-only">Message</label>
                            <textarea id="chat-body" name="body" rows="1" maxlength="2000" placeholder="Type a message" class="bk-input max-h-32 min-w-0 flex-1 resize-none"></textarea>
                            <button class="bk-btn bk-btn-primary shrink-0">Send</button>
                        </div>
                    </form>
                    <p id="chat-error" class="hidden px-4 pb-3 text-xs text-[#b84331]" role="alert"></p>
                </div>
            @else
                <div class="hidden items-center justify-center p-8 text-center lg:flex">
                    <div>
                        <p class="font-semibold text-[#49665f]">No conversation open</p>
                        <p class="mt-1 text-sm text-[#607a72]">Select a conversation, or choose someone to message.</p>
                    </div>
                </div>
            @endif
        </div>
    </section>

    @if ($conversation)
        <script>
            (() => {
                const log = document.getElementById('chat-log');
                const form = document.getElementById('chat-form');
                const input = document.getElementById('chat-body');
                const error = document.getElementById('chat-error');
                const imageInput = document.getElementById('chat-image');
                const attachment = document.getElementById('chat-attachment');
                const attachmentName = document.getElementById('chat-attachment-name');
                const otherName = @js($otherUser->name);

                const showAttachment = () => {
                    const file = imageInput.files[0];
                    attachment.classList.toggle('hidden', ! file);
                    attachment.classList.toggle('flex', !! file);
                    attachmentName.textContent = file ? file.name : '';
                };
                imageInput.addEventListener('change', showAttachment);
                document.getElementById('chat-attachment-remove').addEventListener('click', () => {
                    imageInput.value = '';
                    showAttachment();
                });
                let lastId = Number(log.dataset.lastId);

                const scrollToBottom = () => { log.scrollTop = log.scrollHeight; };

                const render = (message) => {
                    if (log.querySelector(`[data-message-id="${message.id}"]`)) {
                        return;
                    }
                    document.getElementById('chat-empty')?.remove();
                    const row = document.createElement('div');
                    row.className = message.mine ? 'flex justify-end' : 'flex';
                    row.dataset.messageId = message.id;
                    const bubble = document.createElement('div');
                    bubble.className = 'max-w-[80%] rounded-2xl px-3.5 py-2 text-sm ' + (message.mine ? 'rounded-br-md bg-[#285443] text-white' : 'rounded-bl-md bg-white text-[#1c2e28] shadow-sm');
                    if (message.image_url) {
                        const link = document.createElement('a');
                        link.href = message.image_url;
                        link.target = '_blank';
                        link.rel = 'noopener';
                        link.className = 'mb-1 block';
                        const image = document.createElement('img');
                        image.src = message.image_url;
                        image.alt = 'Photo from ' + (message.mine ? 'you' : otherName);
                        image.className = 'max-h-64 max-w-full rounded-xl object-cover';
                        image.addEventListener('load', () => { if (message.mine) scrollToBottom(); });
                        link.append(image);
                        bubble.append(link);
                    }
                    if (message.body) {
                        const body = document.createElement('p');
                        body.className = 'whitespace-pre-wrap break-words';
                        body.textContent = message.body;
                        bubble.append(body);
                    }
                    const time = document.createElement('p');
                    time.className = 'mt-1 text-right text-[10px] ' + (message.mine ? 'text-white/70' : 'text-[#8aa39c]');
                    time.textContent = message.time;
                    bubble.append(time);
                    row.append(bubble);
                    log.append(row);
                    lastId = Math.max(lastId, message.id);
                };

                const poll = async () => {
                    try {
                        const response = await fetch(`${log.dataset.pollUrl}?after=${lastId}`, { headers: { Accept: 'application/json' } });
                        if (! response.ok) {
                            return;
                        }
                        const { messages } = await response.json();
                        const wasAtBottom = log.scrollHeight - log.scrollTop - log.clientHeight < 60;
                        messages.forEach(render);
                        if (messages.length && wasAtBottom) {
                            scrollToBottom();
                        }
                    } catch {
                        // Network blips are retried on the next poll.
                    }
                };

                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    if (! input.value.trim() && ! imageInput.files.length) {
                        return;
                    }
                    error.classList.add('hidden');
                    const response = await fetch(form.action, { method: 'POST', headers: { Accept: 'application/json' }, body: new FormData(form) });
                    if (response.ok) {
                        render(await response.json());
                        input.value = '';
                        imageInput.value = '';
                        showAttachment();
                        scrollToBottom();
                    } else {
                        const problem = response.status === 422 ? Object.values((await response.json()).errors ?? {})[0]?.[0] : null;
                        error.textContent = problem ?? 'Your message could not be sent. Please try again.';
                        error.classList.remove('hidden');
                    }
                });

                input.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' && ! event.shiftKey) {
                        event.preventDefault();
                        form.requestSubmit();
                    }
                });

                scrollToBottom();
                setInterval(poll, 4000);
            })();
        </script>
    @endif
</x-workspace.layout>
