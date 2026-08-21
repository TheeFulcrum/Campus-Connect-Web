document.getElementById('waitlistForm').addEventListener('submit', function (e) {
  e.preventDefault();

  const emailInput = document.getElementById('email');
  const message = document.getElementById('formMessage');
  const email = emailInput.value.trim();

  message.className = 'form-message';
  message.textContent = '';

  if (!email.toLowerCase().endsWith('.edu')) {
    message.textContent = 'Please use your .edu university email.';
    message.classList.add('error');
    return;
  }

  fetch('submit.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'email=' + encodeURIComponent(email)
  })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      if (data.success) {
        message.textContent = "You're on the list! We'll email you when Campus Connect launches at your school.";
        message.classList.add('success');
        emailInput.value = '';
      } else {
        message.textContent = data.error || 'Something went wrong. Please try again.';
        message.classList.add('error');
      }
    })
    .catch(function () {
      message.textContent = 'Could not reach the server. Please try again later.';
      message.classList.add('error');
    });
});
