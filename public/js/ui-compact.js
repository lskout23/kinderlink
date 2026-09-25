/* Compact desktop navigation. Load after ui.js; no data or URL changes. */
(function () {
  'use strict';
  if (!document.body.classList.contains('compact-ui')) return;
  var nav = document.getElementById('app-nav');
  if (!nav) return;
  var desktop = window.matchMedia('(min-width: 1025px)');
  var groups = Array.from(nav.querySelectorAll(':scope > ul > li')).filter(function (li) {
    return li.querySelector(':scope > ul');
  });
  function closeGroups(except) {
    groups.forEach(function (li) {
      if (li === except) return;
      li.classList.remove('open');
      li.querySelector(':scope > a').setAttribute('aria-expanded', 'false');
    });
  }
  if (desktop.matches) closeGroups();
  function closeOtherGroups(event) {
    if (!desktop.matches) return;
    var trigger = event.target.closest('a[aria-expanded]');
    if (trigger) closeGroups(trigger.parentElement);
  }
  nav.addEventListener('click', closeOtherGroups);
  nav.addEventListener('keydown', function (event) {
    if (event.key === ' ') closeOtherGroups(event);
  });
  document.addEventListener('click', function (event) {
    if (desktop.matches && !nav.contains(event.target)) closeGroups();
  });
  document.addEventListener('keydown', function (event) {
    if (!desktop.matches || event.key !== 'Escape') return;
    var open = groups.find(function (li) { return li.classList.contains('open'); });
    closeGroups();
    if (open && open.contains(document.activeElement)) open.querySelector(':scope > a').focus();
  });
  nav.addEventListener('focusout', function (event) {
    if (desktop.matches && event.relatedTarget && !nav.contains(event.relatedTarget)) closeGroups();
  });
  desktop.addEventListener('change', function () { closeGroups(); });
})();