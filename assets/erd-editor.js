/*
 * Diagram editor of a design exercise (exercise.php and the exam page, for an
 * exercise with 'type' => 'erd') and of the free canvas (erd-draw.php).
 * Only a drawing: nothing here touches a database.
 *
 *   - Drag the "Entity" block onto the canvas (or click it) for a new entity;
 *     or right-click (or double-click) the canvas: "New entity here".
 *     Right-click an entity for "Add attribute" and "Remove entity".
 *     Drag its coloured bar to move it, its bottom right corner to resize it,
 *     click the dot in the bar for another colour.
 *   - Type its name, add attributes, choose a data type for each. PK and FK in
 *     front of an attribute mark it as (part of) the primary key / a foreign key.
 *     Drag the grip in front of a row to change the order of the attributes.
 *   - Relationship, row by row: drag the + behind an attribute onto the
 *     attribute it belongs with in another entity (or click the +, then that
 *     attribute). Near an entity the line snaps to it (see magnet()): to the
 *     row under the pointer, else the row with the same name, else the key. The foreign key row is the one marked FK, otherwise the one
 *     that is not a primary key, otherwise the row you started from.
 *   - Click an end of a line to switch it between "one" and "many". Many at
 *     both ends is a many-to-many line (no foreign key); one at both ends is
 *     one-to-one. The × in the middle of a line removes the relationship.
 *   - Export / Import: the drawing as a text file. PNG: the drawing as a picture.
 *   - The canvas has no edges: drag its background (or use the arrow keys) to
 *     move around, zoom with the − / + buttons, the + and − keys or
 *     Ctrl + scroll wheel; "Fit" shows everything, "Full screen" gives the
 *     canvas the whole window (Esc leaves).
 *   - Undo / Redo: the buttons, Ctrl+Z and Ctrl+Y (or Shift+Ctrl+Z).
 *   - Selecting: Ctrl+A selects every entity, a click on an entity's bar that
 *     one, Shift+click adds or removes one, Shift+drag on the background
 *     draws a frame around the ones to select. Dragging a selected entity moves
 *     all selected ones; Delete removes them; Esc or a click on the
 *     background clears the selection.
 *   - Copying: Ctrl+C copies the selected entities, Ctrl+V pastes them (also in
 *     another drawing, except on the exam page), Ctrl+D duplicates them. The
 *     relationships between the copied entities come along. Also in the
 *     right-click menu.
 *   - Bend points: drag a line to bend it around an entity; drag a point to
 *     move it, double-click it to remove it. While dragging a new line from a
 *     +, every press of Shift (or Ctrl) drops a bend point under the pointer.
 *   - "Legend" explains the marks; "Overview" is a small map of the whole
 *     drawing in the bottom left corner (click or drag in it to go there).
 *
 * The design lives as text in the form's textarea, in the notation that
 * lib.php reads (parseErd()), one line per entity:
 *
 *     Players @320,24,290,0 #green: PlayerID int PK, Name varchar, TeamID int > Teams.TeamID
 *     Students <> Courses                         (a many-to-many line)
 *
 * "@x,y,w,h" is where the entity stands and how big it is (h = 0: as high as
 * its rows), "#green" its colour, UNIQUE after a foreign key means one-to-one,
 * a lone FK marks a foreign key that is not linked yet. "~(x,y;x,y)" after a
 * relationship holds the bend points of its line. Every change rewrites
 * the textarea, so Check, the exam's autosave (exam.js) and a page reload all
 * work on that text. "Text" in the toolbar shows it; it can be edited there
 * too. Without JavaScript the textarea alone is the editor.
 *
 * Free drawing (erd-draw.php): the form has data-free="<localStorage key>".
 * Then there is no Check; the text is kept in this browser under that key and
 * the toolbar gets "Clear". On the exam page (data-store-key) there is no
 * Export / Import.
 */
