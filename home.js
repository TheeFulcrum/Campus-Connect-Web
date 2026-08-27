document.addEventListener('DOMContentLoaded', function () {
  const raw = sessionStorage.getItem('cc-user');

  if (!raw) {
    // No active session — bounce back to login
    window.location.href = 'login.html';
    return;
  }

  const user = JSON.parse(raw);
  document.getElementById('welcomeHeading').textContent = 'Welcome, ' + user.username + '!';
  document.getElementById('campusLine').textContent = 'Campus: ' + user.campus;

  document.getElementById('logoutBtn').addEventListener('click', function () {
    sessionStorage.removeItem('cc-user');
    window.location.href = 'index.html';
  });
});
