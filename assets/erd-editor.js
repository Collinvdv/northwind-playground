/*
 * Diagram editor of a design exercise (exercise.php and the exam page, for an
 * exercise with 'type' => 'erd'). Only a drawing: nothing here touches a database.
 *
 *   - Drag the "Entity" block onto the canvas (or click it) for a new entity.
 *   - Type its name, add attributes, choose a data type for each, click PK for the key.
 *   - Relationship: drag one of the round connectors on the sides of an entity
 *     onto another entity (or click the connector, then the entity). The entity
 *     you start from is the "one" side; the other one gets the foreign key.
 *   - Click an end of a line to switch it between "one" and "many". Many at
 *     both ends is a many-to-many line (no foreign key); one at both ends is
 *     one-to-one. The × in the middle of a line removes the relationship.
 *   - The coloured dot in an entity's bar changes its colour; drag the bar to move.
 *
 * The design lives as text in the form's textarea, in the notation that
 * lib.php reads (parseErd()), one line per entity:
 *
 *     Players @320,24 #green: PlayerID int PK, Name varchar, TeamID int > Teams
 *     Students <> Courses                         (a many-to-many line)
 *
 * "@x,y" is where the entity stands, "#green" its colour, UNIQUE after a
 * foreign key means one-to-one. Every change rewrites the textarea, so Check,
 * the exam's autosave (exam.js) and a page reload all work on that text.
 * "Text" in the toolbar shows it; it can be edited there too. Without
 * JavaScript the textarea alone is the editor.
 */
