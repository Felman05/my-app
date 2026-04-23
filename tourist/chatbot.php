<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('tourist');
$currentUser = getCurrentUser();
$pageTitle = 'Chatbot';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

$sessions = [];
$messages = [];
$activeSessionToken = trim((string) ($_GET['sid'] ?? ''));
$activeSessionId = 0;

try {
    $stmt = $pdo->prepare(
      'SELECT s.id, s.session_token, s.created_at,
                (SELECT m.content FROM chatbot_messages m WHERE m.session_id = s.id ORDER BY m.created_at DESC, m.id DESC LIMIT 1) AS latest_message,
                (SELECT COUNT(*) FROM chatbot_messages m WHERE m.session_id = s.id) AS message_count
         FROM chatbot_sessions s
         WHERE s.user_id = ?
         ORDER BY s.created_at DESC'
    );
    $stmt->execute([$currentUser['id']]);
    $sessions = $stmt->fetchAll();

    if ($activeSessionToken === '' && !empty($sessions)) {
      $activeSessionToken = (string) ($sessions[0]['session_token'] ?? '');
    }

    if ($activeSessionToken !== '') {
      $stmt = $pdo->prepare('SELECT id FROM chatbot_sessions WHERE user_id = ? AND session_token = ? LIMIT 1');
      $stmt->execute([$currentUser['id'], $activeSessionToken]);
      $sessionRow = $stmt->fetch();
      if ($sessionRow) {
        $activeSessionId = (int) $sessionRow['id'];
        $stmt = $pdo->prepare('SELECT id, role, content, metadata, created_at FROM chatbot_messages WHERE session_id = ? ORDER BY created_at ASC, id ASC');
        $stmt->execute([$activeSessionId]);
        $messages = $stmt->fetchAll();
      }
    }
} catch (Exception $e) {
    $sessions = [];
    $messages = [];
}
?>
<?php include '../includes/header.php'; ?>
<style>
  .chat-layout { position: relative; }
  .history-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.25); z-index: 190; }
  .history-backdrop.open { display: block; }
  .history-panel { display: none; }
  .history-panel.open {
    display: block;
    position: fixed;
    top: 18px;
    left: calc(var(--sb) + 12px);
    bottom: 18px;
    width: min(460px, 42vw);
    z-index: 200;
    overflow: auto;
    background: var(--wh);
    box-shadow: var(--s2);
  }
  .history-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
  @media (max-width: 768px) {
    .history-panel.open {
      top: 12px;
      left: 12px;
      right: 12px;
      bottom: 12px;
      width: auto;
    }
  }
