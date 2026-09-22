document.addEventListener('DOMContentLoaded', function () {
  const requestForm = document.getElementById('otpRequestForm');
  const verifyForm = document.getElementById('otpVerifyForm');
  const emailInput = document.getElementById('otpEmail');
  const codeInput = document.getElementById('otpCode');
  const requestMsg = document.getElementById('otpRequestMsg');
  const verifyMsg = document.getElementById('otpVerifyMsg');
  const params = new URLSearchParams(window.location.search);
  const next = params.get('next') === 'login' ? 'login' : 'home';
  let email = params.get('email') || '';

  function showMessage(element, text, color) {
    element.textContent = text;
    element.style.color = color;
  }

  function callApi(payload) {
    return fetch('auth_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function (response) {
      return response.json().then(function (body) {
        if (!response.ok || !body.success) throw new Error(body.error || 'Authentication request failed.');
        return body;
      });
    });
  }

  requestForm.addEventListener('submit', function (event) {
    event.preventDefault();
    email = emailInput.value.trim().toLowerCase();
    if (!/^\d{10}@edenuniversity\.education$/i.test(email)) {
      showMessage(requestMsg, 'Use your 10-digit ID@edenuniversity.education email.', 'var(--error)');
      return;
    }

    const button = requestForm.querySelector('button');
    button.disabled = true;
    callApi({ action: 'request_otp', email: email })
      .then(function () {
        requestForm.hidden = true;
        verifyForm.hidden = false;
        codeInput.focus();
      })
      .catch(function (error) { showMessage(requestMsg, error.message, 'var(--error)'); })
      .finally(function () { button.disabled = false; });
  });

  if (params.get('sent') === '1' && /^\d{10}@edenuniversity\.education$/i.test(email)) {
    emailInput.value = email;
    requestForm.hidden = true;
    verifyForm.hidden = false;
    codeInput.focus();
  }

  verifyForm.addEventListener('submit', function (event) {
    event.preventDefault();
    const code = codeInput.value.trim();
    if (!/^\d{6}$/.test(code)) {
      showMessage(verifyMsg, 'Enter the six-digit code from your email.', 'var(--error)');
      return;
    }

    const button = verifyForm.querySelector('button[type="submit"]');
    button.disabled = true;
    callApi({ action: 'verify_otp', email: email, code: code })
      .then(function (result) {
        if (next === 'login') {
          return fetch('logout.php', { method: 'POST' }).finally(function () {
            sessionStorage.removeItem('cc-user');
            sessionStorage.setItem('cc-pending-login', result.user.username);
            window.location.href = 'login.html';
          });
        }

        sessionStorage.setItem('cc-user', JSON.stringify(result.user));
        sessionStorage.setItem('cc-welcome-splash', result.user.username);
        window.location.href = 'home.html';
      })
      .catch(function (error) { showMessage(verifyMsg, error.message, 'var(--error)'); })
      .finally(function () { button.disabled = false; });
  });

  document.getElementById('otpStartOver').addEventListener('click', function () {
    verifyForm.hidden = true;
    requestForm.hidden = false;
    codeInput.value = '';
    emailInput.focus();
  });
});