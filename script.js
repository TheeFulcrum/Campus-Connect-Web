document.addEventListener('DOMContentLoaded', function () {
  // ---- Theme toggle (light/dark) ----
  const themeToggle = document.getElementById('themeToggle');
  const root = document.documentElement;

  function getStoredTheme() {
    try { return localStorage.getItem('cc-theme'); } catch (e) { return null; }
  }
  function storeTheme(theme) {
    try { localStorage.setItem('cc-theme', theme); } catch (e) { /* private browsing etc. */ }
  }

  themeToggle.addEventListener('click', function () {
    const current = root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    storeTheme(next);
  });

  // Keep in sync if the user changes their OS theme and hasn't manually chosen one
  if (window.matchMedia) {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
      if (!getStoredTheme()) {
        root.setAttribute('data-theme', e.matches ? 'dark' : 'light');
      }
    });
  }

  // ---- Waitlist form ----
  const form = document.getElementById('waitlistForm');
  const emailInput = document.getElementById('waitlistEmail');
  const msg = document.getElementById('formMsg');

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const email = emailInput.value.trim();

    if (!email.toLowerCase().endsWith('.education')) {
      showMessage("Use your .education email — that's how we verify students.", '#c0392b');
      return;
    }

    const submitBtn = form.querySelector('button');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Joining...';

    fetch('submit.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'email=' + encodeURIComponent(email)
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.status === 'success') {
          showMessage("You're on the list. Check " + email + " when your campus goes live.", '#2E8B57');
          form.reset();
        } else {
          showMessage(data.message || 'Something went wrong. Try again.', '#c0392b');
        }
      })
      .catch(function () {
        showMessage('Could not reach the server. Try again in a moment.', '#c0392b');
      })
      .finally(function () {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Join the waitlist';
      });
  });

  function showMessage(text, color) {
    msg.textContent = text;
    msg.style.color = color;
  }
});
