{{--
    Chat Widget Component for PWA interfaces

    Usage: @include('partials.chat-widget', [
        'chatType' => 'mat',       // mat, weging, spreker, dojo, hoofdjury
        'chatId' => 1,             // mat number (only for mat type)
        'toernooiId' => $toernooi->id,
        'chatApiBase' => route('toernooi.chat.index', $toernooi, false),
    ])
--}}
@php
    $chatType = $chatType ?? 'hoofdjury';
    $chatId = $chatId ?? null;
    $toernooiId = $toernooiId ?? null;
    $chatApiBase = $chatApiBase ?? '';

    // Type labels for UI
    $typeLabels = [
        'hoofdjury' => 'Hoofdjury',
        'mat' => 'Mat',
        'weging' => 'Weging',
        'spreker' => 'Spreker',
        'dojo' => 'Dojo',
    ];
@endphp

{{-- Chat Icon Button (fixed position) --}}
<button id="chat-toggle-btn"
        onclick="toggleChat()"
        class="fixed bottom-20 right-4 z-40 bg-blue-600 hover:bg-blue-700 text-white p-3 rounded-full shadow-lg transition-transform hover:scale-105"
        title="Chat">
    {{-- Chat icon --}}
    <svg id="chat-icon" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
    </svg>
    {{-- Close icon (hidden by default) --}}
    <svg id="chat-close-icon" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
    </svg>
    {{-- Unread badge --}}
    <span id="chat-unread-badge" class="hidden absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">
        0
    </span>
</button>

{{-- Chat Panel (slide in from right) --}}
<div id="chat-panel"
     class="fixed top-0 right-0 h-full w-full sm:w-96 bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 flex flex-col">

    {{-- Header --}}
    <div class="bg-blue-800 text-white p-4 flex items-center justify-between">
        <div>
            <h2 class="font-bold text-lg">Chat</h2>
            <p class="text-blue-200 text-sm">{{ $typeLabels[$chatType] ?? $chatType }}@if($chatId) {{ $chatId }}@endif</p>
        </div>
        <button onclick="toggleChat()" class="p-2 hover:bg-blue-700 rounded-full">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>

    {{-- Message List --}}
    <div id="chat-messages" class="flex-1 overflow-y-auto p-4 space-y-3 bg-gray-50">
        {{-- Messages will be inserted here --}}
        <div id="chat-empty" class="text-center text-gray-500 py-8">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
            </svg>
            <p>Nog geen berichten</p>
        </div>
    </div>

    {{-- Input Area --}}
    <div class="border-t bg-white p-4">
        <form id="chat-form" onsubmit="sendMessage(event)" class="flex gap-2">
            <input type="text"
                   id="chat-input"
                   placeholder="Typ een bericht..."
                   class="flex-1 border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                   autocomplete="off"
                   maxlength="1000">
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                </svg>
            </button>
        </form>
        <p class="text-xs text-gray-400 mt-2 text-center">Berichten gaan naar Hoofdjury</p>
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
            <p id="chat-toast-sender" class="font-bold text-sm">Hoofdjury</p>
            <p id="chat-toast-message" class="text-blue-100 text-sm truncate">Nieuw bericht</p>
        </div>
        <button onclick="event.stopPropagation(); hideToast();" class="text-blue-200 hover:text-white">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
</div>

{{-- Overlay when chat is open --}}
<div id="chat-overlay"
     class="fixed inset-0 bg-black/50 z-40 hidden"
     onclick="toggleChat()"></div>

