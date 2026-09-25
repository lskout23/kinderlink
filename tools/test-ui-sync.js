#!/usr/bin/env node
'use strict';

/*
 * Offline UI regression tests. Run with Node, or Code.exe with
 * ELECTRON_RUN_AS_NODE=1. Only built-in modules; no PHP execution, configuration,
 * database, network, browser, packages, or filesystem writes. Paths use __dirname.
 * Functions are extracted from the current views, never copied implementations.
 * Inline-script checks substitute PHP output; they do NOT validate PHP rendering.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const viewRoot = path.resolve(__dirname, '..', 'views');
const tests = [];
const test = (name, run) => tests.push({ name, run });
const relative = file => path.relative(viewRoot, file).split(path.sep).join('/');
const plain = value => JSON.parse(JSON.stringify(value));
const tick = () => new Promise(resolve => setImmediate(resolve));
function deferred() {
  let resolve, reject;
  const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
  return { promise, resolve, reject };
}
function walk(dir) {
  return fs.readdirSync(dir, { withFileTypes: true }).sort((a, b) => a.name.localeCompare(b.name))
    .flatMap(entry => entry.isDirectory() ? walk(path.join(dir, entry.name)) : [path.join(dir, entry.name)]);
}

// Mask PHP BEFORE scanning HTML: a PHP expression can itself contain '>' or HTML.
// Keep newlines for useful diagnostics. Unknown output forms fail closed rather
// than being silently erased or repeatedly replaced until compilation succeeds.
function inlineScripts(file) {
  const php = [];
  const html = fs.readFileSync(file, 'utf8').replace(/<\?(?:php\b|=)[\s\S]*?\?>/g, block => {
    const token = `__UI_PHP_${php.length}__`;
    php.push(block);
    return token + '\n'.repeat((block.match(/\n/g) || []).length);
  });
  const scripts = [];
  const pattern = /<script\b((?:"[^"]*"|'[^']*'|[^'">])*)>([\s\S]*?)<\/script\s*>/gi;
  let match;
  while ((match = pattern.exec(html))) {
    const attributes = match[1];
    const external = /\bsrc\s*=/i.test(attributes);
    const type = /\btype\s*=\s*["']([^"']*)["']/i.exec(attributes);
    assert.ok(!type || /^(?:module|(?:text|application)\/(?:java|ecma)script)$/i.test(type[1]),
      `Unsupported script type in ${relative(file)}: ${type && type[1]}`);
    const source = match[2].replace(/__UI_PHP_(\d+)__/g, (token, index, offset, body) => {
      const block = php[Number(index)];
      if (/^<\?php\s*(?:if\s*\([\s\S]*\)\s*:|else\s*:|endif\s*;)\s*\?>$/.test(block)) return '';
      assert.match(block, /^<\?=/, `Unsupported PHP statement in ${relative(file)}: ${block}`);
      const before = body[offset - 1];
      const after = body.slice(offset + token.length).replace(/^\n*/, '')[0];
      if ((before === "'" || before === '"') && after === before) return 'PHP_VALUE';
      if (/\bjson_encode\s*\(/.test(block)) {
        if (/json_encode\s*\(\s*\(string\)/.test(block)) return '""';
        if (/\$parentOptions\b|\barray_reduce\s*\(/.test(block)) return '[]';
      }
      if (/\?\s*'true'\s*:\s*'false'/.test(block)) return 'false';
      if (/\(int\)/.test(block)) return '0';
      throw new Error(`Unclassified PHP output in ${relative(file)}: ${block}`);
    });
    scripts.push({ source, external, line: html.slice(0, match.index).split('\n').length });
  }
  const starts = (html.match(/<script\b/gi) || []).length;
  assert.equal(scripts.length, starts, `Unclosed/unrecognized script in ${relative(file)}`);
  return scripts;
}

const scriptCache = new Map();
function scriptsFor(file) {
  if (!scriptCache.has(file)) scriptCache.set(file, inlineScripts(path.join(viewRoot, file)));
  return scriptCache.get(file);
}