</style>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar">
    <div>
      <h1 class="d-page-title">Chatbot Assistant</h1>
      <p class="d-page-sub">Gemini-powered travel help with your saved places and itinerary context.</p>
    </div>
    <div style="display:flex;gap:8px;">
      <button id="historyToggleBtn" class="s-btn" type="button">History</button>
      <button id="newChatBtn" class="s-btn green" type="button">New Chat</button>
    </div>
  </div>

  <div id="historyBackdrop" class="history-backdrop"></div>

  <div class="chat-layout">
    <aside id="historyPanel" class="dc history-panel">
      <div class="history-head">
        <div class="dc-title">Past Conversations</div>
        <button id="historyCloseBtn" class="s-btn" type="button">Close</button>
      </div>
      <div id="sessionList" class="dest-list">
        <?php foreach ($sessions as $s): ?>
        <a class="dest-row <?php echo (($s['session_token'] ?? '') === $activeSessionToken) ? 'active' : ''; ?>" href="/doon-app/tourist/chatbot.php?sid=<?php echo urlencode((string) ($s['session_token'] ?? '')); ?>">
          <div class="dest-ico">S</div>
          <div>
            <div class="dest-name">Session <?php echo escape(substr((string) ($s['session_token'] ?? ''), 0, 8)); ?></div>
            <div class="dest-meta"><?php echo escape(substr((string) ($s['latest_message'] ?? 'New conversation'), 0, 50)); ?></div>
          </div>
          <div class="dest-rating"><?php echo (int) $s['message_count']; ?></div>
        </a>
        <?php endforeach; ?>
        <?php if (empty($sessions)): ?>
        <div class="dest-row">No conversations yet. Start one now.</div>
        <?php endif; ?>
      </div>
    </aside>

    <section class="dc">
      <div class="chat-wrap" style="height:520px;">
        <div id="chatMessages" class="chat-msgs">
          <?php foreach ($messages as $msg): ?>
            <?php $meta = json_decode((string) ($msg['metadata'] ?? ''), true); ?>
            <?php $metaDestinations = $meta['destinations'] ?? ($meta['cards'] ?? []); ?>
            <?php if ($msg['role'] === 'user'): ?>
            <div class="chat-row u">
              <div class="chat-ava">U</div>
              <div class="chat-bub"><?php echo nl2br(escape($msg['content'])); ?></div>
            </div>
            <?php else: ?>
            <div class="chat-row b">
              <div class="chat-ava">B</div>
              <div>
                <div class="chat-bub" data-md="1"><?php echo nl2br(escape($msg['content'])); ?></div>
                <?php if (!empty($metaDestinations) && is_array($metaDestinations)): ?>
                <div class="g2" style="margin-top:8px;gap:8px;">
                  <?php foreach ($metaDestinations as $card): ?>
                  <a href="/doon-app/tourist/destination.php?id=<?php echo (int) ($card['id'] ?? 0); ?>" class="dest-card">
                    <?php echo escape($card['name'] ?? 'Destination'); ?>
                  </a>
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>
              </div>
            </div>
            <?php endif; ?>
          <?php endforeach; ?>
          <?php if (empty($messages)): ?>
          <div class="chat-row b"><div class="chat-ava">B</div><div class="chat-bub">Hello! Ask me about weather, recommendations, saved places, or itinerary planning in CALABARZON.</div></div>
          <?php endif; ?>
        </div>

        <form id="chatForm" class="chat-bar">
          <input id="chatInput" class="chat-in" type="text" placeholder="Ask about destinations, weather, or your saved places..." required>
          <button class="chat-snd" type="submit">Send</button>
        </form>
      </div>
    </section>
    </div>
</main>
</div>

