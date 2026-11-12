/*
 * Teacher overview (exam-admin.php): hovering (or tabbing to) a cell shows a
 * card with the assignment and the student's answer. The assignments are in
 * the page once (#exam-tasks); each cell carries its answer in data-sql and
 * the verdict / points in data-info.
 */
(function () {
  var source = document.getElementById('exam-tasks');
  if (!source) return;
  var tasks = JSON.parse(source.textContent);

  var card = document.createElement('div');
  card.className = 'peek';
  card.hidden = true;
  document.body.appendChild(card);

  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text !== undefined) e.textContent = text;
    return e;
  }

  function show(cell) {
    var task = tasks[cell.dataset.q];
    if (!task) return;
    card.innerHTML = '';
    card.appendChild(el('strong', '', task.title));
    var body = el('div', 'peek-task');
    body.innerHTML = task.task;                // trusted: the exam's own HTML
    card.appendChild(body);
    card.appendChild(el('div', 'peek-info ' + cell.className.replace(/\bcell\b/, ''), cell.dataset.info));
    var sql = cell.dataset.sql;
    card.appendChild(sql ? el('pre', '', sql) : el('p', 'hint', 'No answer.'));
    card.hidden = false;

    var r = cell.getBoundingClientRect();
    var w = card.offsetWidth;
    var left = Math.max(8, Math.min(r.left + r.width / 2 - w / 2, document.documentElement.clientWidth - w - 8));
    var below = r.bottom + 6;
    var top = below + card.offsetHeight > window.innerHeight && r.top - card.offsetHeight - 6 > 0
      ? r.top - card.offsetHeight - 6 : below;
    card.style.left = left + window.scrollX + 'px';
    card.style.top = top + window.scrollY + 'px';
  }

  function hide() { card.hidden = true; }

  document.addEventListener('mouseover', function (e) {
    var cell = e.target.closest && e.target.closest('td[data-q]');
    if (cell) show(cell); else hide();
  });
  document.addEventListener('focusin', function (e) {
    var cell = e.target.closest && e.target.closest('td[data-q]');
    if (cell) show(cell); else hide();
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') hide(); });
  window.addEventListener('scroll', hide, { passive: true });
})();
