const STORAGE_KEYS = {
  apiBase: 'kl_mobile_api_base',
  token: 'kl_mobile_token',
  user: 'kl_mobile_user'
};

const state = {
  apiBase: '',
  token: '',
  user: null,
  currentThreadId: null,
  threads: [],
  currentView: null,
  childrenPage: 1,
  usersPage: 1,
  groupsPage: 1,
  activitiesType: 'both',
  parentsList: null,
  allGroups: null,
  inboxChildren: null
};

const els = {
  apiBase: document.getElementById('api-base'),
  serverChip: document.getElementById('server-chip'),
  saveSettings: document.getElementById('save-settings'),
  settingsPanel: document.getElementById('settings-panel'),
  loginPanel: document.getElementById('login-panel'),
  loginForm: document.getElementById('login-form'),
  username: document.getElementById('username'),
  password: document.getElementById('password'),
  userPanel: document.getElementById('user-panel'),
  welcomeTitle: document.getElementById('welcome-title'),
  roleChip: document.getElementById('role-chip'),
  userLine: document.getElementById('user-line'),
  logoutBtn: document.getElementById('logout-btn'),
  appNav: document.getElementById('app-nav'),

  parentPanel: document.getElementById('parent-panel'),
  parentChild: document.getElementById('parent-child'),
  parentFrom: document.getElementById('parent-from'),
  parentTo: document.getElementById('parent-to'),
  parentRows: document.getElementById('parent-rows'),
  parentEmpty: document.getElementById('parent-empty'),
  parentRefresh: document.getElementById('parent-refresh'),

  staffPanel: document.getElementById('staff-panel'),
  staffRefresh: document.getElementById('staff-refresh'),
  inboxNewBtn: document.getElementById('inbox-new-btn'),
  threadList: document.getElementById('thread-list'),
  threadTitle: document.getElementById('thread-title'),
  threadDeleteBtn: document.getElementById('thread-delete-btn'),
  threadMessages: document.getElementById('thread-messages'),
  replyBody: document.getElementById('reply-body'),
  replyBtn: document.getElementById('reply-btn'),

  dashboardStats: document.getElementById('dashboard-stats'),

  childrenTable: document.getElementById('children-table'),
  childrenPagination: document.getElementById('children-pagination'),
  childrenSearch: document.getElementById('children-search'),
  childrenAddBtn: document.getElementById('children-add-btn'),

  groupsTable: document.getElementById('groups-table'),
  groupsPagination: document.getElementById('groups-pagination'),
  groupsAddBtn: document.getElementById('groups-add-btn'),

  gaGroup: document.getElementById('ga-group'),
  gaChildren: document.getElementById('ga-children'),
  gaSaveBtn: document.getElementById('ga-save-btn'),

  activitiesTabs: document.getElementById('activities-tabs'),
  activitiesTable: document.getElementById('activities-table'),
  activitiesAddBtn: document.getElementById('activities-add-btn'),

  usersTable: document.getElementById('users-table'),
  usersPagination: document.getElementById('users-pagination'),
  usersAddBtn: document.getElementById('users-add-btn'),

  faTable: document.getElementById('fa-table'),
  faAddBtn: document.getElementById('fa-add-btn'),

  fiChild: document.getElementById('fi-child'),
  fiAddBtn: document.getElementById('fi-add-btn'),
  fiPendingTable: document.getElementById('fi-pending-table'),
  fiFullTable: document.getElementById('fi-full-table'),

  fitFrom: document.getElementById('fit-from'),
  fitTo: document.getElementById('fit-to'),
  fitSearchBtn: document.getElementById('fit-search-btn'),
  fitTable: document.getElementById('fit-table'),

  feFrom: document.getElementById('fe-from'),
  feTo: document.getElementById('fe-to'),
  feSearchBtn: document.getElementById('fe-search-btn'),
  feTable: document.getElementById('fe-table'),
  feAddBtn: document.getElementById('fe-add-btn'),

  paramsHealth: document.getElementById('params-health'),
  paramsForm: document.getElementById('params-form'),
  pEmailMode: document.getElementById('p-email-mode'),
  pSchoolName: document.getElementById('p-school-name'),
  pPhotoHidden: document.getElementById('p-photo-hidden'),
  pPhotoRetention: document.getElementById('p-photo-retention'),

  etForm: document.getElementById('et-form'),
  etSubject: document.getElementById('et-subject'),
  etHtml: document.getElementById('et-html'),

  personalProfileForm: document.getElementById('personal-profile-form'),
  ppName: document.getElementById('pp-name'),
  ppUsername: document.getElementById('pp-username'),
  ppEmail: document.getElementById('pp-email'),
  personalPasswordForm: document.getElementById('personal-password-form'),
  pwCurrent: document.getElementById('pw-current'),
  pwNew: document.getElementById('pw-new'),
  pwConfirm: document.getElementById('pw-confirm'),

  mcGroup: document.getElementById('mc-group'),
  mcDate: document.getElementById('mc-date'),
  mcLoadBtn: document.getElementById('mc-load-btn'),
  mcPreviewBtn: document.getElementById('mc-preview-btn'),
  mcClearBtn: document.getElementById('mc-clear-btn'),
  mcActsBoth: document.getElementById('mc-acts-both'),
  mcActsEmail: document.getElementById('mc-acts-email'),
  mcRows: document.getElementById('mc-rows'),
  mcSaveBtn: document.getElementById('mc-save-btn'),
  mcSendBtn: document.getElementById('mc-send-btn'),
  mcPhotoChild: document.getElementById('mc-photo-child'),
  mcPhotoMode: document.getElementById('mc-photo-mode'),
  mcPhotoChildWrap: document.getElementById('mc-photo-child-wrap'),
  mcPhotoFiles: document.getElementById('mc-photo-files'),
  mcPhotoUploadBtn: document.getElementById('mc-photo-upload-btn'),
  mcPhotoAllDates: document.getElementById('mc-photo-alldates'),
  mcPhotoList: document.getElementById('mc-photo-list'),

  mlGroup: document.getElementById('ml-group'),
  mlFrom: document.getElementById('ml-from'),
  mlTo: document.getElementById('ml-to'),
  mlSearchBtn: document.getElementById('ml-search-btn'),
  mlTable: document.getElementById('ml-table'),

  fe2Form: document.getElementById('fe2-form'),
  fe2Group: document.getElementById('fe2-group'),
  fe2SendAllWrap: document.getElementById('fe2-sendall-wrap'),
  fe2SendAll: document.getElementById('fe2-sendall'),
  fe2Subject: document.getElementById('fe2-subject'),
  fe2Body: document.getElementById('fe2-body'),

  modalOverlay: document.getElementById('modal-overlay'),
  modalTitle: document.getElementById('modal-title'),
  modalForm: document.getElementById('modal-form'),
  modalClose: document.getElementById('modal-close'),

  toast: document.getElementById('toast')
};

const NAV_BY_ROLE = {
  admin: [
    { view: 'dashboard', label: 'Αρχική' },
    { view: 'children', label: 'Παιδιά' },
    { view: 'groups', label: 'Τμήματα' },
    { view: 'groups-assign', label: 'Παιδιά/Τμήμα' },
    { view: 'activities', label: 'Δραστηριότητες' },
    { view: 'users', label: 'Χρήστες' },
    { view: 'messages-create', label: 'Νέο Μήνυμα' },
    { view: 'messages-list', label: 'Λίστα Μηνυμάτων' },
    { view: 'free-email', label: 'Ελεύθ. Email' },
    { view: 'inbox', label: 'Εισερχόμενα' },
    { view: 'financial-income', label: 'Έσοδα' },
    { view: 'financial-income-totals', label: 'Σύνολα Εσόδων' },
    { view: 'financial-activities', label: 'Δρ. Εσόδων' },
    { view: 'financial-expenses', label: 'Έξοδα' },
    { view: 'parameters', label: 'Παράμετροι' },
    { view: 'email-template', label: 'Πρότυπο Email' },
    { view: 'personal', label: 'Προφίλ' }
  ],
  teacher: [
    { view: 'dashboard', label: 'Αρχική' },
    { view: 'activities', label: 'Δραστηριότητες' },
    { view: 'messages-create', label: 'Νέο Μήνυμα' },
    { view: 'messages-list', label: 'Λίστα Μηνυμάτων' },
    { view: 'free-email', label: 'Ελεύθ. Email' },
    { view: 'inbox', label: 'Εισερχόμενα' },
    { view: 'personal', label: 'Προφίλ' }
  ],
  parent: [
    { view: 'parent', label: 'Παιδί μου' },
    { view: 'inbox', label: 'Εισερχόμενα' },
    { view: 'personal', label: 'Προφίλ' }
  ]
};

const VIEW_LOADERS = {
  dashboard: loadDashboard,
  children: () => loadChildren(1),
  groups: () => loadGroups(1),
  'groups-assign': loadGroupsAssign,
  activities: loadActivities,
  users: () => loadUsers(1),
  'financial-activities': loadFinancialActivities,
  'financial-income': loadFinancialIncomeScreen,
  'financial-income-totals': loadIncomeTotals,
  'financial-expenses': loadExpenses,
  parameters: loadParameters,
  'email-template': loadEmailTemplate,
  personal: loadPersonal,
  'messages-create': loadMessagesCreateScreen,
  'messages-list': loadMessagesListScreen,
  'free-email': loadFreeEmailScreen,
  parent: async () => { await loadParentChildren(); await loadParentMessages(); },
  inbox: () => loadThreads()
};

boot();

async function boot() {
  const now = new Date();
  const from = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
  const to = now.toISOString().slice(0, 10);
  els.parentFrom.value = from;
  els.parentTo.value = to;
  els.fitFrom.value = from;
  els.fitTo.value = to;
  els.feFrom.value = from;
  els.feTo.value = to;
  els.mlFrom.value = from;
  els.mlTo.value = to;
  els.mcDate.value = to;

  const defaultBase = detectDefaultApiBase();
  state.apiBase = normalizeBase(localStorage.getItem(STORAGE_KEYS.apiBase) || defaultBase);
  els.apiBase.value = state.apiBase;

  state.token = localStorage.getItem(STORAGE_KEYS.token) || '';
  const userRaw = localStorage.getItem(STORAGE_KEYS.user);
  state.user = userRaw ? safeJson(userRaw) : null;

  wireEvents();

  if (!state.apiBase) {
    showToast('Ορίστε πρώτα API Base URL.');
    updateServerChip('offline');
    showLoggedOut();
    return;
  }

  const ok = await pingServer();
  if (!ok) {
    showLoggedOut();
    return;
  }

  if (state.token) {
    const me = await apiPost('/api/auth/me', {});
    if (me.ok && me.data && me.data.user) {
      state.user = me.data.user;
      persistUser();
      await showLoggedIn();
      return;
    }
    clearAuth();
  }

  showLoggedOut();
}

