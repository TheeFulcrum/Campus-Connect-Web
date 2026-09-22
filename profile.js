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
  document.getElementById('profilePageName').textContent = username;
  document.getElementById('profilePageCampus').textContent = user.campus || 'Main Campus';
  document.getElementById('profilePageAvatar').textContent = username.slice(0, 2).toUpperCase();
  document.getElementById('profileBio').textContent = user.bio || 'Student seller and campus community member.';

  const settingsToggle = document.getElementById('settingsToggle');
  const settingsList = document.getElementById('profileSettingsList');
  const profileMenuButton = document.getElementById('profileMenuButton');
  const profileMenu = document.getElementById('profileMenu');
  if (profileMenuButton && profileMenu) {
    profileMenuButton.addEventListener('click', function () {
      const isOpen = !profileMenu.hidden;
      profileMenu.hidden = isOpen;
      profileMenuButton.setAttribute('aria-expanded', String(!isOpen));
    });
    profileMenu.addEventListener('click', function (event) {
      const action = event.target.dataset.profileMenuAction;
      profileMenu.hidden = true;
      profileMenuButton.setAttribute('aria-expanded', 'false');
      if (action === 'settings' && settingsToggle) {
        if (settingsList.hidden) settingsToggle.click();
        document.getElementById('profileSettingsTitle').scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
      if (action === 'edit') document.getElementById('profileEditAction').click();
    });
  }
  if (settingsToggle && settingsList) {
    const settingsOpen = localStorage.getItem('cc-settings-open') === '1';
    settingsList.hidden = !settingsOpen;
    settingsToggle.setAttribute('aria-expanded', String(settingsOpen));
    settingsToggle.classList.toggle('is-open', settingsOpen);
    settingsToggle.addEventListener('click', function () {
      const nextOpen = settingsList.hidden;
      settingsList.hidden = !nextOpen;
      settingsToggle.setAttribute('aria-expanded', String(nextOpen));
      settingsToggle.classList.toggle('is-open', nextOpen);
      localStorage.setItem('cc-settings-open', nextOpen ? '1' : '0');
    });
  }

  const notificationsSetting = document.getElementById('settingNotifications');
  const motionSetting = document.getElementById('settingMotion');
  if (notificationsSetting) {
    notificationsSetting.checked = localStorage.getItem('cc-notifications') !== 'off';
    notificationsSetting.addEventListener('change', function () {
      localStorage.setItem('cc-notifications', notificationsSetting.checked ? 'on' : 'off');
    });
  }
  if (motionSetting) {
    motionSetting.checked = localStorage.getItem('cc-motion') !== 'off';
    motionSetting.addEventListener('change', function () {
      localStorage.setItem('cc-motion', motionSetting.checked ? 'on' : 'off');
      document.documentElement.classList.toggle('reduced-motion', !motionSetting.checked);
    });
    document.documentElement.classList.toggle('reduced-motion', !motionSetting.checked);
  }

  document.getElementById('clearLocalData').addEventListener('click', function () {
    if (!window.confirm('Clear saved preferences, messages, and settings from this browser?')) return;
    const currentUser = sessionStorage.getItem('cc-user');
    localStorage.clear();
    sessionStorage.clear();
    if (currentUser) sessionStorage.setItem('cc-user', currentUser);
    window.location.reload();
  });

  document.getElementById('logoutBtn').addEventListener('click', function () {
    fetch('logout.php', { method: 'POST' }).finally(function () {
      sessionStorage.removeItem('cc-user');
      window.location.href = 'index.html';
    });
  });

  document.getElementById('profileEditAction').addEventListener('click', function () {
    const nextUsername = window.prompt('Username', username);
    if (!nextUsername || nextUsername.trim().length < 3) return;
    const nextCampus = window.prompt('Campus', user.campus || 'Main Campus') || user.campus || 'Main Campus';
    const nextBio = window.prompt('Bio', user.bio || 'Student seller and campus community member.') || '';
    const updatedUser = { username: nextUsername.trim(), email: user.email, campus: nextCampus, bio: nextBio.trim() };
    sessionStorage.setItem('cc-user', JSON.stringify(updatedUser));
    window.location.reload();
  });

  document.getElementById('shareProfileAction').addEventListener('click', async function () {
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
