(function () {
  const input = document.getElementById('password');
  const toggle = document.getElementById('password-toggle');

  if (!input || !toggle) {
    return;
  }

  const openIcon = toggle.querySelector('.password-toggle__icon--show');
  const closedIcon = toggle.querySelector('.password-toggle__icon--hide');

  function setVisible(visible) {
    input.type = visible ? 'text' : 'password';
    toggle.setAttribute('aria-pressed', visible ? 'true' : 'false');
    toggle.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');

    if (openIcon) {
      openIcon.hidden = !visible;
    }
    if (closedIcon) {
      closedIcon.hidden = visible;
    }
  }

  toggle.addEventListener('click', function () {
    setVisible(input.type !== 'text');
  });
})();
