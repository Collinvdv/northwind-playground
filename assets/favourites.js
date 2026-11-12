/*
 * Favourite exercises: a star on the exercise page and in the chapter's list
 * of exercises. Favourites live in this browser only (localStorage), as
 * [{id: "3.2", title: "3.2 Customers per country"}]. The home page lists
 * them (#favourites); the sidebar grid marks them with a small star.
 *
 *   <button class="fav-toggle" data-fav="3.2" data-fav-title="3.2 …" hidden>   star
 *   <a data-fav="3.2">                                                         marked when favourite
 */
(function () {
  var KEY = 'nw.favourites';

  function load() {
    try {
      var list = JSON.parse(localStorage.getItem(KEY) || '[]');
      return Array.isArray(list) ? list : [];
    } catch (e) {
      return [];
    }
  }

  function store(list) {
    try { localStorage.setItem(KEY, JSON.stringify(list)); } catch (e) { /* private window: stars last until reload */ }
  }

  var favs = load();

  function has(id) { return favs.some(function (f) { return f.id === id; }); }

  function url(id) {
    var p = id.split('.');
    return 'exercise.php?c=' + encodeURIComponent(p[0]) + '&e=' + encodeURIComponent(p[1]);
  }

  // Chapter, then exercise number: 2.10 after 2.9.
  function byNumber(a, b) {
    var x = a.id.split('.').map(Number), y = b.id.split('.').map(Number);
    return x[0] - y[0] || x[1] - y[1];
  }

  function render() {
    document.querySelectorAll('button.fav-toggle').forEach(function (btn) {
      var on = has(btn.dataset.fav);
      btn.hidden = false;
      btn.textContent = on ? '★' : '☆';
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      btn.title = on ? 'Remove from favourites' : 'Add to favourites';
    });
    document.querySelectorAll('a[data-fav]').forEach(function (a) {
      a.classList.toggle('fav', has(a.dataset.fav));
    });

    var section = document.getElementById('favourites');
    if (!section) return;
    var list = section.querySelector('ol');
    list.innerHTML = '';
    favs.slice().sort(byNumber).forEach(function (f) {
      var li = document.createElement('li');
      var a = document.createElement('a');
      a.href = url(f.id);
      a.textContent = f.title;
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'fav-toggle';
      btn.dataset.fav = f.id;
      btn.dataset.favTitle = f.title;
      btn.textContent = '★';
      btn.setAttribute('aria-pressed', 'true');
      btn.title = 'Remove from favourites';
      li.append(a, ' ', btn);
      list.appendChild(li);
    });
    section.hidden = favs.length === 0;
  }

  // Keep a stored title up to date when the exercise was renamed.
  document.querySelectorAll('button.fav-toggle').forEach(function (btn) {
    favs.forEach(function (f) { if (f.id === btn.dataset.fav) f.title = btn.dataset.favTitle; });
  });

  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('button.fav-toggle');
    if (!btn) return;
    var id = btn.dataset.fav;
    favs = has(id) ? favs.filter(function (f) { return f.id !== id; }) : favs.concat([{ id: id, title: btn.dataset.favTitle }]);
    store(favs);
    render();
  });

  // Starred in another tab.
  window.addEventListener('storage', function (e) {
    if (e.key === KEY) { favs = load(); render(); }
  });

  render();
})();
