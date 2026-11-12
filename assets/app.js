/*
 * Shared editor + submit logic for playground.php and exercise.php.
 *
 * - CodeMirror editor (when the CDN loaded) with table/column autocomplete.
 * - Ctrl/Cmd+Enter triggers the page's primary action (Run or Check).
 * - The form is sent with fetch(); only the results block comes back.
 * - Shared hosts answer 429 when a whole classroom (one public IP) runs
 *   queries at once, and lib.php answers 503 when every database slot is
 *   taken: wait for Retry-After and retry (up to 4 times) instead of showing
 *   an error page. The buttons stay disabled while a request runs.
 */
(function () {
  var form = document.getElementById('sqlform');
  var textarea = document.getElementById('sql');
  var resultsBox = document.getElementById('results');
  if (!form || !textarea || !resultsBox) return;

  var buttons = Array.prototype.slice.call(form.querySelectorAll('button[type=submit]'));
  var labels = buttons.map(function (b) { return b.textContent; });
  var config = window.NW || {};
  var primaryAction = config.primaryAction || 'run';
  var editor = null;
  var busy = false;
  var action = primaryAction;

  function primaryButton() {
    for (var i = 0; i < buttons.length; i++) {
      if (buttons[i].value === primaryAction) return buttons[i];
    }
    return buttons[0];
  }

  function run() {
    if (busy) return;
    var b = primaryButton();
    if (b && form.requestSubmit) form.requestSubmit(b); else form.submit();
  }

  function setBusy(on) {
    busy = on;
    buttons.forEach(function (b, i) {
      b.disabled = on;
      b.textContent = on && b.value === action ? 'Running…' : labels[i];
    });
  }

  function notice(text, isError) {
    resultsBox.innerHTML = '';
    var box = document.createElement('div');
    box.className = 'result';
    var inner = document.createElement('div');
    inner.className = isError ? 'error' : 'notice';
    inner.textContent = text;
    box.appendChild(inner);
    resultsBox.appendChild(box);
  }

  function wait(seconds) { return new Promise(function (r) { setTimeout(r, seconds * 1000); }); }

  // Full-page canvas overlay for the short verdict effects below. Returns null
  // when the user asked the OS for reduced motion, so callers just bail out.
  function overlayCanvas() {
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return null;
    var canvas = document.createElement('canvas');
    canvas.className = 'fx';
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    document.body.appendChild(canvas);
    return canvas;
  }

  function removeCanvas(canvas) {
    if (canvas.parentNode) canvas.parentNode.removeChild(canvas);
  }

  // A short burst of confetti over the page when an exercise is correct.
  // Plain canvas, about 1.5 seconds, nothing to click through.
  function confetti() {
    var canvas = overlayCanvas();
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    var colors = ['#1d4ed8', '#16a34a', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
    var pieces = [];
    for (var i = 0; i < 140; i++) {
      pieces.push({
        x: canvas.width / 2 + (Math.random() - 0.5) * canvas.width * 0.4,
        y: canvas.height * 0.3,
        vx: (Math.random() - 0.5) * 14,
        vy: -Math.random() * 14 - 4,
        w: 6 + Math.random() * 6,
        h: 4 + Math.random() * 4,
        rot: Math.random() * Math.PI,
        vr: (Math.random() - 0.5) * 0.3,
        color: colors[i % colors.length]
      });
    }
    var start = null;
    function frame(ts) {
      if (start === null) start = ts;
      var t = (ts - start) / 1000;
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      ctx.globalAlpha = Math.max(0, 1 - Math.max(0, t - 1) / 0.6);
      pieces.forEach(function (p) {
        p.vy += 0.35;
        p.vx *= 0.99;
        p.x += p.vx;
        p.y += p.vy;
        p.rot += p.vr;
        ctx.save();
        ctx.translate(p.x, p.y);
        ctx.rotate(p.rot);
        ctx.fillStyle = p.color;
        ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
        ctx.restore();
      });
      if (t < 1.6) requestAnimationFrame(frame);
      else removeCanvas(canvas);
    }
    requestAnimationFrame(frame);
  }

  function submitViaFetch(attempt) {
    var body = new FormData(form);
    body.set('action', action);
    return fetch(location.href, {
      method: 'POST',
      body: body,
      headers: { 'X-Requested-With': 'fetch' },
      credentials: 'same-origin'
    }).then(function (res) {
      if (res.status === 429 || res.status === 503) {
        var retry = parseInt(res.headers.get('Retry-After'), 10);
        if (!(retry > 0)) retry = 3;
        retry = Math.min(retry, 30);
        if (attempt < 4) {
          notice('Server busy (HTTP ' + res.status + '), retrying in ' + retry + ' s…');
          return wait(retry).then(function () { return submitViaFetch(attempt + 1); });
        }
        notice('Server busy (HTTP ' + res.status + '). Wait a few seconds and try again.', true);
        return;
      }
      if (!res.ok) throw new Error('HTTP ' + res.status);
      return res.text().then(function (html) {
        resultsBox.innerHTML = html;
        var verdict = resultsBox.querySelector('.verdict');
        if (verdict && verdict.scrollIntoView) verdict.scrollIntoView({ block: 'nearest' });
        if (verdict && verdict.classList.contains('correct')) confetti();
      });
    });
  }

  buttons.forEach(function (b) {
    b.addEventListener('click', function () { action = b.value || primaryAction; });
  });

  form.addEventListener('submit', function (e) {
    if (editor) editor.save();
    if (!window.fetch || !window.FormData) return; // old browser: normal form post
    e.preventDefault();
    if (busy) return;
    if (e.submitter && e.submitter.value) action = e.submitter.value;
    setBusy(true);
    notice('Running…');
    submitViaFetch(0)
      .catch(function (err) { notice('Request failed: ' + err.message + '. Try again.', true); })
      .then(function () {
        setBusy(false);
        // Page extras (assets/exam.js) react to the new results, or to a failed request.
        document.dispatchEvent(new CustomEvent('nw:result', { detail: { action: action } }));
      });
  });

  // Several statements: their results are tabs (markup from renderResults()).
  resultsBox.addEventListener('click', function (e) {
    var tab = e.target.closest ? e.target.closest('.tabs .tab') : null;
    if (!tab) return;
    var box = tab.closest('.tabbed');
    box.querySelectorAll('.tab').forEach(function (t) {
      var on = t === tab;
      t.classList.toggle('active', on);
      t.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    box.querySelectorAll('.panel').forEach(function (p) {
      p.hidden = p.dataset.panel !== tab.dataset.tab;
    });
  });

  // Plain textarea fallback (no CDN): Ctrl/Cmd+Enter still runs the query.
  textarea.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); run(); }
  });

  // Text pasted from Word, PDF, slides or chat: curly quotes, non-breaking
  // spaces, zero-width characters and long dashes break MySQL. Same rules as
  // normalizeSql() in lib.php, which stays authoritative on the server.
  function normalizeSql(str) {
    return str
      .replace(/[﻿​‌‍⁠­]/g, '')
      .replace(/[   -   　]/g, ' ')
      .replace(/[‘’‚‛′]/g, "'")
      .replace(/[“”„‟″]/g, '"')
      .replace(/—/g, '--')
      .replace(/–/g, '-');
  }

  // Save: download the editor's text as a .sql file (works without CodeMirror too).
  function currentSql() { return editor ? editor.getValue() : textarea.value; }
  Array.prototype.forEach.call(form.querySelectorAll('button[data-cmd=save]'), function (b) {
    b.addEventListener('click', function () {
      var name = b.dataset.filename || 'query.sql';
      var text = currentSql().replace(/\n?$/, '\n');
      var blob = new Blob([text], { type: 'application/sql;charset=utf-8' });
      var url = URL.createObjectURL(blob);
      var a = document.createElement('a');
      a.href = url;
      a.download = name;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
      if (editor) editor.focus();
    });
  });

  var editorButtons = Array.prototype.slice.call(form.querySelectorAll('button[data-cmd]:not([data-cmd=save])'));

  // No CodeMirror (CDN slow or blocked, e.g. on a school network): the buttons
  // still work on the plain textarea. execCommand keeps the browser's own
  // undo history, so Undo also takes back a Format.
  if (!window.CodeMirror) {
    editorButtons.forEach(function (b) {
      b.addEventListener('click', function () {
        textarea.focus();
        if (b.dataset.cmd !== 'format') {
          document.execCommand(b.dataset.cmd);
          return;
        }
        if (!window.formatSql) return;
        var formatted = window.formatSql(textarea.value).replace(/\n$/, '');
        if (formatted === textarea.value) return;
        textarea.select();
        if (!document.execCommand('insertText', false, formatted)) textarea.value = formatted;
      });
    });
    return;
  }

  // Autocomplete: CodeMirror's sql-hint (tables, Table.columns, keywords) plus
  // bare column names of every table and the function names from sqlformat.js
  // (ROUND, CONCAT, YEAR, ... which sql-hint does not know), minus the word
  // already typed in full, so Enter after "FROM" inserts a newline instead of
  // "accepting" FROM again. A function is inserted with its "(".
  var tables = config.tables || {};
  var functions = window.sqlFunctions || [];
  var functionSet = {};
  functions.forEach(function (f) { functionSet[f] = 1; });
  var columnIndex = {};                       // lower-case column -> {text, tables: []}
  Object.keys(tables).forEach(function (t) {
    (tables[t] || []).forEach(function (c) {
      var key = c.toLowerCase();
      if (!columnIndex[key]) columnIndex[key] = { text: c, tables: [] };
      columnIndex[key].tables.push(t);
    });
  });
  var columnKeys = Object.keys(columnIndex).sort();

  function sqlHint(cm, options) {
    var result = CodeMirror.hint.sql(cm, options);
    if (!result || !result.list) return result;
    var word = cm.getRange(result.from, result.to).toLowerCase();
    var tableHits = [], rest = [];
    var bare = word.length > 0 && word.indexOf('.') < 0 && word.charAt(0) !== '`';
    result.list.forEach(function (item) {
      var text = typeof item === 'string' ? item : item.text;
      if (text.toLowerCase() === word) return;
      if (bare && functionSet[text.toUpperCase()]) return;   // shown as a function below
      if (tables.hasOwnProperty(text)) tableHits.push(item); else rest.push(item);
    });
    var columnHits = [], functionHits = [];
    if (bare) {
      columnKeys.forEach(function (key) {
        if (key.indexOf(word) !== 0 || key === word) return;
        var col = columnIndex[key];
        columnHits.push({ text: col.text, displayText: col.text + '  ·  ' + col.tables.join(', '), className: 'hint-column' });
      });
      var upper = word.toUpperCase();
      functions.forEach(function (f) {
        if (f.indexOf(upper) !== 0) return;
        functionHits.push({ text: f + '(', displayText: f + '()', className: 'hint-function' });
      });
    }
    result.list = tableHits.concat(columnHits, functionHits, rest);
    return result.list.length ? result : null;
  }

  // Format the whole query as one undo step.
  function formatQuery(cm) {
    if (!window.formatSql) return;
    var text = cm.getValue();
    var formatted = window.formatSql(text).replace(/\n$/, '');
    if (formatted === text) return;
    var last = cm.lastLine();
    cm.replaceRange(formatted, { line: 0, ch: 0 }, { line: last, ch: cm.getLine(last).length }, '+format');
    cm.focus();
  }

  var hintOptions = {
    hint: sqlHint,
    tables: tables,
    completeSingle: false,
    closeOnUnfocus: true
  };

  // Tab inserts spaces (no selection) or indents the selection; Backspace in
  // the leading whitespace of a line removes a whole indent step, so the line
  // after an indented one goes back one level with one key press.
  function softTab(cm) {
    if (cm.somethingSelected()) cm.indentSelection('add');
    else cm.execCommand('insertSoftTab');
  }
  function smartBackspace(cm) {
    if (cm.somethingSelected()) return CodeMirror.Pass;
    var cur = cm.getCursor();
    var before = cm.getRange({ line: cur.line, ch: 0 }, cur);
    if (before === '' || /\S/.test(before)) return CodeMirror.Pass;
    var unit = cm.getOption('indentUnit');
    var col = CodeMirror.countColumn(before, null, cm.getOption('tabSize'));
    var target = Math.max(0, Math.ceil(col / unit) - 1) * unit;
    cm.replaceRange(new Array(target + 1).join(' '), { line: cur.line, ch: 0 }, cur, '+delete');
  }

  editor = CodeMirror.fromTextArea(textarea, {
    mode: 'text/x-mysql',
    lineNumbers: true,
    indentWithTabs: false,
    indentUnit: 2,
    tabSize: 2,
    viewportMargin: Infinity,
    historyEventDelay: 400,
    extraKeys: {
      'Ctrl-Enter': run,
      'Cmd-Enter': run,
      'Tab': softTab,
      'Shift-Tab': 'indentLess',
      'Backspace': smartBackspace,
      'Ctrl-Space': 'autocomplete',
      'Ctrl-Z': 'undo',
      'Cmd-Z': 'undo',
      'Shift-Ctrl-Z': 'redo',
      'Shift-Cmd-Z': 'redo',
      'Ctrl-Y': 'redo',
      'Shift-Ctrl-F': formatQuery,
      'Shift-Cmd-F': formatQuery,
      'Shift-Alt-F': formatQuery
    },
    hintOptions: hintOptions
  });
  config.editor = editor;                     // for page extras (assets/exam.js)

  // Clean pasted text before it lands in the editor.
  editor.on('beforeChange', function (cm, change) {
    if (change.origin !== 'paste' || !change.update) return;
    var lines = normalizeSql(change.text.join('\n')).split('\n');
    change.update(change.from, change.to, lines);
  });

  // Suggestions while typing: after two identifier characters or a dot,
  // not inside strings or comments.
  var hintTimer = null;
  editor.on('inputRead', function (cm, change) {
    if (change.origin !== '+input' || cm.state.completionActive) return;
    var typed = change.text.join('');
    if (!/^[\w.`]$/.test(typed)) return;
    var cur = cm.getCursor();
    var token = cm.getTokenAt(cur);
    if (token.type === 'string' || token.type === 'comment') return;
    if (typed !== '.' && !/^[\w`]{2,}$/.test(token.string)) return;
    clearTimeout(hintTimer);
    hintTimer = setTimeout(function () {
      if (!cm.state.completionActive) cm.showHint(hintOptions);
    }, 120);
  });

  // Format shortcut outside the editor too (after clicking Run or a tab the
  // editor has no focus). Inside the editor extraKeys above handle it first and
  // mark the event as handled.
  document.addEventListener('keydown', function (e) {
    if (e.defaultPrevented || e.repeat) return;
    if ((e.key === 'F' || e.key === 'f' || e.code === 'KeyF') && e.shiftKey && (e.ctrlKey || e.metaKey || e.altKey)) {
      e.preventDefault();
      formatQuery(editor);
    }
  });

  // Undo / Redo / Format buttons.
  function updateHistoryButtons() {
    var size = editor.historySize();
    editorButtons.forEach(function (b) {
      if (b.dataset.cmd === 'undo') b.disabled = size.undo === 0;
      else if (b.dataset.cmd === 'redo') b.disabled = size.redo === 0;
    });
  }
  editorButtons.forEach(function (b) {
    b.addEventListener('click', function () {
      if (b.dataset.cmd === 'undo') editor.undo();
      else if (b.dataset.cmd === 'redo') editor.redo();
      else if (b.dataset.cmd === 'format') formatQuery(editor);
      editor.focus();
    });
  });
  editor.on('change', updateHistoryButtons);
  updateHistoryButtons();

  editor.focus();
})();
