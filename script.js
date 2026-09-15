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

  const verificationBadge = document.getElementById('verificationBadge');
  if (verificationBadge) {
    let lastScrollY = window.scrollY;
    window.addEventListener('scroll', function () {
      const currentScrollY = window.scrollY;
      const scrollingDown = currentScrollY > lastScrollY && currentScrollY > 80;
      verificationBadge.classList.toggle('is-hidden', scrollingDown);
      if (currentScrollY <= 24) verificationBadge.classList.remove('is-hidden');
      lastScrollY = currentScrollY;
    }, { passive: true });
  }

  // ---- Waitlist form (only present on index.html) ----
  const form = document.getElementById('waitlistForm');
  if (!form) return;

  const emailInput = document.getElementById('waitlistEmail');
  const msg = document.getElementById('formMsg');

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const email = emailInput.value.trim();

    if (!/^\d{10}@edenuniversity\.education$/i.test(email)) {
      showMessage('Use your 10-digit ID@edenuniversity.education email.', 'var(--error)');
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
        if (data.success) {
          showMessage("You're on the list. Check " + email + " when your campus goes live.", 'var(--success)');
          form.reset();
        } else {
          showMessage(data.error || 'Something went wrong. Try again.', 'var(--error)');
        }
      })
      .catch(function () {
        showMessage('Could not reach the server. Try again in a moment.', 'var(--error)');
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
