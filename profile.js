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
      user = Object.assign({}, user, result.user);
      sessionStorage.setItem('cc-user', JSON.stringify(user));
      renderHeader();
    })
    .catch(function () {});

  function renderHeader() {
    const username = user.username || 'Student';
    const realName = user.real_name || user.realName || username;
    const bio = user.bio || 'Student seller and campus community member.';
    const campus = user.campus || 'Main Campus';
    const avatar = user.avatar || '';

    document.getElementById('profileUsernameDisplay').textContent = username;
    document.getElementById('profileHandle').textContent = username;
    document.getElementById('profileRealName').textContent = realName;
    document.getElementById('profileBio').textContent = bio;
    document.getElementById('profileCampusBadge').textContent = campus;

    const avatarEl = document.getElementById('profilePageAvatar');
    if (avatar) {
      avatarEl.textContent = '';
      avatarEl.style.backgroundImage = 'url(' + avatar + ')';
      avatarEl.style.backgroundSize = 'cover';
      avatarEl.style.backgroundPosition = 'center';
      avatarEl.style.border = '2px solid var(--border)';
    } else {
      avatarEl.textContent = username.slice(0, 2).toUpperCase();
      avatarEl.style.backgroundImage = '';
    }

    const listings = document.getElementById('profileListings');
    if (listings) document.getElementById('statPosts').textContent = listings.children.length.toString();
  }

  renderHeader();

  const profileMenuButton = document.getElementById('profileMenuButton');
  const profileMenu = document.getElementById('profileMenu');
  if (profileMenuButton && profileMenu) {
    profileMenuButton.addEventListener('click', function () {
      const isOpen = !profileMenu.hidden;
      profileMenu.hidden = isOpen;
      profileMenuButton.setAttribute('aria-expanded', String(!isOpen));
    });
    document.addEventListener('click', function (e) {
      if (!profileMenu.contains(e.target) && e.target !== profileMenuButton) {
        profileMenu.hidden = true;
        profileMenuButton.setAttribute('aria-expanded', 'false');
      }
    });
    profileMenu.addEventListener('click', function (event) {
      const action = event.target.dataset.profileMenuAction;
      profileMenu.hidden = true;
      profileMenuButton.setAttribute('aria-expanded', 'false');
      if (action === 'settings' || action === 'notifications') window.location.href = 'settings.html';
      if (action === 'qr') alert('QR code — coming soon on campus.');
      if (action === 'logout') {
        fetch('logout.php', { method: 'POST' }).finally(function () {
          sessionStorage.removeItem('cc-auth-token');
          sessionStorage.removeItem('cc-user');
          window.location.href = 'index.html';
        });
      }
    });
  }

  document.getElementById('instaSettingsBtn').addEventListener('click', function () {
    window.location.href = 'settings.html';
  });

  // Avatar quick edit
  const avatarFileInput = document.getElementById('avatarFileInput');
  document.getElementById('avatarEditBtn').addEventListener('click', function () { avatarFileInput.click(); });
  avatarFileInput.addEventListener('change', function () {
    const file = avatarFileInput.files && avatarFileInput.files[0];
    if (!file) return;
    if (file.size > 500000) { alert('Profile picture must be under 500KB.'); return; }
    const reader = new FileReader();
    reader.onload = function () {
      const dataUrl = String(reader.result || '');
      user.avatar = dataUrl;
      sessionStorage.setItem('cc-user', JSON.stringify(user));
      try { localStorage.setItem('cc-avatar-' + (user.email || user.username), dataUrl); } catch (e) {}
      // try to persist to backend
      fetch('profile_update.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ avatar: dataUrl }) }).catch(function(){});
      renderHeader();
    };
    reader.readAsDataURL(file);
  });

  document.getElementById('logoutBtn').addEventListener('click', function () {
    fetch('logout.php', { method: 'POST' }).finally(function () {
      sessionStorage.removeItem('cc-auth-token');
      sessionStorage.removeItem('cc-user');
      window.location.href = 'index.html';
    });
  });

  // Edit profile modal
  const modal = document.getElementById('editProfileModal');
  const openBtn = document.getElementById('profileEditAction');
  const closeBtn = document.getElementById('closeEditProfileModal');
  const form = document.getElementById('editProfileForm');

  function openModal() {
    document.getElementById('editUsername').value = user.username || '';
    document.getElementById('editRealName').value = user.real_name || user.realName || '';
    document.getElementById('editBio').value = user.bio || '';
    document.getElementById('editCampus').value = user.campus || 'Main Campus';
    modal.hidden = false;
  }
  function closeModal() { modal.hidden = true; }
  openBtn.addEventListener('click', openModal);
  closeBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const nextUsername = document.getElementById('editUsername').value.trim();
    const nextRealName = document.getElementById('editRealName').value.trim();
    const nextBio = document.getElementById('editBio').value.trim();
    const nextCampus = document.getElementById('editCampus').value;
    const avatarFile = document.getElementById('editAvatarFile').files[0];

    if (nextUsername.length < 3) { alert('Username must be at least 3 characters.'); return; }

    function saveAndRefresh(finalAvatar) {
      const updated = {
        username: nextUsername,
        real_name: nextRealName,
        bio: nextBio,
        campus: nextCampus,
        avatar: typeof finalAvatar === 'string' ? finalAvatar : (user.avatar || ''),
        email: user.email
      };
      user = Object.assign({}, user, updated);
      sessionStorage.setItem('cc-user', JSON.stringify(user));
      try { localStorage.setItem('cc-avatar-' + (user.email || user.username), user.avatar); } catch (e) {}
      fetch('profile_update.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(updated) }).catch(function(){});
      renderHeader();
      closeModal();
    }

    if (avatarFile) {
      if (avatarFile.size > 500000) { alert('Profile picture must be under 500KB.'); return; }
      const r = new FileReader();
      r.onload = function () { saveAndRefresh(String(r.result || '')); };
      r.readAsDataURL(avatarFile);
    } else {
      saveAndRefresh(user.avatar || '');
    }
  });

  document.querySelector('.insta-new').addEventListener('click', function () { window.location.href = 'post.html'; });

  document.getElementById('shareProfileAction').addEventListener('click', async function () {
    const username = user.username || 'Student';
    const shareText = username + ' on Campus Connect';
    if (navigator.share) await navigator.share({ title: shareText, text: shareText, url: window.location.href });
    else if (navigator.clipboard) await navigator.clipboard.writeText(window.location.href);
  });

  const listings = document.getElementById('profileListings');
  const empty = document.getElementById('profileTabEmpty');
  const emptyTitle = document.getElementById('profileEmptyTitle');
  const emptyText = document.getElementById('profileEmptyText');
  document.querySelectorAll('[data-profile-tab]').forEach(function (tab) {
    tab.addEventListener('click', function () {
      document.querySelectorAll('[data-profile-tab]').forEach(function (item) { item.classList.toggle('is-active', item === tab); });
      const isListings = tab.dataset.profileTab === 'listings';
      listings.hidden = !isListings;
      empty.hidden = isListings;
      if (!isListings) {
        emptyTitle.textContent = tab.dataset.profileTab === 'saved' ? 'No saved listings yet' : 'No reviews yet';
        emptyText.textContent = tab.dataset.profileTab === 'saved' ? 'Save useful campus listings and they will appear here.' : 'Reviews will appear after completed campus deals.';
      }
    });
  });
});