(function () {
  var form = document.getElementById('sqlform');
  var textarea = document.getElementById('sql');
  var results = document.getElementById('results');
  if (!form || !textarea) return;

  var BOX_W = 264;
  var GAP_X = 80;
  var GAP_Y = 30;
  var PAD = 24;
  var HEAD_H = 34;
  var ROW_H = 29;       // lib.php: ERD_EDITOR_Y_SCALE = its row height / this one
  var SVG = 'http://www.w3.org/2000/svg';
  var TYPES = ['int', 'decimal', 'varchar', 'text', 'date', 'datetime', 'boolean'];
  var TYPE_ALIASES = { integer: 'int', smallint: 'int', tinyint: 'int', bigint: 'int', numeric: 'decimal', float: 'decimal', double: 'decimal',
    char: 'varchar', string: 'varchar', timestamp: 'datetime', bool: 'boolean', bit: 'boolean' };
  // As ERD_COLORS in lib.php.
  var COLORS = { blue: '#1d4ed8', green: '#15803d', orange: '#c2410c', purple: '#7e22ce', red: '#b91c1c', teal: '#0f766e', grey: '#475569' };
  var COLOR_NAMES = Object.keys(COLORS);

  var entities = [];    // {id, name, x, y, color, attrs: [{name, type, pk, ref (entity id or null), unique}]}
  var many = [];        // many-to-many lines: {a: entity id, b: entity id}
  var nextId = 1;
  var linking = null;   // {entity, side, moved, startX, startY, temp} while a connector is dragged
  var picking = null;   // {entity} after a click on a connector: the next entity clicked is the other side

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

  function height(e) { return HEAD_H + e.attrs.length * ROW_H + 30; }

  function load(text) {
    var list = [];
    var refs = [];
    var manyRefs = [];
    text.split(/\r?\n/).forEach(function (line) {
      line = line.trim();
      var mm = /^(\w+)\s*<>\s*(\w+)$/.exec(line);
      if (mm) { manyRefs.push([mm[1], mm[2]]); return; }
      var m = /^(\+?)\s*([\w ]+?)\s*(?:@\s*(-?\d+)\s*,\s*(-?\d+)\s*)?(?:#(\w+)\s*)?:\s*(.*)$/.exec(line);
      if (!m) return;
      var color = (m[5] || '').toLowerCase();
      var e = { id: nextId++, name: cleanName(m[2]), x: null, y: null, color: COLORS[color] ? color : 'blue', stack: !!m[1], attrs: [] };
      if (m[3] !== undefined) { e.x = Math.max(0, +m[3]); e.y = Math.max(0, +m[4]); }
      m[6].split(/,(?![^()]*\))/).forEach(function (column) {
        var c = /^(\S+)\s*(.*)$/.exec(column.trim());
        if (!c) return;
        var rest = c[2];
        var a = { name: cleanName(c[1]), type: '', pk: false, ref: null, unique: false };
        var r = /-?>\s*(\w+)(?:\.(\w+))?/.exec(rest);
        if (r) {
          refs.push([a, r[1]]);
          rest = rest.replace(r[0], '');
          a.unique = /\bUNIQUE\b/i.test(rest);
        }
        a.pk = /\bPK\b/i.test(rest);
        a.type = cleanType(rest.replace(/\b(PK|FK|UNIQUE)\b/ig, ''));
        if (a.name) e.attrs.push(a);
      });
      list.push(e);
    });
    function find(name) {
      for (var i = 0; i < list.length; i++) if (norm(list[i].name, true) === norm(name, true)) return list[i];
      return null;
    }
    refs.forEach(function (pair) {
      var target = find(pair[1]);
      if (target) pair[0].ref = target.id; else pair[0].unique = false;
    });
    many = [];
    manyRefs.forEach(function (pair) {
      var a = find(pair[0]);
      var b = find(pair[1]);
      if (a && b) many.push({ a: a.id, b: b.id });
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
    entities = list;
  }

  function label(e) { return e.name || 'Unnamed'; }

  function serialize() {
    var out = entities.map(function (e) {
      var columns = e.attrs.filter(function (a) { return a.name !== ''; }).map(function (a) {
        var target = a.ref !== null ? byId(a.ref) : null;
        return a.name + (a.type ? ' ' + a.type : '') + (a.pk ? ' PK' : '') + (target ? ' > ' + label(target) + (a.unique ? ' UNIQUE' : '') : '');
      });
      return label(e) + ' @' + Math.round(e.x) + ',' + Math.round(e.y) + (e.color !== 'blue' ? ' #' + e.color : '') + ': ' + columns.join(', ');
    });
    many.forEach(function (m) {
      var a = byId(m.a);
      var b = byId(m.b);
      if (a && b) out.push(label(a) + ' <> ' + label(b));
    });
    return out.join('\n');
  }

  var ownInput = false;
  /** The model changed: rewrite the textarea and tell whoever listens (exam.js autosave). */
  function sync() {
    textarea.value = serialize();
    ownInput = true;
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    ownInput = false;
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
  var status = el('span', 'hint erd-status');
  toolbar.append(chip, tidyButton, textButton, status);

  var canvas = el('div', 'erd-canvas');
  var surface = el('div', 'erd-surface');
  var lines = document.createElementNS(SVG, 'svg');
  lines.setAttribute('class', 'erd-lines');
  var empty = el('p', 'erd-empty', 'Drag the Entity block here, or click it, to start your diagram.');
  surface.append(lines, empty);
  canvas.appendChild(surface);
  root.append(toolbar, canvas);
  form.insertBefore(root, form.firstChild);
  form.classList.add('visual');

  var IDLE = 'Drag “Entity” onto the canvas · drag a round connector to another entity for a relationship · click a line end: one ↔ many';
  function say(text) { status.textContent = text || IDLE; }

  function entityNode(e) { return surface.querySelector('.ent[data-id="' + e.id + '"]'); }

  /** Build every entity box again (after adding, removing, linking). Typing never calls this: it would take the focus away. */
  function render() {
    Array.prototype.slice.call(surface.querySelectorAll('.ent')).forEach(function (n) { n.remove(); });
    empty.hidden = entities.length > 0;
    entities.forEach(function (e) {
      var box = el('div', 'ent');
      box.dataset.id = e.id;
      box.style.left = e.x + 'px';
      box.style.top = e.y + 'px';

      var head = el('div', 'ent-head');
      head.style.backgroundColor = COLORS[e.color];
      var name = el('input', 'ent-name');
      name.value = e.name;
      name.placeholder = 'EntityName';
      name.spellcheck = false;
      name.setAttribute('aria-label', 'Entity name');
      head.append(name, button('color', '', 'Change the colour'), button('x', '×', 'Remove this entity'));
      box.appendChild(head);
      box.append(button('port left', '', 'Relationship: drag onto another entity (this entity is the “one” side)'),
                 button('port right', '', 'Relationship: drag onto another entity (this entity is the “one” side)'));

      e.attrs.forEach(function (a, i) {
        var row = el('div', 'ent-row' + (a.ref !== null && byId(a.ref) ? ' is-fk' : ''));
        row.dataset.index = i;
        var pk = button('pk' + (a.pk ? ' on' : ''), 'PK', a.pk ? 'Primary key (click to remove)' : 'Make this the primary key');
        pk.setAttribute('aria-pressed', a.pk ? 'true' : 'false');
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
        row.append(pk, input, type);
        if (a.ref !== null && byId(a.ref)) {
          var fk = el('span', 'fk-tag', 'FK');
          fk.title = 'Foreign key: follow the line';
          row.appendChild(fk);
        }
        row.appendChild(button('x', '×', 'Remove this attribute'));
        box.appendChild(row);
      });

      box.appendChild(button('ent-add', '+ attribute', 'Add an attribute'));
      surface.appendChild(box);
    });
    draw();
  }

  // ---- lines ---------------------------------------------------------------

  /** Where a relationship leaves both boxes: [da, db] = +1 right side, -1 left side. */
  function sides(a, b) {
    if (a.x + BOX_W / 2 < b.x) return [1, -1];
    if (a.x > b.x + BOX_W / 2) return [-1, 1];
    return [1, 1];                    // above each other, or the entity itself: loop on the right
  }

  function crowFoot(g, x1, y, ax) { [-7, 0, 7].forEach(function (dy) { svgEl('line', { x1: x1, y1: y, x2: ax, y2: y + dy }, g); }); }
  function oneBar(g, x2, y, bx, d) {
    svgEl('line', { x1: x2, y1: y, x2: bx, y2: y }, g);
    svgEl('line', { x1: bx + d * 7, y1: y - 7, x2: bx + d * 7, y2: y + 7 }, g);
  }

  /**
   * One relationship: the curve, the marks at both ends, a round click area on
   * each end (one ↔ many) and a × in the middle. data goes on the click areas.
   */
  function relation(ax, ay, da, aMany, bx, by, db, bMany, data, title) {
    var g = svgEl('g', { 'class': 'rel' });
    var x1 = ax + da * 16;
    var x2 = bx + db * 16;
    var k = Math.max(50, Math.abs(x2 - x1) / 2);
    var c1x = x1 + da * k;
    var c2x = x2 + db * k;
    var d = 'M' + x1 + ' ' + ay + ' C' + c1x + ' ' + ay + ' ' + c2x + ' ' + by + ' ' + x2 + ' ' + by;
    svgEl('path', { d: d, 'class': 'hit' }, g);
    svgEl('path', { d: d }, g);
    if (aMany) crowFoot(g, x1, ay, ax); else oneBar(g, x1, ay, ax, da);
    if (bMany) crowFoot(g, x2, by, bx); else oneBar(g, x2, by, bx, db);

    function endArea(x, y, end, isMany) {
      var c = svgEl('circle', { cx: x, cy: y, r: 12, 'class': 'end-hit' }, g);
      Object.keys(data).forEach(function (key) { c.dataset[key] = data[key]; });
      c.dataset.end = end;
      svgEl('title', {}, c).textContent = (isMany ? 'Many' : 'One') + ' ' + title[end] + '. Click to make it ' + (isMany ? 'one' : 'many') + '.';
    }
    endArea(ax + da * 9, ay, 'a', aMany);
    endArea(bx + db * 9, by, 'b', bMany);

    // Middle of the curve (Bézier at t = 0.5).
    var mx = (x1 + 3 * c1x + 3 * c2x + x2) / 8;
    var my = (ay + by) / 2;
    var del = svgEl('g', { 'class': 'rel-del', transform: 'translate(' + mx + ' ' + my + ')' }, g);
    Object.keys(data).forEach(function (key) { del.dataset[key] = data[key]; });
    svgEl('circle', { r: 9 }, del);
    svgEl('path', { d: 'M-3.5 -3.5 L3.5 3.5 M3.5 -3.5 L-3.5 3.5' }, del);
    svgEl('title', {}, del).textContent = 'Remove this relationship';
  }

  /** Grow the surface with the entities and draw the relationship lines. */
  function draw() {
    var w = canvas.clientWidth;
    var h = canvas.clientHeight;
    entities.forEach(function (e) {
      w = Math.max(w, e.x + BOX_W + PAD + 80);
      h = Math.max(h, e.y + height(e) + PAD);
    });
    surface.style.width = w + 'px';
    surface.style.height = h + 'px';
    lines.setAttribute('width', w);
    lines.setAttribute('height', h);
    while (lines.firstChild) lines.removeChild(lines.firstChild);

    // Foreign keys: from the attribute (end "a", the child) to the key of the other entity (end "b", the parent).
    entities.forEach(function (e) {
      e.attrs.forEach(function (a, i) {
        var target = a.ref !== null ? byId(a.ref) : null;
        if (!target) return;
        var keyRow = -1;
        target.attrs.some(function (t, j) { if (t.pk) { keyRow = j; return true; } return false; });
        var s = sides(e, target);
        relation(
          e.x + (s[0] > 0 ? BOX_W : 0), e.y + HEAD_H + i * ROW_H + ROW_H / 2, s[0], !a.unique,
          target.x + (s[1] > 0 ? BOX_W : 0), target.y + (keyRow < 0 ? HEAD_H / 2 : HEAD_H + keyRow * ROW_H + ROW_H / 2), s[1], false,
          { kind: 'fk', entity: e.id, index: i },
          { a: label(e) + ' per ' + label(target), b: label(target) + ' per ' + label(e) }
        );
      });
    });
    // Many-to-many lines: name bar to name bar.
    many.forEach(function (m, i) {
      var a = byId(m.a);
      var b = byId(m.b);
      if (!a || !b) return;
      var s = sides(a, b);
      relation(
        a.x + (s[0] > 0 ? BOX_W : 0), a.y + HEAD_H / 2, s[0], true,
        b.x + (s[1] > 0 ? BOX_W : 0), b.y + HEAD_H / 2, s[1], true,
        { kind: 'mm', index: i },
        { a: label(a) + ' per ' + label(b), b: label(b) + ' per ' + label(a) }
      );
    });
  }

  // -------------------------------------------------------------------------
  // Changing the model
  // -------------------------------------------------------------------------

  /** A free spot for an entity that was added with a click. */
  function freeSpot() {
    var perRow = Math.max(1, Math.floor((canvas.clientWidth - PAD) / (BOX_W + GAP_X)));
    for (var n = 0; n < 200; n++) {
      var x = PAD + (n % perRow) * (BOX_W + GAP_X);
      var y = PAD + Math.floor(n / perRow) * 190;
      var taken = entities.some(function (e) {
        return x < e.x + BOX_W + 20 && x + BOX_W + 20 > e.x && y < e.y + height(e) + 20 && y + 120 > e.y;
      });
      if (!taken) return { x: x, y: y };
    }
    return { x: PAD, y: PAD };
  }

  function addEntity(x, y) {
    var n = entities.length + 1;
    var name;
    do { name = 'Entity' + n++; } while (entities.some(function (e) { return e.name === name; }));
    var e = { id: nextId++, name: name, x: Math.max(0, Math.round(x)), y: Math.max(0, Math.round(y)), color: 'blue', attrs: [] };
    entities.push(e);
    render();
    sync();
    var input = entityNode(e).querySelector('.ent-name');
    input.focus();
    input.select();
  }

  function addAttribute(e) {
    e.attrs.push({ name: '', type: '', pk: false, ref: null, unique: false });
    render();
    sync();
    var inputs = entityNode(e).querySelectorAll('.attr');
    inputs[inputs.length - 1].focus();
  }

  /**
   * The foreign key of a one-to-many (or one-to-one) relationship: an
   * attribute in child that points to parent. An attribute that already has
   * the name of the parent's key and no link yet is used; otherwise one is added.
   */
  function addForeignKey(child, parent, unique) {
    var key = parent.attrs.filter(function (t) { return t.pk && t.name; })[0];
    var name = key ? key.name : cleanName(label(parent)) + 'ID';
    var type = key && key.type ? key.type : 'int';
    var existing = child.attrs.filter(function (a) { return norm(a.name) === norm(name); })[0];
    if (existing && existing.ref === null && child !== parent) {
      existing.ref = parent.id;
      existing.unique = !!unique;
      if (!existing.type) existing.type = type;
      return;
    }
    if (existing) {
      // The name is taken: the entity itself (its own key) or a second relationship with the same entity.
      var base = child === parent ? 'Parent' + name : name;
      var n = child === parent ? 1 : 2;
      name = base + (n > 1 ? n : '');
      while (child.attrs.some(function (a) { return norm(a.name) === norm(name); })) name = base + (++n);
    }
    child.attrs.push({ name: name, type: type, pk: false, ref: parent.id, unique: !!unique });
  }

  /** Take a foreign key away: the attribute goes too, unless it is part of the primary key. */
  function removeForeignKey(e, a) {
    if (a.pk) { a.ref = null; a.unique = false; }
    else e.attrs.splice(e.attrs.indexOf(a), 1);
  }

  /** A new relationship, drawn from one to other: one "one" has many "other". */
  function connect(one, other) {
    addForeignKey(other, one, false);
    render();
    sync();
    say(one === other
      ? 'Linked ' + label(one) + ' to itself. Rename the new attribute if you like.'
      : 'One ' + label(one) + ' has many ' + label(other) + '. Not right? Click an end of the line to switch between one and many.');
  }

  /** A click on an end of a line: one ↔ many. */
  function toggleEnd(data) {
    if (data.kind === 'mm') {
      // Many-to-many, one end becomes "one": that entity is the parent, the other gets the foreign key.
      var m = many[+data.index];
      if (!m) return;
      var a = byId(m.a);
      var b = byId(m.b);
      many.splice(+data.index, 1);
      if (a && b) addForeignKey(data.end === 'a' ? b : a, data.end === 'a' ? a : b, false);
      say('One-to-many now: the foreign key is in the entity on the “many” side.');
    } else {
      var child = byId(+data.entity);
      var attr = child ? child.attrs[+data.index] : null;
      var parent = attr ? byId(attr.ref) : null;
      if (!attr || !parent) return;
      if (data.end === 'a') {
        // The child's end: many ↔ one (one-to-one).
        attr.unique = !attr.unique;
        say(attr.unique ? 'One-to-one: every ' + label(parent) + ' has at most one ' + label(child) + '.'
                        : 'One ' + label(parent) + ' has many ' + label(child) + '.');
      } else if (attr.unique) {
        // One-to-one, the parent's end becomes "many": the relationship turns around, the foreign key moves.
        removeForeignKey(child, attr);
        addForeignKey(parent, child, false);
        say('One ' + label(child) + ' has many ' + label(parent) + ': the foreign key moved to ' + label(parent) + '.');
      } else if (child !== parent) {
        // Many at both ends: no foreign key can hold that.
        removeForeignKey(child, attr);
        many.push({ a: parent.id, b: child.id });
        say('Many-to-many: no foreign key can store this. Tables need a link table in between.');
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
      if (e && e.attrs[+data.index]) removeForeignKey(e, e.attrs[+data.index]);
    }
    render();
    sync();
    say('Relationship removed.');
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

  function surfacePoint(ev) {
    var r = surface.getBoundingClientRect();
    return { x: ev.clientX - r.left, y: ev.clientY - r.top };
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
    var clean = cleanName(ev.target.value);
    if (clean !== ev.target.value) ev.target.value = clean;   // letters, digits and _ only
    if (ev.target.classList.contains('ent-name')) hit.entity.name = clean;
    else if (hit.attr) hit.attr.name = clean;
    sync();
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
    var inputs = hit.box.querySelectorAll('.attr');
    var index = hit.row ? +hit.row.dataset.index + 1 : 0;
    if (index < inputs.length) inputs[index].focus();
    else if (!hit.row || ev.target.value !== '') addAttribute(hit.entity);
  });

  // Buttons inside an entity, the ends of a line, and choosing the other side of a relationship by clicking.
  surface.addEventListener('click', function (ev) {
    var hit = fromEvent(ev);
    if (picking) {
      var p = picking;
      stopPicking();
      if (hit && hit.entity) connect(p.entity, hit.entity);
      return;
    }
    var end = ev.target.closest('.end-hit');
    if (end) { toggleEnd(end.dataset); return; }
    var del = ev.target.closest('.rel-del');
    if (del) { removeRelation(del.dataset); return; }

    if (!hit || !hit.entity || ev.target.tagName !== 'BUTTON') return;
    var e = hit.entity;
    var cls = ev.target.classList;
    if (cls.contains('ent-add')) {
      addAttribute(e);
    } else if (cls.contains('color')) {
      e.color = COLOR_NAMES[(COLOR_NAMES.indexOf(e.color) + 1) % COLOR_NAMES.length];
      render();
      sync();
    } else if (cls.contains('pk') && hit.attr) {
      hit.attr.pk = !hit.attr.pk;
      render();
      sync();
    } else if (cls.contains('x') && hit.attr) {
      e.attrs.splice(+hit.row.dataset.index, 1);
      render();
      sync();
    } else if (cls.contains('x')) {
      if (e.attrs.length && !window.confirm('Remove the entity ' + label(e) + ' and its attributes?')) return;
      entities = entities.filter(function (o) { return o !== e; });
      // Relationships with it go too: the foreign keys that pointed to it, the many-to-many lines.
      entities.forEach(function (o) {
        o.attrs.filter(function (a) { return a.ref === e.id; }).forEach(function (a) { removeForeignKey(o, a); });
      });
      many = many.filter(function (m) { return m.a !== e.id && m.b !== e.id; });
      render();
      sync();
    }
  });

  // Moving an entity by its bar, and dragging a connector to another entity.
  var moving = null;   // {entity, box, dx, dy}
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
    var hit = fromEvent(ev);
    if (!hit || !hit.entity || picking) return;
    if (ev.target.classList.contains('port')) {
      ev.preventDefault();
      linking = { entity: hit.entity, side: ev.target.classList.contains('right') ? 1 : -1, moved: false, startX: ev.clientX, startY: ev.clientY, temp: null };
      return;
    }
    if (ev.target.closest('.ent-head') && ev.target.tagName !== 'INPUT' && ev.target.tagName !== 'BUTTON') {
      ev.preventDefault();
      var p = surfacePoint(ev);
      moving = { entity: hit.entity, box: hit.box, dx: p.x - hit.entity.x, dy: p.y - hit.entity.y };
      hit.box.classList.add('moving');
    }
  });

  function entityUnder(ev) {
    var over = document.elementFromPoint(ev.clientX, ev.clientY);
    return over && over.closest ? over.closest('.ent') : null;
  }

  document.addEventListener('pointermove', function (ev) {
    if (moving) {
      var p = surfacePoint(ev);
      moving.entity.x = Math.max(0, Math.round(p.x - moving.dx));
      moving.entity.y = Math.max(0, Math.round(p.y - moving.dy));
      moving.box.style.left = moving.entity.x + 'px';
      moving.box.style.top = moving.entity.y + 'px';
      draw();
    } else if (linking) {
      if (!linking.moved && Math.abs(ev.clientX - linking.startX) + Math.abs(ev.clientY - linking.startY) < 6) return;
      linking.moved = true;
      var q = surfacePoint(ev);
      var e = linking.entity;
      if (linking.temp) linking.temp.remove();
      linking.temp = svgEl('path', { d: 'M' + (e.x + (linking.side > 0 ? BOX_W : 0)) + ' ' + (e.y + HEAD_H / 2) + ' L' + q.x + ' ' + q.y, 'class': 'temp' });
      var box = entityUnder(ev);
      Array.prototype.forEach.call(surface.querySelectorAll('.ent.target'), function (n) { n.classList.remove('target'); });
      if (box) box.classList.add('target');
      say(box ? 'Release: one ' + label(e) + ' has many ' + label(byId(+box.dataset.id)) + '.' : 'Drag onto the entity on the “many” side.');
    }
  });

  document.addEventListener('pointerup', function (ev) {
    if (moving) {
      moving.box.classList.remove('moving');
      moving = null;
      draw();
      sync();
    } else if (linking) {
      var l = linking;
      linking = null;
      if (l.temp) l.temp.remove();
      swallowNextClick();         // the click that ends this press is not a click on an entity
      if (l.moved) {
        var box = entityUnder(ev);
        var target = box ? byId(+box.dataset.id) : null;
        if (target) connect(l.entity, target);
        else { render(); say(); }
      } else {
        picking = { entity: l.entity };
        canvas.classList.add('picking');
        say('Now click the entity on the “many” side (Esc cancels).');
      }
    }
  });

  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape' && picking) stopPicking();
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
  tidyButton.addEventListener('click', function () {
    var perRow = Math.max(1, Math.floor((canvas.clientWidth - PAD) / (BOX_W + GAP_X)));
    var tops = [];
    entities.forEach(function (e, i) {
      var c = i % perRow;
      e.x = PAD + c * (BOX_W + GAP_X);
      e.y = tops[c] || PAD;
      tops[c] = e.y + height(e) + GAP_Y;
    });
    render();
    sync();
  });
  textButton.addEventListener('click', function () {
    var open = form.classList.toggle('show-text');
    textButton.textContent = open ? 'Hide text' : 'Text';
  });

  // The text can be edited too: the drawing follows.
  var textTimer = null;
  textarea.addEventListener('input', function () {
    if (ownInput) return;
    clearTimeout(textTimer);
    textTimer = setTimeout(function () { load(textarea.value); render(); }, 400);
  });

  // Check: send the text, show the verdict.
  var checkButton = form.querySelector('button[type=submit]');
  var busy = false;
  function check() {
    if (busy) return;
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
      if (verdict && verdict.scrollIntoView) verdict.scrollIntoView({ block: 'nearest' });
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

  window.addEventListener('resize', draw);
  if (window.ResizeObserver) new ResizeObserver(draw).observe(canvas);   // the canvas can be made taller

  load(textarea.value);
  render();
  say();
})();