(function () {
  var form = document.getElementById('sqlform');
  var textarea = document.getElementById('sql');
  var results = document.getElementById('results');
  if (!form || !textarea) return;

  var freeKey = form.dataset.free || '';   // free drawing: no check, kept in localStorage
  var isExam = !!form.dataset.storeKey;    // exam page: no files in or out

  var BOX_W = 290;      // width of a new entity
  var MIN_W = 230;
  var MAX_W = 720;
  var GAP_X = 60;
  var GAP_Y = 30;
  var PAD = 24;
  var HEAD_H = 34;
  var ROW_H = 29;       // lib.php: ERD_EDITOR_Y_SCALE = its row height / this one
  var FOOT_H = 30;      // the "+ attribute" row
  var SVG = 'http://www.w3.org/2000/svg';
  var TYPES = ['int', 'decimal', 'varchar', 'text', 'date', 'datetime', 'boolean', 'blob'];
  var TYPE_ALIASES = { integer: 'int', smallint: 'int', tinyint: 'int', bigint: 'int', numeric: 'decimal', float: 'decimal', double: 'decimal',
    char: 'varchar', string: 'varchar', timestamp: 'datetime', bool: 'boolean', bit: 'boolean',
    longblob: 'blob', mediumblob: 'blob', tinyblob: 'blob', binary: 'blob', varbinary: 'blob' };
  // As ERD_COLORS in lib.php.
  var COLORS = { blue: '#1d4ed8', green: '#15803d', orange: '#c2410c', purple: '#7e22ce', red: '#b91c1c', teal: '#0f766e', grey: '#475569' };
  var COLOR_NAMES = Object.keys(COLORS);

  // {id, name, x, y, w, h, color, attrs: [attr]}
  // attr: {name, type, pk, fk, ref (entity id or null), refAttr (the attr it points to, or null), unique,
  //        via: bend points [{x, y}] of its line, from this attribute to the one it points to}
  var entities = [];
  var many = [];        // many-to-many lines: {a: entity id, b: entity id, was: the foreign key it came from, or null, via: bend points from a to b}
  var nextId = 1;
  var drag = null;      // what the pointer is doing: {kind: 'move' | 'size' | 'order' | 'link' | 'bend' | 'marquee' | 'pan', ...}
  var clip = null;      // what Ctrl+C copied: {text: the entities in the notation, pastes: how often it was pasted}
  var CLIP_KEY = 'nw.erd.clip';
  var view = { x: 0, y: 0, z: 1 };   // the canvas: moved by x, y pixels, zoomed by z
  var MIN_Z = 0.25;
  var MAX_Z = 2.5;
  var ZOOM_STEP = 1.1;  // per button press, key press or wheel notch: 10%
  var selected = {};    // ids of the selected entities
  var history = [];     // the text after every change, for undo / redo
  var at = -1;          // where in history we are
  var lastTyping = 0;   // time of the last change made by typing: a word is one step, not one per letter
  var picking = null;   // {entity, attr} after a click on a +: the next attribute clicked is the other side

  // Data mode ("Data" in the toolbar): every entity as a small table with example rows, to see whether the
  // design holds real data. The rows are never part of the design text; they are kept in this browser only,
  // per page: {entity name (norm): [{attribute name (norm): value}]}.
  var dataMode = false;
  var DATA_ROWS = 3;    // empty rows of an entity that has no data yet
  var dataKey = 'nw.erd.data.' + (freeKey || location.pathname + location.search);
  var sample = {};
  try { sample = JSON.parse(localStorage.getItem(dataKey) || '{}') || {}; } catch (err) { sample = {}; }

  // -------------------------------------------------------------------------
  // Text <-> model
  // -------------------------------------------------------------------------

  /** A name as lib.php compares it: Team = teams, first_name = FirstName. */
  function norm(name, table) {
    var n = name.toLowerCase().replace(/[^a-z0-9]/g, '');
    if (table) n = /ies$/.test(n) ? n.replace(/ies$/, 'y') : n.replace(/s$/, '');
    return n;
  }

  function cleanName(s) { return s.replace(/[^\w]/g, ''); }

  /** varchar(40) → varchar, INTEGER → int: the editor offers kinds of types, not sizes. */
  function cleanType(type) {
    var t = type.toLowerCase().replace(/\(.*$/, '').trim();
    return TYPE_ALIASES[t] || t;
  }

  function byId(id) {
    for (var i = 0; i < entities.length; i++) if (entities[i].id === id) return entities[i];
    return null;
  }

  function natural(e) { return HEAD_H + e.attrs.length * ROW_H + FOOT_H; }
  function height(e) { return Math.max(natural(e), e.h || 0); }
  function label(e) { return e.name || 'Unnamed'; }
  function target(a) { return a.ref !== null ? byId(a.ref) : null; }

  /** "120,40;200,80" → [{x: 120, y: 40}, {x: 200, y: 80}]. */
  function points(text) {
    return (text || '').split(';').map(function (pair) {
      var xy = pair.split(',');
      return { x: parseFloat(xy[0]), y: parseFloat(xy[1]) };
    }).filter(function (p) { return isFinite(p.x) && isFinite(p.y); });
  }
  function viaText(via) {
    return via.length ? ' ~(' + via.map(function (p) { return Math.round(p.x) + ',' + Math.round(p.y); }).join(';') + ')' : '';
  }

  /**
   * Text → {list: entities, many: many-to-many lines}. extra: entities that are
   * already on the canvas, which the text may point to as well (pasting).
   */
  function parse(text, extra) {
    var list = [];
    var refs = [];
    var manyRefs = [];
    text.split(/\r?\n/).forEach(function (line) {
      line = line.trim();
      var mm = /^(\w+)\s*<>\s*(\w+)\s*(?:~\s*\(([^)]*)\))?$/.exec(line);
      if (mm) { manyRefs.push([mm[1], mm[2], points(mm[3])]); return; }
      var m = /^(\+?)\s*([\w ]+?)\s*(?:@\s*(-?\d+)\s*,\s*(-?\d+)(?:\s*,\s*(\d+)\s*,\s*(\d+))?\s*)?(?:#(\w+)\s*)?:\s*(.*)$/.exec(line);
      if (!m) return;
      var color = (m[7] || '').toLowerCase();
      var e = { id: nextId++, name: cleanName(m[2]), x: null, y: null, w: BOX_W, h: 0, color: COLORS[color] ? color : 'blue', stack: !!m[1], attrs: [] };
      if (m[3] !== undefined) { e.x = +m[3]; e.y = +m[4]; }
      if (m[5] !== undefined) { e.w = Math.min(MAX_W, Math.max(MIN_W, +m[5])); e.h = +m[6]; }
      m[8].split(/,(?![^()]*\))/).forEach(function (column) {
        var c = /^(\S+)\s*(.*)$/.exec(column.trim());
        if (!c) return;
        var rest = c[2];
        var a = { name: cleanName(c[1]), type: '', pk: false, fk: false, ref: null, refAttr: null, unique: false, via: [] };
        var v = /~\s*\(([^)]*)\)/.exec(rest);
        if (v) { a.via = points(v[1]); rest = rest.replace(v[0], ''); }
        var r = /-?>\s*(\w+)(?:\.(\w+))?/.exec(rest);
        if (r) {
          refs.push([a, r[1], r[2] || '']);
          rest = rest.replace(r[0], '');
          a.unique = /\bUNIQUE\b/i.test(rest);
        }
        a.pk = /\bPK\b/i.test(rest);
        a.fk = !!r || /\bFK\b/i.test(rest);
        a.type = cleanType(rest.replace(/\b(PK|FK|UNIQUE)\b/ig, ''));
        if (a.name) e.attrs.push(a);
      });
      list.push(e);
    });
    function find(name) {
      var all = extra ? list.concat(extra) : list;
      for (var i = 0; i < all.length; i++) if (norm(all[i].name, true) === norm(name, true)) return all[i];
      return null;
    }
    refs.forEach(function (ref) {
      var a = ref[0];
      var t = find(ref[1]);
      if (!t) { a.unique = false; a.via = []; return; }
      a.ref = t.id;
      // The attribute it points to: the one that is named, else the first key attribute.
      a.refAttr = t.attrs.filter(function (x) { return ref[2] !== '' && norm(x.name) === norm(ref[2]); })[0]
        || t.attrs.filter(function (x) { return x.pk; })[0] || null;
    });
    var links = [];
    manyRefs.forEach(function (pair) {
      var a = find(pair[0]);
      var b = find(pair[1]);
      if (a && b) links.push({ a: a.id, b: b.id, was: null, via: pair[2] });
    });
    // Entities without a place (a starter text, a design typed as text): left to right, "+" under the previous one.
    var column = 0;
    var previous = null;
    list.forEach(function (e) {
      if (e.x === null) {
        if (e.stack && previous) {
          e.x = previous.x;
          e.y = previous.y + height(previous) + GAP_Y;
        } else {
          e.x = PAD + column * (BOX_W + GAP_X);
          e.y = PAD;
          column++;
        }
      }
      previous = e;
    });
    return { list: list, many: links };
  }

  function load(text) {
    var d = parse(text);
    entities = d.list;
    many = d.many;
    selected = {};
  }

  /** The drawing as text; with a list: only those entities and the many-to-many lines between them (copying). */
  function serialize(only) {
    var ids = {};
    (only || entities).forEach(function (e) { ids[e.id] = true; });
    var out = (only || entities).map(function (e) {
      var columns = e.attrs.filter(function (a) { return a.name !== ''; }).map(function (a) {
        var t = target(a);
        var link = '';
        if (t) link = ' > ' + label(t) + (a.refAttr && a.refAttr.name ? '.' + a.refAttr.name : '') + (a.unique ? ' UNIQUE' : '') + viaText(a.via);
        else if (a.fk) link = ' FK';
        return a.name + (a.type ? ' ' + a.type : '') + (a.pk ? ' PK' : '') + link;
      });
      var size = e.w !== BOX_W || e.h ? ',' + Math.round(e.w) + ',' + Math.round(e.h || 0) : '';
      return label(e) + ' @' + Math.round(e.x) + ',' + Math.round(e.y) + size + (e.color !== 'blue' ? ' #' + e.color : '') + ': ' + columns.join(', ');
    });
    many.forEach(function (m) {
      var a = byId(m.a);
      var b = byId(m.b);
      if (a && b && ids[m.a] && ids[m.b]) out.push(label(a) + ' <> ' + label(b) + viaText(m.via));
    });
    return out.join('\n');
  }

  var ownInput = false;
  /** Put the model's text in the textarea and tell whoever listens (exam.js autosave). */
  function publish() {
    textarea.value = serialize();
    ownInput = true;
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    ownInput = false;
    remember();
  }

  /** The model changed: publish it and add a step to the history. typing: letters typed shortly after each other are one step. */
  function sync(typing) {
    publish();
    var text = textarea.value;
    var now = Date.now();
    if (text !== history[at]) {
      if (typing && at > 0 && now - lastTyping < 1200) {
        history[at] = text;                       // still the same word
      } else {
        history.splice(at + 1, history.length, text);
        if (history.length > 200) history.shift();
        at = history.length - 1;
      }
    }
    lastTyping = typing ? now : 0;
    historyButtons();
  }

  /** Undo (-1) or redo (+1). */
  function travel(step) {
    if (at + step < 0 || at + step >= history.length) return;
    at += step;
    stopPicking();
    load(history[at]);
    render();
    publish();
    lastTyping = 0;
    historyButtons();
    say(step < 0 ? 'Undone.' : 'Redone.');
  }

  function historyButtons() {
    if (!undoButton) return;
    undoButton.disabled = at <= 0;
    redoButton.disabled = at >= history.length - 1;
  }

  function remember() {
    if (!freeKey) return;
    try { localStorage.setItem(freeKey, textarea.value); } catch (e) { /* private window: lasts until the page closes */ }
  }

  // -------------------------------------------------------------------------
  // The editor's elements
  // -------------------------------------------------------------------------

  function el(tag, cls, text) {
    var node = document.createElement(tag);
    if (cls) node.className = cls;
    if (text !== undefined) node.textContent = text;
    return node;
  }
  function button(cls, text, title) {
    var b = el('button', cls, text);
    b.type = 'button';
    if (title) { b.title = title; b.setAttribute('aria-label', title); }
    return b;
  }
  function svgEl(tag, attrs, parent) {
    var node = document.createElementNS(SVG, tag);
    Object.keys(attrs).forEach(function (k) { node.setAttribute(k, attrs[k]); });
    (parent || lines).appendChild(node);
    return node;
  }

  var root = el('div', 'erd-editor');
  var toolbar = el('div', 'erd-toolbar');
  var chip = el('span', 'erd-chip', '▦ Entity');
  chip.draggable = true;
  chip.tabIndex = 0;
  chip.setAttribute('role', 'button');
  chip.title = 'Drag onto the canvas, or click, for a new entity';
  var tidyButton = button('secondary erd-tool', 'Tidy up', 'Put the entities in neat columns');
  var textButton = button('secondary erd-tool', 'Text', 'Show the design as text');
  var dataButton = button('secondary erd-tool', 'Data', 'Switch between the diagram and example data: every entity as a small table you can fill in');
  var status = el('span', 'hint erd-status');
  var undoButton = button('secondary erd-tool', '↶', 'Undo (Ctrl+Z / ⌘+Z)');
  var redoButton = button('secondary erd-tool', '↷', 'Redo (Ctrl+Y / ⇧⌘+Z)');
  var zoomOut = button('secondary erd-tool', '−', 'Zoom out (− key, or Ctrl + scroll)');
  var zoomLabel = button('secondary erd-tool erd-zoom', '100%', 'Back to 100%');
  var zoomIn = button('secondary erd-tool', '+', 'Zoom in (+ key, or Ctrl + scroll)');
  var fitButton = button('secondary erd-tool', 'Fit', 'Show the whole drawing (0 key)');
  var fullButton = button('secondary erd-tool', 'Full screen', 'Give the canvas the whole window (Esc leaves)');
  var group = function () {
    var g = el('span', 'erd-group');
    g.append.apply(g, arguments);
    return g;
  };
  var legendButton = button('secondary erd-tool', 'Legend', 'Show or hide the legend');
  var mapButton = button('secondary erd-tool', 'Overview', 'Show or hide the small map of the whole drawing');
  toolbar.append(chip, dataButton, group(undoButton, redoButton), group(zoomOut, zoomLabel, zoomIn, fitButton), fullButton, group(legendButton, mapButton), tidyButton, textButton);

  if (!isExam) {
    var exportButton = button('secondary erd-tool', 'Export', 'Download the drawing as a file');
    var importButton = button('secondary erd-tool', 'Import', 'Open a drawing that was exported before');
    var pngButton = button('secondary erd-tool', 'PNG', 'Download the drawing as a picture');
    var fileInput = el('input');
    fileInput.type = 'file';
    fileInput.accept = '.txt,.erd,text/plain';
    fileInput.hidden = true;
    toolbar.append(group(exportButton, importButton, pngButton), fileInput);
    pngButton.addEventListener('click', savePng);
    exportButton.addEventListener('click', function () {
      var url = URL.createObjectURL(new Blob([serialize() + '\n'], { type: 'text/plain' }));
      var a = document.createElement('a');
      a.href = url;
      a.download = 'erd.txt';
      root.appendChild(a);
      a.click();
      a.remove();
      setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    });
    importButton.addEventListener('click', function () { fileInput.click(); });
    fileInput.addEventListener('change', function () {
      var file = fileInput.files && fileInput.files[0];
      fileInput.value = '';             // the same file can be chosen again
      if (!file) return;
      var reader = new FileReader();
      reader.onload = function () {
        var before = entities.length;
        var text = String(reader.result || '');
        load(text);
        if (!entities.length) {
          load(textarea.value);         // not a drawing: keep what was there
          render();
          say('That file holds no drawing: it must be a file made with Export.');
          return;
        }
        if (before && !window.confirm('Replace the ' + before + ' entities on the canvas by the ' + entities.length + ' of ' + file.name + '?')) {
          load(textarea.value);
          render();
          return;
        }
        render();
        sync();
        say('Opened ' + file.name + ': ' + entities.length + ' entities.');
      };
      reader.readAsText(file);
    });
  }
  if (freeKey) {
    var clearButton = button('secondary erd-tool', 'Clear', 'Remove everything from the canvas');
    toolbar.appendChild(clearButton);
    clearButton.addEventListener('click', function () {
      if (entities.length && !window.confirm('Remove all ' + entities.length + ' entities from the canvas?')) return;
      entities = [];
      many = [];
      render();
      sync();
    });
  }
  toolbar.appendChild(status);

  var canvas = el('div', 'erd-canvas');
  var surface = el('div', 'erd-surface');
  var lines = document.createElementNS(SVG, 'svg');
  lines.setAttribute('class', 'erd-lines');
  var empty = el('p', 'erd-empty', 'Drag the Entity block here, click it, or right-click here, to start your diagram.');
  surface.appendChild(lines);
  canvas.append(surface, empty);
  // Legend (bottom right) and overview map (bottom left), both over the canvas.
  var legend = el('div', 'erd-legend');
  legend.innerHTML = '<strong>Legend</strong>'
    + '<div><span class="k pk">PK</span> primary key</div>'
    + '<div><span class="k fk">FK</span> foreign key</div>'
    + '<div><svg width="34" height="16" viewBox="0 0 34 16"><line x1="2" y1="8" x2="32" y2="8"/><line x1="26" y1="1" x2="26" y2="15"/></svg> one</div>'
    + '<div><svg width="34" height="16" viewBox="0 0 34 16"><line x1="2" y1="8" x2="32" y2="8"/><line x1="18" y1="8" x2="32" y2="1"/><line x1="18" y1="8" x2="32" y2="15"/></svg> many</div>'
    + '<div><span class="k plus">+</span> drag to link two attributes</div>'
    + '<div><span class="k grip">⋮⋮</span> drag to reorder</div>';
  var map = el('div', 'erd-map');
  map.title = 'Overview: click or drag to go there';
  var mapSvg = document.createElementNS(SVG, 'svg');
  map.appendChild(mapSvg);
  canvas.append(legend, map);
  var MAP_W = 190;
  var MAP_H = 120;
  var mapScale = null;  // {s, ox, oy}: drawing coordinate → map pixel

  function preference(name, fallback) {
    try { var v = localStorage.getItem('nw.erd.' + name); return v === null ? fallback : v === '1'; } catch (e) { return fallback; }
  }
  function setPreference(name, on) {
    try { localStorage.setItem('nw.erd.' + name, on ? '1' : '0'); } catch (e) { /* not remembered */ }
  }
  function showPanel(node, btn, name, on) {
    node.hidden = !on;
    btn.classList.toggle('on', on);
    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    setPreference(name, on);
    if (on && node === map) drawMap();
  }

  canvas.tabIndex = 0;
  canvas.setAttribute('aria-label', 'Drawing canvas. Arrow keys move it, + and - zoom, 0 shows everything.');
  root.append(toolbar, canvas);
  form.insertBefore(root, form.firstChild);
  form.classList.add('visual');

  var IDLE = 'Drag “Entity” onto the canvas · drag the + of an attribute onto an attribute of another entity for a relationship · click a line end: one ↔ many · drag a line to bend it · drag the background to move around, with Shift to select';
  function say(text) {
    status.textContent = text || IDLE;
    status.title = status.textContent;      // the whole message when it does not fit on the line
  }

  function entityNode(e) { return surface.querySelector('.ent[data-id="' + e.id + '"]'); }

  /** Build every entity box again (after adding, removing, linking). Typing never calls this: it would take the focus away. */
  function render() {
    Array.prototype.slice.call(surface.querySelectorAll('.ent')).forEach(function (n) { n.remove(); });
    empty.hidden = entities.length > 0;
    entities.forEach(function (e) {
      if (dataMode) { surface.appendChild(dataBox(e)); return; }
      var box = el('div', 'ent');
      box.dataset.id = e.id;
      box.style.left = e.x + 'px';
      box.style.top = e.y + 'px';
      box.style.width = e.w + 'px';
      box.style.minHeight = height(e) + 'px';
      if (selected[e.id]) box.classList.add('selected');

      var head = el('div', 'ent-head');
      head.style.backgroundColor = COLORS[e.color];
      var name = el('input', 'ent-name');
      name.value = e.name;
      name.placeholder = 'EntityName';
      name.spellcheck = false;
      name.setAttribute('aria-label', 'Entity name');
      head.append(name, button('color', '', 'Change the colour'), button('x', '×', 'Remove this entity'));
      box.appendChild(head);

      e.attrs.forEach(function (a, i) {
        var linked = !!target(a);
        var row = el('div', 'ent-row' + (a.fk ? ' is-fk' : ''));
        row.dataset.index = i;
        var grip = el('span', 'grip', '⋮⋮');
        grip.title = 'Drag to change the order of the attributes';
        var pk = button('key pk' + (a.pk ? ' on' : ''), 'PK', a.pk ? 'Primary key (click to remove)' : 'Make this (part of) the primary key');
        pk.setAttribute('aria-pressed', a.pk ? 'true' : 'false');
        var fk = button('key fk' + (a.fk ? ' on' : ''), 'FK', a.fk
          ? (linked ? 'Foreign key (click to remove the mark and its relationship)' : 'Foreign key, not linked yet: drag its + onto the key it points to (click to remove the mark)')
          : 'Make this a foreign key');
        fk.setAttribute('aria-pressed', a.fk ? 'true' : 'false');
        var input = el('input', 'attr');
        input.value = a.name;
        input.placeholder = 'attribute';
        input.spellcheck = false;
        input.setAttribute('aria-label', 'Attribute name');
        var type = el('select', 'type' + (a.type ? '' : ' none'));
        type.setAttribute('aria-label', 'Data type');
        type.title = 'Data type';
        [''].concat(TYPES, a.type && TYPES.indexOf(a.type) < 0 ? [a.type] : []).forEach(function (t) {
          var option = el('option', '', t === '' ? 'type…' : t.toUpperCase());
          option.value = t;
          type.appendChild(option);
        });
        type.value = a.type;
        row.append(grip, pk, fk, input, type,
          button('link', '+', 'Relationship: drag onto the attribute of another entity that this one belongs with'),
          button('x', '×', 'Remove this attribute'));
        box.appendChild(row);
      });

      box.appendChild(button('ent-add', '+ attribute', 'Add an attribute'));
      var grow = el('span', 'resize');
      grow.title = 'Drag to make the entity bigger or smaller';
      box.appendChild(grow);
      surface.appendChild(box);
    });
    draw();
    if (dataMode) say(DATA_HELP);
  }

  // ---- data mode ---------------------------------------------------------------

  var DATA_HELP = 'Example data: fill in a few rows per entity to see whether your design holds real data · Enter: next row · Diagram: back to the drawing';
  var EXAMPLES = { int: '1', decimal: '9.95', varchar: 'text', text: 'long text', date: '2026-01-31', datetime: '2026-01-31 14:00', boolean: '0 / 1', blob: 'file.jpg' };

  /** The example rows of an entity (a few empty ones when it has none yet). */
  function rowsOf(e) {
    var key = norm(label(e), true);
    if (!sample[key] || !sample[key].length) {
      sample[key] = [];
      for (var i = 0; i < DATA_ROWS; i++) sample[key].push({});
    }
    return sample[key];
  }
  function cell(row, a) { var v = row[norm(a.name)]; return v === undefined || v === null ? '' : String(v).trim(); }
  function saveSample() {
    try { localStorage.setItem(dataKey, JSON.stringify(sample)); } catch (err) { /* private window: lasts until the page closes */ }
  }

  /** An entity as a small table: the attributes are the columns, every example row a row of cells. */
  function dataBox(e) {
    var box = el('div', 'ent data');
    box.dataset.id = e.id;
    box.style.left = e.x + 'px';
    box.style.top = e.y + 'px';
    box.style.minWidth = e.w + 'px';
    if (selected[e.id]) box.classList.add('selected');
    var head = el('div', 'ent-head');
    head.style.backgroundColor = COLORS[e.color];
    head.appendChild(el('span', 'ent-name', label(e)));
    box.appendChild(head);
    if (!e.attrs.length) {
      box.appendChild(el('p', 'ent-nodata', 'No attributes yet: add them in the diagram.'));
      return box;
    }
    var table = el('table', 'ent-data');
    var tr = el('tr');
    e.attrs.forEach(function (a) {
      var th = el('th', a.pk ? 'is-pk' : a.fk ? 'is-fk' : '');
      if (a.pk) th.appendChild(el('span', 'tag pk', 'PK'));
      if (a.fk) th.appendChild(el('span', 'tag fk', 'FK'));
      th.appendChild(el('span', 'col', a.name || '?'));
      var t = target(a);
      th.title = (a.name || 'attribute') + (a.type ? ' ' + a.type.toUpperCase() : '') + (a.pk ? ', primary key' : '')
        + (t ? ', foreign key to ' + label(t) + (a.refAttr ? '.' + a.refAttr.name : '') : a.fk ? ', foreign key (not linked)' : '');
      tr.appendChild(th);
    });
    tr.appendChild(el('th', 'act'));
    var thead = el('thead');
    thead.appendChild(tr);
    table.appendChild(thead);
    var tbody = el('tbody');
    rowsOf(e).forEach(function (row, ri) {
      var r = el('tr');
      e.attrs.forEach(function (a, ai) {
        var td = el('td');
        var input = el('input', 'cell' + (a.type === 'text' || a.type === 'datetime' ? ' wide' : ''));
        input.value = cell(row, a);
        input.placeholder = EXAMPLES[a.type] || '';
        input.spellcheck = false;
        input.dataset.row = ri;
        input.dataset.attr = ai;
        input.setAttribute('aria-label', label(e) + ' row ' + (ri + 1) + ', ' + (a.name || 'attribute'));
        td.appendChild(input);
        r.appendChild(td);
      });
      var act = el('td', 'act');
      act.appendChild(button('row-del', '×', 'Remove this row'));
      r.appendChild(act);
      tbody.appendChild(r);
    });
    table.appendChild(tbody);
    box.appendChild(table);
    box.appendChild(button('row-add', '+ row', 'Add an example row'));
    return box;
  }

  function addDataRow(e) {
    rowsOf(e).push({});
    saveSample();
    render();
  }

  function setDataMode(on) {
    dataMode = on;
    stopPicking();
    root.classList.toggle('data-mode', on);
    dataButton.classList.toggle('on', on);
    dataButton.setAttribute('aria-pressed', on ? 'true' : 'false');
    dataButton.textContent = on ? 'Diagram' : 'Data';
    render();
    if (!on) say();
  }

  // ---- lines ---------------------------------------------------------------

  /** Where a relationship leaves both boxes: [da, db] = +1 right side, -1 left side. */
  function sides(a, b) {
    if (a.x + boxW(a) / 2 < b.x) return [1, -1];
    if (a.x > b.x + boxW(b) / 2) return [-1, 1];
    return [1, 1];                    // above each other, or the entity itself: loop on the right
  }
  // Data mode: a box is as big as its table, and every line runs from name bar to name bar.
  function boxW(e) { var n = dataMode && entityNode(e); return n ? n.offsetWidth : e.w; }
  function boxH(e) { var n = dataMode && entityNode(e); return n ? n.offsetHeight : height(e); }
  function rowY(e, index) { return e.y + (index < 0 || dataMode ? HEAD_H / 2 : HEAD_H + index * ROW_H + ROW_H / 2); }

  function crowFoot(g, x1, y, ax) { [-7, 0, 7].forEach(function (dy) { svgEl('line', { x1: x1, y1: y, x2: ax, y2: y + dy }, g); }); }
  function oneBar(g, x2, y, bx, d) {
    svgEl('line', { x1: x2, y1: y, x2: bx, y2: y }, g);
    svgEl('line', { x1: bx + d * 7, y1: y - 7, x2: bx + d * 7, y2: y + 7 }, g);
  }

  function distance(p, q) { return Math.sqrt((p.x - q.x) * (p.x - q.x) + (p.y - q.y) * (p.y - q.y)); }
  function fix(n) { return Math.round(n * 10) / 10; }

  /**
   * The line of a relationship (one of relations()): {d: its path, mx, my: where
   * the × goes, pts: its corners, or null for a line without bend points}.
   * Without bend points it is one curve; with them, straight pieces from point
   * to point with rounded corners.
   */
  function route(r) {
    var x1 = r.ax + r.da * 16;
    var x2 = r.bx + r.db * 16;
    if (!r.via.length) {
      var k = Math.max(50, Math.abs(x2 - x1) / 2);
      var c1x = x1 + r.da * k;
      var c2x = x2 + r.db * k;
      return {
        d: 'M' + x1 + ' ' + r.ay + ' C' + c1x + ' ' + r.ay + ' ' + c2x + ' ' + r.by + ' ' + x2 + ' ' + r.by,
        mx: (x1 + 3 * c1x + 3 * c2x + x2) / 8, my: (r.ay + r.by) / 2,     // Bézier at t = 0.5
        pts: null
      };
    }
    var pts = [{ x: x1, y: r.ay }].concat(r.via, [{ x: x2, y: r.by }]);
    var d = 'M' + x1 + ' ' + r.ay;
    var longest = -1;
    var mx = x1;
    var my = r.ay;
    for (var i = 1; i < pts.length; i++) {
      var q = pts[i - 1];
      var p = pts[i];
      var n = pts[i + 1];
      var len = distance(p, q);
      if (len > longest) { longest = len; mx = (p.x + q.x) / 2; my = (p.y + q.y) / 2; }   // the ×: middle of the longest piece
      var next = n ? distance(n, p) : 0;
      var rad = Math.min(12, len / 2, next / 2);
      if (!n || rad < 1) { d += ' L' + fix(p.x) + ' ' + fix(p.y); continue; }
      d += ' L' + fix(p.x - (p.x - q.x) / len * rad) + ' ' + fix(p.y - (p.y - q.y) / len * rad)
        + ' Q' + fix(p.x) + ' ' + fix(p.y) + ' ' + fix(p.x + (n.x - p.x) / next * rad) + ' ' + fix(p.y + (n.y - p.y) / next * rad);
    }
    return { d: d, mx: mx, my: my, pts: pts };
  }

  /** Which piece of the line pts[i] → pts[i + 1] is nearest to p: its i. */
  function segmentAt(pts, p) {
    var best = 0;
    var bestD = Infinity;
    for (var i = 0; i < pts.length - 1; i++) {
      var a = pts[i];
      var b = pts[i + 1];
      var len2 = (b.x - a.x) * (b.x - a.x) + (b.y - a.y) * (b.y - a.y);
      var t = len2 ? Math.max(0, Math.min(1, ((p.x - a.x) * (b.x - a.x) + (p.y - a.y) * (b.y - a.y)) / len2)) : 0;
      var d = distance(p, { x: a.x + t * (b.x - a.x), y: a.y + t * (b.y - a.y) });
      if (d < bestD) { bestD = d; best = i; }
    }
    return best;
  }

  /**
   * One relationship: the line, the marks at both ends, a round click area on
   * each end (one ↔ many), a × in the middle and a handle on every bend point.
   * Its data goes on the group and on the click areas.
   */
  function relation(r) {
    var data = r.data;
    var g = svgEl('g', { 'class': 'rel' });
    Object.keys(data).forEach(function (key) { g.dataset[key] = data[key]; });
    var x1 = r.ax + r.da * 16;
    var x2 = r.bx + r.db * 16;
    var line = route(r);
    var hit = svgEl('path', { d: line.d, 'class': 'hit' }, g);
    svgEl('title', {}, hit).textContent = 'Drag to bend this line around an entity';
    svgEl('path', { d: line.d }, g);
    if (r.aMany) crowFoot(g, x1, r.ay, r.ax); else oneBar(g, x1, r.ay, r.ax, r.da);
    if (r.bMany) crowFoot(g, x2, r.by, r.bx); else oneBar(g, x2, r.by, r.bx, r.db);

    function endArea(x, y, end, isMany) {
      var c = svgEl('circle', { cx: x, cy: y, r: 12, 'class': 'end-hit' }, g);
      Object.keys(data).forEach(function (key) { c.dataset[key] = data[key]; });
      c.dataset.end = end;
      svgEl('title', {}, c).textContent = (isMany ? 'Many' : 'One') + ' ' + r.title[end] + '. Click to make it ' + (isMany ? 'one' : 'many') + '.';
    }
    endArea(r.ax + r.da * 9, r.ay, 'a', r.aMany);
    endArea(r.bx + r.db * 9, r.by, 'b', r.bMany);

    var del = svgEl('g', { 'class': 'rel-del', transform: 'translate(' + line.mx + ' ' + line.my + ')' }, g);
    Object.keys(data).forEach(function (key) { del.dataset[key] = data[key]; });
    svgEl('circle', { r: 9 }, del);
    svgEl('path', { d: 'M-3.5 -3.5 L3.5 3.5 M3.5 -3.5 L-3.5 3.5' }, del);
    svgEl('title', {}, del).textContent = 'Remove this relationship';

    r.via.forEach(function (p, i) {
      var c = svgEl('circle', { cx: p.x, cy: p.y, r: 5, 'class': 'via' }, g);
      c.dataset.point = i;
      svgEl('title', {}, c).textContent = 'Bend point: drag to move it, double-click to remove it';
    });
  }

  /** Every relationship as the numbers relation() and route() need: both ends, their sides and marks, its bend points. */
  function relations() {
    var list = [];
    function add(a, ai, b, bi, via, aMany, bMany, data) {
      if (dataMode) via = [];           // the boxes are other boxes there
      // With bend points a line leaves a box on the side of its first (last) point.
      var s = via.length
        ? [via[0].x > a.x + boxW(a) / 2 ? 1 : -1, via[via.length - 1].x > b.x + boxW(b) / 2 ? 1 : -1]
        : sides(a, b);
      list.push({
        ax: a.x + (s[0] > 0 ? boxW(a) : 0), ay: rowY(a, ai), da: s[0], aMany: aMany,
        bx: b.x + (s[1] > 0 ? boxW(b) : 0), by: rowY(b, bi), db: s[1], bMany: bMany,
        via: via, data: data,
        title: { a: label(a) + ' per ' + label(b), b: label(b) + ' per ' + label(a) }
      });
    }
    // Foreign keys: from the foreign key row (end "a", the child) to the row it points to (end "b", the parent).
    entities.forEach(function (e) {
      e.attrs.forEach(function (a, i) {
        var t = target(a);
        if (t) add(e, i, t, t.attrs.indexOf(a.refAttr), a.via, !a.unique, false, { kind: 'fk', entity: e.id, index: i });
      });
    });
    // Many-to-many lines: name bar to name bar.
    many.forEach(function (m, i) {
      var a = byId(m.a);
      var b = byId(m.b);
      if (a && b) add(a, -1, b, -1, m.via, true, true, { kind: 'mm', index: i });
    });
    return list;
  }

  /** Draw the relationship lines. */
  function draw() {
    while (lines.firstChild) lines.removeChild(lines.firstChild);
    relations().forEach(relation);
    drawMap();
  }

  // ---- bend points ------------------------------------------------------------

  /** The bend points of the relationship that data (of a .rel) names, or null. */
  function viaOf(data) {
    if (data.kind === 'mm') {
      var m = many[+data.index];
      return m ? m.via : null;
    }
    var e = byId(+data.entity);
    var a = e ? e.attrs[+data.index] : null;
    return a && target(a) ? a.via : null;
  }

  function relData(node) {
    return { kind: node.dataset.kind, entity: node.dataset.entity, index: node.dataset.index };
  }

  /** A new bend point at p, in the piece of the line that is nearest. Returns its place in the list, or -1. */
  function addBend(data, p) {
    var via = viaOf(data);
    if (!via) return -1;
    var r = relations().filter(function (o) {
      return o.data.kind === data.kind && String(o.data.index) === String(data.index) && (data.kind === 'mm' || String(o.data.entity) === String(data.entity));
    })[0];
    var pts = r ? route(r).pts : null;
    var i = pts ? segmentAt(pts, p) : 0;
    via.splice(i, 0, { x: Math.round(p.x), y: Math.round(p.y) });
    return i;
  }

  /** The bend points of the relationships that run between entities of list (they move along with them). */
  function innerVias(list, links) {
    var ids = {};
    list.forEach(function (e) { ids[e.id] = true; });
    var out = [];
    list.forEach(function (e) {
      e.attrs.forEach(function (a) { if (a.ref !== null && ids[a.ref]) out = out.concat(a.via); });
    });
    links.forEach(function (m) { if (ids[m.a] && ids[m.b]) out = out.concat(m.via); });
    return out;
  }

  // ---- copy and paste -----------------------------------------------------------

  /** What was copied last: here, or (not on the exam page) in another drawing in this browser. */
  function clipboard() {
    if (!isExam) {
      try {
        var text = localStorage.getItem(CLIP_KEY);
        if (text && (!clip || clip.text !== text)) clip = { text: text, pastes: 0 };
      } catch (err) { /* no storage: only what was copied on this page */ }
    }
    return clip;
  }

  function copyEntities(list) {
    if (!list.length) return;
    clip = { text: serialize(list), pastes: 0 };
    if (!isExam) {
      try { localStorage.setItem(CLIP_KEY, clip.text); } catch (err) { /* stays on this page */ }
    }
    say('Copied ' + list.length + ' entit' + (list.length === 1 ? 'y' : 'ies') + '. Ctrl+V pastes ' + (list.length === 1 ? 'it' : 'them') + '.');
  }

  /**
   * Add the entities of text to the drawing, under new names where a name is
   * taken. at: where their top left corner goes; without it they land step × 30
   * pixels from where they were copied (or in the middle of the screen, when
   * that is out of sight). The new entities are selected.
   */
  function pasteText(text, at, step) {
    var d = parse(text, entities);
    if (!d.list.length) return null;
    if (dataMode) setDataMode(false);
    var x1 = Infinity, y1 = Infinity, x2 = -Infinity, y2 = -Infinity;
    d.list.forEach(function (e) {
      x1 = Math.min(x1, e.x); y1 = Math.min(y1, e.y);
      x2 = Math.max(x2, e.x + e.w); y2 = Math.max(y2, e.y + height(e));
    });
    var dx = 30 * step;
    var dy = 30 * step;
    if (at) {
      dx = at.x - x1;
      dy = at.y - y1;
    } else {
      var vx = -view.x / view.z, vy = -view.y / view.z, vw = canvas.clientWidth / view.z, vh = canvas.clientHeight / view.z;
      if (x1 + dx > vx + vw - 60 || x2 + dx < vx + 60 || y1 + dy > vy + vh - 60 || y2 + dy < vy + 60) {
        dx = vx + vw / 2 - (x1 + x2) / 2;
        dy = vy + vh / 2 - (y1 + y2) / 2;
      }
    }
    dx = Math.round(dx);
    dy = Math.round(dy);
    var taken = {};
    var ids = {};
    entities.forEach(function (e) { taken[norm(label(e), true)] = true; });
    d.list.forEach(function (e) {
      ids[e.id] = true;
      e.x += dx;
      e.y += dy;
      var name = e.name;
      for (var n = 1; taken[norm(name, true)]; n++) name = e.name + 'Copy' + (n > 1 ? n : '');
      e.name = name;
      taken[norm(name, true)] = true;
    });
    // Bend points come along between the new entities; a line to an entity that was already here starts straight.
    d.list.forEach(function (e) {
      e.attrs.forEach(function (a) { if (a.ref !== null && !ids[a.ref]) a.via = []; });
    });
    innerVias(d.list, d.many).forEach(function (p) { p.x += dx; p.y += dy; });
    entities = entities.concat(d.list);
    many = many.concat(d.many);
    render();
    select(d.list);
    sync();
    canvas.focus({ preventScroll: true });
    return d.list;
  }

  function paste(at) {
    var c = clipboard();
    if (!c) { say('Nothing to paste yet: select entities and press Ctrl+C first.'); return; }
    if (!at) c.pastes++;
    var list = pasteText(c.text, at, c.pastes);
    if (list) say('Pasted ' + list.length + ' entit' + (list.length === 1 ? 'y' : 'ies') + ': drag one of them to move them.');
  }

  function duplicate(list) {
    if (!list.length) return;
    var made = pasteText(serialize(list), null, 1);
    if (made) say('Duplicated ' + made.length + ' entit' + (made.length === 1 ? 'y' : 'ies') + ': drag one of them to move them.');
  }

  // ---- selection --------------------------------------------------------------

  function selection() { return entities.filter(function (e) { return selected[e.id]; }); }

  function markSelection() {
    entities.forEach(function (e) {
      var node = entityNode(e);
      if (node) node.classList.toggle('selected', !!selected[e.id]);
    });
    drawMap();
  }

  function select(list) {
    selected = {};
    list.forEach(function (e) { selected[e.id] = true; });
    markSelection();
  }

  // ---- the overview map ---------------------------------------------------------

  /** Every entity as a small block, and the part of the canvas that is on screen as a frame. */
  function drawMap() {
    if (map.hidden) return;
    while (mapSvg.firstChild) mapSvg.removeChild(mapSvg.firstChild);
    var vx = -view.x / view.z;
    var vy = -view.y / view.z;
    var vw = canvas.clientWidth / view.z;
    var vh = canvas.clientHeight / view.z;
    var b = bounds() || { x1: vx, y1: vy, x2: vx + vw, y2: vy + vh };
    var x1 = Math.min(b.x1, vx), y1 = Math.min(b.y1, vy), x2 = Math.max(b.x2, vx + vw), y2 = Math.max(b.y2, vy + vh);
    var scale = Math.min((MAP_W - 8) / (x2 - x1), (MAP_H - 8) / (y2 - y1));
    mapScale = { s: scale, ox: (MAP_W - (x2 - x1) * scale) / 2 - x1 * scale, oy: (MAP_H - (y2 - y1) * scale) / 2 - y1 * scale };
    mapSvg.setAttribute('width', MAP_W);
    mapSvg.setAttribute('height', MAP_H);
    entities.forEach(function (e) {
      svgEl('rect', {
        x: e.x * scale + mapScale.ox, y: e.y * scale + mapScale.oy,
        width: Math.max(2, boxW(e) * scale), height: Math.max(2, boxH(e) * scale),
        fill: COLORS[e.color], 'class': selected[e.id] ? 'sel' : ''
      }, mapSvg);
    });
    svgEl('rect', { x: vx * scale + mapScale.ox, y: vy * scale + mapScale.oy, width: vw * scale, height: vh * scale, 'class': 'frame' }, mapSvg);
  }

  /** Put the point of the drawing under this spot of the map in the middle of the canvas. */
  function mapGo(ev) {
    if (!mapScale) return;
    var r = mapSvg.getBoundingClientRect();
    var x = (ev.clientX - r.left - mapScale.ox) / mapScale.s;
    var y = (ev.clientY - r.top - mapScale.oy) / mapScale.s;
    view.x = canvas.clientWidth / 2 - x * view.z;
    view.y = canvas.clientHeight / 2 - y * view.z;
    applyView();
  }
  var mapDragging = false;
  map.addEventListener('pointerdown', function (ev) {
    ev.preventDefault();
    ev.stopPropagation();
    mapDragging = true;
    map.setPointerCapture(ev.pointerId);
    mapGo(ev);
  });
  map.addEventListener('pointermove', function (ev) { if (mapDragging) mapGo(ev); });
  map.addEventListener('pointerup', function () { mapDragging = false; });
  map.addEventListener('pointercancel', function () { mapDragging = false; });

  // ---- the view: moving around and zooming ----------------------------------

  function applyView() {
    surface.style.transform = 'translate(' + view.x + 'px, ' + view.y + 'px) scale(' + view.z + ')';
    canvas.style.backgroundPosition = view.x + 'px ' + view.y + 'px';
    canvas.style.backgroundSize = (20 * view.z) + 'px ' + (20 * view.z) + 'px';
    zoomLabel.textContent = Math.round(view.z * 100) + '%';
    drawMap();
  }

  /** Zoom to z, keeping the point (cx, cy) of the canvas where it is (default: its middle). */
  function zoomTo(z, cx, cy) {
    z = Math.min(MAX_Z, Math.max(MIN_Z, z));
    if (cx === undefined) { cx = canvas.clientWidth / 2; cy = canvas.clientHeight / 2; }
    view.x = cx - (cx - view.x) * z / view.z;
    view.y = cy - (cy - view.y) * z / view.z;
    view.z = z;
    applyView();
  }

  function pan(dx, dy) {
    view.x += dx;
    view.y += dy;
    applyView();
  }

  /** The rectangle around everything that is drawn (lines that loop out on the right included). */
  function bounds() {
    if (!entities.length) return null;
    var b = { x1: Infinity, y1: Infinity, x2: -Infinity, y2: -Infinity };
    entities.forEach(function (e) {
      b.x1 = Math.min(b.x1, e.x);
      b.y1 = Math.min(b.y1, e.y);
      b.x2 = Math.max(b.x2, e.x + boxW(e));
      b.y2 = Math.max(b.y2, e.y + boxH(e));
    });
    if (!dataMode) {
      innerVias(entities, many).forEach(function (p) {
        b.x1 = Math.min(b.x1, p.x); b.y1 = Math.min(b.y1, p.y);
        b.x2 = Math.max(b.x2, p.x); b.y2 = Math.max(b.y2, p.y);
      });
    }
    b.x1 -= 30; b.y1 -= 20; b.x2 += 90; b.y2 += 20;
    return b;
  }

  /** Show the whole drawing, not larger than 100%. */
  function fit() {
    var b = bounds();
    if (!b) { view = { x: 0, y: 0, z: 1 }; applyView(); return; }
    var z = Math.min(1, canvas.clientWidth / (b.x2 - b.x1), canvas.clientHeight / (b.y2 - b.y1));
    view.z = Math.max(MIN_Z, z);
    view.x = (canvas.clientWidth - (b.x2 - b.x1) * view.z) / 2 - b.x1 * view.z;
    view.y = Math.max(8, (canvas.clientHeight - (b.y2 - b.y1) * view.z) / 2) - b.y1 * view.z;
    applyView();
  }

  function setFull(on) {
    form.classList.toggle('full', on);
    document.documentElement.classList.toggle('erd-full', on);
    fullButton.textContent = on ? 'Exit full screen' : 'Full screen';
    fit();
  }

  // ---- the drawing as a picture ----------------------------------------------

  function esc(text) {
    return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  /** The drawing as a stand-alone SVG text (what "PNG" turns into a picture), or null when the canvas is empty. */
  function svgText() {
    var b = bounds();
    if (!b) return null;
    var w = Math.ceil(b.x2 - b.x1);
    var h = Math.ceil(b.y2 - b.y1);
    var mono = 'ui-monospace, Menlo, Consolas, monospace';
    var sans = 'system-ui, -apple-system, Segoe UI, Arial, sans-serif';
    var out = '<svg xmlns="http://www.w3.org/2000/svg" width="' + w + '" height="' + h + '" viewBox="' + b.x1 + ' ' + b.y1 + ' ' + w + ' ' + h + '">'
      + '<rect x="' + b.x1 + '" y="' + b.y1 + '" width="' + w + '" height="' + h + '" fill="#fff"/>'
      + '<g stroke="#64748b" stroke-width="1.5" fill="none" stroke-linecap="round">';
    function seg(x1, y1, x2, y2) { return '<line x1="' + x1 + '" y1="' + y1 + '" x2="' + x2 + '" y2="' + y2 + '"/>'; }
    function mark(isMany, x, y, edge, d) {
      if (isMany) return seg(x, y, edge, y - 7) + seg(x, y, edge, y) + seg(x, y, edge, y + 7);
      return seg(x, y, edge, y) + seg(edge + d * 7, y - 7, edge + d * 7, y + 7);
    }
    relations().forEach(function (r) {
      var x1 = r.ax + r.da * 16;
      var x2 = r.bx + r.db * 16;
      out += '<path d="' + route(r).d + '"/>'
        + mark(r.aMany, x1, r.ay, r.ax, r.da) + mark(r.bMany, x2, r.by, r.bx, r.db);
    });
    out += '</g>';
    entities.forEach(function (e) {
      var eh = height(e) - FOOT_H + 6;              // without the "+ attribute" row
      out += '<g transform="translate(' + e.x + ' ' + e.y + ')">'
        + '<rect width="' + e.w + '" height="' + eh + '" rx="6" fill="#fff" stroke="#c9cfd6"/>'
        + '<path d="M0 6 Q0 0 6 0 H' + (e.w - 6) + ' Q' + e.w + ' 0 ' + e.w + ' 6 V' + HEAD_H + ' H0 Z" fill="' + COLORS[e.color] + '"/>'
        + '<text x="' + (e.w / 2) + '" y="22" text-anchor="middle" fill="#fff" font-family="' + sans + '" font-size="14" font-weight="600">' + esc(label(e)) + '</text>';
      e.attrs.forEach(function (a, i) {
        var top = HEAD_H + i * ROW_H;
        var base = top + 19;
        if (i > 0) out += '<line x1="8" y1="' + top + '" x2="' + (e.w - 8) + '" y2="' + top + '" stroke="#eceff3"/>';
        if (a.pk) out += '<text x="8" y="' + base + '" fill="#1d4ed8" font-family="' + mono + '" font-size="10" font-weight="700">PK</text>';
        if (a.fk) out += '<text x="' + (a.pk ? 26 : 8) + '" y="' + base + '" fill="#6d28d9" font-family="' + mono + '" font-size="10" font-weight="700">FK</text>';
        out += '<text x="46" y="' + base + '" fill="#1f2328" font-family="' + mono + '" font-size="13"' + (a.pk || a.fk ? ' font-weight="700"' : '') + '>' + esc(a.name) + '</text>'
          + '<text x="' + (e.w - 10) + '" y="' + base + '" text-anchor="end" fill="#6b7280" font-family="' + mono + '" font-size="11">' + esc(a.type.toUpperCase()) + '</text>';
      });
      out += '</g>';
    });
    return { svg: out + '</svg>', width: w, height: h };
  }

  function savePng() {
    var drawing = svgText();
    if (!drawing) { say('Nothing to save yet: the canvas is empty.'); return; }
    var img = new Image();
    img.onload = function () {
      var scale = 2;                                 // sharp on a projector and in a document
      var c = document.createElement('canvas');
      c.width = drawing.width * scale;
      c.height = drawing.height * scale;
      var g = c.getContext('2d');
      g.fillStyle = '#fff';
      g.fillRect(0, 0, c.width, c.height);
      g.drawImage(img, 0, 0, c.width, c.height);
      c.toBlob(function (blob) {
        if (!blob) { say('The picture could not be made in this browser.'); return; }
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'erd.png';
        root.appendChild(a);
        a.click();
        a.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
        say('Saved erd.png (' + c.width + ' × ' + c.height + ' pixels).');
      }, 'image/png');
    };
    img.onerror = function () { say('The picture could not be made in this browser.'); };
    img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(drawing.svg);
  }

  // -------------------------------------------------------------------------
  // Changing the model
  // -------------------------------------------------------------------------

  /** A free spot for an entity that was added with a click. */
  function freeSpot() {
    // In the part of the canvas that is on screen now.
    var left = Math.round(-view.x / view.z);
    var top = Math.round(-view.y / view.z);
    var perRow = Math.max(1, Math.floor((canvas.clientWidth / view.z - PAD) / (BOX_W + GAP_X)));
    for (var n = 0; n < 200; n++) {
      var x = left + PAD + (n % perRow) * (BOX_W + GAP_X);
      var y = top + PAD + Math.floor(n / perRow) * 190;
      var taken = entities.some(function (e) {
        return x < e.x + e.w + 20 && x + BOX_W + 20 > e.x && y < e.y + height(e) + 20 && y + 120 > e.y;
      });
      if (!taken) return { x: x, y: y };
    }
    return { x: left + PAD, y: top + PAD };
  }

  function addEntity(x, y) {
    if (dataMode) setDataMode(false);       // a new entity is drawn in the diagram
    var n = entities.length + 1;
    var name;
    do { name = 'Entity' + n++; } while (entities.some(function (e) { return e.name === name; }));
    var e = { id: nextId++, name: name, x: Math.round(x), y: Math.round(y), w: BOX_W, h: 0, color: 'blue', attrs: [] };
    entities.push(e);
    render();
    sync();
    var input = entityNode(e).querySelector('.ent-name');
    input.focus();
    input.select();
  }

  function addAttribute(e) {
    e.attrs.push({ name: '', type: '', pk: false, fk: false, ref: null, refAttr: null, unique: false, via: [] });
    render();
    sync();
    var inputs = entityNode(e).querySelectorAll('.attr');
    inputs[inputs.length - 1].focus();
  }

  function unlink(a) {
    a.ref = null;
    a.refAttr = null;
    a.unique = false;
    a.via = [];
  }

  /** Nothing may keep pointing to an attribute or entity that is gone. */
  function forget(test) {
    entities.forEach(function (o) {
      o.attrs.forEach(function (a) { if (a.ref !== null && test(a)) unlink(a); });
    });
    many.forEach(function (m) { if (m.was && test(m.was.attr)) m.was = null; });
  }

  /**
   * A relationship between two rows. Which one holds the foreign key: the one
   * marked FK; otherwise the one that is not a primary key; otherwise the row
   * the line was started from (e1, a1). via: the bend points that were dropped
   * on the way from a1 to a2.
   */
  function linkRows(e1, a1, e2, a2, via) {
    if (a1 === a2) return;
    var firstIsKey;
    if (a1.fk !== a2.fk) firstIsKey = a1.fk;
    else if (a1.pk !== a2.pk) firstIsKey = !a1.pk;
    else firstIsKey = true;
    var child = firstIsKey ? e1 : e2;
    var fk = firstIsKey ? a1 : a2;
    var parent = firstIsKey ? e2 : e1;
    var key = firstIsKey ? a2 : a1;
    fk.fk = true;
    fk.ref = parent.id;
    fk.refAttr = key;
    fk.unique = false;
    fk.via = !via ? [] : firstIsKey ? via : via.slice().reverse();
    if (!fk.type && key.type) fk.type = key.type;      // a foreign key has the type of the key it points to
    render();
    sync();
    say((child === parent ? label(child) + ' is linked to itself: ' : 'One ' + label(parent) + ' has many ' + label(child) + ': ')
      + (fk.name || 'the attribute') + ' is the foreign key' + (key.name ? ' to ' + key.name : '') + '. Click an end of the line to switch between one and many.');
  }

  /** A click on an end of a line: one ↔ many. */
  function toggleEnd(data) {
    if (data.kind === 'mm') {
      var m = many[+data.index];
      if (!m) return;
      // Back to the foreign key this line was made from, when the clicked end is the side that was "one".
      var one = data.end === 'a' ? m.a : m.b;
      if (m.was && m.was.parent === one && byId(m.was.parent) && byId(m.was.child) && byId(m.was.child).attrs.indexOf(m.was.attr) >= 0) {
        m.was.attr.fk = true;
        m.was.attr.ref = m.was.parent;
        m.was.attr.refAttr = byId(m.was.parent).attrs.indexOf(m.was.key) >= 0 ? m.was.key : null;
        m.was.attr.via = (m.a === m.was.parent ? m.via.slice().reverse() : m.via.slice());
        many.splice(+data.index, 1);
        say('One-to-many again: ' + (m.was.attr.name || 'the attribute') + ' is the foreign key.');
      } else {
        say('A “one” side needs a foreign key in the other entity: add that attribute there and drag its + onto the key. Then remove this line.');
        return;
      }
    } else {
      var child = byId(+data.entity);
      var attr = child ? child.attrs[+data.index] : null;
      var parent = attr ? target(attr) : null;
      if (!attr || !parent) return;
      if (data.end === 'a') {
        // The child's end: many ↔ one (one-to-one).
        attr.unique = !attr.unique;
        say(attr.unique ? 'One-to-one: every ' + label(parent) + ' has at most one ' + label(child) + '.'
                        : 'One ' + label(parent) + ' has many ' + label(child) + '.');
      } else if (attr.unique) {
        say('To turn the relationship around, the foreign key must be in ' + label(parent) + ': remove this line and draw it from an attribute there.');
        return;
      } else if (child !== parent) {
        // Many at both ends: no foreign key can hold that. The attribute stays, without its link.
        many.push({ a: parent.id, b: child.id, was: { parent: parent.id, child: child.id, attr: attr, key: attr.refAttr }, via: attr.via.slice().reverse() });
        unlink(attr);
        say('Many-to-many: no foreign key can store this. Tables need a link table in between.');
      } else {
        return;
      }
    }
    render();
    sync();
  }

  function removeRelation(data) {
    if (data.kind === 'mm') {
      many.splice(+data.index, 1);
    } else {
      var e = byId(+data.entity);
      if (e && e.attrs[+data.index]) unlink(e.attrs[+data.index]);
    }
    render();
    sync();
    say('Relationship removed. The attribute is still there.');
  }

  function stopPicking() {
    picking = null;
    canvas.classList.remove('picking');
    say();
  }

  function fromEvent(ev) {
    var box = ev.target.closest('.ent');
    if (!box) return null;
    var e = byId(+box.dataset.id);
    var row = ev.target.closest('.ent-row');
    return { box: box, entity: e, row: row, attr: row && e ? e.attrs[+row.dataset.index] : null };
  }

  var MAGNET = 40;      // px around an entity (on screen) in which a dragged + is pulled to it

  /**
   * Where a relationship being dragged from (from, fromAttr) would land: the
   * entity at or near the drawing point p, and the row in it that fits best.
   * Over the rows: the row under the pointer. On the name bar or in the margin
   * around the entity: the attribute with the same name as the dragged one,
   * else the primary key, else the nearest row. Returns {entity, attr, index} or null.
   */
  function magnet(p, from, fromAttr) {
    var reach = MAGNET / view.z;
    var best = null;
    entities.forEach(function (e) {
      if (!e.attrs.length) return;
      var h = height(e);
      var dx = Math.max(e.x - p.x, 0, p.x - (e.x + e.w));
      var dy = Math.max(e.y - p.y, 0, p.y - (e.y + h));
      var d = Math.sqrt(dx * dx + dy * dy);
      if (d > reach || (best && d >= best.d)) return;
      var inside = d === 0;
      var index = -1;
      var onRows = inside && p.y >= e.y + HEAD_H && p.y < e.y + HEAD_H + e.attrs.length * ROW_H;
      if (onRows) {
        index = Math.floor((p.y - e.y - HEAD_H) / ROW_H);
      } else {
        var wanted = fromAttr.name ? norm(fromAttr.name) : '';
        e.attrs.some(function (a, i) { if (wanted && norm(a.name) === wanted && a !== fromAttr) { index = i; return true; } return false; });
        if (index < 0) e.attrs.some(function (a, i) { if (a.pk && a !== fromAttr) { index = i; return true; } return false; });
        if (index < 0) {
          var y = Math.max(e.y + HEAD_H, Math.min(p.y, e.y + HEAD_H + e.attrs.length * ROW_H - 1));
          index = Math.floor((y - e.y - HEAD_H) / ROW_H);
        }
      }
      if (e.attrs[index] === fromAttr) {
        // Not the row the line starts from: the next one, if there is one.
        index = e.attrs.length > 1 ? (index + 1) % e.attrs.length : -1;
      }
      if (index < 0) return;
      best = { entity: e, attr: e.attrs[index], index: index, d: d };
    });
    return best;
  }

  /** The attribute row under the pointer, as {entity, attr, row}. */
  function rowUnder(ev) {
    var over = document.elementFromPoint(ev.clientX, ev.clientY);
    var row = over && over.closest ? over.closest('.ent-row') : null;
    if (!row) return null;
    var e = byId(+row.closest('.ent').dataset.id);
    var a = e ? e.attrs[+row.dataset.index] : null;
    return a ? { entity: e, attr: a, row: row } : null;
  }

  /** A pointer position in the coordinates of the drawing (the view may be moved and zoomed). */
  function surfacePoint(ev) {
    var r = canvas.getBoundingClientRect();
    return { x: (ev.clientX - r.left - canvas.clientLeft - view.x) / view.z, y: (ev.clientY - r.top - canvas.clientTop - view.y) / view.z };
  }

  function clearMarks(cls) {
    Array.prototype.forEach.call(surface.querySelectorAll('.' + cls), function (n) { n.classList.remove(cls); });
  }

  // -------------------------------------------------------------------------
  // Events
  // -------------------------------------------------------------------------

  // New entity: drag the block onto the canvas, or click it.
  chip.addEventListener('dragstart', function (ev) {
    ev.dataTransfer.setData('text/plain', 'entity');
    ev.dataTransfer.effectAllowed = 'copy';
  });
  canvas.addEventListener('dragover', function (ev) { ev.preventDefault(); ev.dataTransfer.dropEffect = 'copy'; });
  canvas.addEventListener('drop', function (ev) {
    ev.preventDefault();
    var p = surfacePoint(ev);
    addEntity(p.x - BOX_W / 2, p.y - HEAD_H / 2);
  });
  function addAtFreeSpot() {
    var spot = freeSpot();
    addEntity(spot.x, spot.y);
  }
  chip.addEventListener('click', addAtFreeSpot);
  chip.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); addAtFreeSpot(); }
  });

  // Typing a name.
  surface.addEventListener('input', function (ev) {
    if (ev.target.tagName !== 'INPUT') return;
    var hit = fromEvent(ev);
    if (!hit || !hit.entity) return;
    if (ev.target.classList.contains('cell')) {
      // Data mode: an example value. Not part of the design, so no history step.
      var a = hit.entity.attrs[+ev.target.dataset.attr];
      var row = rowsOf(hit.entity)[+ev.target.dataset.row];
      if (a && row) { row[norm(a.name)] = ev.target.value; saveSample(); }
      return;
    }
    var clean = cleanName(ev.target.value);
    if (clean !== ev.target.value) ev.target.value = clean;   // letters, digits and _ only
    if (ev.target.classList.contains('ent-name')) hit.entity.name = clean;
    else if (hit.attr) hit.attr.name = clean;
    sync(true);
  });

  // Choosing a data type.
  surface.addEventListener('change', function (ev) {
    if (!ev.target.classList.contains('type')) return;
    var hit = fromEvent(ev);
    if (!hit || !hit.attr) return;
    hit.attr.type = ev.target.value;
    ev.target.classList.toggle('none', ev.target.value === '');
    sync();
  });

  // Enter: on to the next attribute (a new one after the last).
  surface.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Enter' || ev.ctrlKey || ev.metaKey || ev.target.tagName !== 'INPUT') return;
    var hit = fromEvent(ev);
    if (!hit || !hit.entity) return;
    ev.preventDefault();
    if (ev.target.classList.contains('cell')) {
      // Data mode: the same column one row down (a new row after the last).
      var ri = +ev.target.dataset.row + 1;
      var ai = ev.target.dataset.attr;
      if (ri >= rowsOf(hit.entity).length) addDataRow(hit.entity);
      var next = entityNode(hit.entity).querySelector('.cell[data-row="' + ri + '"][data-attr="' + ai + '"]');
      if (next) next.focus();
      return;
    }
    var inputs = hit.box.querySelectorAll('.attr');
    var index = hit.row ? +hit.row.dataset.index + 1 : 0;
    if (index < inputs.length) inputs[index].focus();
    else if (!hit.row || ev.target.value !== '') addAttribute(hit.entity);
  });

  // Buttons inside an entity, the ends of a line, and choosing the other row of a relationship by clicking.
  surface.addEventListener('click', function (ev) {
    var hit = fromEvent(ev);
    if (picking) {
      var p = picking;
      stopPicking();
      if (hit && hit.attr) linkRows(p.entity, p.attr, hit.entity, hit.attr);
      else if (hit) say('Click an attribute, not the entity: a relationship joins two attributes.');
      return;
    }
    if (dataMode) {
      if (!hit || !hit.entity) return;
      if (ev.target.closest('.row-add')) {
        addDataRow(hit.entity);
        var cells = entityNode(hit.entity).querySelectorAll('.cell');
        if (cells.length) cells[cells.length - hit.entity.attrs.length].focus();
      } else if (ev.target.closest('.row-del')) {
        var tr = ev.target.closest('tr');
        var input = tr && tr.querySelector('.cell');
        if (input) {
          rowsOf(hit.entity).splice(+input.dataset.row, 1);
          saveSample();
          render();
        }
      }
      return;
    }
    var end = ev.target.closest('.end-hit');
    if (end) { toggleEnd(end.dataset); return; }
    var del = ev.target.closest('.rel-del');
    if (del) { removeRelation(del.dataset); return; }

    if (!hit || !hit.entity || ev.target.tagName !== 'BUTTON') return;
    var e = hit.entity;
    var a = hit.attr;
    var cls = ev.target.classList;
    if (cls.contains('ent-add')) {
      addAttribute(e);
      return;
    }
    if (cls.contains('color')) {
      e.color = COLOR_NAMES[(COLOR_NAMES.indexOf(e.color) + 1) % COLOR_NAMES.length];
    } else if (cls.contains('pk') && a) {
      a.pk = !a.pk;
    } else if (cls.contains('fk') && a) {
      a.fk = !a.fk;
      if (!a.fk) unlink(a);                       // no foreign key, no relationship
      say(a.fk ? 'Foreign key. Now drag its + onto the key it points to.' : '');
    } else if (cls.contains('x') && a) {
      e.attrs.splice(e.attrs.indexOf(a), 1);
      forget(function (o) { return o.refAttr === a || o === a; });
    } else if (cls.contains('x')) {
      removeEntity(e);
      return;
    } else {
      return;
    }
    render();
    sync();
  });

  // The click that ends a drag (or the first click of click-then-click linking) is not a click on what is under it.
  var suppressClick = false;
  function swallowNextClick() {
    suppressClick = true;
    setTimeout(function () { suppressClick = false; }, 0);
  }
  surface.addEventListener('click', function (ev) {
    if (suppressClick) { ev.stopImmediatePropagation(); ev.preventDefault(); }
  }, true);

  surface.addEventListener('pointerdown', function (ev) {
    if (ev.button !== undefined && ev.button !== 0) return;
    var cls = ev.target.classList;
    var relNode = !dataMode && !picking && ev.target.closest('.rel');
    if (relNode && (cls.contains('via') || cls.contains('hit'))) {
      // A line or one of its bend points: drag to bend it. point -1: a new point, made when the pointer really moves.
      var data = relData(relNode);
      var via = viaOf(data);
      if (!via) return;
      ev.preventDefault();
      canvas.focus({ preventScroll: true });
      drag = { kind: 'bend', data: data, via: via, point: cls.contains('via') ? +ev.target.dataset.point : -1, startX: ev.clientX, startY: ev.clientY, moved: false };
      return;
    }
    var hit = fromEvent(ev);
    if (!hit || !hit.entity || picking) return;
    var e = hit.entity;
    var p = surfacePoint(ev);
    if (cls.contains('link') && hit.attr) {
      // Relationship: from this row to another row.
      ev.preventDefault();
      drag = { kind: 'link', entity: e, attr: hit.attr, index: +hit.row.dataset.index, moved: false, startX: ev.clientX, startY: ev.clientY, temp: null, via: [], p: p };
    } else if (cls.contains('grip') && hit.attr) {
      // Order of the attributes.
      ev.preventDefault();
      drag = { kind: 'order', entity: e, box: hit.box, from: +hit.row.dataset.index, to: +hit.row.dataset.index };
      hit.row.classList.add('dragging');
    } else if (cls.contains('resize')) {
      ev.preventDefault();
      drag = { kind: 'size', entity: e, box: hit.box, x: p.x, y: p.y, w: e.w, h: height(e) };
      hit.box.classList.add('sizing');
    } else if (ev.target.closest('.ent-head') && ev.target.tagName !== 'INPUT' && ev.target.tagName !== 'BUTTON') {
      ev.preventDefault();
      canvas.focus({ preventScroll: true });
      if (ev.shiftKey || ev.ctrlKey || ev.metaKey) {
        // Add to or take from the selection; no move.
        if (selected[e.id]) delete selected[e.id]; else selected[e.id] = true;
        markSelection();
        return;
      }
      if (!selected[e.id]) select([e]);           // dragging one that is not selected: only that one
      drag = {
        kind: 'move', entity: e, x: p.x, y: p.y,
        group: selection().map(function (o) { return { e: o, x: o.x, y: o.y, box: entityNode(o) }; }),
        // The bend points of the lines between them move along.
        points: innerVias(selection(), many).map(function (pt) { return { p: pt, x: pt.x, y: pt.y }; })
      };
      drag.group.forEach(function (g) { g.box.classList.add('moving'); });
    }
  });

  // Right-click: a small menu. On the background "New entity here", on an entity what you can do with it.
  var menu = el('div', 'erd-menu');
  menu.hidden = true;
  canvas.appendChild(menu);
  function closeMenu() { menu.hidden = true; }
  function openMenu(ev, items) {
    while (menu.firstChild) menu.removeChild(menu.firstChild);
    items.forEach(function (item) {
      var b = button('', item[0]);
      b.disabled = item[2] === false;
      b.addEventListener('click', function () { closeMenu(); item[1](); });
      menu.appendChild(b);
    });
    menu.hidden = false;
    var r = canvas.getBoundingClientRect();
    menu.style.left = Math.max(4, Math.min(ev.clientX - r.left, canvas.clientWidth - menu.offsetWidth - 4)) + 'px';
    menu.style.top = Math.max(4, Math.min(ev.clientY - r.top, canvas.clientHeight - menu.offsetHeight - 4)) + 'px';
  }
  function selectAll() {
    select(entities);
    say(entities.length ? 'All ' + entities.length + ' entities selected: drag one of them to move them together. Esc clears the selection.' : 'Nothing to select yet.');
  }
  function removeSelected() {
    var list = selection();
    if (!list.length) return;
    if (!window.confirm('Remove the ' + list.length + ' selected entit' + (list.length === 1 ? 'y' : 'ies') + ' and their attributes?')) return;
    entities = entities.filter(function (o) { return !selected[o.id]; });
    forget(function (o) { return !!selected[o.ref]; });
    many = many.filter(function (m) { return !selected[m.a] && !selected[m.b]; });
    selected = {};
    render();
    sync();
  }
  function removeEntity(e) {
    if (e.attrs.length && !window.confirm('Remove the entity ' + label(e) + ' and its attributes?')) return;
    entities = entities.filter(function (o) { return o !== e; });
    forget(function (o) { return o.ref === e.id; });
    many = many.filter(function (m) { return m.a !== e.id && m.b !== e.id; });
    render();
    sync();
  }
  canvas.addEventListener('contextmenu', function (ev) {
    if (ev.target.closest('.erd-menu, .erd-map, .erd-legend')) { ev.preventDefault(); return; }
    if (/^(INPUT|SELECT|TEXTAREA)$/.test(ev.target.tagName)) return;      // the browser's own menu: copy, paste
    ev.preventDefault();
    stopPicking();
    var p = surfacePoint(ev);
    var hit = fromEvent(ev);
    var relNode = !dataMode && ev.target.closest('.rel');
    var via = relNode ? viaOf(relData(relNode)) : null;
    if (dataMode) {
      var de = hit && hit.entity;
      openMenu(ev, (de ? [['Add row', function () { addDataRow(de); }]] : []).concat([
        ['Back to the diagram', function () { setDataMode(false); }],
        ['Show everything (Fit)', fit]
      ]));
    } else if (via) {
      // A line, or one of its bend points.
      var data = relData(relNode);
      var point = ev.target.classList.contains('via') ? +ev.target.dataset.point : -1;
      openMenu(ev, [
        point >= 0
          ? ['Remove this bend point', function () { via.splice(point, 1); draw(); sync(); }]
          : ['Add bend point here', function () { addBend(data, p); draw(); sync(); }],
        ['Straighten the line', function () { via.length = 0; draw(); sync(); }, via.length > 0],
        ['Remove relationship', function () { removeRelation(data); }]
      ]);
    } else if (hit && hit.entity) {
      var e = hit.entity;
      // On one of the selected entities: copy them all. On another one: only that one.
      var which = function () { return selected[e.id] ? selection() : [e]; };
      var count = selected[e.id] ? selection().length : 1;
      openMenu(ev, [
        ['Add attribute', function () { addAttribute(e); }],
        ['Next colour', function () { e.color = COLOR_NAMES[(COLOR_NAMES.indexOf(e.color) + 1) % COLOR_NAMES.length]; render(); sync(); }],
        [count > 1 ? 'Copy ' + count + ' entities (Ctrl+C)' : 'Copy (Ctrl+C)', function () { copyEntities(which()); }],
        [count > 1 ? 'Duplicate ' + count + ' entities (Ctrl+D)' : 'Duplicate (Ctrl+D)', function () { duplicate(which()); }],
        ['Remove entity', function () { removeEntity(e); }]
      ]);
    } else {
      openMenu(ev, [
        ['New entity here', function () { addEntity(p.x, p.y); }],
        ['Paste here (Ctrl+V)', function () { paste(p); }, !!clipboard()],
        ['Select all (Ctrl+A)', selectAll, entities.length > 0],
        ['Show everything (Fit)', fit],
        ['Undo', function () { travel(-1); }, at > 0],
        ['Redo', function () { travel(1); }, at < history.length - 1]
      ]);
    }
  });
  document.addEventListener('pointerdown', function (ev) {
    if (!menu.hidden && !menu.contains(ev.target)) closeMenu();
  }, true);
  // Double-click on the background: a new entity there.
  canvas.addEventListener('dblclick', function (ev) {
    if (dataMode) return;
    if (ev.target !== canvas && ev.target !== surface && ev.target !== empty) return;
    var p = surfacePoint(ev);
    addEntity(p.x - 20, p.y - HEAD_H / 2);
  });
  // Double-click on a bend point: gone.
  surface.addEventListener('dblclick', function (ev) {
    if (!ev.target.classList.contains('via')) return;
    var via = viaOf(relData(ev.target.closest('.rel')));
    if (!via) return;
    via.splice(+ev.target.dataset.point, 1);
    draw();
    sync();
    say('Bend point removed.');
  });

  // The background: drag it to move around (the canvas has no edges); with Shift, to draw a frame around entities to select.
  canvas.addEventListener('pointerdown', function (ev) {
    if (ev.button !== undefined && ev.button !== 0) return;
    if (ev.target !== canvas && ev.target !== surface && ev.target !== lines && ev.target !== empty) return;
    if (picking) return;
    ev.preventDefault();
    canvas.focus({ preventScroll: true });
    if (ev.shiftKey) {
      var p = surfacePoint(ev);
      var keep = {};
      Object.keys(selected).forEach(function (id) { keep[id] = true; });
      drag = { kind: 'marquee', x: p.x, y: p.y, keep: keep, box: el('div', 'erd-marquee') };
      surface.appendChild(drag.box);
      return;
    }
    drag = { kind: 'pan', x: ev.clientX, y: ev.clientY, vx: view.x, vy: view.y, moved: false };
    canvas.classList.add('panning');
  });

  // Ctrl + wheel (and a pinch on a trackpad) zooms around the pointer. In full screen the wheel alone moves the canvas.
  canvas.addEventListener('wheel', function (ev) {
    var r = canvas.getBoundingClientRect();
    if (ev.ctrlKey || ev.metaKey) {
      ev.preventDefault();
      // One notch of a mouse wheel (deltaY about 100) is ZOOM_STEP; a trackpad pinch sends many small deltas
      // and so zooms smoothly. Capped per event, so a fast wheel or a hard pinch never jumps.
      var dy = ev.deltaMode === 1 ? ev.deltaY * 33 : ev.deltaY;
      dy = Math.max(-100, Math.min(100, dy));
      zoomTo(view.z * Math.pow(ZOOM_STEP, -dy / 100), ev.clientX - r.left, ev.clientY - r.top);
    } else if (form.classList.contains('full')) {
      ev.preventDefault();
      pan(-ev.deltaX, -ev.deltaY);
    }
  }, { passive: false });

  document.addEventListener('pointermove', function (ev) {
    if (!drag) return;
    if (drag.kind === 'pan') {
      if (!drag.moved && Math.abs(ev.clientX - drag.x) + Math.abs(ev.clientY - drag.y) < 4) return;
      drag.moved = true;
      view.x = drag.vx + ev.clientX - drag.x;
      view.y = drag.vy + ev.clientY - drag.y;
      applyView();
      return;
    }
    var p = surfacePoint(ev);
    var e = drag.entity;
    if (drag.kind === 'marquee') {
      // The frame, and everything it touches (on top of what was selected before).
      var mx1 = Math.min(drag.x, p.x), my1 = Math.min(drag.y, p.y), mx2 = Math.max(drag.x, p.x), my2 = Math.max(drag.y, p.y);
      drag.box.style.left = mx1 + 'px';
      drag.box.style.top = my1 + 'px';
      drag.box.style.width = (mx2 - mx1) + 'px';
      drag.box.style.height = (my2 - my1) + 'px';
      selected = {};
      Object.keys(drag.keep).forEach(function (id) { selected[id] = true; });
      entities.forEach(function (o) {
        if (o.x < mx2 && o.x + boxW(o) > mx1 && o.y < my2 && o.y + boxH(o) > my1) selected[o.id] = true;
      });
      markSelection();
    } else if (drag.kind === 'bend') {
      if (!drag.moved) {
        if (Math.abs(ev.clientX - drag.startX) + Math.abs(ev.clientY - drag.startY) < 4) return;
        drag.moved = true;
        if (drag.point < 0) drag.point = addBend(drag.data, p);
      }
      var pt = drag.via[drag.point];
      if (!pt) return;
      pt.x = Math.round(p.x);
      pt.y = Math.round(p.y);
      draw();
    } else if (drag.kind === 'move') {
      drag.group.forEach(function (g) {
        g.e.x = Math.round(g.x + p.x - drag.x);
        g.e.y = Math.round(g.y + p.y - drag.y);
        g.box.style.left = g.e.x + 'px';
        g.box.style.top = g.e.y + 'px';
      });
      drag.points.forEach(function (o) {
        o.p.x = Math.round(o.x + p.x - drag.x);
        o.p.y = Math.round(o.y + p.y - drag.y);
      });
      draw();
    } else if (drag.kind === 'size') {
      e.w = Math.min(MAX_W, Math.max(MIN_W, Math.round(drag.w + p.x - drag.x)));
      var h = Math.round(drag.h + p.y - drag.y);
      e.h = h > natural(e) ? h : 0;               // not smaller than its rows
      drag.box.style.width = e.w + 'px';
      drag.box.style.minHeight = height(e) + 'px';
      draw();
    } else if (drag.kind === 'order') {
      var to = Math.max(0, Math.min(e.attrs.length - 1, Math.floor((p.y - e.y - HEAD_H) / ROW_H)));
      drag.to = to;
      clearMarks('drop-before');
      clearMarks('drop-after');
      var rows = drag.box.querySelectorAll('.ent-row');
      if (to !== drag.from) rows[to].classList.add(to < drag.from ? 'drop-before' : 'drop-after');
    } else if (drag.kind === 'link') {
      if (!drag.moved && Math.abs(ev.clientX - drag.startX) + Math.abs(ev.clientY - drag.startY) < 6) return;
      drag.moved = true;
      drag.p = p;
      if (drag.temp) drag.temp.remove();
      clearMarks('target-row');
      clearMarks('target');
      var snap = magnet(p, e, drag.attr);
      drag.snap = snap;
      // The loose end: at the pointer, or pulled to the edge of the row it snapped to.
      var endX = p.x;
      var endY = p.y;
      if (snap) {
        var t = snap.entity;
        endX = p.x < t.x + t.w / 2 && t !== e ? t.x : t.x + t.w;
        endY = rowY(t, snap.index);
      }
      // Through the bend points that were dropped on the way (Shift or Ctrl).
      var fromRight = (drag.via.length ? drag.via[0].x : endX) > e.x + e.w / 2;
      var through = drag.via.map(function (v) { return ' L' + v.x + ' ' + v.y; }).join('');
      drag.temp = svgEl('path', { d: 'M' + (e.x + (fromRight ? e.w : 0)) + ' ' + rowY(e, drag.index) + through + ' L' + endX + ' ' + endY, 'class': 'temp' + (snap ? ' snapped' : '') });
      if (snap) {
        var node = entityNode(snap.entity);
        node.classList.add('target');
        node.querySelectorAll('.ent-row')[snap.index].classList.add('target-row');
        say('Release to link ' + (drag.attr.name || 'this attribute') + ' with ' + label(snap.entity) + '.' + (snap.attr.name || 'attribute')
          + '. Move over another row to choose that one.');
      } else {
        say('Drag towards the entity it belongs with: the line snaps to it. Press Shift on the way for a bend point.');
      }
    }
  });

  document.addEventListener('pointerup', function (ev) {
    if (!drag) return;
    var d = drag;
    drag = null;
    if (d.kind === 'pan') {
      canvas.classList.remove('panning');
      if (!d.moved && selection().length) { select([]); say(); }     // a click on the background: nothing selected
      return;
    }
    if (d.kind === 'marquee') {
      d.box.remove();
      var n = selection().length;
      say(n ? n + ' entit' + (n === 1 ? 'y' : 'ies') + ' selected: drag one of them to move them together, Ctrl+C copies, Delete removes. Esc clears the selection.' : '');
      return;
    }
    if (d.kind === 'bend') {
      if (!d.moved) return;
      swallowNextClick();
      draw();
      sync();
      say('Bend point: drag it to move it, double-click it to remove it.');
      return;
    }
    if (d.kind === 'move') {
      d.group.forEach(function (g) { g.box.classList.remove('moving'); });
      draw();
      sync();
    } else if (d.kind === 'size') {
      d.box.classList.remove('sizing');
      draw();
      sync();
    } else if (d.kind === 'order') {
      if (d.to !== d.from) d.entity.attrs.splice(d.to, 0, d.entity.attrs.splice(d.from, 1)[0]);
      render();
      if (d.to !== d.from) sync();
    } else if (d.kind === 'link') {
      if (d.temp) d.temp.remove();
      clearMarks('target-row');
      clearMarks('target');
      swallowNextClick();
      if (d.moved) {
        var snap = magnet(surfacePoint(ev), d.entity, d.attr);
        if (snap) linkRows(d.entity, d.attr, snap.entity, snap.attr, d.via);
        else { draw(); say('Not linked: release on or near an entity that has attributes.'); }
      } else {
        picking = { entity: d.entity, attr: d.attr };
        canvas.classList.add('picking');
        say('Now click the attribute it belongs with (Esc cancels).');
      }
    }
  });

  document.addEventListener('keydown', function (ev) {
    var inEditor = root.contains(ev.target) || ev.target === document.body;
    var typing = /^(INPUT|SELECT|TEXTAREA)$/.test(ev.target.tagName);
    // While a new line is being dragged: Shift or Ctrl drops a bend point under the pointer.
    if (drag && drag.kind === 'link' && (ev.key === 'Shift' || ev.key === 'Control')) {
      if (drag.moved && !ev.repeat) {
        drag.via.push({ x: Math.round(drag.p.x), y: Math.round(drag.p.y) });
        say('Bend point ' + drag.via.length + ' added. Drag on to the attribute it belongs with.');
      }
      return;
    }
    if (ev.key === 'Escape' && !menu.hidden) { closeMenu(); return; }
    if (ev.key === 'Escape' && picking) { stopPicking(); return; }
    if (ev.key === 'Escape' && selection().length) { select([]); say(); return; }
    if (ev.key === 'Escape' && form.classList.contains('full')) { setFull(false); return; }

    // Ctrl+A: every entity (inside a text box it selects the text, as usual).
    if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 'a' && inEditor && !typing) {
      ev.preventDefault();
      selectAll();
      return;
    }
    if ((ev.key === 'Delete' || ev.key === 'Backspace') && inEditor && !typing && ev.target.tagName !== 'BUTTON' && selection().length) {
      ev.preventDefault();
      removeSelected();
      return;
    }

    // Copy, paste, duplicate the selected entities (inside a text box, or with text selected: the text, as usual).
    if ((ev.ctrlKey || ev.metaKey) && !ev.altKey && !ev.shiftKey && inEditor && !typing) {
      var letter = ev.key.toLowerCase();
      if (letter === 'c' && selection().length && !String(window.getSelection()).trim()) {
        ev.preventDefault();
        copyEntities(selection());
        return;
      }
      if (letter === 'v') {
        if (clipboard()) { ev.preventDefault(); paste(); }
        return;
      }
      if (letter === 'd' && selection().length) {
        ev.preventDefault();
        duplicate(selection());
        return;
      }
    }

    // Undo / redo, also while a name is being typed (the drawing is one history, not one per box).
    if ((ev.ctrlKey || ev.metaKey) && !ev.altKey && inEditor && ev.target !== textarea && !ev.target.classList.contains('cell')) {
      var key = ev.key.toLowerCase();
      if (key === 'z' || key === 'y') {
        ev.preventDefault();
        travel(key === 'y' || ev.shiftKey ? 1 : -1);
        return;
      }
    }

    // Moving and zooming with the keyboard, when no text is being typed.
    if (inEditor && !typing && !ev.ctrlKey && !ev.metaKey && !ev.altKey) {
      var step = ev.shiftKey ? 240 : 60;
      var handled = true;
      if (ev.key === 'ArrowLeft') pan(step, 0);
      else if (ev.key === 'ArrowRight') pan(-step, 0);
      else if (ev.key === 'ArrowUp') pan(0, step);
      else if (ev.key === 'ArrowDown') pan(0, -step);
      else if (ev.key === '+' || ev.key === '=') zoomTo(view.z * ZOOM_STEP);
      else if (ev.key === '-' || ev.key === '_') zoomTo(view.z / ZOOM_STEP);
      else if (ev.key === '0') fit();
      else handled = false;
      if (handled) { ev.preventDefault(); return; }
    }

    // Ctrl+Enter / Cmd+Enter checks, from anywhere in the editor.
    if (ev.key === 'Enter' && (ev.ctrlKey || ev.metaKey) && (form.contains(ev.target) || ev.target === document.body)) {
      ev.preventDefault();
      if (form.requestSubmit) form.requestSubmit(checkButton || undefined);   // a real submit: exam.js listens to it
      else check();
    }
  });
  document.addEventListener('click', function (ev) {
    if (picking && !surface.contains(ev.target)) stopPicking();
  });

  // Toolbar.
  undoButton.addEventListener('click', function () { travel(-1); });
  redoButton.addEventListener('click', function () { travel(1); });
  zoomIn.addEventListener('click', function () { zoomTo(view.z * ZOOM_STEP); });
  zoomOut.addEventListener('click', function () { zoomTo(view.z / ZOOM_STEP); });
  zoomLabel.addEventListener('click', function () { zoomTo(1); });
  fitButton.addEventListener('click', fit);
  fullButton.addEventListener('click', function () { setFull(!form.classList.contains('full')); });
  legendButton.addEventListener('click', function () { showPanel(legend, legendButton, 'legend', legend.hidden); });
  mapButton.addEventListener('click', function () { showPanel(map, mapButton, 'map', map.hidden); });

  tidyButton.addEventListener('click', function () {
    var widest = entities.reduce(function (w, e) { return Math.max(w, boxW(e)); }, BOX_W);
    var perRow = Math.max(1, Math.floor((canvas.clientWidth / view.z - PAD) / (widest + GAP_X)));
    var tops = [];
    entities.forEach(function (e, i) {
      var c = i % perRow;
      e.x = PAD + c * (widest + GAP_X);
      e.y = tops[c] || PAD;
      tops[c] = e.y + boxH(e) + GAP_Y;
    });
    // Every entity moved: the bend points are no longer where the lines run.
    entities.forEach(function (e) { e.attrs.forEach(function (a) { a.via = []; }); });
    many.forEach(function (m) { m.via = []; });
    view.x = 0;
    view.y = 0;
    applyView();
    render();
    sync();
  });
  dataButton.addEventListener('click', function () { setDataMode(!dataMode); });
  textButton.addEventListener('click', function () {
    var open = form.classList.toggle('show-text');
    textButton.textContent = open ? 'Hide text' : 'Text';
  });

  // The text can be edited too: the drawing follows.
  var textTimer = null;
  textarea.addEventListener('input', function () {
    if (ownInput) return;
    clearTimeout(textTimer);
    textTimer = setTimeout(function () {
      load(textarea.value);
      render();
      remember();
      // A step in the history, without rewriting the text that is being typed.
      var text = serialize();
      if (text !== history[at]) { history.splice(at + 1, history.length, text); at = history.length - 1; historyButtons(); }
    }, 400);
  });

  // Check: send the text, show the verdict.
  var checkButton = form.querySelector('button[type=submit]');
  var busy = false;
  function check() {
    if (busy || freeKey) return;          // free drawing: nothing to check
    busy = true;
    if (checkButton) checkButton.disabled = true;
    if (!textarea.matches(':focus')) textarea.value = serialize();
    var body = new FormData();
    body.set('sql', textarea.value);
    body.set('action', 'check');
    body.set('visual', '1');
    fetch(location.href, {
      method: 'POST',
      body: body,
      headers: { 'X-Requested-With': 'fetch' },
      credentials: 'same-origin'
    }).then(function (res) {
      if (!res.ok) throw new Error('HTTP ' + res.status);
      return res.text();
    }).then(function (html) {
      if (!results) return;
      results.innerHTML = html;
      var verdict = results.querySelector('.verdict');
      if (verdict && form.classList.contains('full')) say(verdict.textContent);   // the verdict itself is behind the canvas
      else if (verdict && verdict.scrollIntoView) verdict.scrollIntoView({ block: 'nearest' });
    }).catch(function (err) {
      if (results) results.textContent = 'Request failed: ' + err.message + '. Try again.';
    }).then(function () {
      busy = false;
      if (checkButton) checkButton.disabled = false;
      // Same signal as app.js: the exam page (exam.js) updates "Saved", the ✓ and the counter.
      document.dispatchEvent(new CustomEvent('nw:result', { detail: { action: 'check' } }));
    });
  }
  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    check();
  });


  if (freeKey && textarea.value.trim() === '') {
    try { textarea.value = localStorage.getItem(freeKey) || ''; } catch (e) { /* no storage: start empty */ }
  }
  showPanel(legend, legendButton, 'legend', preference('legend', true));
  showPanel(map, mapButton, 'map', preference('map', true));
  load(textarea.value);
  render();
  applyView();
  history = [serialize()];
  at = 0;
  historyButtons();
  say();
})();
