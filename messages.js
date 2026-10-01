document.addEventListener('DOMContentLoaded', function () {
  var rawUser = sessionStorage.getItem('cc-user');
  if (!rawUser) {
    window.location.href = 'login.html';
    return;
  }
  var user;
  try { user = JSON.parse(rawUser); } catch (e) {
    window.location.href = 'login.html';
    return;
  }

  var username = user.username || 'Student';
  var storageKey = 'cc-conversations-' + (user.email || username).toLowerCase();
  var activeId = new URLSearchParams(window.location.search).get('conversation') || null;
  var filterUnreadOnly = false;
  var searchQuery = '';

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
    layout: document.getElementById('messagesLayout')
  };

  function escapeHtml(v) {
    return String(v).replace(/[&<>'"]/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[c];
    });
  }

  function formatRelative(at) {
    if (!at) return 'now';
    var diff = Date.now() - at;
    if (diff < 60000) return 'now';
    if (diff < 3600000) return Math.floor(diff / 60000) + 'm';
    if (diff < 86400000) return Math.floor(diff / 3600000) + 'h';
    if (diff < 604800000) return Math.floor(diff / 86400000) + 'd';
    return new Date(at).toLocaleDateString();
  }

  function formatBubbleTime(at) {
    try {
      return new Date(at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    } catch (e) { return ''; }
  }

  function loadConversations() {
    try {
      var stored = JSON.parse(localStorage.getItem(storageKey) || 'null');
      if (Array.isArray(stored) && stored.length > 0) {
        // migrate: ensure fields exist
        return stored.map(function (c) {
          c.messages = (c.messages || []).map(function (m) {
            if (typeof m.at !== 'number') m.at = Date.now() - 600000;
            return m;
          });
          if (typeof c.lastAt !== 'number') {
            var last = c.messages[c.messages.length - 1];
            c.lastAt = last ? last.at : Date.now();
          }
          if (!c.time) c.time = formatRelative(c.lastAt);
          return c;
        }).sort(function (a, b) { return (b.lastAt || 0) - (a.lastAt || 0); });
      }
    } catch (e) {}
    var now = Date.now();
    var seeded = [
      {
        id: 'alex-tutoring',
        name: 'Alex M.',
        initials: 'AM',
        avatar: 'avatar-navy',
        listing: 'Calc II tutoring · K50/hr',
        time: '12m',
        lastAt: now - 12 * 60000,
        unread: true,
        messages: [
          { text: 'Hey! Is Tuesday at 17:00 at the library still good for Calc II?', mine: false, at: now - 13 * 60000 },
          { text: 'I can bring past papers and we can focus on integration techniques.', mine: false, at: now - 12 * 60000 }
        ]
      },
      {
        id: 'tendai-fridge',
        name: 'Tendai N.',
        initials: 'TN',
        avatar: 'avatar-orange',
        listing: 'Mini fridge · K450',
        time: '34m',
        lastAt: now - 34 * 60000,
        unread: true,
        messages: [
          { text: 'Hi, is the mini fridge still available for pickup near Block C?', mine: false, at: now - 35 * 60000 },
          { text: 'Yes — clean, quiet, barely used. Can do tomorrow afternoon if that works.', mine: false, at: now - 34 * 60000 }
        ]
      },
      {
        id: 'sarah-creative',
        name: 'Sarah K.',
        initials: 'SK',
        avatar: 'avatar-green',
        listing: 'Logo design · From K120',
        time: '2h',
        lastAt: now - 7200000,
        unread: false,
        messages: [
          { text: 'Love your club logo brief — I can deliver a first draft in 24h.', mine: false, at: now - 7300000 },
          { text: 'Perfect, sending the colour palette now!', mine: true, at: now - 7200000 }
        ]
      }
    ];
    localStorage.setItem(storageKey, JSON.stringify(seeded));
    return seeded;
  }

  var conversations = loadConversations();

  function save() {
    // update derived time before save
    conversations.forEach(function (c) { c.time = formatRelative(c.lastAt); });
    conversations.sort(function (a, b) { return (b.lastAt || 0) - (a.lastAt || 0); });
    localStorage.setItem(storageKey, JSON.stringify(conversations));
  }

  function filteredConversations() {
    return conversations.filter(function (c) {
      if (filterUnreadOnly && !c.unread) return false;
      if (!searchQuery) return true;
      var q = searchQuery.toLowerCase();
      return c.name.toLowerCase().indexOf(q) !== -1 ||
        c.listing.toLowerCase().indexOf(q) !== -1 ||
        c.messages.some(function (m) { return m.text.toLowerCase().indexOf(q) !== -1; });
    });
  }

  function renderList() {
    var filtered = filteredConversations();
    var unreadCount = conversations.filter(function (c) { return c.unread; }).length;
    els.count.textContent = unreadCount;
    els.count.hidden = false;
    if (unreadCount === 0) {
      els.count.classList.add('is-zero');
    } else {
      els.count.classList.remove('is-zero');
    }
    if (els.filterUnread) {
      els.filterUnread.textContent = 'Unread' + (unreadCount ? ' · ' + unreadCount : '');
    }

    if (conversations.length === 0) {
      els.list.innerHTML = '';
      els.conversationEmpty.hidden = false;
      els.noResults.hidden = true;
      return;
    }
    els.conversationEmpty.hidden = true;

    if (filtered.length === 0) {
      els.list.innerHTML = '';
      els.noResults.hidden = false;
      return;
    }
    els.noResults.hidden = true;

    els.list.innerHTML = filtered.map(function (c) {
      var latest = c.messages[c.messages.length - 1];
      var preview = latest ? latest.text : 'Start a conversation';
      return '<button class="conversation-row' + (c.id === activeId ? ' is-active' : '') + (c.unread ? ' is-unread' : '') + '" type="button" data-conversation-id="' + escapeHtml(c.id) + '">'
        + '<span class="conversation-avatar ' + escapeHtml(c.avatar) + '">' + escapeHtml(c.initials) + '</span>'
        + '<span class="conversation-copy"><span class="conversation-copy-top"><strong>' + escapeHtml(c.name) + '</strong><time>' + escapeHtml(formatRelative(c.lastAt)) + '</time></span>'
        + '<small class="conversation-listing">' + escapeHtml(c.listing) + '</small>'
        + '<small class="conversation-preview' + (c.unread ? ' is-unread' : '') + '">' + escapeHtml(preview) + '</small></span>'
        + (c.unread ? '<span class="unread-dot" aria-label="Unread"></span>' : '<span class="read-dot" aria-hidden="true"></span>')
        + '</button>';
    }).join('');

    els.list.querySelectorAll('[data-conversation-id]').forEach(function (btn) {
      btn.addEventListener('click', function () { openConversation(btn.dataset.conversationId, true); });
    });
  }

  function renderMessages(conversation) {
    if (!conversation.messages.length) {
      els.chatMessages.innerHTML = '<div class="chat-day-separator"><span>New conversation</span></div><div class="chat-system"><p>Say hi to ' + escapeHtml(conversation.name) + ' about <strong>' + escapeHtml(conversation.listing) + '</strong>.</p></div>';
      return;
    }
    var html = '<div class="chat-day-separator"><span>Today</span></div>';
    conversation.messages.forEach(function (m) {
      var mine = !!m.mine;
      html += '<div class="chat-row' + (mine ? ' is-mine-row' : '') + '">'
        + (mine ? '' : '<span class="conversation-avatar ' + escapeHtml(conversation.avatar) + ' chat-row-avatar">' + escapeHtml(conversation.initials) + '</span>')
        + '<div class="chat-bubble' + (mine ? ' is-mine' : '') + '"><p>' + escapeHtml(m.text) + '</p><time>' + escapeHtml(formatBubbleTime(m.at)) + '</time></div>'
        + '</div>';
    });
    els.chatMessages.innerHTML = html;
    els.chatMessages.scrollTop = els.chatMessages.scrollHeight;
  }

  function openConversation(id, pushUrl) {
    var conv = conversations.find(function (c) { return c.id === id; });
    if (!conv) return;
    activeId = id;
    conv.unread = false;
    save();
    els.chatName.textContent = conv.name;
    els.chatListing.textContent = conv.listing;
    els.chatAvatar.textContent = conv.initials;
    els.chatAvatar.className = 'conversation-avatar ' + conv.avatar;
    renderMessages(conv);
    els.chatPanel.hidden = false;
    els.chatEmpty.hidden = true;
    els.layout.classList.add('has-active');
    renderList();
    els.chatInput.focus();
    if (pushUrl) {
      var url = new URL(window.location.href);
      url.searchParams.set('conversation', id);
      window.history.replaceState({}, '', url.toString());
    }
  }

  function closeConversationOnMobile() {
    if (window.innerWidth > 600) return;
    els.chatPanel.hidden = true;
    els.chatEmpty.hidden = false;
    els.layout.classList.remove('has-active');
    activeId = null;
    var url = new URL(window.location.href);
    url.searchParams.delete('conversation');
    window.history.replaceState({}, '', url.toString());
    renderList();
  }

  var autoReplies = {
    'Calc II tutoring': [
      'Great — Tuesday 17:00 works. Meet at the library main desk?',
      'I can share past exam papers as well. What topics feel trickiest?'
    ],
    'Mini fridge': [
      'Yes still available! Are you on Main or Great East? We can arrange pickup after 15:00.',
      'Happy to hold it until tomorrow if you need.'
    ],
    'default': [
      'Thanks for reaching out! When suits you to meet on campus?',
      'Happy to help — let me know what you had in mind.',
      'Got it! I can meet in a public spot near campus.'
    ]
  };

  function pickReply(listing) {
    for (var key in autoReplies) {
      if (listing.indexOf(key) !== -1 && key !== 'default') return autoReplies[key][Math.floor(Math.random() * autoReplies[key].length)];
    }
    var d = autoReplies['default'];
    return d[Math.floor(Math.random() * d.length)];
  }

  // Events
  if (els.search) {
    els.search.addEventListener('input', function () {
      searchQuery = els.search.value.trim();
      renderList();
    });
  }
  if (els.filterAll && els.filterUnread) {
    els.filterAll.addEventListener('click', function () {
      filterUnreadOnly = false;
      els.filterAll.classList.add('is-active');
      els.filterUnread.classList.remove('is-active');
      renderList();
    });
    els.filterUnread.addEventListener('click', function () {
      filterUnreadOnly = true;
      els.filterUnread.classList.add('is-active');
      els.filterAll.classList.remove('is-active');
      renderList();
    });
  }
  if (els.chatForm) {
    els.chatForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var text = els.chatInput.value.trim();
      if (!text) return;
      var conv = conversations.find(function (c) { return c.id === activeId; });
      if (!conv) {
        // no active conversation: create quick one
        if (conversations.length === 0) return;
        conv = conversations[0];
        activeId = conv.id;
      }
      if (text.length > 500) text = text.slice(0, 500);
      conv.messages.push({ text: text, mine: true, at: Date.now() });
      conv.lastAt = Date.now();
      conv.unread = false;
      save();
      els.chatInput.value = '';
      updateCharCount();
      renderMessages(conv);
      renderList();
      // simulate reply
      var replyListing = conv.listing;
      var replyText = pickReply(replyListing);
      var typingId = 'typing-' + Date.now();
      var typingEl = document.createElement('div');
      typingEl.id = typingId;
      typingEl.className = 'chat-typing';
      typingEl.innerHTML = '<span class="conversation-avatar ' + escapeHtml(conv.avatar) + '">' + escapeHtml(conv.initials) + '</span><span class="typing-bubble"><i></i><i></i><i></i></span>';
      els.chatMessages.appendChild(typingEl);
      els.chatMessages.scrollTop = els.chatMessages.scrollHeight;
      setTimeout(function () {
        var t = document.getElementById(typingId);
        if (t) t.remove();
        conv.messages.push({ text: replyText, mine: false, at: Date.now() });
        conv.lastAt = Date.now();
        // if chat still open on this conversation, show immediately; if not, mark unread
        var isStillActive = activeId === conv.id && !els.chatPanel.hidden;
        if (!isStillActive) conv.unread = true;
        save();
        if (isStillActive) renderMessages(conv);
        renderList();
      }, 900 + Math.random() * 600);
    });
  }

  function updateCharCount() {
    var counter = document.getElementById('chatCharCount');
    if (!counter) return;
    var len = els.chatInput.value.length;
    counter.textContent = len + '/500';
    counter.classList.toggle('is-near-limit', len > 420);
    counter.classList.toggle('is-over', len >= 500);
  }
  if (els.chatInput) {
    els.chatInput.addEventListener('input', updateCharCount);
  }

  if (els.backBtn) {
    els.backBtn.addEventListener('click', closeConversationOnMobile);
  }

  // delete conversation
  var deleteBtn = document.getElementById('chatDeleteBtn');
  if (deleteBtn) {
    deleteBtn.addEventListener('click', function () {
      var conv = conversations.find(function (c) { return c.id === activeId; });
      if (!conv) return;
      if (!window.confirm('Delete conversation with ' + conv.name + '?')) return;
      conversations = conversations.filter(function (c) { return c.id !== activeId; });
      save();
      activeId = null;
      els.chatPanel.hidden = true;
      els.chatEmpty.hidden = false;
      els.layout.classList.remove('has-active');
      var url = new URL(window.location.href);
      url.searchParams.delete('conversation');
      window.history.replaceState({}, '', url.toString());
      renderList();
    });
  }

  // new message button
  var newBtn = document.getElementById('newConversationBtn');
  if (newBtn) {
    newBtn.addEventListener('click', function () {
      var name = window.prompt('Who do you want to message? (e.g. Alex M.)');
      if (!name || !name.trim()) return;
      name = name.trim();
      var listing = window.prompt('About which listing?') || 'Campus listing';
      var id = 'conv-' + Date.now();
      var initials = name.split(' ').map(function (w) { return w[0]; }).join('').slice(0, 2).toUpperCase();
      var avatars = ['avatar-navy', 'avatar-orange', 'avatar-green'];
      var avatar = avatars[conversations.length % 3];
      var conv = { id: id, name: name, initials: initials, avatar: avatar, listing: listing, time: 'now', lastAt: Date.now(), unread: false, messages: [] };
      conversations.unshift(conv);
      save();
      renderList();
      openConversation(id, true);
    });
  }

  // browse link in empty
  document.querySelectorAll('[data-browse-adverts]').forEach(function (btn) {
    btn.addEventListener('click', function () { window.location.href = 'adverts.html'; });
  });

  // initial render
  renderList();
  if (activeId) {
    var exists = conversations.some(function (c) { return c.id === activeId; });
    if (exists) openConversation(activeId, false);
    else if (conversations.length) openConversation(conversations[0].id, false);
  } else if (window.innerWidth > 600 && conversations.length) {
    // auto-open first on desktop
    // keep empty on load to preserve original behavior, but show first as hint? keep empty for now
  }

  // handle resize to reset mobile drawer
  window.addEventListener('resize', function () {
    if (window.innerWidth > 600) {
      els.layout.classList.remove('has-active');
    } else if (activeId) {
      els.layout.classList.add('has-active');
    }
  });

  // logout
  var logoutBtn = document.getElementById('logoutBtn');
  if (logoutBtn) logoutBtn.addEventListener('click', function () {
    fetch('logout.php', { method: 'POST' }).finally(function () {
      sessionStorage.removeItem('cc-auth-token');
      sessionStorage.removeItem('cc-user');
      window.location.href = 'index.html';
    });
  });

  // update char count init
  updateCharCount();
});
