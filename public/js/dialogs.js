/* Promise-based confirmations. Never override window.confirm or execute actions. */
(function () {
  'use strict';
  var active = false;
  window.appConfirm = function (message, options) {
    // Fail closed on concurrent requests, rather than queueing destructive actions.
    if (active) return Promise.resolve(false);
    options = options || {};
    active = true;
    return new Promise(function (resolve) {
      var previousFocus = document.activeElement;
      var dialog = document.createElement('dialog');
      var settled = false;
      dialog.className = 'app-confirm-dialog' + (options.danger ? ' is-danger' : '');
      dialog.setAttribute('aria-labelledby', 'app-confirm-title');
      dialog.setAttribute('aria-describedby', 'app-confirm-message app-confirm-note');
      // Static markup only; caller-provided content is assigned with textContent.
      dialog.innerHTML = '<div class="app-confirm-heading"><span class="app-confirm-symbol" aria-hidden="true">'
        + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v6m0 4h.01"/></svg></span>'
        + '<button type="button" class="app-confirm-close" aria-label="Ακύρωση"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="m6 6 12 12M18 6 6 18"/></svg></button></div>'
        + '<h2 id="app-confirm-title"></h2><p id="app-confirm-message"></p><p id="app-confirm-note"></p>'
        + '<div class="app-confirm-actions"><button type="button" class="btn btn-secondary" data-dialog-cancel autofocus>Ακύρωση</button>'
        + '<button type="button" class="btn" data-dialog-approve></button></div>';
      dialog.querySelector('#app-confirm-title').textContent = options.title || 'Επιβεβαίωση ενέργειας';
      dialog.querySelector('#app-confirm-message').textContent = String(message);
      dialog.querySelector('#app-confirm-note').textContent = options.danger
        ? 'Η διαγραφή δεν αναιρείται. Ελέγξτε προσεκτικά πριν συνεχίσετε.'
        : 'Η ενέργεια θα εκτελεστεί μόνο αν την επιβεβαιώσετε.';
      var cancel = dialog.querySelector('[data-dialog-cancel]');
      var approve = dialog.querySelector('[data-dialog-approve]');
      cancel.textContent = options.cancelLabel || 'Ακύρωση';
      approve.textContent = options.confirmLabel || 'Συνέχεια';
      approve.classList.add(options.danger ? 'btn-danger' : 'btn-accent');
      function finish(confirmed) {
        if (settled) return;
        settled = true;
        if (dialog.open) dialog.close();
        dialog.remove();
        active = false;
        if (previousFocus && previousFocus.isConnected && !previousFocus.disabled) previousFocus.focus();
        resolve(confirmed);
      }
      cancel.addEventListener('click', function () { finish(false); });
      dialog.querySelector('.app-confirm-close').addEventListener('click', function () { finish(false); });
      approve.addEventListener('click', function () { finish(true); });
      dialog.addEventListener('cancel', function (event) { event.preventDefault(); finish(false); });
      dialog.addEventListener('close', function () { finish(false); });
      document.body.appendChild(dialog);
      try {
        dialog.showModal();
        cancel.focus();
      } catch (error) {
        // Unsupported/invalid modal state must never approve the action.
        finish(false);
      }
    });
  };
})();