function wireEvents() {
  els.saveSettings.addEventListener('click', async () => {
    state.apiBase = normalizeBase(els.apiBase.value);
    if (!state.apiBase) {
      showToast('Βάλτε έγκυρο API Base URL.');
      return;
    }
    localStorage.setItem(STORAGE_KEYS.apiBase, state.apiBase);
    const ok = await pingServer();
    if (ok) {
      showToast('Η ρύθμιση αποθηκεύτηκε.');
    }
  });

  els.loginForm.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    await login();
  });

  els.logoutBtn.addEventListener('click', async () => {
    if (state.token) {
      await apiPost('/api/auth/revoke', {});
    }
    clearAuth();
    showLoggedOut();
    showToast('Αποσυνδέθηκε.');
  });

  els.parentRefresh.addEventListener('click', async () => {
    await loadParentMessages();
  });

  els.parentChild.addEventListener('change', async () => {
    await loadParentMessages();
  });

  els.staffRefresh.addEventListener('click', async () => {
    await loadThreads();
  });

  els.replyBtn.addEventListener('click', async () => {
    await sendReply();
  });

  els.inboxNewBtn.addEventListener('click', () => {
    openNewInboxMessageForm();
  });

  els.threadDeleteBtn.addEventListener('click', async () => {
    const threadId = Number(state.currentThreadId);
    if (!threadId) return;
    if (!await appConfirm('Ολόκληρη η συνομιλία και όλα τα μηνύματά της θα διαγραφούν οριστικά.', { title: 'Διαγραφή συνομιλίας;', confirmLabel: 'Διαγραφή', danger: true })) return;
    const r = await apiPost('/api/inbox/delete-thread', { thread_id: threadId });
    if (!r.ok) { showToast(errMsg(r)); return; }
    state.currentThreadId = null;
    showToast('Η συνομιλία διαγράφηκε.');
    await loadThreads();
  });

  els.modalClose.addEventListener('click', closeModal);
  els.modalOverlay.addEventListener('click', (ev) => {
    if (ev.target === els.modalOverlay) closeModal();
  });

  document.querySelector('[data-refresh="dashboard"]').addEventListener('click', loadDashboard);

  els.childrenAddBtn.addEventListener('click', () => openChildForm(null));
  els.childrenSearch.addEventListener('input', debounce(() => loadChildren(1), 350));
  document.getElementById('children-bulk-activate').addEventListener('click', () => childrenBulkAction('activate'));
  document.getElementById('children-bulk-deactivate').addEventListener('click', () => childrenBulkAction('deactivate'));
  document.getElementById('children-bulk-clone').addEventListener('click', () => childrenBulkAction('clone'));
  document.getElementById('children-bulk-delete').addEventListener('click', () => childrenBulkAction('delete'));

  els.groupsAddBtn.addEventListener('click', () => openGroupForm(null));

  els.gaGroup.addEventListener('change', () => loadGroupAssignChildren(Number(els.gaGroup.value)));
  els.gaSaveBtn.addEventListener('click', saveGroupAssignment);

  els.activitiesAddBtn.addEventListener('click', () => openActivityForm(null));
  Array.from(els.activitiesTabs.querySelectorAll('.tab')).forEach((tab) => {
    tab.addEventListener('click', () => {
      state.activitiesType = tab.getAttribute('data-type');
      Array.from(els.activitiesTabs.querySelectorAll('.tab')).forEach((t) => t.classList.toggle('active', t === tab));
      loadActivities();
    });
  });

  els.usersAddBtn.addEventListener('click', () => openUserForm(null));

  els.faAddBtn.addEventListener('click', () => openFinancialActivityForm(null));

  els.fiChild.addEventListener('change', () => loadFinancialIncome());
  els.fiAddBtn.addEventListener('click', () => openIncomeForm(null));

  els.fitSearchBtn.addEventListener('click', () => loadIncomeTotals());

  els.feSearchBtn.addEventListener('click', () => loadExpenses());
  els.feAddBtn.addEventListener('click', () => openExpenseForm(null));

  els.paramsForm.addEventListener('submit', saveParameters);
  els.etForm.addEventListener('submit', saveEmailTemplate);
  els.personalProfileForm.addEventListener('submit', savePersonalProfile);
  els.personalPasswordForm.addEventListener('submit', savePersonalPassword);

  els.mcLoadBtn.addEventListener('click', loadMessagesCreateRows);
  els.mcPreviewBtn.addEventListener('click', openMessagesPreview);
  els.mcClearBtn.addEventListener('click', clearMessagesCreateRows);
  els.mcSaveBtn.addEventListener('click', saveMessagesCreateRows);
  els.mcSendBtn.addEventListener('click', sendMessagesCreateEmails);
  els.mcPhotoChild.addEventListener('change', loadMcPhotos);
  els.mcPhotoAllDates.addEventListener('change', loadMcPhotos);
  els.mcPhotoMode.addEventListener('change', () => {
    const isGroup = els.mcPhotoMode.value === 'group';
    els.mcPhotoChildWrap.classList.toggle('hidden', isGroup);
    if (isGroup) {
      els.mcPhotoList.innerHTML = '<div class="empty">Η μεταφόρτωση θα σταλεί σε όλα τα ενεργά παιδιά του τμήματος. Δεν εμφανίζεται λίστα εδώ.</div>';
    } else {
      loadMcPhotos();
    }
  });
  els.mcPhotoUploadBtn.addEventListener('click', uploadMcPhotos);
  Array.from(document.querySelectorAll('[data-bulk]')).forEach((btn) => {
    btn.addEventListener('click', () => bulkApplyActivities(btn.getAttribute('data-bulk'), btn.getAttribute('data-mode')));
  });

  els.mlSearchBtn.addEventListener('click', loadMessagesList);
  document.getElementById('ml-bulk-delete').addEventListener('click', messagesListBulkDelete);

  els.fe2SendAll.addEventListener('change', () => {
    els.fe2Group.disabled = els.fe2SendAll.checked;
  });
  els.fe2Form.addEventListener('submit', sendFreeEmail);
}

async function login() {
  if (!state.apiBase) {
    showToast('Ορίστε πρώτα API Base URL.');
    return;
  }

  const username = (els.username.value || '').trim();
  const password = els.password.value || '';
  if (!username || !password) {
    showToast('Συμπληρώστε username και κωδικό.');
    return;
  }

  const payload = {
    username,
    password,
    device_name: getDeviceName(),
    platform: getPlatform()
  };
  const res = await apiPost('/api/auth/token', payload, { withAuth: false });
  if (!res.ok || !res.data || !res.data.token) {
    showToast((res.data && res.data.error) || 'Αποτυχία σύνδεσης.');
    return;
  }

  state.token = res.data.token;
  state.user = res.data.user || null;
  localStorage.setItem(STORAGE_KEYS.token, state.token);
  persistUser();

  els.password.value = '';
  await showLoggedIn();
  showToast('Επιτυχής σύνδεση.');
}

async function showLoggedIn() {
  if (!state.user) {
    showLoggedOut();
    return;
  }

  els.settingsPanel.classList.add('hidden');
  els.loginPanel.classList.add('hidden');
  els.userPanel.classList.remove('hidden');
  els.logoutBtn.classList.remove('hidden');
  els.appNav.classList.remove('hidden');

  els.welcomeTitle.textContent = `Καλωσήρθες, ${state.user.name || state.user.username || ''}`;
  els.roleChip.textContent = roleLabel(state.user.role);
  els.userLine.textContent = `${state.user.username || ''} · ${state.user.email || ''}`;

  els.inboxNewBtn.classList.toggle('hidden', state.user.role !== 'parent');

  renderNav(state.user.role);
  await refreshInboxBadge();
  startInboxBadgePolling();

  const items = NAV_BY_ROLE[state.user.role] || [];
  if (items.length) {
    await showView(items[0].view);
  } else {
    showToast('Μη υποστηριζόμενος ρόλος για mobile app.');
  }
}

function showLoggedOut() {
  els.settingsPanel.classList.remove('hidden');
  els.loginPanel.classList.remove('hidden');
  els.userPanel.classList.add('hidden');
  els.appNav.classList.add('hidden');
  els.appNav.innerHTML = '';
  Array.from(document.querySelectorAll('.view')).forEach((v) => v.classList.add('hidden'));
  els.logoutBtn.classList.add('hidden');
  stopInboxBadgePolling();
}

function renderNav(role) {
  const items = NAV_BY_ROLE[role] || [];
  els.appNav.innerHTML = items.map((item) => `<button type="button" class="nav-btn" data-view="${item.view}">${escapeHtml(item.label)}${item.view === 'inbox' ? ' <span class="nav-badge hidden" id="nav-inbox-badge"></span>' : ''}</button>`).join('');
  Array.from(els.appNav.querySelectorAll('[data-view]')).forEach((btn) => {
    btn.addEventListener('click', () => showView(btn.getAttribute('data-view')));
  });
}

async function refreshInboxBadge() {
  const badge = document.getElementById('nav-inbox-badge');
  if (!badge) return;
  const res = await apiPost('/api/inbox/unread-count', {});
  const unread = (res.ok && res.data && Number(res.data.unread)) || 0;
  badge.textContent = unread > 99 ? '99+' : String(unread);
  badge.classList.toggle('hidden', unread === 0);
}

function startInboxBadgePolling() {
  stopInboxBadgePolling();
  state.inboxBadgeInterval = setInterval(refreshInboxBadge, 30000);
}

function stopInboxBadgePolling() {
  if (state.inboxBadgeInterval) {
    clearInterval(state.inboxBadgeInterval);
    state.inboxBadgeInterval = null;
  }
}

async function showView(viewId) {
  state.currentView = viewId;
  Array.from(document.querySelectorAll('.view')).forEach((v) => {
    v.classList.toggle('hidden', v.getAttribute('data-view') !== viewId);
  });
  Array.from(els.appNav.querySelectorAll('[data-view]')).forEach((btn) => {
    btn.classList.toggle('active', btn.getAttribute('data-view') === viewId);
  });
  const loader = VIEW_LOADERS[viewId];
  if (loader) {
    try {
      await loader();
    } catch (err) {
      showToast('Σφάλμα φόρτωσης οθόνης.');
    }
  }
}

async function loadParentChildren() {
  const res = await apiPost('/api/parent/children', {});
  if (!res.ok || !res.data || !Array.isArray(res.data.children)) {
    showToast((res.data && res.data.error) || 'Αποτυχία φόρτωσης παιδιών.');
    return;
  }

  const options = res.data.children
    .map((c) => `<option value="${escapeHtml(c.id)}">${escapeHtml(`${c.first_name} ${c.last_name}`)}${Number(c.today_count || 0) > 0 ? ` (σήμερα: ${Number(c.today_count)})` : ''}</option>`)
    .join('');

  els.parentChild.innerHTML = options;

  if (!res.data.children.length) {
    els.parentEmpty.textContent = 'Δεν βρέθηκαν συνδεδεμένα παιδιά σε αυτόν τον λογαριασμό.';
    els.parentEmpty.classList.remove('hidden');
  } else {
    els.parentEmpty.classList.add('hidden');
  }
}

async function loadParentMessages() {
  const childId = els.parentChild.value;
  if (!childId) {
    els.parentRows.innerHTML = '';
    return;
  }

  const payload = {
    child_id: childId,
    from: els.parentFrom.value,
    to: els.parentTo.value
  };
  const res = await apiPost('/api/parent/messages', payload);
  if (!res.ok || !res.data || !Array.isArray(res.data.rows)) {
    showToast((res.data && res.data.error) || 'Αποτυχία φόρτωσης αναφορών.');
    return;
  }

  const rows = res.data.rows;
  if (!rows.length) {
    const hint = res.data.last_message_date ? ` Τελευταία αναφορά: ${formatDate(res.data.last_message_date)}.` : '';
    els.parentRows.innerHTML = `<div class="empty">Δεν υπάρχουν αναφορές για το διάστημα.${escapeHtml(hint)}</div>`;
    return;
  }

  const html = rows.map((row) => renderParentRow(row)).join('');
  els.parentRows.innerHTML = html;
}

function renderParentRow(row) {
  return `
    <article class="card">
      <h4>${escapeHtml(formatDate(row.message_date))} · ${escapeHtml(row.group_name || '')}</h4>
      <div class="row-grid">
        ${metric('Πρωινό', ratingLabel(row.breakfast))}
        ${metric('Μεσημεριανό', ratingLabel(row.lunch))}
        ${metric('Διάθεση', ratingLabel(row.mood))}
        ${metric('Ύπνος', Number(row.sleep_minutes || 0) > 0 ? `${Number(row.sleep_minutes)} λ.` : '-')}
      </div>
      <p><strong>WC:</strong> ${row.wc ? 'Ναι' : 'Όχι'}</p>
      <p><strong>Δραστηριότητες:</strong> ${escapeHtml(row.activities || '-')}</p>
      <p><strong>Σχόλια:</strong> ${escapeHtml(row.comments || '-')}</p>
    </article>
  `;
}

