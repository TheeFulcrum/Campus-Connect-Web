document.addEventListener('DOMContentLoaded', function () {
  // Splash screen is now handled in inline script in <head>
  
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

  const siteHeader = document.querySelector('.site-header');
  if (siteHeader) {
    let lastHeaderScrollY = window.scrollY;
    let headerScrollFrame = null;
    window.addEventListener('scroll', function () {
      if (headerScrollFrame) return;
      headerScrollFrame = window.requestAnimationFrame(function () {
        const currentScrollY = window.scrollY;
        const scrollingDown = currentScrollY > lastHeaderScrollY + 4 && currentScrollY > 72;
        const scrollingUp = currentScrollY < lastHeaderScrollY - 4;
        if (scrollingDown) siteHeader.classList.add('is-hidden');
        if (scrollingUp || currentScrollY <= 24) siteHeader.classList.remove('is-hidden');
        lastHeaderScrollY = currentScrollY;
        headerScrollFrame = null;
      });
    }, { passive: true });
  }

  const verificationBadge = document.getElementById('verificationBadge');
  if (verificationBadge) {
    let lastScrollY = window.scrollY;
    let scrollFrame = null;
    window.addEventListener('scroll', function () {
      if (scrollFrame) return;
      scrollFrame = window.requestAnimationFrame(function () {
        const currentScrollY = window.scrollY;
        const scrollingDown = currentScrollY > lastScrollY + 2 && currentScrollY > 40;
        const scrollingUp = currentScrollY < lastScrollY - 2;
        if (scrollingDown) verificationBadge.classList.add('is-hidden');
        if (scrollingUp || currentScrollY <= 24) verificationBadge.classList.remove('is-hidden');
        lastScrollY = currentScrollY;
        scrollFrame = null;
      });
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

// Global logout handler
document.addEventListener('DOMContentLoaded', function() {
  const logoutBtn = document.getElementById('logoutBtn');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', function() {
      fetch('logout.php', {method: 'POST'}).finally(function() {
        sessionStorage.removeItem('cc-user');
        sessionStorage.removeItem('cc-auth-token');
        location.href = 'index.html';
      });
    });
  }
});
