// Messages — wired to /api/messages/ (conversations, thread polling, send, mark-read, unread badge).
document.addEventListener('DOMContentLoaded', function () {
  var rawUser = sessionStorage.getItem('cc-user');
  if (!rawUser) { window.location.href = 'login.html'; return; }
  var user;
  try { user = JSON.parse(rawUser); } catch (e) { window.location.href = 'login.html'; return; }
  var token = sessionStorage.getItem('cc-auth-token') || '';
  if (!token) { window.location.href = 'login.html'; return; }

  var els = {
    list: document.getElementById('conversationList'),
    search: document.getElementById('messageSearch'),
    count: document.getElementById('messageCount'),
    chatPanel: document.getElementById('chatPanel'),
    chatEmpty: document.getElementById('chatEmpty'),
    chatMessages: document.getElementById('chatMessages'),
    chatForm: document.getElementById('chatForm'),
    chatInput: document.getElementById('chatInput'),
    chatName: document.getElementById('chatName'),
    chatListing: document.getElementById('chatListing'),
    chatAvatar: document.getElementById('chatAvatar'),
    conversationEmpty: document.getElementById('conversationEmpty'),
    noResults: document.getElementById('noResults'),
    backBtn: document.getElementById('chatBackBtn'),
    filterAll: document.getElementById('filterAll'),
    filterUnread: document.getElementById('filterUnread'),
    layout: document.getElementById('messagesLayout'),
    charCount: document.getElementById('chatCharCount'),
    newBtn: document.getElementById('newConversationBtn'),
    deleteBtn: document.getElementById('chatDeleteBtn')
  };

  var conversations = [];
  var activeId = parseInt(new URLSearchParams(window.location.search).get('conversation'), 10) || null;
  var composeUser = (new URLSearchParams(window.location.search).get('u') || '').trim() || null;
  var filterUnreadOnly = false;
  var searchQuery = '';
  var pollTimer = null;
  var lastMessageId = 0;
  var sending = false;

  function api(path, options) {
    options = options || {};
    options.headers = Object.assign({ 'Authorization': 'Bearer ' + token }, options.headers || {});
    if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(options.body);
    }
    return fetch(path, options).then(function (r) {
      return r.json().catch(function () { return { success: false, error: 'Unexpected response.' }; })
        .then(function (data) {
          if (r.status === 401) {
            sessionStorage.removeItem('cc-auth-token');
            sessionStorage.removeItem('cc-user');
            window.location.href = 'login.html';
            throw new Error('unauthorized');
          }
          if (!data.success) throw new Error(data.error || 'Request failed.');
          return data;
        });
    });
  }

  function setText(el, value) { if (el) el.textContent = value == null ? '' : String(value); }

  function formatRelative(iso) {
    if (!iso) return '';
    var at = new Date(String(iso).replace(' ', 'T'));
    if (isNaN(at)) return '';
    var diff = Date.now() - at.getTime();
    if (diff < 60000) return 'now';
    if (diff < 3600000) return Math.floor(diff / 60000) + 'm';
    if (diff < 86400000) return Math.floor(diff / 3600000) + 'h';
    if (diff < 604800000) return Math.floor(diff / 86400000) + 'd';
    return at.toLocaleDateString();
  }

  function formatBubbleTime(iso) {
    var at = new Date(String(iso).replace(' ', 'T'));
    if (isNaN(at)) return '';
    return at.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  function initialsFor(name) {
    return String(name || '?').trim().split(/\s+/).map(function (p) { return p[0]; }).join('').slice(0, 2).toUpperCase() || '?';
  }

  function avatarClassFor(id) {
    var classes = ['avatar-navy', 'avatar-orange', 'avatar-green'];
    return classes[Math.abs(Number(id) || 0) % classes.length];
  }

  function setAvatar(el, url, name, id) {
    if (!el) return;
    el.textContent = '';
    el.style.backgroundImage = '';
    el.className = 'conversation-avatar ' + avatarClassFor(id);
    if (url) {
      el.style.backgroundImage = 'url("' + String(url).replace(/["\\]/g, '') + '")';
      el.style.backgroundSize = 'cover';
      el.style.backgroundPosition = 'center';
    } else {
      el.textContent = initialsFor(name);
    }
  }

  function updateUnreadBadge() {
    var unread = conversations.reduce(function (n, c) { return n + (Number(c.unread) > 0 ? 1 : 0); }, 0);
    setText(els.count, String(unread));
    document.dispatchEvent(new CustomEvent('cc:unread-messages', { detail: { count: unread } }));
  }

  function visibleConversations() {
    var q = searchQuery.trim().toLowerCase();
    return conversations.filter(function (c) {
      if (filterUnreadOnly && !(Number(c.unread) > 0)) return false;
      if (!q) return true;
      var hay = [c.other_username, c.other_display_name, c.listing_title, c.last_message].join(' ').toLowerCase();
      return hay.indexOf(q) !== -1;
    });
  }

  function renderConversationList() {
    var items = visibleConversations();
    els.list.textContent = '';
    items.forEach(function (c) {
      var row = document.createElement('button');
      row.type = 'button';
      row.className = 'conversation-row' + (c.id === activeId ? ' is-active' : '');
      row.setAttribute('role', 'listitem');

      var avatar = document.createElement('span');
      avatar.className = 'conversation-avatar ' + avatarClassFor(c.other_id);
      if (c.other_avatar_url) {
        avatar.style.backgroundImage = 'url("' + String(c.other_avatar_url).replace(/["\\]/g, '') + '")';
        avatar.style.backgroundSize = 'cover';
        avatar.style.backgroundPosition = 'center';
      } else {
        avatar.textContent = initialsFor(c.other_display_name || c.other_username);
      }

      var copy = document.createElement('span');
      copy.className = 'conversation-copy';
      var name = document.createElement('strong');
      name.textContent = c.other_display_name || c.other_username || 'Student';
      var preview = document.createElement('small');
      preview.textContent = c.last_message || 'Start a conversation';
      copy.appendChild(name);
      copy.appendChild(preview);

      var time = document.createElement('time');
      time.textContent = formatRelative(c.updated_at);

      row.appendChild(avatar);
      row.appendChild(copy);
      row.appendChild(time);
      if (Number(c.unread) > 0) {
        var dot = document.createElement('span');
        dot.className = 'unread-dot';
        row.appendChild(dot);
      }
      row.addEventListener('click', function () { openConversation(c.id); });
      els.list.appendChild(row);
    });

    if (els.conversationEmpty) els.conversationEmpty.hidden = conversations.length > 0;
    if (els.noResults) els.noResults.hidden = !(conversations.length > 0 && items.length === 0);
    updateUnreadBadge();
  }

  function renderMessages(messages, append) {
    if (!append) els.chatMessages.textContent = '';
    messages.forEach(function (m) {
      var bubble = document.createElement('div');
      var mine = m.is_mine === true ||
        (m.sender_username && String(m.sender_username).toLowerCase() === String(user.username || '').toLowerCase());
      bubble.className = 'chat-bubble' + (mine ? ' is-mine' : '');
      bubble.textContent = m.body;
      var stamp = formatBubbleTime(m.created_at);
      if (stamp) {
        var time = document.createElement('small');
        time.textContent = stamp;
        bubble.appendChild(time);
      }
      els.chatMessages.appendChild(bubble);
      lastMessageId = Math.max(lastMessageId, Number(m.id) || 0);
    });
    els.chatMessages.scrollTop = els.chatMessages.scrollHeight;
  }

  function activeConversation() {
    return conversations.find(function (c) { return c.id === activeId; }) || null;
  }

  function openConversation(id) {
    var c = conversations.find(function (item) { return item.id === id; });
    if (!c) return;
    activeId = id;
    lastMessageId = 0;
    setText(els.chatName, c.other_display_name || c.other_username || 'Conversation');
    setText(els.chatListing, c.listing_title || 'Campus conversation');
    setAvatar(els.chatAvatar, c.other_avatar_url, c.other_display_name || c.other_username, c.other_id);
    els.chatPanel.hidden = false;
    if (els.chatEmpty) els.chatEmpty.hidden = true;
    if (els.layout) els.layout.classList.add('chat-open');

    api('api/messages/thread.php?conversation_id=' + encodeURIComponent(id))
      .then(function (data) {
        renderMessages(data.messages || [], false);
        c.unread = 0;
        renderConversationList();
        return api('api/messages/mark-read.php', { method: 'POST', body: { conversation_id: id } }).catch(function () {});
      })
      .catch(function (err) { toast(err.message); });
  }

  function pollThread() {
    if (!activeId) return;
    api('api/messages/thread.php?conversation_id=' + encodeURIComponent(activeId) + '&after_id=' + lastMessageId)
      .then(function (data) {
        if (data.messages && data.messages.length) {
          renderMessages(data.messages, true);
          var c = activeConversation();
          if (c) {
            c.last_message = data.messages[data.messages.length - 1].body;
            c.updated_at = data.messages[data.messages.length - 1].created_at;
          }
          api('api/messages/mark-read.php', { method: 'POST', body: { conversation_id: activeId } }).catch(function () {});
          renderConversationList();
        }
      })
      .catch(function () {});
  }

  function loadConversations() {
    return api('api/messages/conversations.php')
      .then(function (data) {
        conversations = (data.conversations || []).map(function (c) {
          c.id = Number(c.id);
          return c;
        });
        renderConversationList();
        if (activeId && activeConversation()) openConversation(activeId);
      })
      .catch(function (err) { toast(err.message); });
  }

  function toast(message) {
    var el = document.getElementById('cc-toast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'cc-toast';
      el.setAttribute('role', 'status');
      document.body.appendChild(el);
    }
    el.textContent = message;
    el.classList.add('is-visible');
    clearTimeout(toast._t);
    toast._t = setTimeout(function () { el.classList.remove('is-visible'); }, 3200);
  }

  // --- Events ---
  if (els.chatForm) {
    els.chatForm.addEventListener('submit', function (e) {
      e.preventDefault();
      if (sending) return;
      var text = els.chatInput.value.trim();
      if (!text) return;
      // Compose mode: first message to a username creates the conversation.
      if (!activeId && composeUser) {
        sending = true;
        api('api/messages/send.php', { method: 'POST', body: { recipient: composeUser, body: text } })
          .then(function (data) {
            els.chatInput.value = '';
            if (els.charCount) els.charCount.textContent = '0/500';
            var cid = data.conversation_id;
            composeUser = null;
            loadConversations().then(function () { if (cid) openConversation(cid); });
          })
          .catch(function (err) { toast(err.message); })
          .finally(function () { sending = false; });
        return;
      }
      if (!activeId) return;
      sending = true;
      api('api/messages/send.php', { method: 'POST', body: { conversation_id: activeId, body: text } })
        .then(function (data) {
          els.chatInput.value = '';
          if (els.charCount) els.charCount.textContent = '0/500';
          if (data.message) {
            data.message.is_mine = true;
            renderMessages([data.message], true);
            var c = activeConversation();
            if (c) { c.last_message = data.message.body; c.updated_at = data.message.created_at; }
            renderConversationList();
          }
        })
        .catch(function (err) { toast(err.message); })
        .finally(function () { sending = false; });
    });
  }

  if (els.chatInput && els.charCount) {
    els.chatInput.addEventListener('input', function () {
      els.charCount.textContent = els.chatInput.value.length + '/500';
    });
  }

  if (els.search) {
    els.search.addEventListener('input', function () {
      searchQuery = els.search.value;
      renderConversationList();
    });
  }

  if (els.filterAll && els.filterUnread) {
    els.filterAll.addEventListener('click', function () {
      filterUnreadOnly = false;
      els.filterAll.classList.add('is-active');
      els.filterAll.setAttribute('aria-selected', 'true');
      els.filterUnread.classList.remove('is-active');
      els.filterUnread.setAttribute('aria-selected', 'false');
      renderConversationList();
    });
    els.filterUnread.addEventListener('click', function () {
      filterUnreadOnly = true;
      els.filterUnread.classList.add('is-active');
      els.filterUnread.setAttribute('aria-selected', 'true');
      els.filterAll.classList.remove('is-active');
      els.filterAll.setAttribute('aria-selected', 'false');
      renderConversationList();
    });
  }

  if (els.backBtn) {
    els.backBtn.addEventListener('click', function () {
      activeId = null;
      els.chatPanel.hidden = true;
      if (els.chatEmpty) els.chatEmpty.hidden = false;
      if (els.layout) els.layout.classList.remove('chat-open');
      renderConversationList();
    });
  }

  if (els.newBtn) {
    els.newBtn.addEventListener('click', function () {
      window.location.href = 'listings.html';
    });
  }

  if (els.deleteBtn) {
    // No delete endpoint exists; use it to jump to the seller's profile instead.
    els.deleteBtn.addEventListener('click', function () {
      var c = activeConversation();
      if (c && c.other_username) window.location.href = 'profile.html?u=' + encodeURIComponent(c.other_username);
    });
    els.deleteBtn.setAttribute('aria-label', 'View profile');
  }

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) { loadConversations(); pollThread(); }
  });

  // Initial load + polling (thread every 4s while open, list every 15s).
  loadConversations().then(function () {
    // Compose mode from profile pages (?u=username): reuse existing thread or open a blank composer.
    if (composeUser) {
      var existing = conversations.find(function (c) {
        return String(c.other_username || '').toLowerCase() === composeUser.toLowerCase();
      });
      if (existing) { composeUser = null; openConversation(existing.id); }
      else {
        setText(els.chatName, '@' + composeUser);
        setText(els.chatListing, 'New conversation');
        setAvatar(els.chatAvatar, null, composeUser, 0);
        els.chatMessages.textContent = '';
        els.chatPanel.hidden = false;
        if (els.chatEmpty) els.chatEmpty.hidden = true;
        if (els.layout) els.layout.classList.add('chat-open');
        if (els.chatInput) els.chatInput.focus();
      }
    }
  });
  pollTimer = setInterval(function () {
    if (document.hidden) return;
    pollThread();
  }, 4000);
  setInterval(function () {
    if (document.hidden) return;
    loadConversations();
  }, 15000);

  document.querySelectorAll('[data-browse-adverts]').forEach(function (btn) {
    btn.addEventListener('click', function () { window.location.href = 'listings.html'; });
  });
});
