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

    loginForm.addEventListener('submit', async function (e) {
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

      try {
        const response = await fetch('login.php', {
          method: 'POST',
          body: new URLSearchParams({ identifier: identifier, password: password })
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.error || 'Unable to log in.');

        sessionStorage.setItem('cc-user', JSON.stringify({
          username: result.username,
          email: result.email,
          campus: result.campus
        }));
        if (sessionStorage.getItem('cc-pending-signup')) {
          window.location.href = 'preferences.html';
        } else {
          sessionStorage.setItem('cc-welcome-splash', result.username);
          window.location.href = 'home.html';
        }
      } catch (error) {
        showMsg(msg, error.message || 'Unable to log in. Try again.', 'var(--error)');
        btn.disabled = false;
        btn.textContent = 'Log In';
      }
    });
  }

  // ---- Sign up form ----
  const signupForm = document.getElementById('signupForm');
  if (signupForm) {
    const msg = document.getElementById('signupMsg');

    signupForm.addEventListener('submit', async function (e) {
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
      if (password.length < 8) {
        showMsg(msg, 'Password must be at least 8 characters.', 'var(--error)');
        return;
      }
      if (password !== confirm) {
        showMsg(msg, 'Passwords do not match.', 'var(--error)');
        return;
      }

      const btn = signupForm.querySelector('button');
      btn.disabled = true;
      btn.textContent = 'Creating account...';

      try {
        const response = await fetch('signup.php', {
          method: 'POST',
          body: new URLSearchParams({ username: username, email: email, campus: campus, password: password })
        });
        const result = await response.json();
        const accountExists = result.error === 'An account with this email already exists.';
        if ((!response.ok || !result.success) && !accountExists) throw new Error(result.error || 'Unable to create account.');

        sessionStorage.setItem('cc-pending-signup', JSON.stringify({
          username: username,
          email: email,
          campus: campus
        }));
        const otpResponse = await fetch('auth_api.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'request_otp', email: email })
        });
        const otpResult = await otpResponse.json();
        if (!otpResponse.ok || !otpResult.success) throw new Error(otpResult.error || 'Unable to send a verification code.');

        window.location.href = 'otp.html?email=' + encodeURIComponent(email) + '&next=login&sent=1';
      } catch (error) {
        showMsg(msg, error.message || 'Unable to create account. Try again.', 'var(--error)');
        btn.disabled = false;
        btn.textContent = 'Sign Up';
      }
    });
  }
});
