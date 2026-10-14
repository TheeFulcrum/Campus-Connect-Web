/* Campus Connect — profile page data layer.
   Own / other-user / logged-out states, tabs, collections, notes,
   followers/following modals, quick view, edit profile with avatar crop. */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var token = sessionStorage.getItem('cc-auth-token');
  var me = {};
  try { me = JSON.parse(sessionStorage.getItem('cc-user') || '{}'); } catch (e) {}

  // Which profile? ?u=username, else own.
  var params = new URLSearchParams(location.search);
  var requestedUser = (params.get('u') || '').trim();
  var profileUsername = requestedUser || (me.username || '');
  var isOwn = !requestedUser || (me.username && requestedUser.toLowerCase() === me.username.toLowerCase());

  if (!profileUsername && !token) { window.location.href = 'login.html'; return; }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function el(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }
  function api(path, opts) {
    opts = opts || {};
    opts.headers = Object.assign({}, opts.headers || {});
    if (!(opts.body instanceof FormData)) opts.headers['Content-Type'] = 'application/json';
    if (token) opts.headers['Authorization'] = 'Bearer ' + token;
    return fetch('api/' + path, opts).then(function (r) { return r.json().catch(function () { return { success: false, error: 'Server error.' }; }); });
  }
  function say(m, e) { if (window.ccToast) window.ccToast(m, e); }

  var state = { profile: null, tab: 'listings', page: 1, done: false, loading: false };

  var grid = document.getElementById('profileListings');
  var reviewsWrap = document.getElementById('profileReviews');
  var empty = document.getElementById('profileTabEmpty');
  var emptyIcon = document.getElementById('profileEmptyIcon');
  var emptyTitle = document.getElementById('profileEmptyTitle');
  var emptyText = document.getElementById('profileEmptyText');
  var emptyCta = document.getElementById('profileEmptyCta');
  var sentinel = document.getElementById('profileSentinel');

  /* ---------------- Load profile ---------------- */
  function loadProfile() {
    api('profile/get.php?username=' + encodeURIComponent(profileUsername)).then(function (res) {
      if (!res.success) { say(res.error || 'Profile not found.', true); return; }
      state.profile = res.profile;
      renderHeader(res.profile);
      renderCollections();
      var tab = (params.get('tab') || 'listings');
      switchTab(['listings', 'saved', 'sold', 'reviews'].indexOf(tab) > -1 ? tab : 'listings', true);
    }).catch(function () { say('You appear to be offline.', true); });
  }

  function renderHeader(p) {
    isOwn = !!p.is_me;
    document.getElementById('profileUsernameDisplay').textContent = p.username;
    document.getElementById('profileRealName').textContent = p.display_name || p.real_name || p.username;
    document.getElementById('profileBio').textContent = p.bio || '';
    document.getElementById('profileCampusBadge').textContent = p.campus || '';
    document.getElementById('statPosts').textContent = p.counts.listings;
    document.getElementById('statFollowers').textContent = p.counts.followers;
    document.getElementById('statFollowing').textContent = p.counts.following;
    document.title = (p.display_name || p.username) + ' — Campus Connect';

    var link = document.getElementById('profileLink');
    if (p.link) { link.hidden = false; link.href = p.link; link.textContent = p.link.replace(/^https?:\/\//, ''); }
    else link.hidden = true;

    var av = document.getElementById('profilePageAvatar');
    if (p.avatar_url) { av.textContent = ''; av.style.backgroundImage = 'url(' + p.avatar_url + ')'; av.style.backgroundSize = 'cover'; av.style.backgroundPosition = 'center'; }
    else { av.textContent = (p.username || 'CC').slice(0, 2).toUpperCase(); av.style.backgroundImage = ''; }

    // Note chip
    var chip = document.getElementById('noteChip');
    if (p.note && p.note.body) { chip.textContent = p.note.body; chip.classList.remove('is-empty'); }
    else { chip.textContent = p.is_me ? 'Note…' : 'No note'; chip.classList.add('is-empty'); }
    chip.style.display = p.is_me || (p.note && p.note.body) ? '' : 'none';

    // Actions row
    var actions = document.getElementById('profileActions');
    var more = document.getElementById('profileMoreBtn');
    document.getElementById('avatarEditBtn').style.display = p.is_me ? '' : 'none';
    if (p.is_me) {
      actions.innerHTML = '';
      var edit = el('button', 'btn btn-primary', 'Edit profile'); edit.type = 'button'; edit.id = 'profileEditAction';
      var arch = el('button', 'btn btn-outline', 'View archive'); arch.type = 'button'; arch.id = 'viewArchiveAction';
      actions.appendChild(edit); actions.appendChild(arch);
      more.hidden = true;
      edit.addEventListener('click', openEdit);
      arch.addEventListener('click', function () { switchTab('sold'); });
    } else {
      actions.innerHTML = '';
      var followBtn = el('button', 'btn ' + (p.is_following ? 'is-following' : 'btn-primary'), p.is_following ? 'Following' : 'Follow');
      followBtn.type = 'button'; followBtn.id = 'followBtn';
      var msgBtn = el('a', 'btn btn-outline', 'Message');
      // Opens messages.html in compose mode; the first message creates the conversation.
      msgBtn.href = 'messages.html?u=' + encodeURIComponent(p.username);
      actions.appendChild(followBtn); actions.appendChild(msgBtn);
      more.hidden = false;
      followBtn.addEventListener('click', function () { toggleFollow(p, followBtn); });
    }
  }

  /* ---------------- Follow (optimistic) ---------------- */
  function toggleFollow(p, btn) {
    if (!token) { window.location.href = 'login.html'; return; }
    var wasFollowing = p.is_following;
    p.is_following = !wasFollowing;
    p.counts.followers += p.is_following ? 1 : -1;
    btn.textContent = p.is_following ? 'Following' : 'Follow';
    btn.className = 'btn ' + (p.is_following ? 'is-following' : 'btn-primary');
    document.getElementById('statFollowers').textContent = p.counts.followers;
    api('follow/' + (p.is_following ? 'follow' : 'unfollow') + '.php', { method: 'POST', body: JSON.stringify({ username: p.username }) })
      .then(function (res) {
        if (!res.success) throw new Error(res.error);
        if (typeof res.followers === 'number') { p.counts.followers = res.followers; document.getElementById('statFollowers').textContent = res.followers; }
      })
      .catch(function (err) {
        // revert
        p.is_following = wasFollowing;
        p.counts.followers += wasFollowing ? 1 : -1;
        btn.textContent = wasFollowing ? 'Following' : 'Follow';
        btn.className = 'btn ' + (wasFollowing ? 'is-following' : 'btn-primary');
        document.getElementById('statFollowers').textContent = p.counts.followers;
        say(err.message || 'Action failed.', true);
      });
  }

  /* ---------------- Collections row ---------------- */
  function renderCollections() {
    var row = document.getElementById('collectionsRow');
    row.innerHTML = '';
    if (isOwn) {
      var add = el('button', 'collection-circle');
      add.type = 'button'; add.setAttribute('aria-label', 'New collection');
      var th = el('span', 'collection-thumb'); th.textContent = '+';
      add.appendChild(th); add.appendChild(el('span', null, 'New'));
      add.addEventListener('click', function () { say('Collections open in a create flow — name it, then add listings from your saved tab.'); });
      row.appendChild(add);
    }
    if (!token) return;
    api('collections/list.php').then(function (res) {
      if (!res.success) return;
      (res.collections || []).forEach(function (c) {
        var b = el('button', 'collection-circle');
        b.type = 'button'; b.setAttribute('aria-label', c.name);
        var thumb = el('span', 'collection-thumb');
        var cover = (c.items && c.items[0] && c.items[0].cover_image) || null;
        if (cover) { var img = new Image(); img.src = cover; img.alt = ''; thumb.appendChild(img); }
        else thumb.textContent = (c.name || 'C').slice(0, 1).toUpperCase();
        b.appendChild(thumb); b.appendChild(el('span', null, c.name));
        b.addEventListener('click', function () { switchTab('saved'); });
        row.appendChild(b);
      });
    }).catch(function () {});
  }

  /* ---------------- Tabs ---------------- */
  document.querySelectorAll('[data-profile-tab]').forEach(function (btn) {
    btn.addEventListener('click', function () { switchTab(btn.dataset.profileTab); });
  });
  function switchTab(tab, skipPush) {
    state.tab = tab; state.page = 1; state.done = false;
    document.querySelectorAll('[data-profile-tab]').forEach(function (b) { b.classList.toggle('is-active', b.dataset.profileTab === tab); });
    grid.innerHTML = ''; reviewsWrap.innerHTML = ''; reviewsWrap.hidden = tab !== 'reviews'; grid.hidden = tab === 'reviews';
    if (!skipPush) { var u = new URL(location.href); u.searchParams.set('tab', tab); if (requestedUser) u.searchParams.set('u', requestedUser); history.pushState({}, '', u); }
    loadTab();
  }
  window.addEventListener('popstate', function () {
    var t = new URLSearchParams(location.search).get('tab') || 'listings';
    switchTab(t, true);
  });

  var EMPTY = {
    listings: ['✦', 'No listings yet', 'When ' + (isOwn ? 'you post' : 'they post') + ' a listing it will show up here.', isOwn ? 'Post your first listing' : null, 'create-listing.html'],
    saved: ['♡', 'No saved listings yet', 'Save useful campus listings and they will appear here.', 'Browse listings', 'listings.html'],
    sold: ['✓', 'Nothing sold yet', 'Completed sales will appear here.', null, null],
    reviews: ['★', 'No reviews yet', 'Reviews appear after completed campus deals.', null, null]
  };
  function showEmpty(tab) {
    var e = EMPTY[tab];
    emptyIcon.textContent = e[0]; emptyTitle.textContent = e[1]; emptyText.textContent = e[2];
    if (e[3]) { emptyCta.hidden = false; emptyCta.textContent = e[3]; emptyCta.href = e[4]; } else emptyCta.hidden = true;
    empty.hidden = false;
  }

  function skeletons() {
    empty.hidden = true;
    grid.innerHTML = '';
    for (var i = 0; i < 6; i++) { var s = el('div', 'cc-skel'); s.style.aspectRatio = '1'; grid.appendChild(s); }
  }

  function loadTab() {
    if (state.loading || state.done) return;
    state.loading = true;
    if (state.page === 1) skeletons();
    var u = state.profile ? state.profile.username : profileUsername;
    var url;
    if (state.tab === 'listings') url = 'profile/listings.php?username=' + encodeURIComponent(u) + '&page=' + state.page;
    else if (state.tab === 'saved') url = 'profile/saved.php?page=' + state.page;
    else if (state.tab === 'sold') url = 'profile/sold.php?username=' + encodeURIComponent(u) + '&page=' + state.page;
    else url = 'profile/reviews.php?username=' + encodeURIComponent(u) + '&page=' + state.page;

    api(url).then(function (res) {
      state.loading = false;
      if (state.page === 1) grid.innerHTML = '';
      if (!res.success) {
        if (state.tab === 'saved' && res.error && /auth|token/i.test(res.error)) { say('Log in to see saved items.', true); }
        if (state.page === 1) showEmpty(state.tab);
        return;
      }
      var items = res.listings || res.reviews || [];
      if (state.tab === 'reviews') { renderReviews(res.reviews || [], res.rating); }
      else { renderGrid(items); }
      var total = res.pagination ? res.pagination.total : items.length;
      if (!items.length && state.page === 1) showEmpty(state.tab);
      else empty.hidden = true;
      state.done = items.length < (res.pagination ? res.pagination.per_page : 20);
      if (!state.done) state.page++;
    }).catch(function () {
      state.loading = false;
      if (state.page === 1) { grid.innerHTML = ''; showEmpty(state.tab); }
      say('You appear to be offline.', true);
    });
  }

  /* infinite scroll */
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (entries) {
      if (entries[0].isIntersecting && !state.done) loadTab();
    }, { rootMargin: '300px' }).observe(sentinel);
  }

  function renderGrid(items) {
    items.forEach(function (l) {
      var tile = el('button', 'profile-grid-tile');
      tile.type = 'button'; tile.setAttribute('aria-label', l.title || 'Listing');
      if (l.cover_image) { var img = new Image(); img.src = l.cover_image; img.alt = ''; tile.appendChild(img); }
      else tile.appendChild(el('span', 'profile-grid-placeholder', (l.title || 'L').slice(0, 1).toUpperCase()));
      var ov = el('span', 'profile-grid-overlay');
      ov.appendChild(el('strong', null, l.price ? 'K' + parseFloat(l.price).toFixed(2) : 'Negotiable'));
      if (typeof l.saves === 'number') ov.appendChild(el('small', null, l.saves + ' saves'));
      tile.appendChild(ov);
      tile.addEventListener('click', function () { openQuickView(l); });
      grid.appendChild(tile);
    });
  }

  function renderReviews(reviews, rating) {
    if (rating && rating.count) {
      var head = el('div', 'cc-review');
      head.appendChild(el('strong', null, '★ ' + (rating.average != null ? rating.average : '—') + ' · ' + rating.count + ' review' + (rating.count === 1 ? '' : 's')));
      reviewsWrap.appendChild(head);
    }
    reviews.forEach(function (r) {
      var d = el('div', 'cc-review');
      var h = el('div', 'cc-review-head');
      h.appendChild(el('span', 'cc-review-stars', '★★★★★'.slice(0, r.rating) + '☆☆☆☆☆'.slice(0, 5 - r.rating)));
      h.appendChild(el('strong', null, r.reviewer_display_name || r.reviewer_username));
      d.appendChild(h);
      if (r.comment) d.appendChild(el('p', null, r.comment));
      d.appendChild(el('small', null, (r.listing_title || '') + (r.created_at ? ' · ' + r.created_at : '')));
      reviewsWrap.appendChild(d);
    });
  }

  /* ---------------- Quick view ---------------- */
  var qv = document.getElementById('quickViewModal');
  var qvListing = null;
  function openQuickView(l) {
    qvListing = l;
    var media = document.getElementById('qvMedia');
    media.innerHTML = '';
    if (l.cover_image) { var img = new Image(); img.src = l.cover_image; img.alt = l.title || ''; media.appendChild(img); }
    else media.appendChild(el('span', null, '📷'));
    document.getElementById('qvTitle').textContent = l.title || 'Listing';
    document.getElementById('qvPrice').textContent = l.price ? 'K' + parseFloat(l.price).toFixed(2) : 'Negotiable';
    document.getElementById('qvDesc').textContent = l.description || '';
    document.getElementById('qvMeta').textContent = (l.category || '') + (l.campus ? ' · ' + l.campus : '');
    document.getElementById('qvOpen').href = 'listing-detail.html?id=' + encodeURIComponent(l.id);
    qv.hidden = false;
    trapFocus(qv);
  }
  document.getElementById('closeQuickView').addEventListener('click', function () { qv.hidden = true; });
  qv.addEventListener('click', function (e) { if (e.target === qv) qv.hidden = true; });
  document.getElementById('qvSave').addEventListener('click', function () {
    if (!token || !qvListing) { window.location.href = 'login.html'; return; }
    api('listings/save.php', { method: 'POST', body: JSON.stringify({ listing_id: qvListing.id }) })
      .then(function (r) { say(r.success ? 'Saved.' : (r.error || 'Could not save.'), !r.success); })
      .catch(function () { say('You appear to be offline.', true); });
  });
  // swipe-down to close on mobile
  var touchY = null;
  qv.addEventListener('touchstart', function (e) { touchY = e.touches[0].clientY; }, { passive: true });
  qv.addEventListener('touchend', function (e) {
    if (touchY !== null && e.changedTouches[0].clientY - touchY > 90) qv.hidden = true;
    touchY = null;
  }, { passive: true });

  /* ---------------- Followers / Following modal ---------------- */
  var followModal = document.getElementById('followModal');
  var followList = document.getElementById('followList');
  var followMode = 'followers';
  function openFollow(mode) {
    followMode = mode;
    document.getElementById('followModalTitle').textContent = mode === 'followers' ? 'Followers' : 'Following';
    document.getElementById('followSearch').value = '';
    followModal.hidden = false;
    loadFollow('');
    trapFocus(followModal);
  }
  document.getElementById('followersStat').addEventListener('click', function () { openFollow('followers'); });
  document.getElementById('followingStat').addEventListener('click', function () { openFollow('following'); });
  document.getElementById('closeFollowModal').addEventListener('click', function () { followModal.hidden = true; });
  followModal.addEventListener('click', function (e) { if (e.target === followModal) followModal.hidden = true; });
  var followTimer = null;
  document.getElementById('followSearch').addEventListener('input', function (e) {
    clearTimeout(followTimer);
    var q = e.target.value.trim();
    followTimer = setTimeout(function () { loadFollow(q); }, 280);
  });
  function loadFollow(q) {
    var u = state.profile ? state.profile.username : profileUsername;
    followList.innerHTML = '';
    followList.appendChild(el('p', 'cc-panel-label', 'Loading…'));
    api('follow/' + followMode + '.php?username=' + encodeURIComponent(u) + '&q=' + encodeURIComponent(q))
      .then(function (res) {
        followList.innerHTML = '';
        if (!res.success || !(res.users || []).length) { followList.appendChild(el('p', 'cc-panel-label', 'No one here yet.')); return; }
        res.users.forEach(function (usr) { followList.appendChild(followRow(usr)); });
      }).catch(function () { followList.innerHTML = ''; followList.appendChild(el('p', 'cc-panel-label', 'You appear to be offline.')); });
  }
  function followRow(usr) {
    var row = el('div', 'cc-follow-row');
    var av = el('span', 'cc-side-avatar', (usr.username || 'U').slice(0, 2).toUpperCase());
    if (usr.avatar_url) { av.textContent = ''; av.style.backgroundImage = 'url(' + usr.avatar_url + ')'; av.style.backgroundSize = 'cover'; }
    var copy = el('span', 'cc-result-copy');
    copy.appendChild(el('strong', null, usr.display_name || usr.real_name || usr.username));
    copy.appendChild(el('small', null, '@' + usr.username));
    row.appendChild(av); row.appendChild(copy);
    var isMe = me.username && usr.username.toLowerCase() === me.username.toLowerCase();
    if (!isMe && token) {
      var btn = el('button', 'btn btn-outline', 'View');
      btn.type = 'button';
      btn.addEventListener('click', function () { window.location.href = 'profile.html?u=' + encodeURIComponent(usr.username); });
      row.appendChild(btn);
    }
    return row;
  }

  /* ---------------- Note chip / modal ---------------- */
  var noteModal = document.getElementById('noteModal');
  var noteInput = document.getElementById('noteInput');
  var noteCount = document.getElementById('noteCount');
  var noteDelete = document.getElementById('noteDelete');
  document.getElementById('noteChip').addEventListener('click', function () {
    if (!isOwn) return;
    noteInput.value = (state.profile && state.profile.note) ? state.profile.note.body : '';
    noteCount.textContent = noteInput.value.length + '/60';
    noteDelete.hidden = !(state.profile && state.profile.note);
    noteModal.hidden = false;
    trapFocus(noteModal);
  });
  noteInput.addEventListener('input', function () { noteCount.textContent = noteInput.value.length + '/60'; });
  document.getElementById('closeNoteModal').addEventListener('click', function () { noteModal.hidden = true; });
  noteModal.addEventListener('click', function (e) { if (e.target === noteModal) noteModal.hidden = true; });
  document.getElementById('noteSave').addEventListener('click', function () {
    var body = noteInput.value.trim();
    if (!body) { say('Write something first.', true); return; }
    api('profile/note.php', { method: 'POST', body: JSON.stringify({ action: 'save', body: body }) })
      .then(function (r) {
        if (r.success) { state.profile.note = r.note; renderHeader(state.profile); noteModal.hidden = true; say('Note shared for 24h.'); }
        else say(r.error || 'Could not save note.', true);
      }).catch(function () { say('You appear to be offline.', true); });
  });
  noteDelete.addEventListener('click', function () {
    api('profile/note.php', { method: 'POST', body: JSON.stringify({ action: 'delete' }) })
      .then(function () { state.profile.note = null; renderHeader(state.profile); noteModal.hidden = true; say('Note removed.'); })
      .catch(function () { say('You appear to be offline.', true); });
  });

  /* ---------------- More options ---------------- */
  var moreModal = document.getElementById('moreModal');
  document.getElementById('profileMoreBtn').addEventListener('click', function () { moreModal.hidden = false; trapFocus(moreModal); });
  moreModal.addEventListener('click', function (e) { if (e.target === moreModal) moreModal.hidden = true; });
  document.getElementById('moreCopyLink').addEventListener('click', function () {
    var url = location.origin + location.pathname + '?u=' + encodeURIComponent(state.profile ? state.profile.username : profileUsername);
    (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).then(function () { say('Link copied.'); moreModal.hidden = true; }).catch(function () { say(url); });
  });
  document.getElementById('moreReport').addEventListener('click', function () { say('Thanks — our team will review this profile.'); moreModal.hidden = true; });

  /* ---------------- Edit profile + avatar crop ---------------- */
  var editModal = document.getElementById('editProfileModal');
  function openEdit() {
    var p = state.profile || {};
    document.getElementById('editUsername').value = p.username || '';
    document.getElementById('editRealName').value = p.display_name || p.real_name || '';
    document.getElementById('editBio').value = p.bio || '';
    document.getElementById('editCampus').value = p.campus || 'Main Campus';
    document.getElementById('editLink').value = p.link || '';
    var bioCounter = document.getElementById('editBioCount');
    if (bioCounter) bioCounter.textContent = (p.bio || '').length + ' / 150';
    ensureEditExtras();
    editModal.hidden = false;
    trapFocus(editModal);
  }
  var extrasAdded = false;
  function ensureEditExtras() {
    if (extrasAdded) return; extrasAdded = true;
    // username availability hint (the link field and bio counter live in profile.html)
    var userInput = document.getElementById('editUsername');
    var hint = el('small', 'field-hint'); hint.id = 'usernameHint';
    userInput.parentNode.appendChild(hint);
    var deb = null;
    userInput.addEventListener('input', function () {
      clearTimeout(deb);
      var v = userInput.value.trim();
      if (v.length < 3) { hint.textContent = ''; return; }
      deb = setTimeout(function () {
        api('profile/username-check.php?username=' + encodeURIComponent(v)).then(function (r) {
          hint.textContent = r.available ? '✓ Username available' : '✗ Username taken';
          hint.style.color = r.available ? 'var(--success)' : 'var(--error)';
        }).catch(function () {});
      }, 350);
    });
    // live bio counter (element is in profile.html)
    var bio = document.getElementById('editBio');
    var counter = document.getElementById('editBioCount');
    if (bio && counter) {
      bio.addEventListener('input', function () { counter.textContent = bio.value.length + ' / 150'; });
    }
    // avatar preview + crop
    var fileInput = document.getElementById('editAvatarFile');
    var prevWrap = el('div', 'cc-avatar-canvas-wrap');
    var canvas = document.createElement('canvas'); canvas.width = 180; canvas.height = 180;
    prevWrap.appendChild(canvas);
    fileInput.parentNode.appendChild(prevWrap);
    prevWrap.hidden = true;
    var img = null, zoom = 1, offX = 0, offY = 0, dragging = false, sx = 0, sy = 0;
    fileInput.addEventListener('change', function () {
      var f = fileInput.files && fileInput.files[0];
      if (!f) { prevWrap.hidden = true; return; }
      var reader = new FileReader();
      reader.onload = function () {
        img = new Image();
        img.onload = function () { prevWrap.hidden = false; draw(); };
        img.src = String(reader.result);
      };
      reader.readAsDataURL(f);
    });
    function draw() {
      var ctx = canvas.getContext('2d');
      ctx.clearRect(0, 0, 180, 180);
      ctx.save(); ctx.beginPath(); ctx.arc(90, 90, 90, 0, Math.PI * 2); ctx.clip();
      var base = 180 / Math.min(img.width, img.height);
      var sc = base * zoom;
      ctx.drawImage(img, offX + (180 - img.width * sc) / 2, offY + (180 - img.height * sc) / 2, img.width * sc, img.height * sc);
      ctx.restore();
    }
    canvas.addEventListener('pointerdown', function (e) { dragging = true; sx = e.clientX - offX; sy = e.clientY - offY; canvas.setPointerCapture(e.pointerId); });
    canvas.addEventListener('pointermove', function (e) { if (!dragging) return; offX = e.clientX - sx; offY = e.clientY - sy; draw(); });
    canvas.addEventListener('pointerup', function () { dragging = false; });
    canvas.addEventListener('wheel', function (e) { e.preventDefault(); zoom = Math.max(1, Math.min(3, zoom - e.deltaY * 0.001)); draw(); }, { passive: false });
    window.__ccAvatarCrop = function () { return img ? { img: img, zoom: zoom, offX: offX, offY: offY } : null; };
  }
  document.getElementById('closeEditProfileModal').addEventListener('click', function () { editModal.hidden = true; });
  editModal.addEventListener('click', function (e) { if (e.target === editModal) editModal.hidden = true; });

  document.getElementById('editProfileForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var username = document.getElementById('editUsername').value.trim();
    var displayName = document.getElementById('editRealName').value.trim();
    var bio = document.getElementById('editBio').value.trim();
    var campus = document.getElementById('editCampus').value;
    var link = (document.getElementById('editLink') || {}).value || '';
    if (username.length < 3) { say('Username must be at least 3 characters.', true); return; }

    function doUpdate(avatarUploaded) {
      api('profile/update.php', { method: 'POST', body: JSON.stringify({ username: username, display_name: displayName, bio: bio, campus: campus, link: link }) })
        .then(function (r) {
          if (!r.success) { say(r.error || 'Could not save.', true); return; }
          me.username = r.profile.username; sessionStorage.setItem('cc-user', JSON.stringify(me));
          say('Profile updated.');
          editModal.hidden = true;
          loadProfile();
        }).catch(function () { say('You appear to be offline.', true); });
    }
    var crop = window.__ccAvatarCrop && window.__ccAvatarCrop();
    if (crop) {
      // render cropped 512px avatar and upload
      var out = document.createElement('canvas'); out.width = 512; out.height = 512;
      var ctx = out.getContext('2d');
      var base = 180 / Math.min(crop.img.width, crop.img.height);
      var sc = base * crop.zoom;
      ctx.drawImage(crop.img, crop.offX + (180 - crop.img.width * sc) / 2, crop.offY + (180 - crop.img.height * sc) / 2, crop.img.width * sc, crop.img.height * sc, 0, 0, 512, 512);
      out.toBlob(function (blob) {
        var fd = new FormData();
        fd.append('avatar', blob, 'avatar.jpg');
        api('profile/avatar.php', { method: 'POST', body: fd }).then(function (r) {
          if (!r.success) say(r.error || 'Avatar upload failed.', true);
          doUpdate(true);
        }).catch(function () { doUpdate(false); });
      }, 'image/jpeg', 0.9);
    } else {
      doUpdate(false);
    }
  });

  // avatar quick-edit button routes into edit modal
  document.getElementById('avatarEditBtn').addEventListener('click', function () { openEdit(); var f = document.getElementById('editAvatarFile'); if (f) f.click(); });

  /* ---------------- Focus trap (a11y) ---------------- */
  function trapFocus(modalEl) {
    var sel = 'a[href],button:not([disabled]),input,select,textarea,[tabindex]:not([tabindex="-1"])';
    var nodes = modalEl.querySelectorAll(sel);
    if (!nodes.length) return;
    var first = nodes[0], last = nodes[nodes.length - 1];
    setTimeout(function () { first.focus(); }, 30);
    modalEl.onkeydown = function (e) {
      if (e.key === 'Escape') { modalEl.hidden = true; }
      if (e.key !== 'Tab') return;
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    };
  }

  /* ---------------- Start ---------------- */
  if (profileUsername) loadProfile();
  else if (token) {
    api('profile/get.php?username=' + encodeURIComponent(me.username || '')).then(function (r) {
      if (r.success) { profileUsername = r.profile.username; loadProfile(); }
    });
  }
});
