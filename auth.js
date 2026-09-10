document.addEventListener('DOMContentLoaded', function () {

  function isValidUniversityEmail(email) {
    return /^\d{10}@edenuniversity\.education$/i.test(email);
  }

  function showMsg(el, text, color) {
    el.textContent = text;
    el.style.color = color;
  }

  // ---- Login form ----
  const loginForm = document.getElementById('loginForm');
  if (loginForm) {
    const msg = document.getElementById('loginMsg');
    const pendingLogin = sessionStorage.getItem('cc-pending-login');

    if (pendingLogin) {
      document.getElementById('loginIdentifier').value = pendingLogin;
      sessionStorage.removeItem('cc-pending-login');
      showMsg(msg, 'Account created. Enter your password to log in.', 'var(--success)');
    }

    loginForm.addEventListener('submit', function (e) {
      e.preventDefault();
      const identifier = document.getElementById('loginIdentifier').value.trim();
      const password = document.getElementById('loginPassword').value;

      if (!identifier || !password) {
        showMsg(msg, 'Enter your login details to continue.', 'var(--error)');
        return;
      }

      const btn = loginForm.querySelector('button');
      btn.disabled = true;
      btn.textContent = 'Opening Campus Connect...';

      const isEmail = identifier.includes('@');
      const username = isEmail ? identifier.split('@')[0] : identifier;
      sessionStorage.setItem('cc-user', JSON.stringify({
        username: username,
        email: isEmail ? identifier.toLowerCase() : '',
        campus: 'Main Campus'
      }));
      window.location.href = 'home.html';
    });
  }

  // ---- Sign up form ----
  const signupForm = document.getElementById('signupForm');
  if (signupForm) {
    const msg = document.getElementById('signupMsg');

    signupForm.addEventListener('submit', function (e) {
      e.preventDefault();
      const username = document.getElementById('signupUsername').value.trim();
      const email = document.getElementById('signupEmail').value.trim();
      const campus = document.getElementById('signupCampus').value;
      const password = document.getElementById('signupPassword').value;
      const confirm = document.getElementById('signupConfirm').value;

      if (username.length < 3) {
        showMsg(msg, 'Username must be at least 3 characters.', 'var(--error)');
        return;
      }
      if (!isValidUniversityEmail(email)) {
        showMsg(msg, 'Use your 10-digit ID@edenuniversity.education email.', 'var(--error)');
        return;
      }
      if (password.length < 6) {
        showMsg(msg, 'Password must be at least 6 characters.', 'var(--error)');
        return;
      }
      if (password !== confirm) {
        showMsg(msg, 'Passwords do not match.', 'var(--error)');
        return;
      }

      const btn = signupForm.querySelector('button');
      btn.disabled = true;
      btn.textContent = 'Opening Campus Connect...';
      sessionStorage.setItem('cc-pending-signup', JSON.stringify({
        username: username,
        email: email,
        campus: campus
      }));
      window.location.href = 'preferences.html';
    });
  }
});