function metric(title, value) {
  return `<div class="metric"><strong>${escapeHtml(title)}</strong><span>${escapeHtml(value)}</span></div>`;
}

async function loadThreads(preserveSelection = false) {
  const res = await apiPost('/api/inbox/thread-list', {});
  if (!res.ok || !res.data || !Array.isArray(res.data.threads)) {
    showToast((res.data && res.data.error) || 'Αποτυχία φόρτωσης συνομιλιών.');
    return;
  }

  state.threads = res.data.threads;
  if (!state.threads.length) {
    els.threadList.innerHTML = '<div class="empty">Δεν υπάρχουν συνομιλίες.</div>';
    els.threadMessages.innerHTML = '';
    els.threadTitle.textContent = 'Επιλέξτε συνομιλία';
    els.threadDeleteBtn.classList.add('hidden');
    state.currentThreadId = null;
    await refreshInboxBadge();
    return;
  }

  const listHtml = state.threads.map((thread) => {
    const unread = Number(thread.unread || 0);
    const active = Number(state.currentThreadId) === Number(thread.id);
    const title = `${thread.first_name || ''} ${thread.last_name || ''}`.trim();
    const meta = state.user.role === 'parent'
      ? (thread.group_name || '')
      : `${thread.parent_name || ''} · ${thread.group_name || ''}`;
    const subject = (thread.subject || '').trim();
    return `
      <div class="thread-item ${active ? 'active' : ''}" data-thread-id="${thread.id}">
        <div class="thread-title-line">
          <strong>${escapeHtml(title || 'Συνομιλία')}</strong>
          ${unread > 0 ? `<span class="unread-pill">${unread}</span>` : ''}
        </div>
        <div class="thread-meta">${escapeHtml(meta)}</div>
        ${subject ? `<div class="thread-subject">${escapeHtml(subject)}</div>` : ''}
      </div>
    `;
  }).join('');

  els.threadList.innerHTML = listHtml;
  Array.from(els.threadList.querySelectorAll('[data-thread-id]')).forEach((el) => {
    el.addEventListener('click', async () => {
      const id = Number(el.getAttribute('data-thread-id'));
      await openThread(id);
    });
  });

  if (!preserveSelection || !state.currentThreadId) {
    await openThread(Number(state.threads[0].id));
  }
  await refreshInboxBadge();
}

async function openThread(threadId) {
  if (!threadId) return;
  state.currentThreadId = threadId;

  Array.from(els.threadList.querySelectorAll('.thread-item')).forEach((item) => {
    item.classList.toggle('active', Number(item.getAttribute('data-thread-id')) === Number(threadId));
  });

  const res = await apiPost('/api/inbox/thread-messages', { thread_id: threadId });
  if (!res.ok || !res.data || !Array.isArray(res.data.messages)) {
    showToast((res.data && res.data.error) || 'Αποτυχία φόρτωσης μηνυμάτων.');
    return;
  }

  const thread = res.data.thread || {};
  const titleParts = [`${thread.first_name || ''} ${thread.last_name || ''}`.trim(), thread.group_name || ''].filter(Boolean);
  if ((thread.subject || '').trim()) titleParts.push(thread.subject.trim());
  els.threadTitle.textContent = titleParts.join(' — ');
  els.threadDeleteBtn.classList.toggle('hidden', state.user.role !== 'admin');

  const myId = Number(state.user.id);
  const html = res.data.messages.map((m) => {
    const mine = Number(m.sender_id) === myId;
    const canDelete = mine || state.user.role === 'admin';
    const readLabel = mine ? (m.read_at ? `<div class="msg-read" title="Διαβάστηκε ${escapeHtml(m.read_at)}">✓✓ Διαβάστηκε</div>` : '<div class="msg-pending">✓ Στάλθηκε</div>') : '';
    return `
      <article class="msg ${mine ? 'mine' : 'theirs'}" data-msg-id="${m.id}">
        <div class="msg-head">${escapeHtml(m.sender_name || '')} · ${escapeHtml(m.created_at || '')}${canDelete ? ` <button class="btn-icon" type="button" data-del-msg="${m.id}" title="Διαγραφή">🗑️</button>` : ''}</div>
        <div class="msg-body">${escapeHtml(m.body || '')}</div>
        ${readLabel}
      </article>
    `;
  }).join('');

  els.threadMessages.innerHTML = html || '<div class="empty">Δεν υπάρχουν μηνύματα.</div>';
  els.threadMessages.scrollTop = els.threadMessages.scrollHeight;

  bindRowActions(els.threadMessages, {
    'del-msg': async (msgId) => {
      if (!await appConfirm('Το μήνυμα θα διαγραφεί οριστικά.', { title: 'Διαγραφή μηνύματος;', confirmLabel: 'Διαγραφή', danger: true })) return;
      const r = await apiPost('/api/inbox/delete-message', { msg_id: msgId });
      if (!r.ok) { showToast(errMsg(r)); return; }
      if (r.data && r.data.thread_deleted) {
        state.currentThreadId = null;
        await loadThreads();
      } else {
        await openThread(threadId);
      }
    }
  });

  await loadThreads(true);
}

async function sendReply() {
  const threadId = Number(state.currentThreadId);
  if (!threadId) {
    showToast('Επιλέξτε πρώτα συνομιλία.');
    return;
  }
  const body = (els.replyBody.value || '').trim();
  if (!body) {
    showToast('Γράψτε μήνυμα.');
    return;
  }

  const res = await apiPost('/api/inbox/reply', { thread_id: threadId, body });
  if (!res.ok || (res.data && res.data.error)) {
    showToast((res.data && res.data.error) || 'Αποτυχία αποστολής.');
    return;
  }

  els.replyBody.value = '';
  await openThread(threadId);
  showToast('Το μήνυμα στάλθηκε.');
}

async function pingServer() {
  const res = await apiGet('/api/auth/ping', { withAuth: false });
  if (!res.networkOk) {
    updateServerChip('offline');
    showToast('Δεν βρίσκω server στο URL που δώσατε.');
    return false;
  }

  // 200 όταν υπάρχει session, 401 όταν δεν υπάρχει. Και τα δύο σημαίνουν ότι ο server απαντά.
  const alive = res.status === 200 || res.status === 401;
  updateServerChip(alive ? 'online' : 'offline');
  if (!alive) {
    showToast('Ο server απάντησε με μη αναμενόμενο status.');
  }
  return alive;
}

async function apiPost(path, payload, opts = {}) {
  return request('POST', path, payload, opts);
}

async function apiGet(path, opts = {}) {
  return request('GET', path, null, opts);
}

async function apiUpload(path, formData) {
  const url = `${state.apiBase}${path}`;
  const headers = {};
  if (state.token) headers.Authorization = `Bearer ${state.token}`;
  try {
    const resp = await fetch(url, { method: 'POST', headers, body: formData });
    const text = await resp.text();
    const data = safeJson(text);
    return { ok: resp.ok, status: resp.status, networkOk: true, data };
  } catch (err) {
    return { ok: false, status: 0, networkOk: false, data: { error: 'Σφάλμα δικτύου.' } };
  }
}

function appendFormValue(params, key, value) {
  if (Array.isArray(value)) {
    value.forEach((v, i) => appendFormValue(params, `${key}[${i}]`, v));
  } else if (value !== null && typeof value === 'object') {
    Object.keys(value).forEach((k) => appendFormValue(params, `${key}[${k}]`, value[k]));
  } else if (value !== undefined && value !== null) {
    params.append(key, value);
  }
}

function buildForm(payload) {
  const params = new URLSearchParams();
  Object.keys(payload || {}).forEach((k) => appendFormValue(params, k, payload[k]));
  return params;
}

async function request(method, path, payload, opts = {}) {
  const withAuth = opts.withAuth !== false;
  const url = `${state.apiBase}${path}`;
  const headers = {};
  const body = method === 'POST' ? buildForm(payload || {}) : null;

  if (withAuth && state.token) {
    headers.Authorization = `Bearer ${state.token}`;
  }
  if (body) {
    headers['Content-Type'] = 'application/x-www-form-urlencoded;charset=UTF-8';
  }

  try {
    const resp = await fetch(url, {
      method,
      headers,
      body
    });
    const text = await resp.text();
    const data = safeJson(text);
    return {
      ok: resp.ok,
      status: resp.status,
      networkOk: true,
      data
    };
  } catch (err) {
    return {
      ok: false,
      status: 0,
      networkOk: false,
      data: { error: 'Σφάλμα δικτύου.' }
    };
  }
}

function updateServerChip(status) {
  const online = status === 'online';
  els.serverChip.textContent = online ? 'online' : 'offline';
  els.serverChip.style.background = online ? '#dcfce7' : '#fee2e2';
  els.serverChip.style.color = online ? '#166534' : '#991b1b';
}

function clearAuth() {
  state.token = '';
  state.user = null;
  state.currentThreadId = null;
  localStorage.removeItem(STORAGE_KEYS.token);
  localStorage.removeItem(STORAGE_KEYS.user);
}

function persistUser() {
  if (state.user) {
    localStorage.setItem(STORAGE_KEYS.user, JSON.stringify(state.user));
  }
}

function roleLabel(role) {
  if (role === 'parent') return 'γονέας';
  if (role === 'teacher') return 'δάσκαλος';
  if (role === 'admin') return 'διαχειριστής';
  return role || 'χρήστης';
}

function ratingLabel(value) {
  const labels = {
    0: '-',
    1: 'Καθόλου',
    2: 'Μέτρια',
    3: 'Καλά',
    4: 'Πολύ Καλά'
  };
  return labels[Number(value)] || '-';
}

function formatDate(value) {
  if (!value) return '-';
  const part = String(value).slice(0, 10);
  const [y, m, d] = part.split('-');
  if (!y || !m || !d) return value;
  return `${d}/${m}/${y}`;
}

function detectDefaultApiBase() {
  const host = window.location.hostname;
  if (host === 'localhost') {
    return 'http://10.0.2.2:8094';
  }
  if (host) {
    const port = window.location.port ? `:${window.location.port}` : '';
    return `${window.location.protocol}//${host}${port}`;
  }
  return '';
}

function normalizeBase(base) {
  const val = (base || '').trim();
  if (!val) return '';
  return val.replace(/\/+$/, '');
}

function getDeviceName() {
  const ua = navigator.userAgent || 'unknown';
  return ua.slice(0, 95);
}

function getPlatform() {
  const ua = (navigator.userAgent || '').toLowerCase();
  if (ua.includes('android')) return 'android';
  if (ua.includes('iphone') || ua.includes('ipad') || ua.includes('ios')) return 'ios';
  return 'web';
}

function showToast(message) {
  els.toast.textContent = message;
  els.toast.classList.remove('hidden');
  requestAnimationFrame(() => els.toast.classList.add('show'));
  setTimeout(() => {
    els.toast.classList.remove('show');
    setTimeout(() => els.toast.classList.add('hidden'), 180);
  }, 2600);
}

function safeJson(text) {
  try {
    return JSON.parse(text);
  } catch (_) {
    return null;
  }
}

