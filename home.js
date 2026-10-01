document.addEventListener('DOMContentLoaded', function () {
  const raw = sessionStorage.getItem('cc-user');

  if (!raw) {
    window.location.href = 'login.html';
    return;
  }

  let user;
  try {
    user = JSON.parse(raw);
  } catch (error) {
    window.location.href = 'login.html';
    return;
  }

  fetch('session.php')
    .then(function (response) { return response.ok ? response.json() : Promise.reject(); })
    .then(function (result) {
      user = result.user;
      sessionStorage.setItem('cc-user', JSON.stringify(user));
    })
    .catch(function () {
      sessionStorage.removeItem('cc-user');
      window.location.href = 'login.html';
    });
  const username = user.username || 'Student';
  const initials = username.slice(0, 2).toUpperCase();
  const allPreferences = ['electronics', 'books', 'furniture', 'tutoring', 'repairs', 'beauty', 'creative', 'campus-help'];
  const preferenceAliases = { goods: ['electronics', 'books', 'furniture'], services: ['tutoring', 'repairs', 'beauty'], gigs: ['creative', 'campus-help'] };
  let storedPreferences;
  try {
    storedPreferences = JSON.parse(sessionStorage.getItem('cc-user-preferences') || JSON.stringify(allPreferences));
  } catch (error) {
    storedPreferences = allPreferences;
  }
  const savedPreferences = storedPreferences
    .flatMap(function (preference) { return preferenceAliases[preference] || [preference]; })
    .filter(function (preference) { return allPreferences.includes(preference); });

  document.getElementById('campusLine').textContent = 'Campus: ' + (user.campus || 'Main Campus');
  document.getElementById('profileName').textContent = username;
  document.getElementById('profileCampus').textContent = user.campus || 'Main Campus';
  document.getElementById('profileAvatar').textContent = initials;
  document.getElementById('profilePageAvatar').textContent = initials;
  document.getElementById('profilePageName').textContent = username;
  document.getElementById('profilePageCampus').textContent = user.campus || 'Main Campus';

  document.getElementById('logoutBtn').addEventListener('click', function () {
    fetch('logout.php', { method: 'POST' }).finally(function () {
      sessionStorage.removeItem('cc-auth-token');
      sessionStorage.removeItem('cc-user');
      window.location.href = 'index.html';
    });
  });

  const tabs = document.querySelectorAll('.category-tab');
  const searchInput = document.getElementById('listingSearch');
  const searchNavButton = document.getElementById('searchNavButton');
  const searchNavPopover = document.getElementById('searchNavPopover');
  const emptyFeed = document.getElementById('emptyFeed');
  const feedIntro = document.getElementById('feedIntro');
  const feedToolbar = document.getElementById('feedToolbar');
  const feedView = document.getElementById('feedView');
  const messagesView = document.getElementById('messagesView');
  const profileView = document.getElementById('profileView');
  const bottomNavItems = document.querySelectorAll('.bottom-nav-item');
  const feedEyebrow = document.getElementById('feedEyebrow');
  const conversationList = document.getElementById('conversationList');
  const chatPanel = document.getElementById('chatPanel');
  const chatEmpty = document.getElementById('chatEmpty');
  const chatMessages = document.getElementById('chatMessages');
  const chatForm = document.getElementById('chatForm');
  const chatInput = document.getElementById('chatInput');
  const chatName = document.getElementById('chatName');
  const chatListing = document.getElementById('chatListing');
  const chatAvatar = document.getElementById('chatAvatar');
  let activeCategory = 'all';
  let activeView = 'home';
  let activeConversationId = null;
  const conversationKey = 'cc-conversations-' + (user.email || username).toLowerCase();
  let conversations = loadConversations();

  if (searchNavButton && searchNavPopover) {
    searchNavButton.addEventListener('click', function () {
      const isOpen = !searchNavPopover.hidden;
      searchNavPopover.hidden = isOpen;
      searchNavButton.setAttribute('aria-expanded', String(!isOpen));
      if (!isOpen) searchInput.focus();
    });
  }

  function formatRelative(at) {
    if (!at) return 'now';
    var diff = Date.now() - at;
    if (diff < 60000) return 'now';
    if (diff < 3600000) return Math.floor(diff / 60000) + 'm';
    if (diff < 86400000) return Math.floor(diff / 3600000) + 'h';
    return Math.floor(diff / 86400000) + 'd';
  }
  function formatBubbleTime(at) {
    try { return new Date(at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); } catch (e) { return ''; }
  }
  function loadConversations() {
    try {
      const stored = JSON.parse(localStorage.getItem(conversationKey) || 'null');
      if (Array.isArray(stored) && stored.length) {
        return stored.map(function (c) {
          c.messages = (c.messages || []).map(function (m) { if (typeof m.at !== 'number') m.at = Date.now() - 600000; return m; });
          if (typeof c.lastAt !== 'number') {
            var last = c.messages[c.messages.length - 1];
            c.lastAt = last ? last.at : Date.now();
          }
          if (!c.time) c.time = formatRelative(c.lastAt);
          return c;
        }).sort(function (a, b) { return (b.lastAt || 0) - (a.lastAt || 0); });
      }
    } catch (error) {}
    var now = Date.now();
    var seeded = [
      { id: 'alex-tutoring', name: 'Alex M.', initials: 'AM', avatar: 'avatar-navy', listing: 'Calc II tutoring · K50/hr', time: '12m', lastAt: now - 12 * 60000, unread: true, messages: [{ text: 'Hey! Is Tuesday at 17:00 at the library still good for Calc II?', mine: false, at: now - 13 * 60000 }, { text: 'I can bring past papers and we can focus on integration.', mine: false, at: now - 12 * 60000 }] },
      { id: 'tendai-fridge', name: 'Tendai N.', initials: 'TN', avatar: 'avatar-orange', listing: 'Mini fridge · K450', time: '34m', lastAt: now - 34 * 60000, unread: true, messages: [{ text: 'Hi, is the mini fridge still available near Block C?', mine: false, at: now - 35 * 60000 }, { text: 'Yes — clean and ready for pickup tomorrow afternoon.', mine: false, at: now - 34 * 60000 }] }
    ];
    localStorage.setItem(conversationKey, JSON.stringify(seeded));
    return seeded;
  }

  function saveConversations() {
    conversations.forEach(function (c) { c.time = formatRelative(c.lastAt); });
    conversations.sort(function (a, b) { return (b.lastAt || 0) - (a.lastAt || 0); });
    localStorage.setItem(conversationKey, JSON.stringify(conversations));
  }

  function renderConversations() {
    const unreadCount = conversations.filter(function (c) { return c.unread; }).length;
    messagesView.querySelector('.panel-count').textContent = unreadCount;
    conversationList.innerHTML = conversations.map(function (conversation) {
      const latest = conversation.messages[conversation.messages.length - 1];
      var preview = latest ? latest.text : 'Start a conversation';
      return '<button class="conversation-row' + (conversation.id === activeConversationId ? ' is-active' : '') + (conversation.unread ? ' is-unread' : '') + '" type="button" data-conversation-id="' + escapeHtml(conversation.id) + '"><span class="conversation-avatar ' + escapeHtml(conversation.avatar) + '">' + escapeHtml(conversation.initials) + '</span><span class="conversation-copy"><span class="conversation-copy-top"><strong>' + escapeHtml(conversation.name) + '</strong><time>' + escapeHtml(formatRelative(conversation.lastAt)) + '</time></span><small class="conversation-listing">' + escapeHtml(conversation.listing) + '</small><small class="conversation-preview' + (conversation.unread ? ' is-unread' : '') + '">' + escapeHtml(preview) + '</small></span>' + (conversation.unread ? '<span class="unread-dot" aria-label="Unread"></span>' : '<span class="read-dot" aria-hidden="true"></span>') + '</button>';
    }).join('');
    conversationList.querySelectorAll('[data-conversation-id]').forEach(function (row) {
      row.addEventListener('click', function () { openConversation(row.dataset.conversationId); });
    });
  }

  function renderMessages(conversation) {
    if (!conversation.messages.length) {
      return '<div class="chat-system"><p>Say hi to ' + escapeHtml(conversation.name) + ' about <strong>' + escapeHtml(conversation.listing) + '</strong>.</p></div>';
    }
    var html = '<div class="chat-day-separator"><span>Today</span></div>';
    conversation.messages.forEach(function (m) {
      var mine = !!m.mine;
      html += '<div class="chat-row' + (mine ? ' is-mine-row' : '') + '">' + (mine ? '' : '<span class="conversation-avatar chat-row-avatar ' + escapeHtml(conversation.avatar) + '">' + escapeHtml(conversation.initials) + '</span>') + '<div class="chat-bubble' + (mine ? ' is-mine' : '') + '"><p>' + escapeHtml(m.text) + '</p><time>' + escapeHtml(formatBubbleTime(m.at)) + '</time></div></div>';
    });
    return html;
  }

  function openConversation(id) {
    const conversation = conversations.find(function (item) { return item.id === id; });
    if (!conversation) return;
    activeConversationId = id;
    conversation.unread = false;
    saveConversations();
    chatName.textContent = conversation.name;
    chatListing.textContent = conversation.listing;
    chatAvatar.textContent = conversation.initials;
    chatAvatar.className = 'conversation-avatar ' + conversation.avatar;
    chatMessages.innerHTML = renderMessages(conversation);
    chatPanel.hidden = false;
    chatEmpty.hidden = true;
    renderConversations();
    chatMessages.scrollTop = chatMessages.scrollHeight;
  }

  function pickReply(listing) {
    var replies = {
      'Calc II': ['Great — Tuesday 17:00 works. Meet at the library main desk?', 'I can share past exam papers as well. What topics feel trickiest?'],
      'Mini fridge': ['Yes still available! Are you on Main or Great East? We can arrange pickup after 15:00.', 'Happy to hold it until tomorrow if you need.'],
      'default': ['Thanks for reaching out! When suits you to meet on campus?', 'Happy to help — let me know what you had in mind.', 'Got it! I can meet in a public spot near campus.']
    };
    for (var k in replies) { if (k !== 'default' && listing.indexOf(k) !== -1) return replies[k][Math.floor(Math.random() * replies[k].length)]; }
    var d = replies['default']; return d[Math.floor(Math.random() * d.length)];
  }

  renderConversations();
  chatForm.addEventListener('submit', function (event) {
    event.preventDefault();
    const text = chatInput.value.trim();
    const conversation = conversations.find(function (item) { return item.id === activeConversationId; });
    if (!text || !conversation) return;
    var trimmed = text.slice(0, 500);
    conversation.messages.push({ text: trimmed, mine: true, at: Date.now() });
    conversation.lastAt = Date.now();
    saveConversations();
    chatInput.value = '';
    openConversation(conversation.id);
    // typing + auto reply
    var typing = document.createElement('div');
    typing.className = 'chat-typing';
    typing.innerHTML = '<span class="conversation-avatar chat-row-avatar ' + escapeHtml(conversation.avatar) + '">' + escapeHtml(conversation.initials) + '</span><span class="typing-bubble"><i></i><i></i><i></i></span>';
    chatMessages.appendChild(typing);
    chatMessages.scrollTop = chatMessages.scrollHeight;
    var replyText = pickReply(conversation.listing);
    setTimeout(function () {
      if (typing.parentNode) typing.remove();
      conversation.messages.push({ text: replyText, mine: false, at: Date.now() });
      conversation.lastAt = Date.now();
      var isActive = activeConversationId === conversation.id && !chatPanel.hidden;
      if (!isActive) conversation.unread = true;
      saveConversations();
      if (isActive) { chatMessages.innerHTML = renderMessages(conversation); chatMessages.scrollTop = chatMessages.scrollHeight; }
      renderConversations();
    }, 900 + Math.random() * 600);
  });

  function filterListings() {
    const query = searchInput.value.trim().toLowerCase();
    let visibleCount = 0;

    document.querySelectorAll('.listing-card').forEach(function (card) {
      const preferredMatches = activeView === 'adverts' || savedPreferences.includes(card.dataset.subcategory);
      const categoryMatches = activeCategory === 'all' || card.dataset.category === activeCategory;
      const searchMatches = !query || card.dataset.search.includes(query);
      const visible = preferredMatches && categoryMatches && searchMatches;
      card.hidden = !visible;
      if (visible) visibleCount += 1;
    });

    emptyFeed.hidden = visibleCount > 0;
  }

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      activeCategory = tab.dataset.category;
      tabs.forEach(function (item) {
        const selected = item === tab;
        item.classList.toggle('is-active', selected);
        item.setAttribute('aria-selected', selected ? 'true' : 'false');
      });
      filterListings();
    });
  });

  searchInput.addEventListener('input', filterListings);
  filterListings();

  function showView(view, updateRoute) {
    if (updateRoute === undefined) updateRoute = true;
    activeView = view;
    const isFeed = view === 'home' || view === 'adverts';
    feedIntro.hidden = !isFeed;
    feedToolbar.hidden = !isFeed;
    feedView.hidden = !isFeed;
    messagesView.hidden = view !== 'messages';
    profileView.hidden = view !== 'profile';
    bottomNavItems.forEach(function (item) {
      const selected = item.dataset.view === view;
      item.classList.toggle('is-active', selected);
      if (selected) item.setAttribute('aria-current', 'page');
      else item.removeAttribute('aria-current');
    });

    if (view === 'home') {
      feedEyebrow.textContent = 'One App, Every Hustle';
      activeCategory = 'all';
    } else if (view === 'adverts') {
      feedEyebrow.textContent = 'Everything happening on campus';
      feedEyebrow.textContent = 'Everything happening on campus';
      activeCategory = 'all';
    }

    if (isFeed) {
      tabs.forEach(function (item) {
        const selected = item.dataset.category === 'all';
        item.classList.toggle('is-active', selected);
        item.setAttribute('aria-selected', selected ? 'true' : 'false');
      });
      filterListings();
    }
    if (updateRoute && window.location.hash !== '#' + view) {
      window.history.pushState({ view: view }, '', '#' + view);
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  bottomNavItems.forEach(function (item) {
    item.addEventListener('click', function () {
      if (!item.dataset.view) return;
      if (item.dataset.view === 'post') {
        window.history.pushState({ view: 'post' }, '', '#post');
        modal.hidden = false;
        document.getElementById('newListingTitle').focus();
        return;
      }
      showView(item.dataset.view);
    });
  });

  document.querySelectorAll('[data-return-home]').forEach(function (button) {
    button.addEventListener('click', function () { showView('home'); });
  });

  function bindReactionButtons(scope) {
    scope.querySelectorAll('.like-button, .save-button').forEach(function (button) {
    button.addEventListener('click', function () {
      button.classList.toggle('is-active');
      if (button.classList.contains('like-button')) {
        const count = button.querySelector('b');
        const current = Number(count.textContent);
        count.textContent = button.classList.contains('is-active') ? current + 1 : current - 1;
      }
    });
  });
  }

  bindReactionButtons(document);

  document.querySelectorAll('[data-message]').forEach(function (button) {
    button.addEventListener('click', function () {
      const card = button.closest('.listing-card');
      const listing = card ? (card.querySelector('h2').textContent + ' · ' + (card.querySelector('.listing-price') ? card.querySelector('.listing-price').textContent.trim() : '')) : 'Campus listing';
      let conversation = conversations.find(function (item) { return item.name === button.dataset.message && item.listing === listing; });
      if (!conversation) {
        var avatars = ['avatar-navy', 'avatar-orange', 'avatar-green'];
        var avatar = avatars[conversations.length % 3];
        conversation = { id: 'conv-' + Date.now(), name: button.dataset.message, initials: button.dataset.message.slice(0, 2).toUpperCase(), avatar: avatar, listing: listing, time: 'now', lastAt: Date.now(), unread: false, messages: [] };
        conversations.unshift(conversation);
        saveConversations();
      } else {
        // bring to top
        conversations = [conversation].concat(conversations.filter(function (c) { return c.id !== conversation.id; }));
        saveConversations();
      }
      showView('messages');
      renderConversations();
      openConversation(conversation.id);
    });
  });

  const modal = document.getElementById('listingModal');
  const listingForm = document.getElementById('listingForm');
  const listingCategory = document.getElementById('newListingCategory');
  const listingType = document.getElementById('newListingType');
  const subcategoryOptions = {
    services: [['tutoring', 'Tutoring'], ['repairs', 'Repairs'], ['beauty', 'Beauty & wellness']],
    goods: [['electronics', 'Electronics'], ['books', 'Books'], ['furniture', 'Furniture']],
    gigs: [['creative', 'Creative work'], ['campus-help', 'Campus help']]
  };

  function updateListingTypes() {
    listingType.innerHTML = subcategoryOptions[listingCategory.value].map(function (option) {
      return '<option value="' + option[0] + '">' + option[1] + '</option>';
    }).join('');
  }

  listingCategory.addEventListener('change', updateListingTypes);
  updateListingTypes();

  function closeModal() {
    modal.hidden = true;
    if (window.location.hash === '#post') {
      window.history.pushState({ view: activeView }, '', '#' + activeView);
    }
  }

  document.getElementById('createListingBtn').addEventListener('click', function () {
    modal.hidden = false;
    document.getElementById('newListingTitle').focus();
  });
  document.getElementById('closeListingModal').addEventListener('click', closeModal);
  modal.addEventListener('click', function (event) {
    if (event.target === modal) closeModal();
  });

  function applyRoute() {
    const route = window.location.hash.slice(1) || 'home';
    if (route === 'post') {
      showView(activeView, false);
      modal.hidden = false;
      return;
    }
    const supportedRoutes = ['home', 'adverts', 'messages', 'profile'];
    showView(supportedRoutes.includes(route) ? route : 'home', false);
  }

  window.addEventListener('popstate', applyRoute);
  window.addEventListener('hashchange', applyRoute);
  applyRoute();

  listingForm.addEventListener('submit', function (event) {
    event.preventDefault();
    const title = document.getElementById('newListingTitle').value.trim();
    const category = document.getElementById('newListingCategory').value;
    const subcategory = document.getElementById('newListingType').value;
    const price = document.getElementById('newListingPrice').value.trim();
    const description = document.getElementById('newListingDescription').value.trim() || 'New listing from ' + username + '.';
    const card = document.createElement('article');
    card.className = 'listing-card';
    card.dataset.category = category;
    card.dataset.subcategory = subcategory;
    card.dataset.preferred = savedPreferences.includes(subcategory) ? 'true' : 'false';
    card.dataset.search = (title + ' ' + description + ' ' + subcategory).toLowerCase();
    card.innerHTML = '<div class="listing-card-header"><div class="seller-avatar avatar-navy">' + escapeHtml(initials) + '</div><div class="seller-details"><strong>' + escapeHtml(username) + '</strong><span>' + escapeHtml(user.campus || 'Main Campus') + ' · just now</span></div><button class="icon-button listing-menu" type="button" aria-label="More options">•••</button></div><div class="listing-image image-design"><img src="assets/hero-poster.jpg" alt="New campus listing"></div><div class="listing-content"><div class="listing-meta"><span class="listing-tag tag-blue">' + escapeHtml(category) + '</span><span class="listing-price">' + escapeHtml(price) + '</span></div><h2>' + escapeHtml(title) + '</h2><p>' + escapeHtml(description) + '</p><div class="listing-actions"><button class="feed-action like-button" type="button" aria-label="Like listing"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 8.6c0 5.2-8.8 10.2-8.8 10.2S3.2 13.8 3.2 8.6A4.6 4.6 0 0 1 12 6.3a4.6 4.6 0 0 1 8.8 2.3z"/></svg><b>0</b></button><button class="feed-action" type="button"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11.5a7.5 7.5 0 0 1-8 7.5 8.5 8.5 0 0 1-4-.9L4 20l1.2-3.3A7.2 7.2 0 0 1 4 12.5 7.5 7.5 0 0 1 12 5a7.5 7.5 0 0 1 8 6.5z"/></svg><b>Message</b></button><button class="feed-action save-button" type="button" aria-label="Save listing"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4.5A1.5 1.5 0 0 1 7.5 3h9A1.5 1.5 0 0 1 18 4.5V21l-6-3-6 3z"/></svg></button></div></div>';
    document.getElementById('listingFeed').prepend(card);
    bindReactionButtons(card);
    filterListings();
    closeModal();
    listingForm.reset();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  document.getElementById('editProfileBtn').addEventListener('click', function () {
    showView('profile');
  });

  document.getElementById('profileEditAction').addEventListener('click', function () {
    document.getElementById('profileUsername').value = username;
    document.getElementById('profileCampusInput').value = user.campus || 'Main Campus';
    document.getElementById('profileBioInput').value = user.bio || 'Student seller and campus community member.';
    document.getElementById('profileModal').hidden = false;
  });

  document.getElementById('closeProfileModal').addEventListener('click', function () { document.getElementById('profileModal').hidden = true; });
  document.getElementById('profileModal').addEventListener('click', function (event) { if (event.target.id === 'profileModal') event.currentTarget.hidden = true; });
  document.getElementById('profileForm').addEventListener('submit', function (event) {
    event.preventDefault();
    const updatedUser = { username: document.getElementById('profileUsername').value.trim(), email: user.email, campus: document.getElementById('profileCampusInput').value, bio: document.getElementById('profileBioInput').value.trim() };
    sessionStorage.setItem('cc-user', JSON.stringify(updatedUser));
    window.location.reload();
  });

  function escapeHtml(value) {
    return value.replace(/[&<>'"]/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
    });
  }
});
