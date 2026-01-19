{{--
    Chat Widget for Hoofdjury - extended version with recipient selector

    Usage: @include('partials.chat-widget-hoofdjury', [
        'toernooi' => $toernooi,
    ])
--}}
@php
    $toernooiId = $toernooi->id ?? null;
    $aantalMatten = $toernooi->matten()->count() ?? $toernooi->aantal_matten ?? 4;
@endphp

{{-- Chat Icon Button (fixed position) --}}
<button id="chat-toggle-btn"
        onclick="toggleChat()"
        class="fixed bottom-20 right-4 z-40 bg-blue-600 hover:bg-blue-700 text-white p-3 rounded-full shadow-lg transition-transform hover:scale-105"
        title="Chat">
    <svg id="chat-icon" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
    </svg>
    <svg id="chat-close-icon" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
    </svg>
    <span id="chat-unread-badge" class="hidden absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">
        0
    </span>
</button>

{{-- Chat Panel (slide in from right) --}}
<div id="chat-panel"
     class="fixed top-0 right-0 h-full w-full sm:w-[420px] bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 flex flex-col">

    {{-- Header --}}
    <div class="bg-blue-800 text-white p-4 flex items-center justify-between">
        <div>
            <h2 class="font-bold text-lg">Hoofdjury Chat</h2>
            <p class="text-blue-200 text-sm">Communicatie met vrijwilligers</p>
        </div>
        <button onclick="toggleChat()" class="p-2 hover:bg-blue-700 rounded-full">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>

    {{-- Recipient Selector --}}
    <div class="bg-gray-100 border-b p-3">
        <label class="text-xs font-medium text-gray-600 block mb-2">Verstuur naar:</label>
        <div class="flex flex-wrap gap-2">
            <button type="button" onclick="setRecipient('iedereen', null)"
                    class="recipient-btn px-3 py-1.5 rounded-full text-sm font-medium border-2 transition-colors"
                    data-type="iedereen">
                Iedereen
            </button>
            <button type="button" onclick="setRecipient('alle_matten', null)"
                    class="recipient-btn px-3 py-1.5 rounded-full text-sm font-medium border-2 transition-colors"
                    data-type="alle_matten">
                Alle Matten
            </button>
            <button type="button" onclick="setRecipient('weging', null)"
                    class="recipient-btn px-3 py-1.5 rounded-full text-sm font-medium border-2 transition-colors"
                    data-type="weging">
                Weging
            </button>
            <button type="button" onclick="setRecipient('spreker', null)"
                    class="recipient-btn px-3 py-1.5 rounded-full text-sm font-medium border-2 transition-colors"
                    data-type="spreker">
                Spreker
            </button>
            <button type="button" onclick="setRecipient('dojo', null)"
                    class="recipient-btn px-3 py-1.5 rounded-full text-sm font-medium border-2 transition-colors"
                    data-type="dojo">
                Dojo
            </button>
        </div>

        {{-- Individual mat buttons --}}
        @if($aantalMatten > 0)
        <div class="mt-2 pt-2 border-t border-gray-200">
            <label class="text-xs font-medium text-gray-500 block mb-2">Individuele mat:</label>
            <div class="flex flex-wrap gap-2">
                @for($i = 1; $i <= $aantalMatten; $i++)
                <button type="button" onclick="setRecipient('mat', {{ $i }})"
                        class="recipient-btn px-3 py-1.5 rounded-full text-sm font-medium border-2 transition-colors"
                        data-type="mat" data-id="{{ $i }}">
                    Mat {{ $i }}
                </button>
                @endfor
            </div>
        </div>
        @endif
    </div>

    {{-- Message List --}}
    <div id="chat-messages" class="flex-1 overflow-y-auto p-4 space-y-3 bg-gray-50">
        <div id="chat-empty" class="text-center text-gray-500 py-8">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
            </svg>
            <p>Nog geen berichten</p>
        </div>
    </div>

    {{-- Input Area --}}
    <div class="border-t bg-white p-4">
        <div id="recipient-display" class="text-xs text-blue-600 font-medium mb-2">
            Naar: <span id="recipient-label">Selecteer ontvanger</span>
        </div>
        <form id="chat-form" onsubmit="sendMessage(event)" class="flex gap-2">
            <input type="text"
                   id="chat-input"
                   placeholder="Typ een bericht..."
                   class="flex-1 border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                   autocomplete="off"
                   maxlength="1000">
            <button type="submit"
                    id="chat-send-btn"
                    disabled
                    class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white px-4 py-2 rounded-lg font-medium transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                </svg>
            </button>
        </form>
    </div>
</div>

