/**
 * Lecture tabs for single lessons.
 *
 * The lesson body holds one ".potw-lecture" group per lecturer inside
 * ".potw-lectures" (created by the POTW Lesson Importer). With 2+ lectures this
 * builds an accessible tab bar from each lecture's ".potw-lecture-meta" line,
 * shows one lecture at a time and keeps the Table of Contents in sync
 * (".lesson-toc [data-lecture]" lists, rendered by [lesson_toc]).
 */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.potw-lectures').forEach(function (wrap, w) {
        var lectures = Array.prototype.filter.call(wrap.children, function (el) {
            return el.classList.contains('potw-lecture');
        });
        if (lectures.length < 2) {
            return;
        }

        var tocLists = document.querySelectorAll('.lesson-toc [data-lecture]');
        var tablist  = document.createElement('div');
        tablist.className = 'potw-lecture-tabs';
        tablist.setAttribute('role', 'tablist');
        tablist.setAttribute('aria-label', 'Choose a lecture');

        var tabs = lectures.map(function (lecture, i) {
            var meta  = lecture.querySelector('.potw-lecture-meta');
            var label = meta ? meta.textContent.trim() : 'Lecture ' + (i + 1);
            var id    = 'potw-lecture-' + w + '-' + i;

            lecture.id = lecture.id || id;
            lecture.setAttribute('role', 'tabpanel');
            lecture.setAttribute('aria-labelledby', id + '-tab');
            lecture.setAttribute('tabindex', '0');

            var tab = document.createElement('button');
            tab.type = 'button';
            tab.id = id + '-tab';
            tab.className = 'potw-lecture-tab';
            tab.setAttribute('role', 'tab');
            tab.setAttribute('aria-controls', lecture.id);

            var parts = label.split('·');
            tab.innerHTML = '';
            var name = document.createElement('span');
            name.className = 'potw-lecture-tab__name';
            name.textContent = parts[0].trim();
            tab.appendChild(name);
            if (parts[1]) {
                var year = document.createElement('span');
                year.className = 'potw-lecture-tab__year';
                year.textContent = parts[1].trim();
                tab.appendChild(year);
            }

            tab.addEventListener('click', function () { select(i, true); });
            tab.addEventListener('keydown', function (e) {
                var next = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: lectures.length - 1 }[e.key];
                if (next === undefined) { return; }
                e.preventDefault();
                next = (next + lectures.length) % lectures.length;
                select(next, true);
                tabs[next].focus();
            });
            tablist.appendChild(tab);
            return tab;
        });

        function select(index, fromUser) {
            lectures.forEach(function (lecture, i) {
                var on = i === index;
                lecture.hidden = !on;
                tabs[i].setAttribute('aria-selected', on ? 'true' : 'false');
                tabs[i].tabIndex = on ? 0 : -1;
            });
            tocLists.forEach(function (list) {
                list.hidden = String(index) !== list.getAttribute('data-lecture');
            });
            // Let lesson-reading.js (reading time, TOC highlight) follow the active tab.
            document.dispatchEvent(new CustomEvent('potw:lecture-change', { detail: { index: index } }));
            if (fromUser) {
                try { sessionStorage.setItem('potw-lecture:' + location.pathname, String(index)); } catch (e) {}
            }
        }

        wrap.classList.add('has-tabs');
        wrap.insertBefore(tablist, wrap.firstChild);

        // Start on: the lecture containing the #hash target, else the last choice, else the newest.
        var start = 0;
        var target = location.hash ? document.getElementById(decodeURIComponent(location.hash.slice(1))) : null;
        if (target) {
            lectures.forEach(function (l, i) { if (l.contains(target)) { start = i; } });
        } else {
            try {
                var saved = parseInt(sessionStorage.getItem('potw-lecture:' + location.pathname), 10);
                if (saved >= 0 && saved < lectures.length) { start = saved; }
            } catch (e) {}
        }
        select(start, false);

        // TOC links: jump into the right lecture.
        document.querySelectorAll('.lesson-toc a[href^="#"]').forEach(function (a) {
            a.addEventListener('click', function () {
                var el = document.getElementById(decodeURIComponent(a.getAttribute('href').slice(1)));
                lectures.forEach(function (l, i) { if (el && l.contains(el) && l.hidden) { select(i, true); } });
            });
        });
    });
});

/**
 * Table of Contents: collapsible on phones, always open on larger screens.
 * Below 782px the TOC sits above the lesson body, so it starts collapsed and
 * closes again after a link is chosen.
 */
document.addEventListener('DOMContentLoaded', function () {
    var details = document.querySelectorAll('.lesson-toc__details');
    if (!details.length || !window.matchMedia) {
        return;
    }
    var phone = window.matchMedia('(max-width: 781px)');

    function apply() {
        details.forEach(function (d) {
            d.open = !phone.matches;
        });
    }
    apply();
    if (phone.addEventListener) {
        phone.addEventListener('change', apply);
    } else if (phone.addListener) {
        phone.addListener(apply);
    }

    details.forEach(function (d) {
        // Larger screens: keep it open (the summary is just a title there).
        d.addEventListener('toggle', function () {
            if (!phone.matches && !d.open) {
                d.open = true;
            }
        });
        d.querySelectorAll('a[href^="#"]').forEach(function (a) {
            a.addEventListener('click', function () {
                if (phone.matches) {
                    d.open = false;
                }
            });
        });
    });
});