<script>
    // Chat configuration
    const chatConfig = {
        type: '{{ $chatType }}',
        id: {{ $chatId ?? 'null' }},
        toernooiId: {{ $toernooiId ?? 'null' }},
        apiBase: '{{ $chatApiBase }}'
    };

    let chatOpen = false;
    let chatMessages = [];
    let lastMessageId = 0;
    let toastTimeout = null;
    let lastToastMessage = null;

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

    // Load messages from API
    async function loadMessages() {
        try {
            const url = new URL(chatConfig.apiBase, window.location.origin);
            url.searchParams.set('type', chatConfig.type);
            if (chatConfig.id) url.searchParams.set('id', chatConfig.id);

            const response = await fetch(url);
            if (!response.ok) throw new Error('Failed to load messages');

            chatMessages = await response.json();
            renderMessages();
        } catch (error) {
            console.error('Chat load error:', error);
        }
    }

    // Render messages in the chat panel
    function renderMessages() {
        const container = document.getElementById('chat-messages');
        const emptyState = document.getElementById('chat-empty');

        if (chatMessages.length === 0) {
            emptyState.classList.remove('hidden');
            return;
        }

        emptyState.classList.add('hidden');

        // Clear existing messages (keep empty state)
        container.querySelectorAll('.chat-msg').forEach(el => el.remove());

        chatMessages.forEach(msg => {
            const div = document.createElement('div');
            div.className = 'chat-msg';

            const isOwn = msg.is_eigen;
            const time = new Date(msg.created_at).toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' });

            div.innerHTML = `
                <div class="flex ${isOwn ? 'justify-end' : 'justify-start'}">
                    <div class="${isOwn ? 'bg-blue-600 text-white' : 'bg-white border'} rounded-lg px-4 py-2 max-w-[80%] shadow-sm">
                        ${!isOwn ? `<p class="text-xs font-bold text-blue-600 mb-1">${escapeHtml(msg.van_naam)}</p>` : ''}
                        <p class="${isOwn ? 'text-white' : 'text-gray-800'}">${escapeHtml(msg.bericht)}</p>
                        <p class="text-xs ${isOwn ? 'text-blue-200' : 'text-gray-400'} mt-1">${time}</p>
                    </div>
                </div>
            `;

            container.appendChild(div);

            // Track last message ID
            if (msg.id > lastMessageId) lastMessageId = msg.id;
        });

        // Scroll to bottom
        container.scrollTop = container.scrollHeight;
    }

    // Send a message
    async function sendMessage(event) {
        event.preventDefault();

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
                    van_type: chatConfig.type,
                    van_id: chatConfig.id,
                    naar_type: 'hoofdjury',  // PWA's always send to hoofdjury
                    naar_id: null,
                    bericht: bericht
                })
            });

            if (!response.ok) throw new Error('Failed to send message');

            const result = await response.json();

            // Add message to local list
            chatMessages.push({
                id: result.message.id,
                van_type: chatConfig.type,
                van_id: chatConfig.id,
                van_naam: result.message.van_naam,
                naar_type: 'hoofdjury',
                naar_id: null,
                naar_naam: 'Hoofdjury',
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

    // Mark messages as read
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
            if (chatConfig.id) url.searchParams.set('id', chatConfig.id);

            const response = await fetch(url);
            if (!response.ok) throw new Error('Failed to check unread');

            const data = await response.json();
            updateUnreadBadge(data.count);
        } catch (error) {
            console.error('Unread check error:', error);
        }
    }

    // Update unread badge
    function updateUnreadBadge(count) {
        const badge = document.getElementById('chat-unread-badge');
        if (count > 0) {
            badge.textContent = count > 9 ? '9+' : count;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }

    // Show toast notification
    function showToast(sender, message) {
        // Don't show toast if chat is open
        if (chatOpen) return;

        const toast = document.getElementById('chat-toast');
        document.getElementById('chat-toast-sender').textContent = sender;
        document.getElementById('chat-toast-message').textContent = message;

        lastToastMessage = { sender, message };

        // Show toast
        toast.classList.remove('translate-x-full', 'opacity-0');

        // Auto hide after 5 seconds
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

    // Handle incoming WebSocket message
    function handleNewMessage(data) {
        // Add to messages if chat is open
        if (chatOpen) {
            chatMessages.push({
                ...data,
                is_eigen: false,
                gelezen: true
            });
            renderMessages();
        } else {
            // Show toast and update badge
            showToast(data.van_naam, data.bericht);
            checkUnreadCount();
        }
    }

    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        // Load messages on init
        loadMessages();

        // Setup WebSocket connection
        setupWebSocket();
    });

    // Setup WebSocket with Laravel Echo
    function setupWebSocket() {
        // Load Pusher and Echo from CDN if not already loaded
        if (typeof Pusher === 'undefined') {
            const pusherScript = document.createElement('script');
            pusherScript.src = 'https://js.pusher.com/8.2.0/pusher.min.js';
            pusherScript.onload = initEcho;
            document.head.appendChild(pusherScript);
        } else {
            initEcho();
        }
    }

    function initEcho() {
        // Reverb config - use env values directly
        const isProduction = window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1';

        const pusher = new Pusher('{{ env("REVERB_APP_KEY", "oixj1bggwjv8qhj3jlpb") }}', {
            wsHost: isProduction ? window.location.hostname : '127.0.0.1',
            wsPort: isProduction ? 443 : 8080,
            wssPort: isProduction ? 443 : 8080,
            forceTLS: isProduction,
            enabledTransports: ['ws', 'wss'],
            disableStats: true,
            cluster: 'mt1'
        });

        // Build channel name based on type
        let channelName = `chat.${chatConfig.toernooiId}.${chatConfig.type}`;
        if (chatConfig.id) {
            channelName += `.${chatConfig.id}`;
        }

        // Subscribe to channel
        const channel = pusher.subscribe(channelName);

        // Also subscribe to broadcast channels
        const broadcastChannel = pusher.subscribe(`chat.${chatConfig.toernooiId}.iedereen`);

        // Listen for new messages (event broadcasts as 'chat.message')
        channel.bind('chat.message', handleNewMessage);
        broadcastChannel.bind('chat.message', handleNewMessage);

        // If we're a mat, also listen to alle_matten
        if (chatConfig.type === 'mat') {
            const mattenChannel = pusher.subscribe(`chat.${chatConfig.toernooiId}.alle_matten`);
            mattenChannel.bind('chat.message', handleNewMessage);
        }

        console.log('Chat WebSocket connected to:', channelName);
    }

    // Expose for external integration
    window.chatHandleNewMessage = handleNewMessage;
</script>