{{-- Toast Notification --}}
<div id="chat-toast"
     class="fixed top-4 right-4 bg-blue-800 text-white px-4 py-3 rounded-lg shadow-lg z-50 transform translate-x-full opacity-0 transition-all duration-300 cursor-pointer max-w-sm"
     onclick="showToastMessage()">
    <div class="flex items-start gap-3">
        <div class="flex-shrink-0 bg-blue-600 rounded-full p-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
            </svg>
        </div>
        <div class="flex-1 min-w-0">
            <p id="chat-toast-sender" class="font-bold text-sm">Mat 1</p>
            <p id="chat-toast-message" class="text-blue-100 text-sm truncate">Nieuw bericht</p>
        </div>
        <button onclick="event.stopPropagation(); hideToast();" class="text-blue-200 hover:text-white">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
</div>

{{-- Overlay --}}
<div id="chat-overlay"
     class="fixed inset-0 bg-black/50 z-40 hidden"
     onclick="toggleChat()"></div>

<style>
    .recipient-btn {
        background-color: white;
        border-color: #d1d5db;
        color: #374151;
    }
    .recipient-btn:hover {
        background-color: #f3f4f6;
        border-color: #9ca3af;
    }
    .recipient-btn.active {
        background-color: #2563eb;
        border-color: #2563eb;
        color: white;
    }
</style>

