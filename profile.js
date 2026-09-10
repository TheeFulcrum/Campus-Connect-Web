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

  document.getElementById('logoutBtn').addEventListener('click', function () {
    sessionStorage.removeItem('cc-user');
    window.location.href = 'index.html';
  });

  document.getElementById('profileEditAction').addEventListener('click', function () {
    window.alert('Profile editing will be available in the next build.');
  });
});
