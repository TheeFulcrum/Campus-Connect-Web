document.addEventListener('DOMContentLoaded', function () {
  const signup = sessionStorage.getItem('cc-pending-signup');
  const form = document.getElementById('preferencesForm');
  const message = document.getElementById('preferencesMsg');

  if (!signup) {
    window.location.href = 'signup.html';
    return;
  }

  const pendingSignup = JSON.parse(signup);

  function finishPreferences(interests) {
    sessionStorage.setItem('cc-user-preferences', JSON.stringify(interests));
    sessionStorage.removeItem('cc-pending-signup');
    sessionStorage.setItem('cc-welcome-splash', pendingSignup.username);
    window.location.href = 'home.html';
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    const interests = Array.from(form.querySelectorAll('input[name="interest"]:checked')).map(function (input) {
      return input.value;
    });

    if (interests.length === 0) {
      message.textContent = 'Choose at least one interest, or skip for now.';
      message.style.color = 'var(--error)';
      return;
    }

    finishPreferences(interests);
  });

  document.getElementById('skipPreferences').addEventListener('click', function () {
    finishPreferences(['electronics', 'books', 'furniture', 'tutoring', 'repairs', 'beauty', 'creative', 'campus-help']);
  });
});
