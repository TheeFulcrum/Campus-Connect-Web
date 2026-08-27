document.addEventListener('DOMContentLoaded', function () {

  function isValidUniversityEmail(email) {
    return email.toLowerCase().endsWith('.education');
  }

  function showMsg(el, text, color) {
    el.textContent = text;
    el.style.color = color;
  }

  // ---- Login form ----
  const loginForm = document.getElementById('loginForm');
  if (loginForm) {
    const msg = document.getElementById('loginMsg');

    loginForm.addEventListener('submit', function (e) {
      e.preventDefault();
      const email = document.getElementById('loginEmail').value.trim();
      const password = document.getElementById('loginPassword').value;

      if (!isValidUniversityEmail(email)) {
        showMsg(msg, 'Please use your .education university email.', 'var(--error)');
        return;
      }

      const btn = loginForm.querySelector('button');
      btn.disabled = true;
      btn.textContent = 'Logging in...';

      fetch('login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'email=' + encodeURIComponent(email) + '&password=' + encodeURIComponent(password)
      })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            sessionStorage.setItem('cc-user', JSON.stringify({
              username: data.username,
              email: email,
              campus: data.campus
            }));
            window.location.href = 'home.html';
          } else {
            showMsg(msg, data.error || 'Invalid email or password.', 'var(--error)');
          }
        })
        .catch(() => showMsg(msg, 'Could not reach the server. Try again.', 'var(--error)'))
        .finally(() => {
          btn.disabled = false;
          btn.textContent = 'Log In';
        });
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
        showMsg(msg, 'Please use your .education university email.', 'var(--error)');
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
      btn.textContent = 'Creating account...';

      fetch('signup.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'username=' + encodeURIComponent(username) +
              '&email=' + encodeURIComponent(email) +
              '&campus=' + encodeURIComponent(campus) +
              '&password=' + encodeURIComponent(password)
      })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            sessionStorage.setItem('cc-user', JSON.stringify({
              username: username,
              email: email,
              campus: campus
            }));
            window.location.href = 'home.html';
          } else {
            showMsg(msg, data.error || 'Something went wrong.', 'var(--error)');
          }
        })
        .catch(() => showMsg(msg, 'Could not reach the server. Try again.', 'var(--error)'))
        .finally(() => {
          btn.disabled = false;
          btn.textContent = 'Sign Up';
        });
    });
  }
});
