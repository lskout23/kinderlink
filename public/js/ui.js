/* Presentation enhancements only: no requests, storage or business actions. */
(function () {
  'use strict';
  var icons = {
    home: '<path d="m3 10 9-7 9 7v10H3Z"/><path d="M9 20v-7h6v7"/>',
    mail: '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m3 7 9 6 9-6"/>',
    menu: '<path d="M4 6h16M4 12h16M4 18h16"/>',
    close: '<path d="m6 6 12 12M18 6 6 18"/>',
    user: '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
    children: '<circle cx="9" cy="8" r="3"/><path d="M2 20v-2a7 7 0 0 1 14 0v2M16 5a3 3 0 0 1 0 6M18 14a6 6 0 0 1 4 6"/>',
    grid: '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
    edit: '<path d="M12 4H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h13a2 2 0 0 0 2-2v-7M16 3l5 5-10 10-6 1 1-6Z"/>',
    list: '<rect x="4" y="3" width="16" height="18" rx="3"/><path d="M8 8h8M8 12h8M8 16h5"/>',
    settings: '<path d="M4 6h16M4 12h16M4 18h16"/><circle cx="8" cy="6" r="2" fill="white"/><circle cx="16" cy="12" r="2" fill="white"/><circle cx="10" cy="18" r="2" fill="white"/>',
    wallet: '<rect x="3" y="5" width="18" height="15" rx="3"/><path d="M3 8V5a2 2 0 0 1 2-2h13M21 11h-6v5h6"/>',
    chart: '<path d="M4 3v18h17M8 16v-4M13 16V8M18 16V5"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    logout: '<path d="M9 3H4v18h5M9 12h12m-4-4 4 4-4 4"/>',
    send: '<path d="m3 3 18 8-8 3-3 7Zm0 0 10 11"/>'
  };
  var lucideNames = {
    home: 'House', mail: 'Mail', menu: 'Menu', close: 'X', user: 'UserRound',
    children: 'Baby', grid: 'LayoutGrid', edit: 'SquarePen', list: 'ListChecks',
    settings: 'Settings2', wallet: 'Wallet', chart: 'ChartNoAxesCombined',
    clock: 'Clock3', logout: 'LogOut', send: 'Send', save: 'Save', search: 'Search',
    eye: 'Eye', trash: 'Trash2', upload: 'Upload', download: 'Download',
    plus: 'Plus', check: 'Check', copy: 'Copy', refresh: 'RefreshCw',
    camera: 'Images', lock: 'LockKeyhole', warning: 'TriangleAlert',
    users: 'UsersRound', tags: 'Tags', clipboard: 'ClipboardList',
    'mail-plus': 'MailPlus', bars: 'ChartColumn', calendar: 'CalendarDays',
    'minus-circle': 'CircleMinus'
  };
  function icon(name) {
    var node = window.lucide && window.lucide.icons[lucideNames[name]];
    if (node) {
      var svg = window.lucide.createElement(node);
      svg.setAttribute('class', 'ui-icon');
      svg.setAttribute('stroke-width', '1.8');
      svg.setAttribute('aria-hidden', 'true');
      svg.setAttribute('focusable', 'false');
      return svg.outerHTML;
    }
    return '<svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + (icons[name] || icons.grid) + '</svg>';
  }
  document.querySelectorAll('[data-icon]').forEach(function (el) { el.innerHTML = icon(el.dataset.icon); });
  function routeIcon(path) {
    if (/personal-details|setup-users/.test(path)) return 'user';
    if (/children/.test(path)) return 'children';
    if (/groups/.test(path)) return 'grid';
    if (/parameters/.test(path)) return 'settings';
    if (/income-totals/.test(path)) return 'chart';
    if (/financial/.test(path)) return 'wallet';
    if (/create-messages|setup-activities/.test(path)) return 'edit';
    if (/free-email/.test(path)) return 'send';
    if (/message-list/.test(path)) return 'list';
    if (/message|inbox|email-template/.test(path)) return 'mail';
    return 'home';
  }
  function routeTone(path) {
    if (/\/financial\//.test(path)) return 'teal';
    if (/\/messages\/|\/inbox|email-template|\/parent\/dashboard/.test(path)) return 'blue';
    return 'green';
  }
  document.body.dataset.uiTone = routeTone(location.pathname);
  document.querySelectorAll('.dash-icon-link').forEach(function (link) {
    link.dataset.uiTone = routeTone(link.pathname);
    var slot = link.querySelector('.icon');
    if (slot) { slot.innerHTML = icon(link.dataset.cardIcon || routeIcon(link.pathname)); slot.setAttribute('aria-hidden', 'true'); }
  });
  document.querySelectorAll('.dash-section').forEach(function (section) {
    var link = section.querySelector('.dash-icon-link');
    if (link) section.dataset.uiTone = routeTone(link.pathname);
  });
  document.querySelectorAll('.stat-card').forEach(function (card) {
    var slot = card.querySelector('[data-icon]');
    card.dataset.uiTone = card.classList.contains('stat-card-warm') ? 'amber' :
      (slot && /mail|edit/.test(slot.dataset.icon) ? 'blue' : slot && slot.dataset.icon === 'grid' ? 'teal' : 'green');
  });

  // Replace only known leading emoji in action controls, never user content,
  // form values, labels, handlers or the button element itself. New AJAX rows
  // and restored loading labels receive the same treatment.
  var actionIcons = [
    ['💾', 'save', 'Αποθήκευση'], ['🔍', 'search', 'Αναζήτηση'],
    ['📧', 'send', 'Αποστολή'], ['📨', 'mail', 'Μηνύματα'],
    ['👁', 'eye', 'Προεπισκόπηση'], ['✏', 'edit', 'Επεξεργασία'],
    ['🗑', 'trash', 'Διαγραφή'], ['🧨', 'trash', 'Διαγραφή'],
    ['➕', 'plus', 'Προσθήκη'], ['⬆', 'upload', 'Μεταφόρτωση'],
    ['⬇', 'download', 'Λήψη'], ['📷', 'camera', 'Φωτογραφίες'],
    ['📋', 'copy', 'Αντιγραφή'], ['🔄', 'refresh', 'Ανανέωση'],
    ['✅', 'check', 'Επιβεβαίωση'], ['🔒', 'lock', 'Ασφάλεια'],
    ['⏳', 'clock', 'Αναμονή']
  ];
  function decorateActions(root) {
    root.querySelectorAll('button.btn, button.btn-tool, a.btn, button.modal-close').forEach(function (button) {
      var replaced = Array.from(button.childNodes).some(function (textNode) {
        if (textNode.nodeType !== Node.TEXT_NODE) return false;
        var text = textNode.nodeValue.trimStart();
        var entry = button.classList.contains('modal-close') && /^[✕×]/.test(text)
          ? [text[0], 'close', 'Κλείσιμο']
          : actionIcons.find(function (item) { return text.indexOf(item[0]) === 0; });
        if (!entry) return false;
        var remainder = text.slice(entry[0].length).replace(/^\uFE0F/, '');
        if (!remainder.trim() && !button.hasAttribute('aria-label')) {
          button.setAttribute('aria-label', button.title || entry[2]);
        }
        var holder = document.createElement('span');
        holder.innerHTML = icon(entry[1]);
        button.insertBefore(holder.firstChild, textNode);
        if (!button.dataset.uiActionIcon) button.dataset.uiActionIcon = entry[1];
        textNode.nodeValue = remainder;
        return true;
      });
      // Some existing loading handlers save/restore textContent (not HTML).
      // Restore the original icon as well, without changing those handlers.
      if (!replaced && button.dataset.uiActionIcon && button.textContent.trim() && !button.querySelector('.ui-icon')) {
        button.insertAdjacentHTML('afterbegin', icon(button.dataset.uiActionIcon));
      }
    });
  }
  var content = document.getElementById('app-wrapper');
  if (content) {
    decorateActions(content);
    var actionsQueued = false;
    new MutationObserver(function () {
      if (actionsQueued) return;
      actionsQueued = true;
      queueMicrotask(function () { actionsQueued = false; decorateActions(content); });
    }).observe(content, {childList: true, subtree: true});
  }

  var nav = document.getElementById('app-nav');
  if (!nav) return;
  var narrow = window.matchMedia('(max-width: 1024px)');
  var currentPath = location.pathname.replace(/\/$/, '');
  var returnFocus = null;
  var background = document.querySelectorAll('#app-header, #app-wrapper, #app-footer, .mobile-tabs, .skip-link');
  nav.querySelectorAll(':scope > ul > li').forEach(function (li, index) {
    var trigger = li.querySelector(':scope > a');
    var submenu = li.querySelector(':scope > ul');
    if (!trigger) return;
    var name = submenu ? (trigger.textContent.indexOf('Οικονομικά') >= 0 ? 'wallet' : trigger.textContent.indexOf('Μηνύματα') >= 0 ? 'mail' : 'grid') : routeIcon(trigger.pathname);
    li.dataset.uiTone = submenu ? (name === 'wallet' ? 'teal' : name === 'mail' ? 'blue' : 'green') : routeTone(trigger.pathname);
    trigger.insertAdjacentHTML('afterbegin', icon(name));
    if (!submenu) return;
    submenu.id = 'nav-section-' + index;
    trigger.setAttribute('role', 'button');
    trigger.setAttribute('aria-controls', submenu.id);
    trigger.setAttribute('aria-expanded', 'false');
    function toggleSection() {
      var open = li.classList.toggle('open');
      trigger.setAttribute('aria-expanded', String(open));
    }
    trigger.addEventListener('click', function (event) { event.preventDefault(); toggleSection(); });
    trigger.addEventListener('keydown', function (event) {
      if (event.key === ' ') { event.preventDefault(); toggleSection(); }
    });
  });
  document.querySelectorAll('#app-nav > ul a, .mobile-tabs a').forEach(function (link) {
    if (link.getAttribute('href') === '#') return;
    if (link.pathname.replace(/\/$/, '') === currentPath) {
      link.setAttribute('aria-current', 'page');
      var group = link.closest('#app-nav > ul > li');
      if (group) {
        group.classList.add('active', 'open');
        var trigger = group.querySelector(':scope > a[aria-expanded]');
        if (trigger) trigger.setAttribute('aria-expanded', 'true');
      }
    }
    if (link.dataset.activePrefix && currentPath.indexOf((window.APP_BASE || '') + link.dataset.activePrefix) === 0) link.classList.add('active');
  });
  // The dashboard also appears as a shortcut inside Management; don't expand
  // that duplicate branch when the top-level Home link is already current.
  var home = nav.querySelector(':scope > ul > li > a[aria-current="page"]');
  if (home) {
    nav.querySelectorAll('ul ul a[aria-current="page"]').forEach(function (link) {
      link.removeAttribute('aria-current');
      var group = link.closest('#app-nav > ul > li');
      group.classList.remove('active', 'open');
      group.querySelector(':scope > a').setAttribute('aria-expanded', 'false');
    });
  }
  function setMenu(open, restore) {
    open = open && narrow.matches;
    document.body.classList.toggle('nav-open', open);
    document.querySelectorAll('[data-menu-toggle]').forEach(function (el) { el.setAttribute('aria-expanded', String(open)); });
    background.forEach(function (el) { el.inert = open; });
    if (open) {
      nav.querySelector('[data-menu-close]').focus();
    } else if (restore && returnFocus) {
      returnFocus.focus();
    }
  }
  document.querySelectorAll('[data-menu-toggle]').forEach(function (el) {
    el.addEventListener('click', function () { returnFocus = el; setMenu(!document.body.classList.contains('nav-open'), true); });
  });
  document.querySelectorAll('[data-menu-close]').forEach(function (el) { el.addEventListener('click', function () { setMenu(false, true); }); });
  document.addEventListener('keydown', function (event) {
    if (!document.body.classList.contains('nav-open')) return;
    if (event.key === 'Escape') { event.preventDefault(); setMenu(false, true); }
    if (event.key !== 'Tab') return;
    var items = Array.from(nav.querySelectorAll('a[href], button:not(:disabled)')).filter(function (el) { return el.getClientRects().length; });
    var first = items[0], last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });
  narrow.addEventListener('change', function () { setMenu(false, false); });
  document.body.classList.add('ui-ready');

  // Add visual labels without replacing cells/controls or changing their order.
  // Watch AJAX-rendered rows, including the parent's multi-child today view.
  function decorateTables() {
    document.querySelectorAll('#msg-list-table, #par-tables-area table, #children-area .msg-table').forEach(function (table) {
      table.classList.add('mobile-cards');
      table.setAttribute('role', 'table');
      var headers = Array.from(table.querySelectorAll('thead th'));
      headers.forEach(function (th) {
        th.setAttribute('scope', 'col');
        th.setAttribute('role', 'columnheader');
        if (th.querySelector('input[type="checkbox"]')) th.classList.add('card-select-header');
      });
      table.querySelectorAll('thead, tbody').forEach(function (el) { el.setAttribute('role', 'rowgroup'); });
      table.querySelectorAll('tr').forEach(function (tr) { tr.setAttribute('role', 'row'); });
      table.querySelectorAll('tbody tr').forEach(function (row, rowIndex) {
        Array.from(row.cells).forEach(function (cell, colIndex) {
          cell.setAttribute('role', 'cell');
          if (cell.colSpan > 1) return;
          var label = headers[colIndex] ? headers[colIndex].textContent.trim() : '';
          cell.dataset.label = label || 'Επιλογή';
          if (/Δραστηριότητες|Σχόλια/.test(label)) cell.dataset.wide = 'true';
          if (label === 'Παιδί' || (table.id !== 'msg-list-table' && /Ημερ/.test(label))) cell.dataset.featured = 'true';
          cell.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach(function (control) {
            if (!control.hasAttribute('aria-label') && !control.labels.length) {
              control.setAttribute('aria-label', (label || 'Επιλογή') + ' — εγγραφή ' + (rowIndex + 1));
            }
          });
          cell.querySelectorAll('.rating-btn').forEach(function (button) {
            var descriptions = {'1': 'Καθόλου', '2': 'Μέτρια', '3': 'Καλά', '4': 'Πολύ καλά'};
            button.setAttribute('aria-label', label + ': ' + (descriptions[button.textContent.trim()] || button.textContent));
            button.title = descriptions[button.textContent.trim()] || button.textContent;
          });
        });
      });
    });
  }
  decorateTables();
  var queued = false;
  var observer = new MutationObserver(function () {
    if (queued) return;
    queued = true;
    // Label AJAX rows even when the review tab is in the background, where
    // animation frames may be suspended by the browser.
    queueMicrotask(function () { queued = false; decorateTables(); });
  });
  ['msg-list-tbody', 'par-tables-area', 'children-area'].forEach(function (id) {
    var el = document.getElementById(id);
    if (el) observer.observe(el, {childList: true, subtree: true});
  });
})();