<script>
(function () {
  let activeSessionToken = <?php echo json_encode((string) $activeSessionToken); ?>;
  const RATE_LIMIT_MS = 2000;
  let lastSentAt = 0;
  let isSending = false;
  const messagesEl = document.getElementById('chatMessages');
  const chatForm = document.getElementById('chatForm');
  const chatInput = document.getElementById('chatInput');
  const sendBtn = chatForm.querySelector('button[type="submit"]');
    function escapeHtml(value) {
      return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function renderMarkdown(text) {
      return escapeHtml(text)
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*([^*\n]+?)\*/g, '<em>$1</em>')
        .replace(/\n/g, '<br>');
    }

    function generateSessionToken() {
      return 'sess_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 10);
    }

  const newChatBtn = document.getElementById('newChatBtn');
  const historyToggleBtn = document.getElementById('historyToggleBtn');
  const historyCloseBtn = document.getElementById('historyCloseBtn');
  const historyPanel = document.getElementById('historyPanel');
  const historyBackdrop = document.getElementById('historyBackdrop');

  function openHistory() {
    historyPanel.classList.add('open');
    historyBackdrop.classList.add('open');
  }

  function closeHistory() {
    historyPanel.classList.remove('open');
    historyBackdrop.classList.remove('open');
  }

  function scrollToBottom() {
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function createMessageRow(role, text) {
    const row = document.createElement('div');
    row.className = 'chat-row ' + (role === 'user' ? 'u' : 'b');

    const ava = document.createElement('div');
    ava.className = 'chat-ava';
    ava.textContent = role === 'user' ? 'U' : 'B';

    const bub = document.createElement('div');
    bub.className = 'chat-bub';
    if (role === 'user') {
      bub.textContent = text;
    } else {
      bub.innerHTML = renderMarkdown(text);
    }

    row.appendChild(ava);
    row.appendChild(bub);
    return row;
  }

  function createCards(destinations) {
    if (!Array.isArray(destinations) || destinations.length === 0) return null;
    const wrapper = document.createElement('div');
    wrapper.className = 'g2';
    wrapper.style.marginTop = '8px';
    wrapper.style.gap = '8px';

    destinations.forEach(dest => {
      const link = document.createElement('a');
      link.className = 'dest-card';
      link.href = '/doon-app/tourist/destination.php?id=' + Number(dest.id || 0);
      link.innerHTML = escapeHtml(dest.name || 'Destination');
      wrapper.appendChild(link);
    });

    return wrapper;
  }

  function setSendingState(loading, label) {
    isSending = loading;
    chatInput.disabled = loading;
    if (sendBtn) {
      sendBtn.disabled = loading;
      sendBtn.textContent = label;
    }
  }

  function showAssistantNotice(text) {
    const row = createMessageRow('assistant', text);
    messagesEl.appendChild(row);
    scrollToBottom();
  }

  function beginCooldown(ms) {
    const started = Date.now();
    setSendingState(true, 'Wait 2s');

    const timer = setInterval(function () {
      const elapsed = Date.now() - started;
      const remaining = Math.max(0, ms - elapsed);
      const seconds = Math.ceil(remaining / 1000);

      if (sendBtn) {
        sendBtn.textContent = remaining > 0 ? ('Wait ' + seconds + 's') : 'Send';
      }

      if (remaining <= 0) {
        clearInterval(timer);
        setSendingState(false, 'Send');
      }
    }, 200);
  }

  async function sendMessage(message) {
    const userRow = createMessageRow('user', message);
    messagesEl.appendChild(userRow);
    scrollToBottom();

    const loadingRow = createMessageRow('assistant', 'Doon Assistant is typing...');
    messagesEl.appendChild(loadingRow);
    scrollToBottom();

    if (!activeSessionToken) {
      activeSessionToken = generateSessionToken();
    }

    const response = await fetch('/doon-app/api/chatbot.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        session_token: activeSessionToken,
        message: message
      })
    });

    const data = await response.json();
    loadingRow.remove();

    if (!data.success) {
      const errRow = createMessageRow('assistant', data.error || 'Failed to get response.');
      messagesEl.appendChild(errRow);
      scrollToBottom();
      return;
    }

    activeSessionToken = String(data.session_token || activeSessionToken || '');
    const botRow = createMessageRow('assistant', data.response || 'No response.');
    messagesEl.appendChild(botRow);

    const cardsEl = createCards(data.destinations || []);
    if (cardsEl) {
      messagesEl.appendChild(cardsEl);
    }

    scrollToBottom();
    setTimeout(function () {
      if (activeSessionToken) {
        window.location.href = '/doon-app/tourist/chatbot.php?sid=' + encodeURIComponent(activeSessionToken);
      }
    }, 200);
  }

  chatForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    if (isSending) return;

    const message = chatInput.value.trim();
    if (!message) return;

    const now = Date.now();
    const elapsed = now - lastSentAt;
    if (elapsed < RATE_LIMIT_MS) {
      const waitMs = RATE_LIMIT_MS - elapsed;
      const waitSec = Math.ceil(waitMs / 1000);
      showAssistantNotice('Please wait ' + waitSec + ' second(s) before sending another message.');
      return;
    }

    chatInput.value = '';
    lastSentAt = now;
    setSendingState(true, 'Sending...');

    try {
      await sendMessage(message);
    } catch (err) {
      const errRow = createMessageRow('assistant', 'Network error: ' + err.message);
      messagesEl.appendChild(errRow);
      scrollToBottom();
    } finally {
      beginCooldown(RATE_LIMIT_MS);
    }
  });

  newChatBtn.addEventListener('click', async function () {
    const token = generateSessionToken();
    window.location.href = '/doon-app/tourist/chatbot.php?sid=' + encodeURIComponent(token);
  });

  historyToggleBtn.addEventListener('click', function () {
    openHistory();
  });

  historyCloseBtn.addEventListener('click', function () {
    closeHistory();
  });

  historyBackdrop.addEventListener('click', function () {
    closeHistory();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeHistory();
    }
  });

  // Re-render existing bot bubbles loaded from history
  document.querySelectorAll('.chat-bub[data-md]').forEach(function (bub) {
    const raw = bub.innerText || bub.textContent || '';
    bub.innerHTML = renderMarkdown(raw);
  });

  scrollToBottom();
})();
</script>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
