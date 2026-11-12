/*
 * Extras for a graded exam (exampage.php), on top of app.js:
 *
 * - Autosave: every 15 seconds the editor text is sent when it changed (no
 *   MySQL, only the student's file), and once more when the student leaves
 *   the page (another assignment, closing the tab) with sendBeacon. The bar
 *   shows "Saved 14:32:05", "Unsaved changes" or why saving failed.
 *   Every 15 s per student keeps a classroom of 150 at about 10 requests per
 *   second at most, which a shared host's per-IP limit tolerates.
 * - After Check / Run (the "nw:result" event from app.js) the saved time,
 *   the ✓ in the left panel and the "x of 6 correct" counter follow along.
 * - "Submit exam" asks for confirmation and sends the current editor text.
 * - A copy of the text stays in this browser (localStorage); an editor that
 *   comes up empty while the copy has text gets it back and saves it.
 */
(function () {
  var form = document.getElementById('sqlform');
  var textarea = document.getElementById('sql');
  var status = document.getElementById('autosave');
  if (!form || !textarea || !status) return;

  var editor = (window.NW && window.NW.editor) || null;
  var SAVE_EVERY_MS = 15000;

  function text() { return editor ? editor.getValue() : textarea.value; }

  var saved = text();          // what the server has
  var pending = null;          // text sent with a Check / Run that is still running
  var saving = false;

  function show(message, cls) {
    status.textContent = message;
    status.className = 'autosave' + (cls ? ' ' + cls : '');
  }

  function payload(action, sql) {
    var body = new FormData();
    body.set('action', action);
    body.set('sql', sql);
    return body;
  }

  function save() {
    var sql = text();
    if (saving || pending !== null || sql === saved) return;
    saving = true;
    show('Saving…');
    fetch(location.href, {
      method: 'POST',
      body: payload('save', sql),
      headers: { 'X-Requested-With': 'fetch' },
      credentials: 'same-origin'
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (data) {
        if (!res.ok || !data.ok) throw new Error(data.error || 'server busy (HTTP ' + res.status + ')');
        saved = sql;
        if (text() === saved) show('Saved ' + data.savedAt, 'ok');
        else show('Unsaved changes', 'dirty');
      });
    }).catch(function (err) {
      show('Not saved: ' + err.message + '. Retrying…', 'err');
    }).then(function () { saving = false; });
  }

  // A copy of the text in this browser (per student and assignment). When the
  // server ever hands out an empty editor while this browser still has the
  // text, it is put back and saved.
  var storeKey = form.dataset.storeKey;
  function keep() {
    try { localStorage.setItem(storeKey, JSON.stringify({ sql: text(), at: Date.now() })); } catch (e) { /* private window */ }
  }
  function kept() {
    try { return (JSON.parse(localStorage.getItem(storeKey) || 'null') || {}).sql || ''; } catch (e) { return ''; }
  }

  function changed() {
    keep();
    if (text() !== saved && !saving) show('Unsaved changes', 'dirty');
  }
  if (storeKey && text().trim() === '' && kept().trim() !== '') {
    if (editor) editor.setValue(kept()); else textarea.value = kept();
    save();
    show('Restored your text from this browser, saving…', 'dirty');
  } else if (storeKey && text().trim() !== '') {
    keep();
  }
  if (editor) editor.on('change', changed);
  else textarea.addEventListener('input', changed);
  setInterval(save, SAVE_EVERY_MS);

  // Leaving the page: sendBeacon survives the navigation.
  function flush() {
    var sql = text();
    if (sql !== saved && navigator.sendBeacon && navigator.sendBeacon(location.href, payload('save', sql))) saved = sql;
  }
  window.addEventListener('pagehide', flush);
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') flush();
  });

  // Check and Run save the text on the server too.
  form.addEventListener('submit', function () { pending = text(); });
  document.addEventListener('nw:result', function () {
    var state = document.getElementById('exam-state');
    if (state && pending !== null) {
      saved = pending;
      if (text() === saved) show('Saved ' + state.dataset.savedAt, 'ok');
      var current = document.querySelector('#exam-nav a.current');
      if (current && state.dataset.status) {
        current.classList.toggle('solved', state.dataset.status === 'correct');
        current.classList.toggle('tried', state.dataset.status !== 'correct');
      }
      var counter = document.getElementById('exam-correct');
      if (counter) counter.textContent = state.dataset.correct;
    }
    pending = null;
  });

  // Final submit: confirm, then send what is in the editor along.
  var submitForm = document.getElementById('exam-submit');
  if (submitForm) {
    submitForm.addEventListener('submit', function (e) {
      var open = document.querySelectorAll('#exam-nav a:not(.solved)').length;
      var question = 'Submit the exam?\n\n'
        + (open ? open + ' assignment' + (open === 1 ? ' is' : 's are') + ' not checked as correct.\n' : '')
        + 'What is in your editors now is your answer. After submitting you cannot change anything.';
      if (!window.confirm(question)) {
        e.preventDefault();
        return;
      }
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'sql';
      input.value = text();
      submitForm.appendChild(input);
      saved = text();          // no beacon on the way out: the submit carries the text
    });
  }
})();