// Restrict to a named top-level declaration and the next top-level function.
// Then try closing-brace boundaries from the end. Compiling as an expression
// excludes subsequent declarations/startup statements and handles nested braces,
// strings, regexes and comments without a fragile brace-counting lexer.
function extract(file, name) {
  assert.match(name, /^[A-Za-z_$][\w$]*$/);
  const found = [];
  for (const { source } of scriptsFor(file)) {
    const start = new RegExp(`^(?:async\\s+)?function\\s+${name}\\s*\\(`, 'm').exec(source);
    if (!start) continue;
    const tail = source.slice(start.index);
    const next = /\n(?:async\s+)?function\s+[A-Za-z_$][\w$]*\s*\(/.exec(tail);
    const region = next ? tail.slice(0, next.index) : tail;
    let extracted;
    for (let end = region.lastIndexOf('}'); end >= 0; end = region.lastIndexOf('}', end - 1)) {
      const candidate = region.slice(0, end + 1);
      try {
        new vm.Script(`(${candidate})`, { filename: `${file}:${name}` });
        extracted = candidate;
        break;
      } catch (error) {
        if (!(error instanceof SyntaxError)) throw error;
      }
    }
    assert.ok(extracted, `Cannot extract intact function ${file}:${name}`);
    found.push(extracted);
  }
  assert.equal(found.length, 1, `Expected one declaration of ${file}:${name}`);
  return found[0];
}
function context(file, names, globals) {
  // No require/process/fetch/real XHR in these contexts. All I/O is mocked.
  const ctx = vm.createContext(globals, { codeGeneration: { strings: false, wasm: false } });
  new vm.Script(names.map(name => extract(file, name)).join('\n'), { filename: file })
    .runInContext(ctx, { timeout: 1000 });
  return ctx;
}
function element(overrides = {}) {
  const attributes = {};
  return Object.assign({
    value: '', checked: false, disabled: false, textContent: '', innerHTML: '', style: {},
    setAttribute: (key, value) => { attributes[key] = value; },
    getAttribute: key => attributes[key]
  }, overrides);
}
function documentFor(elements, toggles = []) {
  return {
    getElementById(id) { assert.ok(elements[id], `Unexpected DOM id: ${id}`); return elements[id]; },
    querySelectorAll(selector) { assert.equal(selector, '.absent-today-toggle'); return toggles; }
  };
}

const composeFile = 'messages/create.php';
function attendanceFixture() {
  const status = element();
  const row = element({ querySelector(selector) { assert.equal(selector, '.attendance-email-status'); return status; } });
  const checkbox = element({ checked: true, closest(selector) { assert.equal(selector, 'tr'); return row; } });
  const elements = {};
  for (const id of ['sel-group', 'sel-date', 'btn-load-children', 'btn-save-all', 'btn-clear-all',
    'btn-preview-email', 'btn-send-email', 'attendance-notice', 'absent-today-badge']) elements[id] = element();
  elements['sel-group'].value = '7';
  elements['sel-date'].value = '2026-09-25';
  const requests = [], toasts = [];
  const c = context(composeFile, ['attendanceEmailStatus', 'toggleAbsentToday', 'composeScopeMatches',
    'updateComposeControls', 'getAbsentTodayChildIds', 'updateAbsentTodayBadge'], {
    childrenData: [{ id: 42, is_absent: false, email_status: 'pending' }],
    attendanceReady: true, attendancePending: false, messageBusy: false,
    loadedGroup: '7', loadedDate: '2026-09-25', CSRF: 'test-csrf',
    document: documentFor(elements, [checkbox]),
    showToast: (...args) => toasts.push(args),
    apiPost: (url, data, callback) => requests.push({ url, data, callback })
  });
  const reply = (response = {}, error = null) => requests[0].callback(error,
    Object.assign({ success: true, date: '2026-09-25', child_id: 42, is_absent: checkbox.checked }, response));
  return { c, elements, checkbox, row, status, requests, toasts, reply };
}

for (const [state, label] of [['pending', 'Αναμονή'], ['sent', 'Εστάλη'], ['failed', 'Αποτυχία'], ['virtual', 'Εικονικό']]) {
  for (const absent of [false, true]) test(`attendance status: ${state}, absent=${absent}`, () => {
    const { c } = attendanceFixture();
    const html = c.attendanceEmailStatus(state, absent);
    assert.ok(html.includes(`status-badge status-${state}`));
    if (absent && state === 'pending') {
      assert.ok(html.includes('Απών — δεν θα σταλεί'));
      assert.ok(!html.includes(label));
    } else {
      assert.ok(html.includes(label));
      assert.equal(html.includes('Απών — παράλειψη νέας αποστολής'), absent);
      assert.ok(!html.includes('δεν θα σταλεί'));
    }
  });
}
for (const state of ['', 'unknown', 'toString', '__proto__', '<img onerror=alert(1)>']) {
  test(`attendance status rejects unknown/inherited markup: ${state || '(empty)'}`, () => {
    const { c } = attendanceFixture();
    assert.equal(c.attendanceEmailStatus(state, false), '');
    assert.equal(c.attendanceEmailStatus(state, true), '<span style="display:block;color:#9a5b00;font-size:11px;">Απών — παράλειψη νέας αποστολής</span>');
  });
}
for (const absent of [true, false]) test(`toggle absence: ${absent ? 'save' : 'undo'} success and toast`, () => {
  const f = attendanceFixture();
  f.c.childrenData[0].is_absent = !absent;
  f.checkbox.checked = absent;
  f.c.toggleAbsentToday(f.checkbox, '42');
  assert.equal(f.c.attendancePending, true);
  assert.equal(f.elements['btn-send-email'].disabled, true);
  assert.equal(f.checkbox.disabled, true);
  assert.equal(f.requests.length, 1);
  assert.equal(f.requests[0].url, '/api/messages/attendance');
  assert.deepEqual(plain(f.requests[0].data), {
    group_id: '7', child_id: '42', date: '2026-09-25', is_absent: absent ? '1' : '0', _token: 'test-csrf'
  });
  f.reply();
  assert.equal(f.c.childrenData[0].is_absent, absent);
  assert.equal(f.c.attendancePending, false);
  assert.equal(f.c.attendanceReady, true);
  assert.equal(f.elements['btn-send-email'].disabled, false);
  assert.equal(f.checkbox.disabled, false);
  assert.equal(f.row.style.opacity, absent ? '0.5' : '');
  assert.equal(f.row.style.background, absent ? '#fff7ed' : '');
  assert.equal(f.status.innerHTML, f.c.attendanceEmailStatus('pending', absent));
  assert.ok(f.elements['attendance-notice'].textContent.includes('25/09/2026'));
  assert.equal(f.elements['absent-today-badge'].style.display, absent ? 'inline-flex' : 'none');
  assert.deepEqual(f.toasts, [[absent ? 'Η απουσία αποθηκεύτηκε.' : 'Η αναίρεση της απουσίας αποθηκεύτηκε.', 'success']]);
});
const attendanceGuards = {
  'not ready': f => { f.c.attendanceReady = false; },
  'pending attendance': f => { f.c.attendancePending = true; },
  'message busy': f => { f.c.messageBusy = true; },
  'changed group': f => { f.elements['sel-group'].value = '8'; },
  'changed date': f => { f.elements['sel-date'].value = '2026-09-26'; },
  'no loaded scope': f => { f.c.loadedGroup = ''; }
};
for (const [name, setup] of Object.entries(attendanceGuards)) test(`toggle guard: ${name}`, () => {
  const f = attendanceFixture();
  setup(f);
  f.c.toggleAbsentToday(f.checkbox, 42);
  assert.equal(f.checkbox.checked, false);
  assert.equal(f.requests.length, 0);
  assert.equal(f.c.childrenData[0].is_absent, false);
  assert.equal(f.toasts.length, 1);
  assert.equal(f.toasts[0][1], 'warning');
});
test('toggle guard: unknown child is a no-op', () => {
  const f = attendanceFixture();
  f.c.toggleAbsentToday(f.checkbox, 999);
  assert.equal(f.requests.length, 0);
  assert.equal(f.toasts.length, 0);
  assert.equal(f.c.attendancePending, false);
});
test('toggle guard: second click while request pending cannot send again', () => {
  const f = attendanceFixture();
  f.c.toggleAbsentToday(f.checkbox, 42);
  f.c.toggleAbsentToday(f.checkbox, 42);
  assert.equal(f.requests.length, 1);
  assert.equal(f.checkbox.checked, false);
  assert.equal(f.c.attendancePending, true);
  f.reply({ is_absent: true });
  assert.equal(f.c.attendancePending, false);
});
for (const [name, err, resp] of [
  ['transport error', new Error('offline'), null], ['missing response', null, null],
  ['API error', null, { success: false, error: 'denied' }], ['nonboolean success', null, { success: 1 }],
  ['wrong date', null, { date: '2026-09-26' }], ['wrong child', null, { child_id: 99 }],
  ['wrong absence', null, { is_absent: false }], ['nonboolean absence', null, { is_absent: 1 }]
]) test(`toggle rejects ${name} and disables sending until reload`, () => {
  const f = attendanceFixture();
  f.c.toggleAbsentToday(f.checkbox, 42);
  if (resp === null) f.requests[0].callback(err, null);
  else f.reply(resp, err);
  assert.equal(f.c.attendancePending, false);
  assert.equal(f.c.attendanceReady, false);
  assert.equal(f.checkbox.checked, false);
  assert.equal(f.c.childrenData[0].is_absent, false);
  assert.equal(f.elements['btn-send-email'].disabled, true);
  assert.equal(f.checkbox.disabled, true);
  assert.equal(f.elements['sel-group'].disabled, false);
  assert.equal(f.toasts[0][1], 'danger');
  assert.ok(f.elements['attendance-notice'].textContent.length > 0);
});

const deletions = [
  ['activities/index.php', 'deleteActivity', 'activityDeletePending', '/api/activities/delete', 'loadActivities', ['sport']],
  ['groups/index.php', 'deleteGroup', 'groupDeletePending', '/api/groups/delete', 'loadGroups', [3]],
  ['financial/expenses.php', 'deleteExp', 'expenseDeletePending', '/api/financial/expenses-delete', 'loadExpenses', []],
  ['financial/income.php', 'deleteIncome', 'incomeDeletePending', '/api/financial/income-delete', 'loadIncomeForChild', []],
  ['financial/setup-activities.php', 'deleteFa', 'financialActivityDeletePending', '/api/financial/activities/delete', 'loadFa', []],
  ['children/index.php', 'deleteChild', 'childrenActionPending', '/api/children/delete', 'loadChildren', [3]],
  ['users/index.php', 'deleteUser', 'userActionPending', '/api/users/delete', 'loadUsers', [3]],
  ['messages/list.php', 'deleteMsg', 'messageDeletePending', '/api/messages/delete', 'searchMessages', []],
  ['inbox/index.php', 'deleteMessage', 'inboxDeletePending', '/api/inbox/delete-message', 'loadThreadList', []]
];
function deleteFixture(spec) {
  const [file, name, lock, url, reload, reloadArgs] = spec;
  const confirmation = deferred(), requests = [], toasts = [], reloads = [], opened = [];
  let confirms = 0;
  const elements = { 'conversation-box': element(), 'conversation-empty': element() };
  const c = context(file, [name], {
    [lock]: false, CSRF_TOKEN: 'test-csrf', groupCurrentPage: 3, childCurrentPage: 3, usersCurrentPage: 3,
    currentThreadId: 17, document: documentFor(elements), openThread: id => opened.push(id),
    confirmDelete: () => { confirms++; return confirmation.promise; },
    apiPost: (url, data, callback) => requests.push({ url, data, callback }),
    showToast: (...args) => toasts.push(args), [reload]: (...args) => reloads.push(args)
  });
  const invoke = () => c[name](42, 'sport');
  return { c, name, lock, url, reload, reloadArgs, confirmation, requests, toasts, reloads, opened, elements,
    invoke, confirms: () => confirms };
}
for (const spec of deletions) {
  const name = spec[1];
  test(`${name}: pre-existing pending lock suppresses dialog and request`, async () => {
    const f = deleteFixture(spec);
    f.c[f.lock] = true;
    await f.invoke();
    assert.equal(f.confirms(), 0);
    assert.equal(f.requests.length, 0);
    assert.equal(f.c[f.lock], true);
  });
  test(`${name}: cancel releases lock, suppresses duplicates, allows new attempt`, async () => {
    const f = deleteFixture(spec), first = f.invoke();
    assert.equal(f.c[f.lock], true);
    await f.invoke();
    assert.equal(f.confirms(), 1);
    f.confirmation.resolve(false);
    await first;
    assert.equal(f.c[f.lock], false);
    assert.equal(f.requests.length, 0);
    assert.equal(f.toasts.length, 0);
    await f.invoke();
    assert.equal(f.confirms(), 2);
  });
  test(`${name}: approval holds lock through callback and ignores duplicate callback`, async () => {
    const f = deleteFixture(spec), first = f.invoke();
    await f.invoke();
    assert.equal(f.confirms(), 1);
    f.confirmation.resolve(true);
    await tick();
    assert.equal(f.c[f.lock], true);
    assert.equal(f.requests.length, 1);
    assert.equal(f.requests[0].url, f.url);
    assert.deepEqual(plain(f.requests[0].data), name === 'deleteMessage' ? { msg_id: 42 } : { id: 42, _token: 'test-csrf' });
    await f.invoke();
    assert.equal(f.requests.length, 1);
    assert.equal(f.confirms(), 1);
    f.requests[0].callback(null, { success: true });
    f.requests[0].callback(new Error('late duplicate'), { error: 'late duplicate' });
    await first;
    assert.equal(f.c[f.lock], false);
    assert.deepEqual(f.reloads, [f.reloadArgs]);
    assert.equal(f.toasts.length, 1);
    assert.equal(f.toasts[0][1], 'success');
    f.requests[0].callback(null, { success: true });
    await tick();
    assert.equal(f.reloads.length, 1);
    assert.equal(f.toasts.length, 1);
    if (name === 'deleteMessage') assert.deepEqual(f.opened, [17]);
    const retry = f.invoke();
    await tick();
    assert.equal(f.requests.length, 2);
    f.requests[1].callback(null, { success: true });
    await retry;
    assert.equal(f.c[f.lock], false);
  });
  for (const [kind, err, response] of [
    ['callback error', new Error('offline'), { success: true }],
    ['missing response', null, null], ['response error', null, { error: 'denied' }]
  ]) test(`${name}: ${kind} releases lock without refresh`, async () => {
    const f = deleteFixture(spec), first = f.invoke();
    f.confirmation.resolve(true);
    await tick();
    f.requests[0].callback(err, response);
    await first;
    assert.equal(f.c[f.lock], false);
    assert.equal(f.reloads.length, 0);
    assert.equal(f.toasts.length, 1);
    assert.equal(f.toasts[0][1], 'danger');
  });
  for (const kind of ['confirmation rejection', 'confirmation throw', 'api throw', 'refresh throw']) {
    test(`${name}: ${kind} is caught and releases lock`, async () => {
      const f = deleteFixture(spec);
      const fail = () => { throw new Error('mock failure'); };
      if (kind === 'confirmation throw') f.c.confirmDelete = fail;
      if (kind === 'api throw') f.c.apiPost = fail;
      if (kind === 'refresh throw') f.c[f.reload] = fail;
      const first = f.invoke();
      if (kind === 'confirmation rejection') f.confirmation.reject(new Error('dialog unavailable'));
      else f.confirmation.resolve(true);
      if (kind === 'refresh throw') { await tick(); f.requests[0].callback(null, { success: true }); }
      await first;
      assert.equal(f.c[f.lock], false);
      // Inbox reports successful deletion before refreshing the thread list.
      assert.equal(f.toasts.length, name === 'deleteMessage' && kind === 'refresh throw' ? 2 : 1);
      assert.equal(f.toasts[f.toasts.length - 1][1], 'danger');
      assert.equal(f.reloads.length, 0);
    });
  }
}
for (const changed of [false, true]) test(`inbox deleted thread respects current selection: changed=${changed}`, async () => {
  const f = deleteFixture(deletions[8]), first = f.invoke();
  f.confirmation.resolve(true);
  await tick();
  if (changed) f.c.currentThreadId = 18;
  f.requests[0].callback(null, { thread_deleted: true });
  await first;
  assert.equal(f.c.currentThreadId, changed ? 18 : null);
  assert.equal(f.elements['conversation-box'].style.display, changed ? undefined : 'none');
  assert.equal(f.elements['conversation-empty'].style.display, changed ? undefined : 'block');
  assert.equal(f.opened.length, 0);
  assert.equal(f.reloads.length, 1);
  assert.equal(f.c[f.lock], false);
});

function photoFixture() {
  const elements = {};
  for (const id of ['sel-group', 'sel-date', 'photo-child', 'photo-all-dates', 'photo-show-hidden',
    'photo-files', 'photo-upload-mode', 'btn-photo-upload-main', 'photo-dup-modal',
    'photo-dup-modal-summary', 'photo-dup-modal-list', 'photo-dup-modal-remember']) elements[id] = element();
  elements['sel-group'].value = '7';
  elements['sel-date'].value = '2026-09-25';
  elements['photo-child'].value = '42';
  elements['photo-upload-mode'].value = 'group';
  elements['btn-photo-upload-main'].textContent = 'Upload';
  const files = [{ name: 'one.jpg' }, { name: 'two.jpg' }];
  elements['photo-files'].files = files.slice();
  elements['photo-files'].value = 'mock-files';
  const selected = [42, 43], requests = [], toasts = [], feedback = [], loads = [], alerts = [];
  const confirmation = deferred();
  let confirms = 0, throwSend = false;
  class MockFormData {
    constructor() { this.entries = []; }
    append(key, value) { this.entries.push([key, value]); }
    getAll(key) { return this.entries.filter(pair => pair[0] === key).map(pair => pair[1]); }
  }
  class MockXHR {
    constructor() { this.headers = {}; }
    open(method, url, async) { Object.assign(this, { method, url, async }); }
    setRequestHeader(key, value) { this.headers[key] = value; }
    send(form) { this.form = form; requests.push(this); if (throwSend) throw new Error('send failed'); }
  }
  const c = context(composeFile, ['sendPhotosUploadGroup', 'capturePhotoScope', 'photoScopeMatches',
    'setUploadBtnLoading', 'openPhotoDupDecisionModal', 'closePhotoDupDecisionModal'], {
    document: documentFor(elements), FormData: MockFormData, XMLHttpRequest: MockXHR,
    CSRF: 'test-csrf', APP_BASE: '/test', childrenLoadVersion: 1,
    childrenData: [{ id: 42 }, { id: 43 }], photoGroupUploadOperation: null, photoActionBusy: false,
    photoDupDecisionResolver: null, photoDupRememberedAction: '',
    getSelectedGroupChildIds: () => selected.slice(),
    appConfirm: () => { confirms++; return confirmation.promise; },
    showToast: (...args) => toasts.push(args), hidePhotoUploadFeedback: () => {},
    showPhotoUploadFeedback: (...args) => feedback.push(args),
    showPhotoStorageAlert: message => alerts.push(message), loadPhotoList: value => loads.push(value),
    updatePhotoDupRememberBanner: () => {}, esc: value => String(value).replace(/</g, '&lt;')
  });
  const invoke = (selectedOnly = true) => c.sendPhotosUploadGroup(false, selectedOnly, selected.slice());
  const approve = async (selectedOnly = true) => { const done = invoke(selectedOnly); confirmation.resolve(true); await done; };
  const respond = (value, index = requests.length - 1) => {
    requests[index].responseText = typeof value === 'string' ? value : JSON.stringify(value);
    requests[index].onload();
  };
  const released = () => {
    assert.equal(c.photoGroupUploadOperation, null);
    assert.equal(elements['btn-photo-upload-main'].disabled, false);
    assert.equal(elements['btn-photo-upload-main'].textContent, 'Upload');
  };
  return { c, elements, files, selected, requests, toasts, feedback, loads, alerts, confirmation,
    invoke, approve, respond, released, confirms: () => confirms, failSend: () => { throwSend = true; } };
}
const photoGuards = {
  'active operation': f => { f.c.photoGroupUploadOperation = {}; },
  'other photo action': f => { f.c.photoActionBusy = true; },
  'disabled button': f => { f.elements['btn-photo-upload-main'].disabled = true; },
  'no group': f => { f.elements['sel-group'].value = ''; },
  'no files': f => { f.elements['photo-files'].files = []; },
  'no children': f => { f.c.childrenData = []; }
};
for (const [name, setup] of Object.entries(photoGuards)) test(`group upload guard: ${name}`, async () => {
  const f = photoFixture();
  setup(f);
  const originalOperation = f.c.photoGroupUploadOperation;
  await f.invoke(false);
  assert.equal(f.requests.length, 0);
  assert.equal(f.confirms(), 0);
  assert.equal(f.c.photoGroupUploadOperation, originalOperation);
});
for (const reject of [false, true]) test(`group upload: confirmation ${reject ? 'reject' : 'cancel'} unlocks`, async () => {
  const f = photoFixture(), first = f.invoke();
  assert.ok(f.c.photoGroupUploadOperation);
  await f.invoke();
  assert.equal(f.confirms(), 1);
  if (reject) f.confirmation.reject(new Error('dialog closed'));
  else f.confirmation.resolve(false);
  await first;
  f.released();
  assert.equal(f.requests.length, 0);
});
const scopeChanges = {
  group: f => { f.elements['sel-group'].value = '8'; },
  date: f => { f.elements['sel-date'].value = '2026-09-26'; },
  child: f => { f.elements['photo-child'].value = '99'; },
  'all dates': f => { f.elements['photo-all-dates'].checked = true; },
  hidden: f => { f.elements['photo-show-hidden'].checked = true; },
  version: f => { f.c.childrenLoadVersion++; },
  mode: f => { f.elements['photo-upload-mode'].value = 'single'; },
  targets: f => { f.selected.push(44); },
  'file count': f => { f.elements['photo-files'].files.pop(); },
  'file identity': f => { f.elements['photo-files'].files[0] = { name: 'one.jpg' }; }
};
for (const [name, change] of Object.entries(scopeChanges)) {
  test(`group upload: changed ${name} during confirmation cancels`, async () => {
    const f = photoFixture(), first = f.invoke();
    change(f);
    f.confirmation.resolve(true);
    await first;
    f.released();
    assert.equal(f.requests.length, 0);
  });
  test(`group upload: changed ${name} during duplicate decision prevents retry`, async () => {
    const f = photoFixture();
    await f.approve();
    f.respond({ error_code: 'PHOTO_NAME_EXISTS_GROUP', duplicates_count: 1 });
    change(f);
    f.c.closePhotoDupDecisionModal('replace');
    f.released();
    assert.equal(f.requests.length, 1);
  });
}
for (const action of ['replace', 'skip']) test(`group upload: duplicate ${action} reuses operation/files and suppresses duplicate clicks`, async () => {
  const f = photoFixture();
  await f.approve();
  const operation = f.c.photoGroupUploadOperation;
  assert.ok(operation);
  const first = f.requests[0];
  assert.equal(first.method, 'POST');
  assert.equal(first.url, '/test/api/messages/photos/upload-group');
  assert.equal(first.async, true);
  assert.equal(first.headers['X-CSRF-TOKEN'], 'test-csrf');
  assert.deepEqual(first.form.getAll('group_id'), ['7']);
  assert.deepEqual(first.form.getAll('date'), ['2026-09-25']);
  assert.deepEqual(first.form.getAll('_token'), ['test-csrf']);
  assert.deepEqual(first.form.getAll('child_ids[]'), ['42', '43']);
  assert.deepEqual(first.form.getAll('photos[]'), f.files);
  assert.equal(first.form.getAll('photos[]')[0], f.files[0]);
  assert.equal(f.elements['btn-photo-upload-main'].disabled, true);
  await f.invoke();
  f.respond({ error_code: 'PHOTO_NAME_EXISTS_GROUP', duplicates_count: 1, duplicates_details: ['one.jpg'] });
  assert.equal(f.c.photoGroupUploadOperation, operation);
  assert.equal(f.elements['photo-dup-modal'].style.display, 'flex');
  await f.invoke();
  assert.equal(f.requests.length, 1);
  f.c.closePhotoDupDecisionModal(action);
  f.c.closePhotoDupDecisionModal(action);
  assert.equal(f.requests.length, 2);
  assert.equal(f.confirms(), 1);
  assert.equal(f.c.photoGroupUploadOperation, operation);
  assert.deepEqual(f.requests[1].form.getAll('photos[]'), f.files);
  assert.deepEqual(f.requests[1].form.getAll('child_ids[]'), ['42', '43']);
  assert.deepEqual(f.requests[1].form.getAll(action === 'replace' ? 'force_overwrite' : 'skip_duplicates'), ['1']);
  assert.deepEqual(f.requests[1].form.getAll(action === 'replace' ? 'skip_duplicates' : 'force_overwrite'), []);
  f.respond({ saved: 4, children_count: 2 });
  f.released();
  assert.equal(f.elements['photo-files'].value, '');
  assert.deepEqual(f.loads, [false]);
  assert.equal(f.toasts.length, 1);
  assert.equal(f.toasts[0][1], 'success');
  await f.c.sendPhotosUploadGroup(true, true, [42], false, true, operation);
  assert.equal(f.requests.length, 2, 'finished operation must not be reusable');
});
test('group upload: duplicate cancel unlocks and preserves files', async () => {
  const f = photoFixture();
  await f.approve();
  f.respond({ error_code: 'PHOTO_NAME_EXISTS_GROUP' });
  f.c.closePhotoDupDecisionModal('cancel');
  f.released();
  assert.equal(f.requests.length, 1);
  assert.equal(f.elements['photo-files'].value, 'mock-files');
  assert.equal(f.toasts[0][1], 'info');
});
test('group upload: foreign retry token cannot replace the active operation', async () => {
  const f = photoFixture();
  await f.approve();
  const operation = f.c.photoGroupUploadOperation;
  await f.c.sendPhotosUploadGroup(true, true, [42], false, true, {});
  assert.equal(f.requests.length, 1);
  assert.equal(f.c.photoGroupUploadOperation, operation);
  f.respond({ saved: 2, children_count: 2 });
  f.released();
});
test('group upload: whole-group request omits selected child IDs', async () => {
  const f = photoFixture();
  await f.approve(false);
  assert.deepEqual(f.requests[0].form.getAll('child_ids[]'), []);
  f.respond({ saved: 4, children_count: 2 });
  f.released();
  assert.equal(f.toasts[0][1], 'success');
});
test('group upload: stale XHR response cannot update a changed scope', async () => {
  const f = photoFixture();
  await f.approve();
  f.elements['sel-group'].value = '8';
  f.respond({ saved: 4 });
  f.released();
  assert.equal(f.toasts.length, 0);
  assert.equal(f.loads.length, 0);
  assert.equal(f.elements['photo-files'].value, 'mock-files');
});
for (const event of ['onerror', 'onabort', 'ontimeout', 'send throw']) test(`group upload: ${event} releases operation and button`, async () => {
  const f = photoFixture();
  if (event === 'send throw') f.failSend();
  await f.approve();
  if (event !== 'send throw') f.requests[0][event]();
  f.released();
  assert.equal(f.elements['photo-files'].value, 'mock-files');
  assert.equal(f.loads.length, 0);
  if (event === 'onerror' || event === 'send throw') assert.equal(f.toasts[0][1], 'danger');
});
for (const [name, response] of [
  ['malformed JSON', '{bad'], ['null response', 'null'],
  ['API error', { error: 'denied', errors: ['bad image'] }],
  ['storage full', { error_code: 'PHOTO_STORAGE_FAILED', error: 'full' }]
]) test(`group upload: ${name} unlocks and preserves selection`, async () => {
  const f = photoFixture();
  await f.approve();
  f.respond(response);
  f.released();
  assert.equal(f.elements['photo-files'].value, 'mock-files');
  assert.equal(f.toasts[0][1], 'danger');
  if (name === 'storage full') { assert.equal(f.alerts.length, 1); assert.deepEqual(f.loads, [true]); }
  if (name === 'API error') assert.deepEqual(plain(f.feedback), [[['bad image'], 'danger']]);
});
for (const errors of [[], ['bad image']]) test(`group upload: skipped duplicates with ${errors.length} errors give warning feedback`, async () => {
  const f = photoFixture();
  await f.approve();
  f.respond({ saved: 1, children_count: 2, skipped_duplicates_count: 1, skipped_duplicates: ['one.jpg'], errors });
  f.released();
  assert.equal(f.toasts[0][1], 'warning');
  assert.equal(f.feedback[0][1], 'warning');
  assert.equal(f.feedback[0][0].length, errors.length + 1);
  assert.ok(f.feedback[0][0].some(text => text.includes('one.jpg')));
  assert.deepEqual(f.loads, [errors.length > 0]);
});

const uncovered = [
  'Real browser rendering/events, external JS/dialog implementation, PHP conditional output branches and real PHP/JSON values.',
  'Backend/DB/auth/CSRF enforcement, real network/email/storage, browser-native FormData/XHR behavior.',
  'Other UI functions (bulk actions, single-child upload, photo hide/delete, send/save email, previews and navigation).',
  'Attendance duplicate response callbacks, synchronous apiPost throws and scope changes after dispatch; photo XHR setup throws/duplicate terminal events.',
  'Remembered photo-duplicate decisions and every server-response/DOM-failure combination.'
];

async function main() {
  let views = 0, inline = 0, external = 0;
  // Register a compile test for EVERY inline script, including empty ones. Do not
  // run startup code or fetch external scripts. Control-flow PHP tags are removed
  // so all JS bodies are parsed, but this is not a PHP branch/rendering test.
  for (const file of walk(viewRoot).filter(file => /\.(?:php|html?)$/i.test(file))) {
    views++;
    const rel = relative(file);
    for (const script of scriptsFor(rel)) {
      if (script.external) { external++; continue; }
      inline++;
      test(`compile ${rel}:${script.line}`, () => {
        assert.ok(!/<\?(?:php\b|=)|__UI_PHP_\d+__/.test(script.source), 'Unresolved PHP');
        new vm.Script(script.source, { filename: `${rel}:${script.line}` });
      });
    }
  }
  assert.ok(views > 0 && inline > 0, 'No views/inline scripts found');
  let passed = 0;
  for (const { name, run } of tests) {
    let timer;
    try {
      await Promise.race([
        Promise.resolve().then(run),
        new Promise((_, reject) => { timer = setTimeout(() => reject(new Error('Test exceeded 3000ms (unsettled async operation)')), 3000); })
      ]);
      passed++;
      console.log(`PASS ${name}`);
    } catch (error) {
      console.error(`FAIL ${name}\n${error.stack || error}`);
    } finally { clearTimeout(timer); }
  }
  console.log(`\nTests: ${tests.length}; passed: ${passed}; failed: ${tests.length - passed}.`);
  console.log(`Coverage: ${tests.length - inline} behavior cases; ${inline} inline scripts compiled across ${views} views; ${external} external script tags not fetched.`);
  console.log('Uncovered:\n' + uncovered.map(item => `- ${item}`).join('\n'));
  process.exitCode = passed === tests.length ? 0 : 1;
}
main().catch(error => { console.error(error.stack || error); process.exitCode = 1; });