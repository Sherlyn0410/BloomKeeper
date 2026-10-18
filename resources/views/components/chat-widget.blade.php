@php
    $unreadMessages = auth()->user()->unreadMessageCount();
@endphp

{{-- Floating chat: a button in the corner that opens the Messages page in a panel over the current page. --}}
<div id="chat-widget" class="fixed right-4 bottom-[calc(1rem+env(safe-area-inset-bottom,0px))] z-40 sm:right-6 sm:bottom-6" data-unread-url="{{ route('messages.unread') }}">
    <div id="chat-widget-panel" hidden class="fixed inset-0 z-50 flex flex-col overflow-hidden bg-white shadow-2xl sm:inset-auto sm:right-6 sm:bottom-24 sm:h-[36rem] sm:max-h-[calc(100vh-8rem)] sm:w-[24rem] sm:rounded-3xl sm:border sm:border-[#d4e4e0]">
        <div class="flex shrink-0 items-center justify-between gap-2 bg-[#285443] px-4 py-3 pt-[calc(0.75rem+env(safe-area-inset-top,0px))] text-white sm:pt-3">
            <p class="font-semibold">Messages</p>
            <button type="button" id="chat-widget-close" class="flex size-8 cursor-pointer items-center justify-center rounded-full hover:bg-white/10" aria-label="Close messages">
                <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
            </button>
        </div>
        <iframe id="chat-widget-frame" title="Messages" data-src="{{ route('messages.index', ['widget' => 1]) }}" class="min-h-0 w-full flex-1 border-0"></iframe>
    </div>

    <button
        type="button"
        id="chat-widget-toggle"
        class="relative flex size-14 cursor-pointer items-center justify-center rounded-full bg-[#285443] text-white shadow-lg transition hover:bg-[#1f4435] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#285443]/30"
        aria-label="Open messages"
        aria-expanded="false"
        aria-controls="chat-widget-panel"
    >
        <svg class="size-7" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4.8 3.6A2.4 2.4 0 0 0 2.4 6v9.6A2.4 2.4 0 0 0 4.8 18h1.8v3.3a.6.6 0 0 0 1 .45L11.7 18h7.5a2.4 2.4 0 0 0 2.4-2.4V6a2.4 2.4 0 0 0-2.4-2.4H4.8Zm2.7 6.3a1.2 1.2 0 1 0 0 2.4 1.2 1.2 0 0 0 0-2.4Zm4.5 0a1.2 1.2 0 1 0 0 2.4 1.2 1.2 0 0 0 0-2.4Zm4.5 0a1.2 1.2 0 1 0 0 2.4 1.2 1.2 0 0 0 0-2.4Z" clip-rule="evenodd" /></svg>
        <span id="chat-widget-badge" @class(['absolute -top-1 -right-1 min-w-5 rounded-full bg-[#b84331] px-1.5 text-center text-xs leading-5 font-bold text-white ring-2 ring-white', 'hidden' => $unreadMessages === 0]) aria-label="{{ $unreadMessages }} unread">{{ $unreadMessages }}</span>
    </button>
</div>

<script>
    (() => {
        const widget = document.getElementById('chat-widget');
        const panel = document.getElementById('chat-widget-panel');
        const toggle = document.getElementById('chat-widget-toggle');
        const frame = document.getElementById('chat-widget-frame');
        const badge = document.getElementById('chat-widget-badge');

        const setOpen = (open) => {
            if (open && ! frame.src) {
                frame.src = frame.dataset.src;
            }
            panel.hidden = ! open;
            toggle.setAttribute('aria-expanded', open);
            toggle.setAttribute('aria-label', open ? 'Close messages' : 'Open messages');
        };

        toggle.addEventListener('click', () => setOpen(panel.hidden));
        document.getElementById('chat-widget-close').addEventListener('click', () => setOpen(false));
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && ! panel.hidden) {
                setOpen(false);
            }
        });

        const refreshUnread = async () => {
            try {
                const response = await fetch(widget.dataset.unreadUrl, { headers: { Accept: 'application/json' } });
                if (response.ok) {
                    const { count } = await response.json();
                    badge.textContent = count;
                    badge.setAttribute('aria-label', `${count} unread`);
                    badge.classList.toggle('hidden', count === 0);
                }
            } catch {
                // Retried on the next refresh.
            }
        };
        setInterval(refreshUnread, 15000);
    })();
</script>
