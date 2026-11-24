/**
 * Lesson tables: click "Date" or "Lesson" in the header row to sort.
 * Click again to reverse. Dates come from the row's potw-sort-YYYYMMDD class;
 * lessons sort alphabetically with numbers in order (Acts 2 before Acts 10).
 */
(function () {
  var collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });

  function dateKey(li) {
    var m = li.className.match(/potw-sort-(\d+)/);
    return m ? parseInt(m[1], 10) : 0;
  }

  function titleKey(li) {
    var t = li.querySelector('.wp-block-post-title');
    return t ? t.textContent.trim().replace(/^[^\p{L}\p{N}]+/u, '').replace(/^the\s+/i, '') : ''; // ignore leading quotes and "The"
  }

  function setup(list) {
    var head = list.querySelector(':scope > .potw-list-head');
    if (!head || head.dataset.sortable) return;
    head.dataset.sortable = '1';
    head.removeAttribute('aria-hidden');

    var cols = [
      { el: head.children[0], key: 'date' },
      { el: head.children[1], key: 'title' }
    ];
    var state = { key: null, dir: 1 };

    cols.forEach(function (col) {
      if (!col.el) return;
      var label = col.el.textContent;
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'potw-sort-btn';
      btn.innerHTML = '<span>' + label + '</span><span class="potw-sort-btn__icon" aria-hidden="true"></span>';
      btn.setAttribute('aria-label', 'Sort by ' + label.toLowerCase());
      col.el.textContent = '';
      col.el.appendChild(btn);

      btn.addEventListener('click', function () {
        state.dir = state.key === col.key ? -state.dir : (col.key === 'date' ? -1 : 1);
        state.key = col.key;
        var rows = Array.prototype.slice.call(list.querySelectorAll(':scope > li.wp-block-post'));
        rows.sort(function (a, b) {
          var c = col.key === 'date'
            ? dateKey(a) - dateKey(b) || collator.compare(titleKey(a), titleKey(b))
            : collator.compare(titleKey(a), titleKey(b)) || dateKey(a) - dateKey(b);
          return c * state.dir;
        });
        rows.forEach(function (r) { list.appendChild(r); });
        cols.forEach(function (c) {
          var b = c.el && c.el.querySelector('.potw-sort-btn');
          if (!b) return;
          b.classList.remove('is-asc', 'is-desc');
          if (c.key === state.key) b.classList.add(state.dir > 0 ? 'is-asc' : 'is-desc');
        });
      });
    });
  }

  function init() {
    document.querySelectorAll('.post-view-area .wp-block-post-template').forEach(setup);
  }

  // grid-list.js adds the header row on DOMContentLoaded; run just after it.
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { setTimeout(init, 0); });
  } else {
    setTimeout(init, 0);
  }
})();