<script>
    // Chat configuration for hoofdjury
    const chatConfig = {
        type: 'hoofdjury',
        id: null,
        toernooiId: {{ $toernooiId ?? 'null' }},
        apiBase: '{{ route('toernooi.chat.index', $toernooi) }}'
    };

    let chatOpen = false;
    let chatMessages = [];
    let selectedRecipient = { type: null, id: null };
    let toastTimeout = null;

    // Recipient labels
    const recipientLabels = {
        'iedereen': 'Iedereen',
        'alle_matten': 'Alle Matten',
        'weging': 'Weging',
        'spreker': 'Spreker',
        'dojo': 'Dojo',
        'mat': 'Mat'
    };

    // Set recipient
    function setRecipient(type, id) {
        selectedRecipient = { type, id };

        // Update button styles
        document.querySelectorAll('.recipient-btn').forEach(btn => {
            const btnType = btn.dataset.type;
            const btnId = btn.dataset.id ? parseInt(btn.dataset.id) : null;

            if (btnType === type && btnId === id) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        // Update label
        let label = recipientLabels[type] || type;
        if (type === 'mat' && id) {
            label = `Mat ${id}`;
        }
        document.getElementById('recipient-label').textContent = label;

        // Enable send button
        document.getElementById('chat-send-btn').disabled = false;
    }

    // Toggle chat panel
    function toggleChat() {
        chatOpen = !chatOpen;
        const panel = document.getElementById('chat-panel');
        const overlay = document.getElementById('chat-overlay');
        const iconChat = document.getElementById('chat-icon');
        const iconClose = document.getElementById('chat-close-icon');

        if (chatOpen) {
            panel.classList.remove('translate-x-full');
            overlay.classList.remove('hidden');
            iconChat.classList.add('hidden');
            iconClose.classList.remove('hidden');
            loadMessages();
            markAsRead();
            document.getElementById('chat-input').focus();
        } else {
            panel.classList.add('translate-x-full');
            overlay.classList.add('hidden');
            iconChat.classList.remove('hidden');
            iconClose.classList.add('hidden');
        }
    }

    // Load messages
    async function loadMessages() {
        try {
            const url = new URL(chatConfig.apiBase, window.location.origin);
            url.searchParams.set('type', chatConfig.type);

            const response = await fetch(url);
            if (!response.ok) throw new Error('Failed to load messages');

            chatMessages = await response.json();
            renderMessages();
        } catch (error) {
            console.error('Chat load error:', error);
        }
    }

    // Render messages
    function renderMessages() {
        const container = document.getElementById('chat-messages');
        const emptyState = document.getElementById('chat-empty');

        if (chatMessages.length === 0) {
            emptyState.classList.remove('hidden');
            return;
        }

        emptyState.classList.add('hidden');
        container.querySelectorAll('.chat-msg').forEach(el => el.remove());

        // Auto-select last incoming message sender as recipient if none selected
        if (!selectedRecipient.type) {
            const lastIncoming = [...chatMessages].reverse().find(m => !m.is_eigen);
            if (lastIncoming) {
                setRecipient(lastIncoming.van_type, lastIncoming.van_id);
            }
        }

        chatMessages.forEach(msg => {
            const div = document.createElement('div');
            div.className = 'chat-msg';

            const isOwn = msg.is_eigen;
            const time = new Date(msg.created_at).toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' });

            // Click on incoming message to reply to that sender
            const clickHandler = !isOwn ? `onclick="setRecipient('${msg.van_type}', ${msg.van_id || 'null'})"` : '';
            const cursorClass = !isOwn ? 'cursor-pointer hover:ring-2 hover:ring-blue-300' : '';

            div.innerHTML = `
                <div class="flex ${isOwn ? 'justify-end' : 'justify-start'}">
                    <div ${clickHandler} class="${isOwn ? 'bg-blue-600 text-white' : 'bg-white border'} rounded-lg px-4 py-2 max-w-[80%] shadow-sm ${cursorClass}" ${!isOwn ? 'title="Klik om te antwoorden"' : ''}>
                        ${!isOwn ? `<p class="text-xs font-bold text-blue-600 mb-1">${escapeHtml(msg.van_naam)}</p>` : `<p class="text-xs text-blue-200 mb-1">Naar: ${escapeHtml(msg.naar_naam)}</p>`}
                        <p class="${isOwn ? 'text-white' : 'text-gray-800'}">${escapeHtml(msg.bericht)}</p>
                        <p class="text-xs ${isOwn ? 'text-blue-200' : 'text-gray-400'} mt-1">${time}</p>
                    </div>
                </div>
            `;

            container.appendChild(div);
        });

        container.scrollTop = container.scrollHeight;
    }

    // Send message
    async function sendMessage(event) {
        event.preventDefault();

        if (!selectedRecipient.type) {
            alert('Selecteer eerst een ontvanger');
            return;
        }

        const input = document.getElementById('chat-input');
        const bericht = input.value.trim();
        if (!bericht) return;

        try {
            const response = await fetch(chatConfig.apiBase, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({
                    van_type: 'hoofdjury',
                    van_id: null,
                    naar_type: selectedRecipient.type,
                    naar_id: selectedRecipient.id,
                    bericht: bericht
                })
            });

            if (!response.ok) throw new Error('Failed to send message');

            const result = await response.json();

            chatMessages.push({
                id: result.message.id,
                van_type: 'hoofdjury',
                van_id: null,
                van_naam: 'Hoofdjury',
                naar_type: selectedRecipient.type,
                naar_id: selectedRecipient.id,
                naar_naam: result.message.naar_naam,
                bericht: bericht,
                gelezen: false,
                created_at: result.message.created_at,
                is_eigen: true
            });

            input.value = '';
            renderMessages();

        } catch (error) {
            console.error('Chat send error:', error);
            alert('Bericht versturen mislukt. Probeer opnieuw.');
        }
    }

    // Mark as read
    async function markAsRead() {
        try {
            const url = chatConfig.apiBase.replace(/\/$/, '') + '/read';

            await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({
                    type: chatConfig.type,
                    id: chatConfig.id
                })
            });

            updateUnreadBadge(0);
        } catch (error) {
            console.error('Mark as read error:', error);
        }
    }

    // Check unread count
    async function checkUnreadCount() {
        try {
            const url = new URL(chatConfig.apiBase.replace(/\/$/, '') + '/unread', window.location.origin);
            url.searchParams.set('type', chatConfig.type);

            const response = await fetch(url);
            if (!response.ok) throw new Error('Failed to check unread');

            const data = await response.json();
            updateUnreadBadge(data.count);
        } catch (error) {
            console.error('Unread check error:', error);
        }
    }

    // Update badge
    function updateUnreadBadge(count) {
        const badge = document.getElementById('chat-unread-badge');
        if (count > 0) {
            badge.textContent = count > 9 ? '9+' : count;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }

    // Toast
    function showToast(sender, message) {
        if (chatOpen) return;

        const toast = document.getElementById('chat-toast');
        document.getElementById('chat-toast-sender').textContent = sender;
        document.getElementById('chat-toast-message').textContent = message;

        toast.classList.remove('translate-x-full', 'opacity-0');

        if (toastTimeout) clearTimeout(toastTimeout);
        toastTimeout = setTimeout(hideToast, 5000);
    }

    function hideToast() {
        const toast = document.getElementById('chat-toast');
        toast.classList.add('translate-x-full', 'opacity-0');
        if (toastTimeout) clearTimeout(toastTimeout);
    }

    function showToastMessage() {
        hideToast();
        toggleChat();
    }

    // Handle incoming message
    function handleNewMessage(data) {
        if (chatOpen) {
            chatMessages.push({
                ...data,
                is_eigen: false,
                gelezen: true
            });
            renderMessages();
        } else {
            showToast(data.van_naam, data.bericht);
            checkUnreadCount();
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        checkUnreadCount();
        setInterval(checkUnreadCount, 30000);
    });

    window.chatHandleNewMessage = handleNewMessage;
</script>
