<?php
// Session starten
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Include authentication mit korrektem Pfad
include_once __DIR__ . '/../config/auth.php';

// Überprüfe ob der Benutzer als Admin eingeloggt ist
redirectToLoginIfNotAdmin();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Admin Chat</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; }
        .chat-container { max-width: 500px; margin: 40px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px #0001; padding: 20px; }
        .chat-messages { height: 350px; overflow-y: auto; border: 1px solid #ccc; border-radius: 4px; padding: 10px; background: #fafafa; margin-bottom: 10px; }
        .chat-message { margin-bottom: 8px; }
        .chat-message .sender { font-weight: bold; margin-right: 6px; }
        .chat-message .time { color: #888; font-size: 0.9em; margin-left: 6px; }
        .chat-input-row { display: flex; gap: 8px; }
        .chat-input-row input, .chat-input-row button { font-size: 1em; }
        .chat-input-row input { flex: 1; padding: 6px; border-radius: 4px; border: 1px solid #ccc; }
        .chat-input-row button { padding: 6px 16px; border-radius: 4px; border: none; background: #007bff; color: #fff; cursor: pointer; }
        .chat-input-row button:hover { background: #0056b3; }
        .chat-error { color: #b00; margin-bottom: 10px; }
    </style>
</head>
<body>
<div class="chat-container">
    <h2>Admin-Chat</h2>
    <button id="clearChatBtn" style="margin-bottom:10px;background:#dc3545;color:#fff;padding:6px 16px;border:none;border-radius:4px;cursor:pointer;font-weight:bold;">Chat leeren</button>
    <div class="chat-error" id="chatError" style="display:none;"></div>
    <div class="chat-messages" id="chatMessages"></div>
    <form id="chatForm" class="chat-input-row" autocomplete="off">
        <input type="text" id="chatInput" placeholder="Nachricht eingeben..." maxlength="500" required />
        <button type="submit">Senden</button>
    </form>
</div>
<script>
const chatMessages = document.getElementById('chatMessages');
const chatForm = document.getElementById('chatForm');
const chatInput = document.getElementById('chatInput');
const chatError = document.getElementById('chatError');
const clearChatBtn = document.getElementById('clearChatBtn');
const CHAT_PASSWORD = 'regattaChat2024';

function escapeHtml(text) {
    return text.replace(/[&<>"']/g, function(m) {
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[m]);
    });
}

function fetchMessages() {
    fetch('../get_messages.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'chat_password=' + encodeURIComponent(CHAT_PASSWORD)
    })
        .then(r => r.json())
        .then(data => {
            if (!data.messages) return;
            chatMessages.innerHTML = '';
            data.messages.forEach(msg => {
                const div = document.createElement('div');
                div.className = 'chat-message';
                div.innerHTML = `<span class=\"sender\">${escapeHtml(msg.sender_name)}</span>: ` +
                    `<span class=\"text\">${escapeHtml(msg.message)}</span> ` +
                    `<span class=\"time\">${new Date(msg.created_at).toLocaleTimeString('de-DE', {hour: '2-digit', minute:'2-digit'})}</span> ` +
                    `<span class=\"delete-msg\" data-id=\"${msg.id}\" style=\"color:#b00;cursor:pointer;font-size:1.2em;margin-left:10px;\" title=\"Nachricht löschen\">🗑️</span>`;
                chatMessages.appendChild(div);
            });
            // Delete-Handler für Papierkorb-Icons
            document.querySelectorAll('.delete-msg').forEach(el => {
                el.onclick = function() {
                    if (confirm('Nachricht wirklich löschen?')) {
                        fetch('../delete_message.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'message_id=' + encodeURIComponent(el.dataset.id)
                        }).then(r => r.json()).then(fetchMessages);
                    }
                };
            });
            chatMessages.scrollTop = chatMessages.scrollHeight;
        });
}

clearChatBtn.onclick = function() {
    if (confirm('Den gesamten Chat unwiderruflich löschen?')) {
        fetch('../clear_chat.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'}
        }).then(r => r.json()).then(fetchMessages);
    }
};

chatForm.addEventListener('submit', function(e) {
    e.preventDefault();
    chatError.style.display = 'none';
    const message = chatInput.value.trim();
    if (!message) return;
    fetch('../send_message.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ sender_name: 'Admin', message, chat_password: CHAT_PASSWORD })
    })
    .then(async r => {
        let data;
        try { data = await r.json(); } catch { data = null; }
        if (!r.ok || !data || data.error) {
            chatError.textContent = data && data.error ? data.error : 'Unbekannter Fehler beim Senden.';
            chatError.style.display = 'block';
        } else {
            chatInput.value = '';
            fetchMessages();
        }
    });
});

setInterval(fetchMessages, 2000);
fetchMessages();
</script>
</body>
</html> 