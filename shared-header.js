/* Campus Connect — shared app chrome
   Injects the desktop icon sidebar, mobile top-bar actions, search &
   notifications slide-out panels, menu popover, floating messages pill,
   and chat drawer on every page. Loaded after script.js. */
(function () {
  'use strict';

  var token = sessionStorage.getItem('cc-auth-token');
  var user = {};
  try { user = JSON.parse(sessionStorage.getItem('cc-user') || '{}'); } catch (e) {}

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }
  function api(path, opts) {
    opts = opts || {};
    opts.headers = Object.assign({ 'Content-Type': 'application/json' }, opts.headers || {});
    if (token) opts.headers['Authorization'] = 'Bearer ' + token;
    return fetch('api/' + path, opts).then(function (r) { return r.json().catch(function () { return { success: false, error: 'Server error.' }; }); });
  }
  function apiGet(path) { return api(path, { method: 'GET' }); }
  function toast(msg, isErr) {
    var host = document.getElementById('ccToastHost');
    if (!host) { host = el('div'); host.id = 'ccToastHost'; document.body.appendChild(host); }
    var t = el('div', 'cc-toast' + (isErr ? ' is-error' : ''), msg);
    host.appendChild(t);
    setTimeout(function () { t.classList.add('is-in'); }, 10);
    setTimeout(function () { t.classList.remove('is-in'); setTimeout(function () { t.remove(); }, 300); }, 3200);
  }
  window.ccToast = toast;

  var initials = (user.username || user.real_name || 'CC').slice(0, 2).toUpperCase();
  var currentPage = (location.pathname.split('/').pop() || 'home.html').replace('.html', '');

  /* ---------- Icons (inline SVG) ---------- */
  var I = {
    home: '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
    listings: '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    messages: '<path d="M20 11.5a7.5 7.5 0 0 1-8 7.5 8.5 8.5 0 0 1-4-.9L4 20l1.2-3.3A7.2 7.2 0 0 1 4 12.5 7.5 7.5 0 0 1 12 5a7.5 7.5 0 0 1 8 6.5z"/>',
    search: '<circle cx="11" cy="11" r="6.5"/><path d="m15.5 15.5 4 4"/>',
    bell: '<path d="M18 9a6 6 0 1 0-12 0c0 6-2 6-2 6h16s-2 0-2-6"/><path d="M10.3 19a2 2 0 0 0 3.4 0"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    menu: '<path d="M4 7h16M4 12h16M4 17h16"/>',
    close: '<path d="m6 6 12 12M18 6 6 18"/>'
  };
  function icon(name) {
    var s = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    s.setAttribute('viewBox', '0 0 24 24');
    s.setAttribute('aria-hidden', 'true');
    s.innerHTML = I[name];
    return s;
  }

  /* ---------- Build the sidebar ---------- */
  var sidebar = el('nav', 'cc-sidebar');
  sidebar.setAttribute('aria-label', 'Primary');

  function sideBtn(label, page, iconName, opts) {
    opts = opts || {};
    var a = document.createElement(opts.href ? 'a' : 'button');
    a.type = 'button';
    if (opts.href) a.href = opts.href;
    a.className = 'cc-side-btn' + (currentPage === page ? ' is-active' : '');
    a.setAttribute('aria-label', label);
    a.setAttribute('data-tip', label);
    a.appendChild(icon(iconName));
    if (opts.badge) {
      var b = el('span', 'cc-side-badge');
      b.id = opts.badge;
      b.hidden = true;
      a.appendChild(b);
    }
    if (opts.avatar) {
      a.innerHTML = '';
      var av = el('span', 'cc-side-avatar');
      av.id = 'ccSideAvatar';
      av.textContent = initials;
      a.appendChild(av);
    }
    return a;
  }

  sidebar.appendChild(sideBtn('Home', 'home', 'home', { href: 'home.html' }));
  sidebar.appendChild(sideBtn('Listings', 'listings', 'listings', { href: 'listings.html' }));
  sidebar.appendChild(sideBtn('Messages', 'messages', 'messages', { href: 'messages.html', badge: 'ccMsgBadge' }));
  sidebar.appendChild(sideBtn('Search', null, 'search', {}));
  sidebar.appendChild(sideBtn('Notifications', null, 'bell', { badge: 'ccNotifBadge' }));
  sidebar.appendChild(sideBtn('Create listing', 'create-listing', 'plus', { href: 'create-listing.html' }));
  sidebar.appendChild(sideBtn('Profile', 'profile', 'menu', { href: 'profile.html', avatar: true }));
  var menuBtn = sideBtn('Menu', null, 'menu', {});
  sidebar.appendChild(menuBtn);
  document.body.appendChild(sidebar);
  document.body.classList.add('cc-has-sidebar');

  /* ---------- Slide-out panels ---------- */
  function makePanel(id, title) {
    var p = el('aside', 'cc-panel');
    p.id = id;
    p.setAttribute('aria-label', title);
    p.hidden = true;
    var head = el('div', 'cc-panel-head');
    var h = el('h2', null, title);
    var x = el('button', 'cc-panel-close');
    x.type = 'button'; x.setAttribute('aria-label', 'Close ' + title);
    x.appendChild(icon('close'));
    x.addEventListener('click', function () { closePanels(); });
    head.appendChild(h); head.appendChild(x);
    p.appendChild(head);
    var bodyEl = el('div', 'cc-panel-body');
    p.appendChild(bodyEl);
    document.body.appendChild(p);
    return { root: p, body: bodyEl };
  }
  var searchPanel = makePanel('ccSearchPanel', 'Search');
  var notifPanel = makePanel('ccNotifPanel', 'Notifications');
  var scrim = el('div', 'cc-scrim');
  scrim.hidden = true;
  document.body.appendChild(scrim);

  var openPanelEl = null;
  function closePanels() {
    [searchPanel.root, notifPanel.root].forEach(function (p) { p.hidden = true; p.classList.remove('is-open'); });
    scrim.hidden = true;
    openPanelEl = null;
  }
  function openPanel(p) {
    if (openPanelEl === p.root) { closePanels(); return; }
    closePanels();
    p.root.hidden = false;
    requestAnimationFrame(function () { p.root.classList.add('is-open'); });
    scrim.hidden = false;
    openPanelEl = p.root;
  }
  scrim.addEventListener('click', closePanels);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closePanels(); });

  /* ---------- Search panel ---------- */
  var searchInput = el('input');
  searchInput.type = 'search';
  searchInput.placeholder = 'Search people and listings';
  searchInput.setAttribute('aria-label', 'Search');
  searchInput.className = 'cc-panel-input';
  var searchResults = el('div', 'cc-panel-results');
  searchPanel.body.appendChild(searchInput);
  searchPanel.body.appendChild(searchResults);

  var searchTimer = null;
  searchInput.addEventListener('input', function () {
    clearTimeout(searchTimer);
    var q = searchInput.value.trim();
    if (q.length < 2) { searchResults.innerHTML = ''; renderRecent(); return; }
    searchTimer = setTimeout(function () { runSearch(q); }, 280);
  });
  function renderRecent() {
    var recent = [];
    try { recent = JSON.parse(localStorage.getItem('cc-recent-searches') || '[]'); } catch (e) {}
    searchResults.innerHTML = '';
    if (!recent.length) return;
    searchResults.appendChild(el('p', 'cc-panel-label', 'Recent'));
    recent.slice(0, 6).forEach(function (q) {
      var b = el('button', 'cc-recent-item', q);
      b.type = 'button';
      b.addEventListener('click', function () { searchInput.value = q; runSearch(q); });
      searchResults.appendChild(b);
    });
  }
  function saveRecent(q) {
    var recent = [];
    try { recent = JSON.parse(localStorage.getItem('cc-recent-searches') || '[]'); } catch (e) {}
    recent = [q].concat(recent.filter(function (r) { return r !== q; })).slice(0, 8);
    try { localStorage.setItem('cc-recent-searches', JSON.stringify(recent)); } catch (e) {}
  }
  function runSearch(q) {
    searchResults.innerHTML = '';
    searchResults.appendChild(el('p', 'cc-panel-label', 'Searching…'));
    apiGet('search/index.php?q=' + encodeURIComponent(q)).then(function (res) {
      searchResults.innerHTML = '';
      if (!res.success) { searchResults.appendChild(el('p', 'cc-panel-label', res.error || 'Search failed.')); return; }
      saveRecent(q);
      (res.users || []).forEach(function (u) {
        var a = el('a', 'cc-result-row');
        a.href = 'profile.html?u=' + encodeURIComponent(u.username);
        var av = el('span', 'cc-side-avatar', (u.username || 'U').slice(0, 2).toUpperCase());
        if (u.avatar_url) { av.textContent = ''; av.style.backgroundImage = 'url(' + u.avatar_url + ')'; av.style.backgroundSize = 'cover'; }
        var copy = el('span', 'cc-result-copy');
        copy.appendChild(el('strong', null, u.display_name || u.real_name || u.username));
        copy.appendChild(el('small', null, '@' + u.username + (u.campus ? ' · ' + u.campus : '')));
        a.appendChild(av); a.appendChild(copy);
        searchResults.appendChild(a);
      });
      (res.listings || []).forEach(function (l) {
        var a = el('a', 'cc-result-row');
        a.href = 'listing-detail.html?id=' + encodeURIComponent(l.id);
        var copy = el('span', 'cc-result-copy');
        copy.appendChild(el('strong', null, l.title));
        copy.appendChild(el('small', null, (l.price ? 'K' + parseFloat(l.price).toFixed(2) : 'Negotiable') + ' · ' + (l.category || '')));
        a.appendChild(copy);
        searchResults.appendChild(a);
      });
      if (!(res.users || []).length && !(res.listings || []).length) {
        searchResults.appendChild(el('p', 'cc-panel-label', 'No results for "' + q + '".'));
      }
    }).catch(function () {
      searchResults.innerHTML = '';
      searchResults.appendChild(el('p', 'cc-panel-label', 'You appear to be offline.'));
    });
  }
  renderRecent();

  /* ---------- Notifications panel ---------- */
  function loadNotifications() {
    notifPanel.body.innerHTML = '';
    if (!token) { notifPanel.body.appendChild(el('p', 'cc-panel-label', 'Log in to see notifications.')); return; }
    apiGet('notifications/list.php?limit=30').then(function (res) {
      notifPanel.body.innerHTML = '';
      if (!res.success || !(res.notifications || []).length) {
        notifPanel.body.appendChild(el('p', 'cc-panel-label', 'No notifications yet.'));
        return;
      }
      res.notifications.forEach(function (n) {
        var row = el('div', 'cc-result-row' + (n.read_at ? '' : ' is-unread'));
        var copy = el('span', 'cc-result-copy');
        copy.appendChild(el('strong', null, n.actor_username || 'Campus Connect'));
        copy.appendChild(el('small', null, n.body || n.type));
        row.appendChild(copy);
        notifPanel.body.appendChild(row);
      });
      api('notifications/read-all.php', { method: 'POST', body: '{}' }).then(updateBadges);
    }).catch(function () {
      notifPanel.body.innerHTML = '';
      notifPanel.body.appendChild(el('p', 'cc-panel-label', 'You appear to be offline.'));
    });
  }

  /* ---------- Wire sidebar buttons ---------- */
  var btns = sidebar.querySelectorAll('.cc-side-btn');
  btns[3].addEventListener('click', function () { openPanel(searchPanel); searchInput.focus(); });
  btns[4].addEventListener('click', function () { openPanel(notifPanel); loadNotifications(); });

  /* ---------- Menu popover ---------- */
  var pop = el('div', 'cc-menu-pop');
  pop.hidden = true;
  function popItem(label, fn, href) {
    var b = document.createElement(href ? 'a' : 'button');
    if (href) b.href = href; else b.type = 'button';
    b.textContent = label;
    b.addEventListener('click', function () { pop.hidden = true; if (fn) fn(); });
    pop.appendChild(b);
  }
  popItem('Settings', null, 'settings.html');
  popItem('Preferences', null, 'preferences.html');
  popItem('Toggle dark mode', function () {
    var cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', cur);
    try { localStorage.setItem('cc-theme', cur); } catch (e) {}
  });
  popItem('Log out', function () {
    fetch('logout.php', { method: 'POST' }).finally(function () {
      sessionStorage.removeItem('cc-auth-token');
      sessionStorage.removeItem('cc-user');
      location.href = 'index.html';
    });
  });
  document.body.appendChild(pop);
  menuBtn.addEventListener('click', function () {
    pop.hidden = !pop.hidden;
    if (!pop.hidden) {
      var r = menuBtn.getBoundingClientRect();
      pop.style.left = (r.right + 8) + 'px';
      pop.style.bottom = (window.innerHeight - r.bottom) + 'px';
    }
  });
  document.addEventListener('click', function (e) {
    if (!pop.hidden && !pop.contains(e.target) && e.target !== menuBtn && !menuBtn.contains(e.target)) pop.hidden = true;
  });

  /* ---------- Badges (poll) ---------- */
  function setBadge(id, n) {
    var b = document.getElementById(id);
    if (!b) return;
    b.hidden = !(n > 0);
    b.textContent = n > 9 ? '9+' : String(n);
  }
  function updateBadges() {
    if (!token) return;
    apiGet('messages/unread-count.php').then(function (r) { if (r.success) setBadge('ccMsgBadge', r.unread); }).catch(function () {});
    apiGet('notifications/unread-count.php').then(function (r) { if (r.success) setBadge('ccNotifBadge', r.unread); }).catch(function () {});
  }
  updateBadges();
  setInterval(updateBadges, 30000);
  window.ccUpdateBadges = updateBadges;

  /* ---------- Floating messages pill + chat drawer ---------- */
  if (token && currentPage !== 'messages') {
    var pill = el('button', 'cc-msg-pill');
    pill.type = 'button';
    pill.setAttribute('aria-label', 'Open messages');
    pill.appendChild(icon('messages'));
    var pillBadge = el('span', 'cc-side-badge');
    pillBadge.id = 'ccPillBadge';
    pillBadge.hidden = true;
    pill.appendChild(pillBadge);
    document.body.appendChild(pill);

    var drawer = el('aside', 'cc-chat-drawer');
    drawer.hidden = true;
    drawer.innerHTML = '<div class="cc-panel-head"><h2>Messages</h2><button class="cc-panel-close" type="button" aria-label="Close messages"></button></div><div class="cc-drawer-body"></div>';
    drawer.querySelector('.cc-panel-close').appendChild(icon('close'));
    document.body.appendChild(drawer);
    var drawerBody = drawer.querySelector('.cc-drawer-body');
    drawer.querySelector('.cc-panel-close').addEventListener('click', function () { drawer.hidden = true; });

    pill.addEventListener('click', function () {
      drawer.hidden = !drawer.hidden;
      if (!drawer.hidden) loadDrawer();
    });
    function loadDrawer() {
      drawerBody.innerHTML = '';
      apiGet('messages/conversations.php').then(function (res) {
        drawerBody.innerHTML = '';
        if (!res.success || !(res.conversations || []).length) {
          drawerBody.appendChild(el('p', 'cc-panel-label', 'No conversations yet.'));
          return;
        }
        res.conversations.slice(0, 8).forEach(function (c) {
          var a = el('a', 'cc-result-row');
          a.href = 'messages.html?conversation=' + encodeURIComponent(c.id);
          var av = el('span', 'cc-side-avatar', (c.other_username || 'U').slice(0, 2).toUpperCase());
          var copy = el('span', 'cc-result-copy');
          copy.appendChild(el('strong', null, c.other_username || 'Conversation'));
          copy.appendChild(el('small', null, c.last_message || c.listing_title || ''));
          a.appendChild(av); a.appendChild(copy);
          if (c.unread) a.appendChild(el('span', 'unread-dot'));
          drawerBody.appendChild(a);
        });
      }).catch(function () { drawerBody.appendChild(el('p', 'cc-panel-label', 'You appear to be offline.')); });
    }
    setInterval(function () {
      if (!token) return;
      apiGet('messages/unread-count.php').then(function (r) {
        if (r.success) { setBadge('ccPillBadge', r.unread); setBadge('ccMsgBadge', r.unread); }
      }).catch(function () {});
    }, 30000);
  }

  /* ---------- Mobile top-bar actions ---------- */
  var headerActions = document.querySelector('.site-header .header-actions');
  if (headerActions && window.matchMedia('(max-width: 767px)').matches) {
    var mSearch = el('button', 'cc-top-icon');
    mSearch.type = 'button'; mSearch.setAttribute('aria-label', 'Search');
    mSearch.appendChild(icon('search'));
    mSearch.addEventListener('click', function () { openPanel(searchPanel); searchInput.focus(); });
    var mMsg = document.createElement('a');
    mMsg.className = 'cc-top-icon'; mMsg.href = 'messages.html'; mMsg.setAttribute('aria-label', 'Messages');
    mMsg.appendChild(icon('messages'));
    var mb = el('span', 'cc-side-badge'); mb.id = 'ccTopMsgBadge'; mb.hidden = true; mMsg.appendChild(mb);
    headerActions.insertBefore(mMsg, headerActions.firstChild);
    headerActions.insertBefore(mSearch, headerActions.firstChild);
    setInterval(function () {
      if (!token) return;
      apiGet('messages/unread-count.php').then(function (r) { if (r.success) setBadge('ccTopMsgBadge', r.unread); }).catch(function () {});
    }, 30000);
  }

  /* ---------- Set sidebar avatar image if available ---------- */
  var sideAvatar = document.getElementById('ccSideAvatar');
  if (sideAvatar && user.avatar) {
    sideAvatar.textContent = '';
    sideAvatar.style.backgroundImage = 'url(' + user.avatar + ')';
    sideAvatar.style.backgroundSize = 'cover';
  }
})();
