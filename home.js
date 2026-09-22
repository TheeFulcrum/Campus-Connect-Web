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

  function loadConversations() {
    try {
      const stored = JSON.parse(localStorage.getItem(conversationKey) || 'null');
      return stored || [
        { id: 'alex-tutoring', name: 'Alex M.', initials: 'AM', avatar: 'avatar-navy', listing: 'Calc II tutoring', time: '12m', unread: true, messages: [{ text: 'Is Tuesday at 17:00 okay?', mine: false }] },
        { id: 'tendai-fridge', name: 'Tendai N.', initials: 'TN', avatar: 'avatar-orange', listing: 'Mini fridge, barely used', time: '34m', unread: true, messages: [{ text: 'The mini fridge is still available.', mine: false }] }
      ];
    } catch (error) { return []; }
  }

  function saveConversations() { localStorage.setItem(conversationKey, JSON.stringify(conversations)); }

  function renderConversations() {
    const unreadCount = conversations.filter(function (conversation) { return conversation.unread; }).length;
    messagesView.querySelector('.panel-count').textContent = unreadCount;
    conversationList.innerHTML = conversations.map(function (conversation) {
      const latest = conversation.messages[conversation.messages.length - 1];
      return '<button class="conversation-row' + (conversation.id === activeConversationId ? ' is-active' : '') + '" type="button" data-conversation-id="' + escapeHtml(conversation.id) + '"><span class="conversation-avatar ' + escapeHtml(conversation.avatar) + '">' + escapeHtml(conversation.initials) + '</span><span class="conversation-copy"><strong>' + escapeHtml(conversation.name) + '</strong><small>' + escapeHtml(latest ? latest.text : 'Start a conversation') + '</small></span><time>' + escapeHtml(conversation.time || 'now') + '</time>' + (conversation.unread ? '<span class="unread-dot"></span>' : '') + '</button>';
    }).join('');
    conversationList.querySelectorAll('[data-conversation-id]').forEach(function (row) {
      row.addEventListener('click', function () { openConversation(row.dataset.conversationId); });
    });
  }

  function openConversation(id) {
    const conversation = conversations.find(function (item) { return item.id === id; });
    if (!conversation) return;
    activeConversationId = id;
    conversation.unread = false;
    chatName.textContent = conversation.name;
    chatListing.textContent = conversation.listing;
    chatAvatar.textContent = conversation.initials;
    chatAvatar.className = 'conversation-avatar ' + conversation.avatar;
    chatMessages.innerHTML = conversation.messages.map(function (message) {
      return '<div class="chat-bubble' + (message.mine ? ' is-mine' : '') + '">' + escapeHtml(message.text) + '</div>';
    }).join('');
    chatPanel.hidden = false;
    chatEmpty.hidden = true;
    renderConversations();
    chatMessages.scrollTop = chatMessages.scrollHeight;
  }

  renderConversations();
  chatForm.addEventListener('submit', function (event) {
    event.preventDefault();
    const text = chatInput.value.trim();
    const conversation = conversations.find(function (item) { return item.id === activeConversationId; });
    if (!text || !conversation) return;
    conversation.messages.push({ text: text, mine: true });
    conversation.time = 'now';
    saveConversations();
    chatInput.value = '';
    openConversation(conversation.id);
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
      const listing = card ? card.querySelector('h2').textContent : 'Campus listing';
      let conversation = conversations.find(function (item) { return item.name === button.dataset.message; });
      if (!conversation) {
        conversation = { id: Date.now().toString(), name: button.dataset.message, initials: button.dataset.message.slice(0, 2).toUpperCase(), avatar: 'avatar-navy', listing: listing, time: 'now', unread: false, messages: [] };
        conversations.unshift(conversation);
        saveConversations();
      }
      showView('messages');
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
