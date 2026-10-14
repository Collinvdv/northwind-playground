/*
 * Shared editor + submit logic for playground.php and exercise.php.
 *
 * - CodeMirror editor (when the CDN loaded) with table/column autocomplete.
 * - Ctrl/Cmd+Enter triggers the page's primary action (Run or Check).
 * - The form is sent with fetch(); only the results block comes back.
 * - Shared hosts answer 429/503 when a whole classroom (one public IP) runs
 *   queries at once: wait for Retry-After and retry instead of showing the
 *   host's error page. The buttons stay disabled while a request runs.
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
        if (attempt < 2) {
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
      .then(function () { setBusy(false); });
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

  var editorButtons = Array.prototype.slice.call(form.querySelectorAll('button[data-cmd]'));

  if (!window.CodeMirror) {
    editorButtons.forEach(function (b) { b.hidden = true; });
    return;
  }

  // Autocomplete: CodeMirror's sql-hint (tables, Table.columns, keywords) plus
  // bare column names of every table, minus the word already typed in full,
  // so Enter after "FROM" inserts a newline instead of "accepting" FROM again.
  var tables = config.tables || {};
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
    result.list.forEach(function (item) {
      var text = typeof item === 'string' ? item : item.text;
      if (text.toLowerCase() === word) return;
      if (tables.hasOwnProperty(text)) tableHits.push(item); else rest.push(item);
    });
    var columnHits = [];
    if (word.length > 0 && word.indexOf('.') < 0 && word.charAt(0) !== '`') {
      columnKeys.forEach(function (key) {
        if (key.indexOf(word) !== 0 || key === word) return;
        var col = columnIndex[key];
        columnHits.push({ text: col.text, displayText: col.text + '  ·  ' + col.tables.join(', '), className: 'hint-column' });
      });
    }
    result.list = tableHits.concat(columnHits, rest);
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
