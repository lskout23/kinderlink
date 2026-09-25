/* Preview-only review links. Actual compact navigation lives in public/js. */
(function () {
  'use strict';
  if (!document.body.classList.contains('compact-ui')) return;
  // Preserve the concept and role in preview navigation, but leave explicit
  // comparison links and real application markup unmodified.
  document.querySelectorAll('a[href^="/"]').forEach(function (link) {
    var url = new URL(link.href);
    if (url.origin !== location.origin) return;
    if (!url.searchParams.has('design')) url.searchParams.set('design', 'compact');
    if (!url.searchParams.has('role')) {
      var role = new URL(location.href).searchParams.get('role');
      if (role) url.searchParams.set('role', role);
    }
    link.href = url.pathname + url.search + url.hash;
  });
})();