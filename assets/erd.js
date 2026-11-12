/*
 * Database diagram (erd.php and the popup on the exam page).
 *
 * - Hovering a table highlights its relationships and the tables at their ends.
 * - "Fit to window" scales the SVG to the width of its box instead of scrolling.
 * - "Save .svg" downloads the SVG; it carries its own <style>, so the file
 *   opens and prints on its own.
 * - [data-erd-open] opens the <dialog id="erd-dialog">, [data-erd-close] closes it.
 */
(function () {
  function each(list, fn) { Array.prototype.forEach.call(list, fn); }

  // Hover highlighting, per diagram.
  each(document.querySelectorAll('svg.erd'), function (svg) {
    var tables = svg.querySelectorAll('.tbl');
    var relations = svg.querySelectorAll('.rel');

    function clear() {
      svg.classList.remove('hover');
      each(tables, function (el) { el.classList.remove('hi'); });
      each(relations, function (el) { el.classList.remove('hi'); });
    }
    each(tables, function (t) {
      t.addEventListener('mouseenter', function () {
        clear();
        var name = t.getAttribute('data-table');
        svg.classList.add('hover');
        t.classList.add('hi');
        each(relations, function (r) {
          var from = r.getAttribute('data-from');
          var to = r.getAttribute('data-to');
          if (from !== name && to !== name) return;
          r.classList.add('hi');
          each(tables, function (o) {
            var n = o.getAttribute('data-table');
            if (n === from || n === to) o.classList.add('hi');
          });
        });
      });
      t.addEventListener('mouseleave', clear);
    });
  });

  // The diagram a control belongs to: the next .erd-wrap after it in the document.
  function wrapFor(control) {
    var wraps = document.querySelectorAll('.erd-wrap');
    for (var i = 0; i < wraps.length; i++) {
      if (control.compareDocumentPosition(wraps[i]) & Node.DOCUMENT_POSITION_FOLLOWING) return wraps[i];
    }
    return wraps[0] || null;
  }

  each(document.querySelectorAll('[data-erd-fit]'), function (box) {
    box.addEventListener('change', function () {
      var wrap = wrapFor(box);
      if (wrap) wrap.classList.toggle('fit', box.checked);
    });
  });

  each(document.querySelectorAll('[data-erd-save]'), function (button) {
    button.addEventListener('click', function () {
      var wrap = wrapFor(button);
      var svg = wrap && wrap.querySelector('svg.erd');
      if (!svg) return;
      var copy = svg.cloneNode(true);
      copy.classList.remove('hover');
      each(copy.querySelectorAll('.hi'), function (el) { el.classList.remove('hi'); });
      // The XML declaration is split in two so PHP never reads it as an open tag.
      var blob = new Blob(['<' + '?xml version="1.0" encoding="UTF-8"?' + '>\n' + copy.outerHTML], { type: 'image/svg+xml' });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'northwind-erd.svg';
      document.body.appendChild(a);
      a.click();
      setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
    });
  });

  // Popup (every page but erd.php). A link with [data-erd-open] still opens
  // erd.php on Ctrl/⌘/Shift/middle click, or when there is no popup.
  var dialog = document.getElementById('erd-dialog');
  if (dialog) {
    each(document.querySelectorAll('[data-erd-open]'), function (button) {
      button.addEventListener('click', function (e) {
        if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
        e.preventDefault();
        if (typeof dialog.showModal === 'function') dialog.showModal();
        else dialog.setAttribute('open', '');
      });
    });
    each(dialog.querySelectorAll('[data-erd-close]'), function (button) {
      button.addEventListener('click', function () { dialog.close(); });
    });
    dialog.addEventListener('click', function (e) {     // click on the backdrop closes
      if (e.target === dialog) dialog.close();
    });
  }
})();
