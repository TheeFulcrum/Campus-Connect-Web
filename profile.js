document.addEventListener('DOMContentLoaded', function () {
  const raw = sessionStorage.getItem('cc-user');

  if (!raw) {
    window.location.href = 'login.html';
    return;
  }

  const user = JSON.parse(raw);
  const username = user.username || 'Student';
  document.getElementById('profilePageName').textContent = username;
  document.getElementById('profilePageCampus').textContent = user.campus || 'Main Campus';
  document.getElementById('profilePageAvatar').textContent = username.slice(0, 2).toUpperCase();
  document.getElementById('profileBio').textContent = user.bio || 'Student seller and campus community member.';

  document.getElementById('logoutBtn').addEventListener('click', function () {
    sessionStorage.removeItem('cc-user');
    window.location.href = 'index.html';
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