function escapeHtml(value) {
  const str = String(value ?? '');
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function errMsg(res) {
  return (res && res.data && res.data.error) || 'Παρουσιάστηκε σφάλμα.';
}

function debounce(fn, wait) {
  let t = null;
  return (...args) => {
    clearTimeout(t);
    t = setTimeout(() => fn(...args), wait);
  };
}

function bindRowActions(container, handlers) {
  Object.keys(handlers).forEach((key) => {
    Array.from(container.querySelectorAll(`[data-${key}]`)).forEach((btn) => {
      btn.addEventListener('click', () => handlers[key](btn.getAttribute(`data-${key}`)));
    });
  });
}

function renderDataTable(container, columns, rows, options = {}) {
  if (!rows || !rows.length) {
    container.innerHTML = `<div class="empty">${escapeHtml(options.emptyText || 'Δεν υπάρχουν εγγραφές.')}</div>`;
    return;
  }
  const thead = columns.map((c) => `<th>${escapeHtml(c.label)}</th>`).join('') + (options.actions ? '<th></th>' : '');
  const tbody = rows.map((row) => {
    const cells = columns.map((c) => `<td>${c.render ? c.render(row) : escapeHtml(row[c.key] ?? '')}</td>`).join('');
    const actionsCell = options.actions ? `<td class="table-actions">${options.actions(row)}</td>` : '';
    return `<tr>${cells}${actionsCell}</tr>`;
  }).join('');
  container.innerHTML = `<div class="table-wrap"><table class="data-table"><thead><tr>${thead}</tr></thead><tbody>${tbody}</tbody></table></div>`;
}

function renderPagination(container, page, pageSize, total, onPage) {
  const pages = Math.max(1, Math.ceil((total || 0) / pageSize));
  if (pages <= 1) {
    container.innerHTML = '';
    return;
  }
  container.innerHTML = `
    <button class="btn ghost" type="button" data-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}>&laquo; Πίσω</button>
    <span class="page-info">${page} / ${pages}</span>
    <button class="btn ghost" type="button" data-page="${page + 1}" ${page >= pages ? 'disabled' : ''}>Επόμενο &raquo;</button>
  `;
  Array.from(container.querySelectorAll('[data-page]')).forEach((btn) => {
    btn.addEventListener('click', () => onPage(Number(btn.getAttribute('data-page'))));
  });
}

// ── Generic modal form ────────────────────────────────────────────────

function renderFieldHtml(f, values) {
  const val = values[f.key] ?? f.default ?? '';
  if (f.type === 'select') {
    const opts = (f.options || []).map((o) => `<option value="${escapeHtml(o.value)}" ${String(o.value) === String(val) ? 'selected' : ''}>${escapeHtml(o.label)}</option>`).join('');
    return `<label class="field"><span>${escapeHtml(f.label)}</span><select name="${f.key}">${opts}</select></label>`;
  }
  if (f.type === 'checkbox') {
    return `<label class="field checkbox-field"><input type="checkbox" name="${f.key}" ${val ? 'checked' : ''}><span>${escapeHtml(f.label)}</span></label>`;
  }
  if (f.type === 'textarea') {
    return `<label class="field"><span>${escapeHtml(f.label)}</span><textarea name="${f.key}" rows="${f.rows || 4}">${escapeHtml(val)}</textarea></label>`;
  }
  if (f.type === 'checkbox-group') {
    const selected = Array.isArray(val) ? val.map(String) : [];
    const items = (f.options || []).map((o) => `<label class="checkbox-item"><input type="checkbox" data-group="${f.key}" value="${escapeHtml(o.value)}" ${selected.includes(String(o.value)) ? 'checked' : ''}><span>${escapeHtml(o.label)}</span></label>`).join('');
    return `<div class="field"><span>${escapeHtml(f.label)}</span><div class="checkbox-list">${items || '<span class="hint">Καμία επιλογή διαθέσιμη.</span>'}</div></div>`;
  }
  const type = f.type || 'text';
  return `<label class="field"><span>${escapeHtml(f.label)}${f.required ? ' *' : ''}</span><input type="${type}" name="${f.key}" value="${escapeHtml(val)}" ${f.required ? 'required' : ''} ${f.min !== undefined ? `min="${f.min}"` : ''} ${f.max !== undefined ? `max="${f.max}"` : ''} ${f.step !== undefined ? `step="${f.step}"` : ''}></label>`;
}

function collectFormData(fields, form) {
  const data = {};
  fields.forEach((f) => {
    if (f.type === 'checkbox') {
      data[f.key] = form.querySelector(`[name="${f.key}"]`).checked ? 1 : 0;
    } else if (f.type === 'checkbox-group') {
      data[f.key] = Array.from(form.querySelectorAll(`[data-group="${f.key}"]:checked`)).map((el) => el.value);
    } else {
      data[f.key] = form.querySelector(`[name="${f.key}"]`).value;
    }
  });
  return data;
}

function openFormModal({ title, fields, values = {}, onSubmit, submitLabel, showCancel }) {
  els.modalTitle.textContent = title;
  const cancelBtn = showCancel ? `<button class="btn ghost" type="button" data-modal-cancel>Ακύρωση</button>` : '';
  els.modalForm.innerHTML = fields.map((f) => renderFieldHtml(f, values)).join('') +
    `<div class="btn-row">${cancelBtn}<button class="btn primary" type="submit">${escapeHtml(submitLabel || 'Αποθήκευση')}</button></div>`;
  els.modalOverlay.classList.remove('hidden');

  if (showCancel) {
    els.modalForm.querySelector('[data-modal-cancel]').addEventListener('click', closeModal);
  }

  els.modalForm.onsubmit = async (ev) => {
    ev.preventDefault();
    const data = collectFormData(fields, els.modalForm);
    const res = await onSubmit(data);
    if (res && res.ok) {
      closeModal();
    }
  };
}

function closeModal() {
  els.modalOverlay.classList.add('hidden');
  els.modalForm.onsubmit = null;
  els.modalForm.innerHTML = '';
}

// ── Custom confirm dialog (styled, replaces native confirm()) ─────────

function appConfirm(message, options = {}) {
  return new Promise((resolve) => {
    const overlay = document.createElement('div');
    overlay.className = 'confirm-overlay';
    overlay.innerHTML = `
      <div class="confirm-box ${options.danger ? 'danger' : ''}">
        <h2>${escapeHtml(options.title || 'Επιβεβαίωση ενέργειας')}</h2>
        <p>${escapeHtml(message).replace(/\n/g, '<br>')}</p>
        <div class="btn-row">
          <button type="button" class="btn ghost" data-cancel>${escapeHtml(options.cancelLabel || 'Ακύρωση')}</button>
          <button type="button" class="btn ${options.danger ? 'danger' : 'primary'}" data-approve>${escapeHtml(options.confirmLabel || 'Συνέχεια')}</button>
        </div>
      </div>
    `;
    document.body.appendChild(overlay);

    const finish = (value) => {
      overlay.remove();
      resolve(value);
    };
    overlay.querySelector('[data-cancel]').addEventListener('click', () => finish(false));
    overlay.querySelector('[data-approve]').addEventListener('click', () => finish(true));
    overlay.addEventListener('click', (ev) => {
      if (ev.target === overlay) finish(false);
    });
  });
}

// ── Dashboard ─────────────────────────────────────────────────────────

async function loadDashboard() {
  const res = await apiPost('/api/dashboard/summary', {});
  if (!res.ok) { showToast(errMsg(res)); return; }
  const s = (res.data && res.data.stats) || {};
  const cards = [
    ['children', 'Ενεργά Παιδιά'],
    ['groups', 'Ενεργά Τμήματα'],
    ['messages_today', 'Μηνύματα Σήμερα'],
    ['emails_sent_today', 'Emails Σήμερα'],
    ['absent_today', 'Εκκρεμή / Απόντες']
  ];
  const html = cards
    .filter(([key]) => s[key] !== undefined)
    .map(([key, label]) => `<div class="stat-card"><strong>${escapeHtml(s[key])}</strong><span>${escapeHtml(label)}</span></div>`)
    .join('');
  els.dashboardStats.innerHTML = html || '<div class="empty">Χωρίς στατιστικά για τον ρόλο σας.</div>';
}

// ── Children ──────────────────────────────────────────────────────────

async function loadChildren(page) {
  state.childrenPage = page;
  const res = await apiPost('/api/children', { page, page_size: 20, search: els.childrenSearch.value || '' });
  if (!res.ok) { showToast(errMsg(res)); return; }
  const rows = res.data.rows || [];
  renderDataTable(els.childrenTable, [
    { label: '', render: (r) => `<input type="checkbox" class="children-select" value="${r.id}">` },
    { key: 'first_name', label: 'Όνομα' },
    { key: 'last_name', label: 'Επώνυμο' },
    { label: 'Γονέας', render: (r) => escapeHtml(r.parent_name || '-') },
    { label: 'Ενεργό', render: (r) => (Number(r.active) ? '✅' : '❌') }
  ], rows, {
    actions: (r) => `<button class="btn-icon" data-edit="${r.id}" title="Επεξεργασία">✏️</button><button class="btn-icon" data-del="${r.id}" title="Διαγραφή">🗑️</button>`
  });
  bindRowActions(els.childrenTable, {
    edit: (id) => openChildForm(rows.find((r) => String(r.id) === String(id))),
    del: (id) => confirmDeleteChild(id)
  });
  renderPagination(els.childrenPagination, page, 20, res.data.total || 0, (p) => loadChildren(p));

  const updateBulkBar = () => {
    const selected = Array.from(els.childrenTable.querySelectorAll('.children-select:checked'));
    document.getElementById('children-bulk-bar').classList.toggle('hidden', selected.length === 0);
    document.getElementById('children-bulk-count').textContent = selected.length ? `${selected.length} επιλεγμένα` : '';
  };
  Array.from(els.childrenTable.querySelectorAll('.children-select')).forEach((cb) => cb.addEventListener('change', updateBulkBar));
  updateBulkBar();
}

async function childrenBulkAction(action) {
  const ids = Array.from(els.childrenTable.querySelectorAll('.children-select:checked')).map((cb) => cb.value);
  if (!ids.length) return;

  const labels = {
    activate: 'Ενεργοποίηση',
    deactivate: 'Απενεργοποίηση',
    clone: 'Κλωνοποίηση',
    delete: 'Διαγραφή'
  };
  const message = action === 'delete'
    ? `Θα διαγραφούν οριστικά ${ids.length} παιδιά, μαζί με τα μηνύματα και τα έσοδά τους.`
    : `${labels[action]} για ${ids.length} επιλεγμένα παιδιά. Συνέχεια;`;
  if (!await appConfirm(message, { title: `${labels[action]};`, confirmLabel: labels[action], danger: action === 'delete' })) return;

  const res = await apiPost('/api/children/bulk-action', { action, ids });
  if (!res.ok) { showToast(errMsg(res)); return; }
  showToast(`${labels[action]}: ${res.data.affected || 0} παιδιά.`);
  await loadChildren(state.childrenPage || 1);
}

async function fetchParentsList() {
  if (state.parentsList) return state.parentsList;
  const res = await apiPost('/api/users', { page: 1, page_size: 100 });
  const rows = (res.data && res.data.rows) || [];
  state.parentsList = rows.filter((u) => u.role === 'parent');
  return state.parentsList;
}

async function openChildForm(row) {
  const parents = await fetchParentsList();
  const parentOptions = [{ value: '', label: '(Χωρίς γονέα)' }].concat(
    parents.map((p) => ({ value: String(p.id), label: `${p.name} (${p.username})` }))
  );
  const fields = [
    { key: 'first_name', label: 'Όνομα', required: true },
    { key: 'last_name', label: 'Επώνυμο', required: true },
    { key: 'dob', label: 'Ημ. Γέννησης', type: 'date' },
    { key: 'mother_mobile', label: 'Κινητό Μητέρας' },
    { key: 'father_mobile', label: 'Κινητό Πατέρα' },
    { key: 'email1', label: 'Email 1', type: 'email' },
    { key: 'send_email1', label: 'Αποστολή στο Email 1', type: 'checkbox' },
    { key: 'email2', label: 'Email 2', type: 'email' },
    { key: 'send_email2', label: 'Αποστολή στο Email 2', type: 'checkbox' },
    { key: 'parent_user_id', label: 'Λογαριασμός Γονέα', type: 'select', options: parentOptions },
    { key: 'active', label: 'Ενεργό', type: 'checkbox' }
  ];
  const values = row
    ? { ...row, active: Number(row.active), send_email1: Number(row.send_email1), send_email2: Number(row.send_email2), parent_user_id: row.parent_user_id || '' }
    : { active: 1 };
  openFormModal({
    title: row ? 'Επεξεργασία Παιδιού' : 'Νέο Παιδί',
    fields,
    values,
    onSubmit: async (data) => {
      const res = await apiPost('/api/children/save', { ...data, id: row ? row.id : 0 });
      if (!res.ok) { showToast(errMsg(res)); return { ok: false }; }
      showToast('Αποθηκεύτηκε.');
      await loadChildren(state.childrenPage || 1);
      return { ok: true };
    }
  });
}

async function confirmDeleteChild(id) {
  if (!await appConfirm('Το παιδί θα διαγραφεί οριστικά, μαζί με τα μηνύματα και τα έσοδά του.', { title: 'Διαγραφή παιδιού;', confirmLabel: 'Διαγραφή', danger: true })) return;
  const res = await apiPost('/api/children/delete', { id });
  if (!res.ok) { showToast(errMsg(res)); return; }
  showToast('Διαγράφηκε.');
  await loadChildren(state.childrenPage || 1);
}

// ── Groups ────────────────────────────────────────────────────────────

async function loadGroups(page) {
  state.groupsPage = page;
  const res = await apiPost('/api/groups', { page, page_size: 20 });
  if (!res.ok) { showToast(errMsg(res)); return; }
  const rows = res.data.rows || [];
  renderDataTable(els.groupsTable, [
    { key: 'name', label: 'Όνομα' },
    { label: 'Τρέχον', render: (r) => (Number(r.is_current) ? '✅' : '❌') }
  ], rows, {
    actions: (r) => `<button class="btn-icon" data-edit="${r.id}">✏️</button><button class="btn-icon" data-del="${r.id}">🗑️</button>`
  });
  bindRowActions(els.groupsTable, {
    edit: (id) => openGroupForm(rows.find((r) => String(r.id) === String(id))),
    del: async (id) => {
      if (!await appConfirm('Το τμήμα θα διαγραφεί οριστικά.', { title: 'Διαγραφή τμήματος;', confirmLabel: 'Διαγραφή', danger: true })) return;
      const res = await apiPost('/api/groups/delete', { id });
      if (!res.ok) { showToast(errMsg(res)); return; }
      showToast('Διαγράφηκε.');
      await loadGroups(state.groupsPage || 1);
    }
  });
  renderPagination(els.groupsPagination, page, 20, res.data.total || 0, (p) => loadGroups(p));
}

function openGroupForm(row) {
  openFormModal({
    title: row ? 'Επεξεργασία Τμήματος' : 'Νέο Τμήμα',
    fields: [
      { key: 'name', label: 'Όνομα', required: true },
      { key: 'is_current', label: 'Τρέχον σχολικό έτος', type: 'checkbox' }
    ],
    values: row ? { ...row, is_current: Number(row.is_current) } : { is_current: 1 },
    onSubmit: async (data) => {
      const res = await apiPost('/api/groups/save', { ...data, id: row ? row.id : 0 });
      if (!res.ok) { showToast(errMsg(res)); return { ok: false }; }
      showToast('Αποθηκεύτηκε.');
      state.allGroups = null;
      await loadGroups(state.groupsPage || 1);
      return { ok: true };
    }
  });
}

async function fetchAllGroups() {
  if (state.allGroups) return state.allGroups;
  const res = await apiPost('/api/groups', { page: 1, page_size: 100 });
  state.allGroups = (res.data && res.data.rows) || [];
  return state.allGroups;
}

// ── Children per Group ───────────────────────────────────────────────

async function loadGroupsAssign() {
  const groups = await fetchAllGroups();
  els.gaGroup.innerHTML = groups.map((g) => `<option value="${g.id}">${escapeHtml(g.name)}</option>`).join('');
  if (groups.length) await loadGroupAssignChildren(Number(groups[0].id));
  else els.gaChildren.innerHTML = '<div class="empty">Δημιουργήστε πρώτα ένα τμήμα.</div>';
}

async function loadGroupAssignChildren(groupId) {
  if (!groupId) return;
  const [childrenRes, assignedRes] = await Promise.all([
    apiPost('/api/children', { page: 1, page_size: 100 }),
    apiPost('/api/groups/children', { group_id: groupId })
  ]);
  const allChildren = (childrenRes.data && childrenRes.data.rows) || [];
  const assignedIds = ((assignedRes.data && assignedRes.data.child_ids) || []).map(String);
  els.gaChildren.innerHTML = allChildren.length
    ? allChildren.map((c) => `
      <label class="checkbox-item">
        <input type="checkbox" value="${c.id}" ${assignedIds.includes(String(c.id)) ? 'checked' : ''}>
        <span>${escapeHtml(c.first_name)} ${escapeHtml(c.last_name)}</span>
      </label>`).join('')
    : '<div class="empty">Δεν υπάρχουν παιδιά.</div>';
}

async function saveGroupAssignment() {
  const groupId = Number(els.gaGroup.value);
  if (!groupId) { showToast('Επιλέξτε τμήμα.'); return; }
  const childIds = Array.from(els.gaChildren.querySelectorAll('input:checked')).map((el) => el.value);
  const res = await apiPost('/api/groups/assign-children', { group_id: groupId, child_ids: childIds });
  if (!res.ok) { showToast(errMsg(res)); return; }
  showToast('Η ανάθεση αποθηκεύτηκε.');
}

// ── Activities ────────────────────────────────────────────────────────

async function loadActivities() {
  const res = await apiPost('/api/activities', { type: state.activitiesType || 'both', page: 1, page_size: 100 });
  if (!res.ok) { showToast(errMsg(res)); return; }
  const rows = res.data.rows || [];
  renderDataTable(els.activitiesTable, [{ key: 'name', label: 'Όνομα' }], rows, {
    actions: (r) => `<button class="btn-icon" data-edit="${r.id}">✏️</button><button class="btn-icon" data-del="${r.id}">🗑️</button>`
  });
  bindRowActions(els.activitiesTable, {
    edit: (id) => openActivityForm(rows.find((r) => String(r.id) === String(id))),
    del: async (id) => {
      if (!await appConfirm('Η δραστηριότητα θα διαγραφεί οριστικά.', { title: 'Διαγραφή δραστηριότητας;', confirmLabel: 'Διαγραφή', danger: true })) return;
      const res = await apiPost('/api/activities/delete', { id });
      if (!res.ok) { showToast(errMsg(res)); return; }
      showToast('Διαγράφηκε.');
      await loadActivities();
    }
  });
}

function openActivityForm(row) {
  openFormModal({
    title: row ? 'Επεξεργασία Δραστηριότητας' : 'Νέα Δραστηριότητα',
    fields: [{ key: 'name', label: 'Όνομα', required: true }],
    values: row || {},
    onSubmit: async (data) => {
      const res = await apiPost('/api/activities/save', { ...data, id: row ? row.id : 0, type: state.activitiesType || 'both' });
      if (!res.ok) { showToast(errMsg(res)); return { ok: false }; }
      showToast('Αποθηκεύτηκε.');
      await loadActivities();
      return { ok: true };
    }
  });
}

// ── Users ─────────────────────────────────────────────────────────────

async function loadUsers(page) {
  state.usersPage = page;
  const res = await apiPost('/api/users', { page, page_size: 20 });
  if (!res.ok) { showToast(errMsg(res)); return; }
  const rows = res.data.rows || [];
  renderDataTable(els.usersTable, [
    { key: 'username', label: 'Username' },
    { key: 'name', label: 'Όνομα' },
    { label: 'Ρόλος', render: (r) => roleLabel(r.role) },
    { label: 'Ενεργός', render: (r) => (Number(r.active) ? '✅' : '❌') },
    { label: 'Τμήματα', render: (r) => escapeHtml(r.teacher_groups || '-') }
  ], rows, {
    actions: (r) => `<button class="btn-icon" data-toggle="${r.id}" title="${Number(r.active) ? 'Απενεργοποίηση' : 'Ενεργοποίηση'}">${Number(r.active) ? '⏸️' : '▶️'}</button><button class="btn-icon" data-edit="${r.id}">✏️</button><button class="btn-icon" data-del="${r.id}">🗑️</button>`
  });
  bindRowActions(els.usersTable, {
    toggle: async (id) => {
      const row = rows.find((r) => String(r.id) === String(id));
      const newActive = Number(row.active) ? 0 : 1;
      const res = await apiPost('/api/users/set-active', { id, active: newActive });
      if (!res.ok) { showToast(errMsg(res)); return; }
      showToast(newActive ? 'Ο χρήστης ενεργοποιήθηκε.' : 'Ο χρήστης απενεργοποιήθηκε.');
      await loadUsers(state.usersPage || 1);
    },
    edit: (id) => openUserForm(rows.find((r) => String(r.id) === String(id))),
    del: async (id) => {
      if (!await appConfirm('Ο χρήστης θα διαγραφεί οριστικά, μαζί με τα tokens και τις αναθέσεις τμημάτων του.', { title: 'Διαγραφή χρήστη;', confirmLabel: 'Διαγραφή', danger: true })) return;
      const res = await apiPost('/api/users/delete', { id });
      if (!res.ok) { showToast(errMsg(res)); return; }
      showToast('Διαγράφηκε.');
      await loadUsers(state.usersPage || 1);
    }
  });
  renderPagination(els.usersPagination, page, 20, res.data.total || 0, (p) => loadUsers(p));
}

async function openUserForm(row) {
  const groups = await fetchAllGroups();
  let assignedGroupIds = [];
  if (row && row.id) {
    const gRes = await apiPost('/api/users/groups', { user_id: row.id });
    assignedGroupIds = ((gRes.data && gRes.data.groups) || []).map((g) => String(g.id));
  }
  const fields = [
    { key: 'username', label: 'Username', required: true },
    { key: 'name', label: 'Όνομα', required: true },
    { key: 'email', label: 'Email', type: 'email' },
    { key: 'role', label: 'Ρόλος', type: 'select', options: [{ value: 'admin', label: 'Διαχειριστής' }, { value: 'teacher', label: 'Δάσκαλος' }, { value: 'parent', label: 'Γονέας' }] },
    { key: 'active', label: 'Ενεργός', type: 'checkbox' },
    { key: 'password', label: row ? 'Νέος Κωδικός (προαιρετικό)' : 'Κωδικός', type: 'password', required: !row },
    { key: 'group_ids', label: 'Τμήματα (μόνο για δάσκαλο)', type: 'checkbox-group', options: groups.map((g) => ({ value: String(g.id), label: g.name })) }
  ];
  const values = row ? { ...row, active: Number(row.active), password: '', group_ids: assignedGroupIds } : { active: 1, role: 'teacher', group_ids: [] };
  openFormModal({
    title: row ? 'Επεξεργασία Χρήστη' : 'Νέος Χρήστης',
    fields,
    values,
    onSubmit: async (data) => {
      const res = await apiPost('/api/users/save', { ...data, id: row ? row.id : 0 });
      if (!res.ok) { showToast(errMsg(res)); return { ok: false }; }
      showToast('Αποθηκεύτηκε.');
      state.allGroups = null;
      state.parentsList = null;
      await loadUsers(state.usersPage || 1);
      return { ok: true };
    }
  });
}

// ── Financial: Activities ────────────────────────────────────────────

async function loadFinancialActivities() {
  const res = await apiPost('/api/financial/activities', {});
  if (!res.ok) { showToast(errMsg(res)); return; }
  const rows = res.data.rows || [];
  renderDataTable(els.faTable, [{ key: 'name', label: 'Όνομα' }], rows, {
    actions: (r) => `<button class="btn-icon" data-edit="${r.id}">✏️</button><button class="btn-icon" data-del="${r.id}">🗑️</button>`
  });
  bindRowActions(els.faTable, {
    edit: (id) => openFinancialActivityForm(rows.find((r) => String(r.id) === String(id))),
    del: async (id) => {
      if (!await appConfirm('Η δραστηριότητα εσόδων θα διαγραφεί οριστικά.', { title: 'Διαγραφή;', confirmLabel: 'Διαγραφή', danger: true })) return;
      const res = await apiPost('/api/financial/activities/delete', { id });
      if (!res.ok) { showToast(errMsg(res)); return; }
      showToast('Διαγράφηκε.');
      await loadFinancialActivities();
    }
  });
}

function openFinancialActivityForm(row) {
  openFormModal({
    title: row ? 'Επεξεργασία' : 'Νέα Δραστηριότητα Εσόδων',
    fields: [{ key: 'name', label: 'Όνομα', required: true }],
    values: row || {},
    onSubmit: async (data) => {
      const res = await apiPost('/api/financial/activities/save', { ...data, id: row ? row.id : 0 });
      if (!res.ok) { showToast(errMsg(res)); return { ok: false }; }
      showToast('Αποθηκεύτηκε.');
      state.financialActivities = null;
      await loadFinancialActivities();
      return { ok: true };
    }
  });
}

async function fetchFinancialActivities() {
  if (state.financialActivities) return state.financialActivities;
  const res = await apiPost('/api/financial/activities', {});
  state.financialActivities = (res.data && res.data.rows) || [];
  return state.financialActivities;
}

// ── Financial: Income ─────────────────────────────────────────────────

async function loadFinancialIncomeScreen() {
  const res = await apiPost('/api/children', { page: 1, page_size: 100 });
  const children = (res.data && res.data.rows) || [];
  els.fiChild.innerHTML = children.map((c) => `<option value="${c.id}">${escapeHtml(c.first_name)} ${escapeHtml(c.last_name)}</option>`).join('');
  if (children.length) await loadFinancialIncome();
  else { els.fiPendingTable.innerHTML = ''; els.fiFullTable.innerHTML = '<div class="empty">Δεν υπάρχουν παιδιά.</div>'; }
}

async function loadFinancialIncome() {
  const childId = Number(els.fiChild.value);
  if (!childId) return;
  const res = await apiPost('/api/financial/income-list', { child_id: childId });
  if (!res.ok) { showToast(errMsg(res)); return; }
  const columns = [
    { label: 'Δραστηριότητα', render: (r) => escapeHtml(r.activity_name || r.description || '-') },
    { label: 'Ημ/νία', render: (r) => formatDate(r.entry_date) },
    { label: 'Ποσό', render: (r) => Number(r.amount).toFixed(2) },
    { label: 'Πληρώθηκε', render: (r) => Number(r.amount_paid).toFixed(2) }
  ];
  renderDataTable(els.fiPendingTable, columns, res.data.pending || [], {
    actions: (r) => `<button class="btn-icon" data-edit="${r.id}">✏️</button><button class="btn-icon" data-del="${r.id}">🗑️</button>`
  });
  renderDataTable(els.fiFullTable, columns, res.data.full || [], {
    actions: (r) => `<button class="btn-icon" data-edit="${r.id}">✏️</button><button class="btn-icon" data-del="${r.id}">🗑️</button>`
  });
  const allRows = (res.data.pending || []).concat(res.data.full || []);
  const del = async (id) => {
    if (!await appConfirm('Η εγγραφή εσόδου θα διαγραφεί οριστικά.', { title: 'Διαγραφή εγγραφής;', confirmLabel: 'Διαγραφή', danger: true })) return;
    const r = await apiPost('/api/financial/income-delete', { id });
    if (!r.ok) { showToast(errMsg(r)); return; }
    showToast('Διαγράφηκε.');
    await loadFinancialIncome();
  };
  const edit = (id) => openIncomeForm(allRows.find((r) => String(r.id) === String(id)));
  bindRowActions(els.fiPendingTable, { edit, del });
  bindRowActions(els.fiFullTable, { edit, del });
}

async function openIncomeForm(row) {
  const activities = await fetchFinancialActivities();
  const childId = Number(els.fiChild.value);
  const fields = [
    { key: 'activity_id', label: 'Δραστηριότητα', type: 'select', options: [{ value: '', label: '(Καμία)' }].concat(activities.map((a) => ({ value: String(a.id), label: a.name }))) },
    { key: 'description', label: 'Περιγραφή' },
    { key: 'entry_date', label: 'Ημ/νία Καταχώρησης', type: 'date' },
    { key: 'collection_date', label: 'Ημ/νία Είσπραξης', type: 'date' },
    { key: 'amount', label: 'Ποσό', type: 'number', step: '0.01', min: 0 },
    { key: 'amount_paid', label: 'Πληρώθηκε', type: 'number', step: '0.01', min: 0 },
    { key: 'recurrence', label: 'Επαναλήψεις', type: 'number', min: 1 },
    { key: 'period_type', label: 'Περίοδος', type: 'select', options: [{ value: 'Μέρες', label: 'Μέρες' }, { value: 'Εβδομάδες', label: 'Εβδομάδες' }, { value: 'Μήνες', label: 'Μήνες' }] },
    { key: 'notes', label: 'Σημειώσεις', type: 'textarea' }
  ];
  const today = new Date().toISOString().slice(0, 10);
  const values = row ? { ...row } : { entry_date: today, recurrence: 1, period_type: 'Μέρες', amount: 0, amount_paid: 0 };
  openFormModal({
    title: row ? 'Επεξεργασία Εσόδου' : 'Νέο Έσοδο',
    fields,
    values,
    onSubmit: async (data) => {
      const res = await apiPost('/api/financial/income-save', { ...data, id: row ? row.id : 0, child_id: childId });
      if (!res.ok) { showToast(errMsg(res)); return { ok: false }; }
      showToast('Αποθηκεύτηκε.');
      await loadFinancialIncome();
      return { ok: true };
    }
  });
}

// ── Financial: Income Totals ──────────────────────────────────────────

async function loadIncomeTotals() {
  const res = await apiPost('/api/financial/income-totals-list', { from: els.fitFrom.value, to: els.fitTo.value });
  if (!res.ok) { showToast(errMsg(res)); return; }
  renderDataTable(els.fitTable, [
    { label: 'Παιδί', render: (r) => `${escapeHtml(r.first_name)} ${escapeHtml(r.last_name)}` },
    { label: 'Χρεώθηκαν', render: (r) => Number(r.total_charged).toFixed(2) },
    { label: 'Πληρώθηκαν', render: (r) => Number(r.total_paid).toFixed(2) },
    { label: 'Υπόλοιπο', render: (r) => Number(r.balance).toFixed(2) }
  ], res.data.rows || []);
}

// ── Financial: Expenses ───────────────────────────────────────────────

async function loadExpenses() {
  const res = await apiPost('/api/financial/expenses-list', { from: els.feFrom.value, to: els.feTo.value });
  if (!res.ok) { showToast(errMsg(res)); return; }
  const rows = res.data.rows || [];
  renderDataTable(els.feTable, [
    { label: 'Δραστηριότητα', render: (r) => escapeHtml(r.activity_name || '-') },
    { label: 'Ημ/νία', render: (r) => formatDate(r.entry_date) },
    { label: 'Ποσό', render: (r) => Number(r.amount).toFixed(2) },
    { label: 'Σημειώσεις', render: (r) => escapeHtml(r.notes || '-') }
  ], rows, {
    actions: (r) => `<button class="btn-icon" data-edit="${r.id}">✏️</button><button class="btn-icon" data-del="${r.id}">🗑️</button>`
  });
  bindRowActions(els.feTable, {
    edit: (id) => openExpenseForm(rows.find((r) => String(r.id) === String(id))),
    del: async (id) => {
      if (!await appConfirm('Η εγγραφή εξόδου θα διαγραφεί οριστικά.', { title: 'Διαγραφή εξόδου;', confirmLabel: 'Διαγραφή', danger: true })) return;
      const r = await apiPost('/api/financial/expenses-delete', { id });
      if (!r.ok) { showToast(errMsg(r)); return; }
      showToast('Διαγράφηκε.');
      await loadExpenses();
    }
  });
}

async function openExpenseForm(row) {
  const activities = await fetchFinancialActivities();
  const today = new Date().toISOString().slice(0, 10);
  openFormModal({
    title: row ? 'Επεξεργασία Εξόδου' : 'Νέο Έξοδο',
    fields: [
      { key: 'activity_id', label: 'Δραστηριότητα', type: 'select', options: [{ value: '', label: '(Καμία)' }].concat(activities.map((a) => ({ value: String(a.id), label: a.name }))) },
      { key: 'entry_date', label: 'Ημ/νία', type: 'date' },
      { key: 'amount', label: 'Ποσό', type: 'number', step: '0.01', min: 0 },
      { key: 'notes', label: 'Σημειώσεις', type: 'textarea' }
    ],
    values: row || { entry_date: today, amount: 0 },
    onSubmit: async (data) => {
      const res = await apiPost('/api/financial/expenses-save', { ...data, id: row ? row.id : 0 });
      if (!res.ok) { showToast(errMsg(res)); return { ok: false }; }
      showToast('Αποθηκεύτηκε.');
      await loadExpenses();
      return { ok: true };
    }
  });
}

// ── Parameters ────────────────────────────────────────────────────────

async function loadParameters() {
  const res = await apiPost('/api/parameters', {});
  if (!res.ok) { showToast(errMsg(res)); return; }
  const d = res.data;
  els.pEmailMode.value = d.email_mode || 'virtual';
  els.pSchoolName.value = d.school_name || '';
  els.pPhotoHidden.value = d.photo_hidden_grace_days || 15;
  els.pPhotoRetention.value = d.photo_retention_days || 180;
  els.paramsHealth.innerHTML = `
    <div class="metric"><strong>SMTP</strong><span>${d.smtp_configured ? 'Ρυθμισμένο' : 'Μη ρυθμισμένο'}</span></div>
    <div class="metric"><strong>Παρουσιολόγιο</strong><span>${d.attendance_ready ? 'Έτοιμο' : 'Μη ενεργό'}</span></div>
  `;

  if (d.attendance_ready) {
    document.getElementById('params-attendance').innerHTML = '';
  } else {
    document.getElementById('params-attendance').innerHTML = `
      <div class="empty">Το παρουσιολόγιο δεν είναι ενεργό. Χωρίς αυτό, δεν μπορείτε να στείλετε email ημερήσιων ενημερώσεων.</div>
      <button class="btn secondary" type="button" id="params-migrate-btn">Ενεργοποίηση αποθήκευσης απουσιών</button>
    `;
    document.getElementById('params-migrate-btn').addEventListener('click', async () => {
      if (!await appConfirm('Απαιτείται πρόσφατο backup της βάσης πριν συνεχίσετε. Τα υπάρχοντα μηνύματα δεν αλλάζουν.', { title: 'Αναβάθμιση βάσης', confirmLabel: 'Έχω backup, συνέχεια' })) return;
      const r = await apiPost('/api/parameters/attendance-migration', { backup_confirmed: '1' });
      if (!r.ok) { showToast(errMsg(r)); return; }
      showToast('Το παρουσιολόγιο ενεργοποιήθηκε.');
      await loadParameters();
    });
  }
}

async function saveParameters(ev) {
  ev.preventDefault();
  const res = await apiPost('/api/parameters/save', {
    email_mode: els.pEmailMode.value,
    school_name: els.pSchoolName.value,
    photo_hidden_grace_days: els.pPhotoHidden.value,
    photo_retention_days: els.pPhotoRetention.value
  });
  if (!res.ok) { showToast(errMsg(res)); return; }
  showToast('Οι παράμετροι αποθηκεύτηκαν.');
}

// ── Email Template ────────────────────────────────────────────────────

async function loadEmailTemplate() {
  const res = await apiPost('/api/email-template', {});
  if (!res.ok) { showToast(errMsg(res)); return; }
  els.etSubject.value = res.data.subject || '';
  els.etHtml.value = res.data.template_html || '';
}

async function saveEmailTemplate(ev) {
  ev.preventDefault();
  const res = await apiPost('/api/email-template/save', { subject: els.etSubject.value, template_html: els.etHtml.value });
  if (!res.ok) { showToast(errMsg(res)); return; }
  showToast('Το πρότυπο αποθηκεύτηκε.');
}

// ── Personal Settings ─────────────────────────────────────────────────

async function loadPersonal() {
  els.ppName.value = (state.user && state.user.name) || '';
  els.ppUsername.value = (state.user && state.user.username) || '';
  els.ppEmail.value = (state.user && state.user.email) || '';
  els.pwCurrent.value = '';
  els.pwNew.value = '';
  els.pwConfirm.value = '';
}

async function savePersonalProfile(ev) {
  ev.preventDefault();
  const res = await apiPost('/api/personal/save', {
    form_mode: 'profile',
    name: els.ppName.value,
    username: els.ppUsername.value,
    email: els.ppEmail.value
  });
  if (!res.ok) { showToast(errMsg(res)); return; }
  showToast('Το προφίλ αποθηκεύτηκε.');
  if (res.data && res.data.user) {
    state.user = { ...state.user, ...res.data.user };
    persistUser();
    els.welcomeTitle.textContent = `Καλωσήρθες, ${state.user.name || state.user.username || ''}`;
  }
}

async function savePersonalPassword(ev) {
  ev.preventDefault();
  const res = await apiPost('/api/personal/save', {
    form_mode: 'password',
    name: els.ppName.value,
    username: els.ppUsername.value,
    email: els.ppEmail.value,
    current_password: els.pwCurrent.value,
    new_password: els.pwNew.value,
    confirm_password: els.pwConfirm.value
  });
  if (!res.ok) { showToast(errMsg(res)); return; }
  showToast('Ο κωδικός άλλαξε.');
  els.pwCurrent.value = '';
  els.pwNew.value = '';
  els.pwConfirm.value = '';
}

// ── Messages: Create ──────────────────────────────────────────────────

async function loadMessagesCreateScreen() {
  const [groupsRes, bothRes, emailRes] = await Promise.all([
    apiPost('/api/messages/my-groups', {}),
    apiPost('/api/activities', { type: 'both', page: 1, page_size: 100 }),
    apiPost('/api/activities', { type: 'email_only', page: 1, page_size: 100 })
  ]);
  const groups = (groupsRes.data && groupsRes.data.rows) || [];
  els.mcGroup.innerHTML = groups.map((g) => `<option value="${g.id}">${escapeHtml(g.name)}</option>`).join('');

  const renderActCheckboxes = (container, rows) => {
    container.innerHTML = (rows || []).map((a) => `
      <label class="checkbox-item"><input type="checkbox" class="act-cb" value="${escapeHtml(a.name)}"><span>${escapeHtml(a.name)}</span></label>
    `).join('') || '<span class="hint">Καμία δραστηριότητα.</span>';
  };
  renderActCheckboxes(els.mcActsBoth, bothRes.data && bothRes.data.rows);
  renderActCheckboxes(els.mcActsEmail, emailRes.data && emailRes.data.rows);

  if (groups.length) await loadMessagesCreateRows();
  else els.mcRows.innerHTML = '<div class="empty">Δεν έχετε διαθέσιμα τμήματα.</div>';
}

function bulkApplyActivities(panel, mode) {
  const container = panel === 'email' ? els.mcActsEmail : els.mcActsBoth;
  const selected = Array.from(container.querySelectorAll('.act-cb:checked')).map((cb) => cb.value);
  if (!selected.length) { showToast('Επιλέξτε τουλάχιστον μία δραστηριότητα.'); return; }

  Array.from(els.mcRows.querySelectorAll('[data-field="activities_txt"]')).forEach((input) => {
    if (mode === 'replace') {
      input.value = selected.join(', ');
      return;
    }
    const existing = input.value.split(',').map((s) => s.trim()).filter(Boolean);
    selected.forEach((name) => {
      if (!existing.includes(name)) existing.push(name);
    });
    input.value = existing.join(', ');
  });
  showToast('Οι δραστηριότητες εφαρμόστηκαν.');
}

const RATING_OPTIONS = [
  { value: '0', label: '-' },
  { value: '1', label: 'Καθόλου' },
  { value: '2', label: 'Μέτρια' },
  { value: '3', label: 'Καλά' },
  { value: '4', label: 'Πολύ Καλά' }
];

async function loadMessagesCreateRows() {
  const groupId = Number(els.mcGroup.value);
  const date = els.mcDate.value;
  if (!groupId || !date) { showToast('Επιλέξτε τμήμα και ημερομηνία.'); return; }
  const res = await apiPost('/api/messages/list-by-group', { group_id: groupId, date });
  if (!res.ok) { showToast(errMsg(res)); return; }
  const rows = res.data.rows || [];
  state.mcAttendanceReady = !!res.data.attendance_ready;

  els.mcRows.innerHTML = rows.map((r) => `
    <article class="card" data-child-id="${r.id}">
      <h4>${escapeHtml(r.first_name)} ${escapeHtml(r.last_name)}</h4>
      <div class="row-grid">
        <label class="field"><span>Πρωινό</span><select data-field="breakfast">${ratingOptionsHtml(r.breakfast)}</select></label>
        <label class="field"><span>Μεσημεριανό</span><select data-field="lunch">${ratingOptionsHtml(r.lunch)}</select></label>
        <label class="field"><span>Διάθεση</span><select data-field="mood">${ratingOptionsHtml(r.mood)}</select></label>
        <label class="field"><span>Ύπνος (λεπτά)</span><input type="number" min="0" data-field="sleep_minutes" value="${Number(r.sleep_minutes || 0)}"></label>
      </div>
      <label class="field checkbox-field"><input type="checkbox" data-field="wc" ${Number(r.wc) ? 'checked' : ''}><span>WC</span></label>
      <label class="field"><span>Δραστηριότητες</span><input type="text" data-field="activities_txt" value="${escapeHtml(r.activities_txt || '')}"></label>
      <label class="field"><span>Σχόλια</span><textarea data-field="comments" rows="2">${escapeHtml(r.comments || '')}</textarea></label>
      ${state.mcAttendanceReady ? `<label class="field checkbox-field"><input type="checkbox" data-attendance="1" ${r.is_absent ? 'checked' : ''}><span>Απών σήμερα</span></label>` : ''}
      <p class="hint">Κατάσταση email: ${escapeHtml(r.email_status || 'pending')}</p>
    </article>
  `).join('') || '<div class="empty">Δεν υπάρχουν παιδιά σε αυτό το τμήμα.</div>';

  Array.from(els.mcRows.querySelectorAll('[data-attendance]')).forEach((cb) => {
    cb.addEventListener('change', async () => {
      const card = cb.closest('[data-child-id]');
      const childId = Number(card.getAttribute('data-child-id'));
      const r = await apiPost('/api/messages/attendance', { group_id: groupId, child_id: childId, date, is_absent: cb.checked ? 1 : 0 });
      if (!r.ok) { showToast(errMsg(r)); cb.checked = !cb.checked; return; }
      showToast(cb.checked ? 'Η απουσία ενημερώθηκε.' : 'Η παρουσία ενημερώθηκε.');
    });
  });

  els.mcPhotoChild.innerHTML = rows.map((r) => `<option value="${r.id}">${escapeHtml(r.first_name)} ${escapeHtml(r.last_name)}</option>`).join('');
  if (rows.length) await loadMcPhotos();
  else els.mcPhotoList.innerHTML = '';
}

function ratingOptionsHtml(current) {
  return RATING_OPTIONS.map((o) => `<option value="${o.value}" ${String(Number(current || 0)) === o.value ? 'selected' : ''}>${escapeHtml(o.label)}</option>`).join('');
}

async function clearMessagesCreateRows() {
  if (!await appConfirm('Θα καθαριστούν οι βαθμολογίες, τα πεδία και οι επιλογές της φόρμας. Οι μη αποθηκευμένες αλλαγές θα χαθούν.\n\nΤα αποθηκευμένα μηνύματα και οι απουσίες διατηρούνται.', { title: 'Καθαρισμός φόρμας;', confirmLabel: 'Καθαρισμός' })) return;
  Array.from(els.mcRows.querySelectorAll('[data-child-id]')).forEach((card) => {
    card.querySelector('[data-field="breakfast"]').value = '0';
    card.querySelector('[data-field="lunch"]').value = '0';
    card.querySelector('[data-field="mood"]').value = '0';
    card.querySelector('[data-field="sleep_minutes"]').value = '0';
    card.querySelector('[data-field="wc"]').checked = false;
    card.querySelector('[data-field="activities_txt"]').value = '';
    card.querySelector('[data-field="comments"]').value = '';
  });
  showToast('Η φόρμα καθαρίστηκε.');
}

// ── Messages Create: Photos ────────────────────────────────────────────

async function loadMcPhotos() {
  if (els.mcPhotoMode.value === 'group') return;

  const groupId = Number(els.mcGroup.value);
  const childId = Number(els.mcPhotoChild.value);
  const date = els.mcDate.value;
  if (!groupId || !childId) { els.mcPhotoList.innerHTML = ''; return; }

  const res = await apiPost('/api/messages/photos/list', {
    group_id: groupId,
    child_id: childId,
    date,
    all_dates: els.mcPhotoAllDates.checked ? 1 : 0
  });
  if (!res.ok) { showToast(errMsg(res)); return; }

  const rows = res.data.rows || [];
  els.mcPhotoList.innerHTML = rows.length ? rows.map((r) => `
    <div class="photo-card" data-photo-id="${r.id}">
      <img src="${escapeHtml(r.url)}" alt="${escapeHtml(r.original_name)}" loading="lazy">
      <div class="photo-meta">
        <span>${escapeHtml(r.original_name)}</span>
        <span>${(Number(r.size_bytes || 0) / 1024).toFixed(0)} KB</span>
      </div>
      <div class="btn-row">
        <button class="btn danger" type="button" data-del="${r.id}">Διαγραφή</button>
      </div>
    </div>
  `).join('') : '<div class="empty">Δεν υπάρχουν φωτογραφίες.</div>';

  bindRowActions(els.mcPhotoList, {
    del: async (id) => {
      if (!await appConfirm('Η φωτογραφία θα διαγραφεί οριστικά, μαζί με τα links της (ακόμη και σε ήδη σταλμένα email).', { title: 'Διαγραφή φωτογραφίας;', confirmLabel: 'Διαγραφή', danger: true })) return;
      const r = await apiPost('/api/messages/photos/purge', { id });
      if (!r.ok) { showToast(errMsg(r)); return; }
      showToast('Διαγράφηκε οριστικά.');
      await loadMcPhotos();
    }
  });
}

async function uploadMcPhotos() {
  const groupId = Number(els.mcGroup.value);
  const date = els.mcDate.value;
  const files = els.mcPhotoFiles.files;
  const isGroupMode = els.mcPhotoMode.value === 'group';

  if (!groupId) { showToast('Επιλέξτε πρώτα τμήμα.'); return; }
  if (!isGroupMode && !Number(els.mcPhotoChild.value)) { showToast('Επιλέξτε παιδί.'); return; }
  if (!files || !files.length) { showToast('Επιλέξτε αρχεία για μεταφόρτωση.'); return; }

  const endpoint = isGroupMode ? '/api/messages/photos/upload-group' : '/api/messages/photos/upload';
  const buildForm = (overwrite) => {
    const fd = new FormData();
    fd.append('group_id', groupId);
    fd.append('date', date);
    if (!isGroupMode) fd.append('child_id', Number(els.mcPhotoChild.value));
    if (overwrite) fd.append('force_overwrite', '1');
    Array.from(files).forEach((f) => fd.append('photos[]', f));
    return fd;
  };

  if (isGroupMode) {
    const groupName = els.mcGroup.selectedOptions[0] ? els.mcGroup.selectedOptions[0].textContent : '';
    if (!await appConfirm(`Η μεταφόρτωση θα προστεθεί σε ΟΛΑ τα ενεργά παιδιά του τμήματος «${groupName}». Συνέχεια;`, { title: 'Μαζική μεταφόρτωση', confirmLabel: 'Μεταφόρτωση' })) return;
  }

  let res = await apiUpload(endpoint, buildForm(false));

  if (!res.ok && res.status === 409 && res.data && res.data.duplicates) {
    const names = res.data.duplicates.join(', ');
    if (!await appConfirm(`Υπάρχουν ήδη φωτογραφίες με ίδιο όνομα: ${names}. Αντικατάσταση;`, { title: 'Διπλότυπα αρχεία', confirmLabel: 'Αντικατάσταση' })) {
      return;
    }
    res = await apiUpload(endpoint, buildForm(true));
  }

  if (!res.ok) { showToast(errMsg(res)); return; }

  if (isGroupMode) {
    showToast(`Μεταφορτώθηκαν ${res.data.saved || 0} αρχεία σε ${res.data.children_count || 0} παιδιά.`);
  } else {
    showToast(`Μεταφορτώθηκαν ${res.data.saved || 0} φωτογραφία/ες.`);
  }
  els.mcPhotoFiles.value = '';
  await loadMcPhotos();
}

async function openMessagesPreview() {
  const cards = Array.from(els.mcRows.querySelectorAll('[data-child-id]'));
  if (!cards.length) { showToast('Φορτώστε πρώτα τμήμα/ημερομηνία.'); return; }
  const options = cards.map((card) => {
    const name = card.querySelector('h4').textContent;
    return { value: card.getAttribute('data-child-id'), label: name };
  });

  els.modalTitle.textContent = 'Προεπισκόπηση Email';
  els.modalForm.innerHTML = `
    <label class="field"><span>Παιδί</span><select id="mc-preview-child">${options.map((o) => `<option value="${o.value}">${escapeHtml(o.label)}</option>`).join('')}</select></label>
    <div id="mc-preview-body" class="email-preview-body">Φόρτωση...</div>
  `;
  els.modalOverlay.classList.remove('hidden');
  els.modalForm.onsubmit = (ev) => ev.preventDefault();

  const select = document.getElementById('mc-preview-child');
  const body = document.getElementById('mc-preview-body');

  const loadPreview = async () => {
    body.innerHTML = 'Φόρτωση...';
    const res = await apiPost('/api/messages/email-preview', {
      child_id: Number(select.value),
      group_id: Number(els.mcGroup.value),
      date: els.mcDate.value
    });
    if (!res.ok) { body.innerHTML = `<div class="empty">${escapeHtml(errMsg(res))}</div>`; return; }
    body.innerHTML = res.data.html || '<div class="empty">Δεν υπάρχει περιεχόμενο.</div>';
  };
  select.addEventListener('change', loadPreview);
  await loadPreview();
}

function collectMessagesCreateRows() {
  return Array.from(els.mcRows.querySelectorAll('[data-child-id]')).map((card) => ({
    child_id: Number(card.getAttribute('data-child-id')),
    breakfast: card.querySelector('[data-field="breakfast"]').value,
    lunch: card.querySelector('[data-field="lunch"]').value,
    mood: card.querySelector('[data-field="mood"]').value,
    sleep_minutes: card.querySelector('[data-field="sleep_minutes"]').value,
    wc: card.querySelector('[data-field="wc"]').checked ? 1 : 0,
    activities_txt: card.querySelector('[data-field="activities_txt"]').value,
    comments: card.querySelector('[data-field="comments"]').value
  }));
}

async function saveMessagesCreateRows() {
  const groupId = Number(els.mcGroup.value);
  const date = els.mcDate.value;
  const rows = collectMessagesCreateRows();
  if (!rows.length) { showToast('Δεν υπάρχουν εγγραφές για αποθήκευση.'); return; }
  const res = await apiPost('/api/messages/save', { group_id: groupId, date, rows });
  if (!res.ok) { showToast(errMsg(res)); return; }
  showToast(`Αποθηκεύτηκαν ${res.data.saved || 0} εγγραφές.`);
}

async function sendMessagesCreateEmails() {
  const groupId = Number(els.mcGroup.value);
  const date = els.mcDate.value;
  if (!state.mcAttendanceReady) {
    showToast('Η αποστολή email απαιτεί ενεργό παρουσιολόγιο (Παράμετροι).');
    return;
  }
  const formattedDate = date.split('-').reverse().join('/');
  if (!await appConfirm(`Να αποσταλούν email για ${formattedDate}; Τα παιδιά που έχουν αποθηκευμένη απουσία στη βάση θα παραλειφθούν.`, { title: 'Αποστολή ημερήσιων ενημερώσεων', confirmLabel: 'Αποστολή Email' })) return;

  // Auto-save πρώτα (όπως το web app), αλλιώς παιδιά που δεν αποθηκεύτηκαν ρητά
  // δεν έχουν γραμμή στο messages και δεν στέλνεται email γι' αυτά.
  const rows = collectMessagesCreateRows();
  const saveRes = await apiPost('/api/messages/save', { group_id: groupId, date, rows });
  if (!saveRes.ok) { showToast(errMsg(saveRes)); return; }

  const res = await apiPost('/api/messages/send-emails', { group_id: groupId, date });
  if (!res.ok) { showToast(errMsg(res)); return; }
  showToast(`Στάλθηκαν: ${res.data.sent || 0}, Απέτυχαν: ${res.data.failed || 0}, Παραλείφθηκαν: ${res.data.skipped || 0}.`);
  await loadMessagesCreateRows();
}

// ── Messages: List ────────────────────────────────────────────────────

async function loadMessagesListScreen() {
  const res = await apiPost('/api/messages/my-groups', {});
  const groups = (res.data && res.data.rows) || [];
  els.mlGroup.innerHTML = '<option value="">Όλα</option>' + groups.map((g) => `<option value="${g.id}">${escapeHtml(g.name)}</option>`).join('');
  await loadMessagesList();
}

async function loadMessagesList() {
  const res = await apiPost('/api/messages/search', { group_id: els.mlGroup.value || 0, from: els.mlFrom.value, to: els.mlTo.value });
  if (!res.ok) { showToast(errMsg(res)); return; }
  const rows = res.data.rows || [];
  renderDataTable(els.mlTable, [
    { label: '', render: (r) => `<input type="checkbox" class="ml-select" value="${r.id}">` },
    { label: 'Ημ/νία', render: (r) => formatDate(r.msg_date) },
    { label: 'Παιδί', render: (r) => `${escapeHtml(r.first_name)} ${escapeHtml(r.last_name)}` },
    { key: 'group_name', label: 'Τμήμα' },
    { label: 'Κατάσταση', render: (r) => escapeHtml(r.email_status || '-') }
  ], rows, {
    actions: (r) => `<button class="btn-icon" data-del="${r.id}">🗑️</button>`
  });
  bindRowActions(els.mlTable, {
    del: async (id) => {
      if (!await appConfirm('Το μήνυμα θα διαγραφεί οριστικά.', { title: 'Διαγραφή μηνύματος;', confirmLabel: 'Διαγραφή', danger: true })) return;
      const r = await apiPost('/api/messages/delete', { id });
      if (!r.ok) { showToast(errMsg(r)); return; }
      showToast('Διαγράφηκε.');
      await loadMessagesList();
    }
  });

  const updateBulkBar = () => {
    const selected = Array.from(els.mlTable.querySelectorAll('.ml-select:checked'));
    document.getElementById('ml-bulk-bar').classList.toggle('hidden', selected.length === 0);
    document.getElementById('ml-bulk-count').textContent = selected.length ? `${selected.length} επιλεγμένα` : '';
  };
  Array.from(els.mlTable.querySelectorAll('.ml-select')).forEach((cb) => cb.addEventListener('change', updateBulkBar));
  updateBulkBar();
}

async function messagesListBulkDelete() {
  const ids = Array.from(els.mlTable.querySelectorAll('.ml-select:checked')).map((cb) => cb.value);
  if (!ids.length) return;
  if (!await appConfirm(`Θα διαγραφούν οριστικά ${ids.length} μηνύματα.`, { title: 'Διαγραφή επιλεγμένων;', confirmLabel: 'Διαγραφή', danger: true })) return;
  const res = await apiPost('/api/messages/deleteBulk', { ids: ids.join(',') });
  if (!res.ok) { showToast(errMsg(res)); return; }
  showToast(`Διαγράφηκαν ${res.data.deleted || 0} μηνύματα.`);
  await loadMessagesList();
}

// ── Free Email ────────────────────────────────────────────────────────

async function loadFreeEmailScreen() {
  const res = await apiPost('/api/messages/my-groups', {});
  const groups = (res.data && res.data.rows) || [];
  els.fe2Group.innerHTML = groups.map((g) => `<option value="${g.id}">${escapeHtml(g.name)}</option>`).join('');
  els.fe2SendAllWrap.classList.toggle('hidden', state.user.role !== 'admin');
  els.fe2SendAll.checked = false;
  els.fe2Group.disabled = false;
}

async function sendFreeEmail(ev) {
  ev.preventDefault();
  const payload = {
    subject: els.fe2Subject.value,
    body: els.fe2Body.value
  };
  if (els.fe2SendAll.checked && state.user.role === 'admin') {
    payload.send_all = 1;
  } else {
    payload.group_id = Number(els.fe2Group.value);
  }
  const res = await apiPost('/api/messages/send-free-email', payload);
  if (!res.ok) { showToast(errMsg(res)); return; }
  showToast('Το email στάλθηκε.');
  els.fe2Subject.value = '';
  els.fe2Body.value = '';
}

// ── Inbox: parent compose ────────────────────────────────────────────

async function openNewInboxMessageForm() {
  const res = await apiPost('/api/inbox/children', {});
  const children = (res.data && res.data.children) || [];
  if (!children.length) {
    showToast('Δεν βρέθηκαν συνδεδεμένα παιδιά/τμήματα.');
    return;
  }
  openFormModal({
    title: 'Νέο Μήνυμα',
    fields: [
      {
        key: 'child_group', label: 'Παιδί / Τμήμα', type: 'select',
        options: children.map((c) => ({ value: `${c.child_id}:${c.group_id}`, label: `${c.first_name} ${c.last_name} — ${c.group_name}` }))
      },
      { key: 'subject', label: 'Θέμα' },
      { key: 'body', label: 'Μήνυμα', type: 'textarea', required: true }
    ],
    values: {},
    submitLabel: 'Αποστολή',
    showCancel: true,
    onSubmit: async (data) => {
      const [childId, groupId] = String(data.child_group || '').split(':').map(Number);
      const res = await apiPost('/api/inbox/send', { child_id: childId, group_id: groupId, subject: data.subject, body: data.body });
      if (!res.ok) { showToast(errMsg(res)); return { ok: false }; }
      showToast('Το μήνυμα στάλθηκε.');
      await loadThreads();
      return { ok: true };
    }
  });
}
