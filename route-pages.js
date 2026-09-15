document.addEventListener('DOMContentLoaded', function () {
  const rawUser = sessionStorage.getItem('cc-user');
  if (!rawUser) {
    window.location.href = 'login.html';
    return;
  }

  const user = JSON.parse(rawUser);
  const storageKey = 'cc-conversations-' + (user.email || user.username || 'student').toLowerCase();
  const escapeHtml = function (value) {
    return String(value).replace(/[&<>'"]/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
    });
  };

  const logout = document.getElementById('logoutBtn');
  if (logout) logout.addEventListener('click', function () {
    sessionStorage.removeItem('cc-user');
    window.location.href = 'index.html';
  });

  const cards = document.querySelectorAll('.route-feed .listing-card');
  const search = document.getElementById('listingSearch');
  const emptyFeed = document.getElementById('emptyFeed');
  let category = 'all';
  function filterCards() {
    const query = (search ? search.value : '').trim().toLowerCase();
    let visible = 0;
    cards.forEach(function (card) {
      const matches = (category === 'all' || card.dataset.category === category) && (!query || card.dataset.search.includes(query));
      card.hidden = !matches;
      if (matches) visible += 1;
    });
    if (emptyFeed) emptyFeed.hidden = visible > 0;
  }
  document.querySelectorAll('.category-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
      category = tab.dataset.category;
      document.querySelectorAll('.category-tab').forEach(function (item) { item.classList.toggle('is-active', item === tab); });
      filterCards();
    });
  });
  if (search) search.addEventListener('input', filterCards);
  filterCards();

  document.querySelectorAll('.save-button').forEach(function (button) {
    button.addEventListener('click', function () { button.classList.toggle('is-active'); button.textContent = button.classList.contains('is-active') ? '♥' : '♡'; });
  });
  document.querySelectorAll('[data-message-name]').forEach(function (button) {
    button.addEventListener('click', function () {
      const listing = button.closest('.listing-card').querySelector('h2').textContent;
      let conversations = JSON.parse(localStorage.getItem(storageKey) || '[]');
      let conversation = conversations.find(function (item) { return item.name === button.dataset.messageName; });
      if (!conversation) {
        conversation = { id: Date.now().toString(), name: button.dataset.messageName, initials: button.dataset.messageName.slice(0, 2).toUpperCase(), avatar: 'avatar-navy', listing: listing, time: 'now', unread: false, messages: [] };
        conversations.unshift(conversation);
        localStorage.setItem(storageKey, JSON.stringify(conversations));
      }
      window.location.href = 'messages.html?conversation=' + encodeURIComponent(conversation.id);
    });
  });

  const conversationList = document.getElementById('conversationList');
  const chatPanel = document.getElementById('chatPanel');
  if (conversationList && chatPanel) {
    const chatEmpty = document.getElementById('chatEmpty');
    const chatMessages = document.getElementById('chatMessages');
    const chatForm = document.getElementById('chatForm');
    const chatInput = document.getElementById('chatInput');
    const conversations = JSON.parse(localStorage.getItem(storageKey) || '[]');
    let activeId = new URLSearchParams(window.location.search).get('conversation');
    function renderConversationList() {
      document.getElementById('messageCount').textContent = conversations.filter(function (item) { return item.unread; }).length;
      conversationList.innerHTML = conversations.map(function (item) {
        const latest = item.messages[item.messages.length - 1];
        return '<button class="conversation-row' + (item.id === activeId ? ' is-active' : '') + '" type="button" data-route-conversation="' + escapeHtml(item.id) + '"><span class="conversation-avatar ' + escapeHtml(item.avatar) + '">' + escapeHtml(item.initials) + '</span><span class="conversation-copy"><strong>' + escapeHtml(item.name) + '</strong><small>' + escapeHtml(latest ? latest.text : 'Start a conversation') + '</small></span><time>' + escapeHtml(item.time || 'now') + '</time>' + (item.unread ? '<span class="unread-dot"></span>' : '') + '</button>';
      }).join('');
      conversationList.querySelectorAll('[data-route-conversation]').forEach(function (row) { row.addEventListener('click', function () { openConversation(row.dataset.routeConversation); }); });
    }
    function openConversation(id) {
      const conversation = conversations.find(function (item) { return item.id === id; });
      if (!conversation) return;
      activeId = id;
      conversation.unread = false;
      document.getElementById('chatAvatar').textContent = conversation.initials;
      document.getElementById('chatName').textContent = conversation.name;
      document.getElementById('chatListing').textContent = conversation.listing;
      chatMessages.innerHTML = conversation.messages.map(function (item) { return '<div class="chat-bubble' + (item.mine ? ' is-mine' : '') + '">' + escapeHtml(item.text) + '</div>'; }).join('');
      chatPanel.hidden = false;
      chatEmpty.hidden = true;
      localStorage.setItem(storageKey, JSON.stringify(conversations));
      renderConversationList();
      chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    renderConversationList();
    if (activeId) openConversation(activeId);
    chatForm.addEventListener('submit', function (event) {
      event.preventDefault();
      const text = chatInput.value.trim();
      const conversation = conversations.find(function (item) { return item.id === activeId; });
      if (!text || !conversation) return;
      conversation.messages.push({ text: text, mine: true });
      conversation.time = 'now';
      chatInput.value = '';
      openConversation(activeId);
    });
  }

  const postForm = document.getElementById('routeListingForm');
  if (postForm) postForm.addEventListener('submit', function (event) {
    event.preventDefault();
    const message = document.getElementById('routePostMessage');
    message.textContent = 'Listing saved locally. It will appear in the shared feed once the backend is connected.';
    message.style.color = 'var(--success)';
    postForm.reset();
    setTimeout(function () { window.location.href = 'adverts.html'; }, 900);
  });
});
