/**
 * Single lesson: reading time + progress, and "you are here" in the TOC.
 *
 * - Reading time is counted for what the reader is actually reading: the
 *   selected lecture tab (".potw-lecture" not hidden), otherwise the whole
 *   lesson body. It shows "12 min read", then "8 min left" while scrolling,
 *   then "Finished" — with a bar that fills up.
 *   · In the Table of Contents (sticky on larger screens).
 *   · On phones, where the TOC is collapsed at the top, as a thin bar fixed to
 *     the top of the screen.
 * - The TOC link of the section being read is highlighted (aria-current).
 * - Switching lecture tabs (event "potw:lecture-change") recalculates both.
 */
(function () {
    'use strict';

    var WORDS_PER_MINUTE = 200;
    var READING_LINE = 0.3; // a heading counts as "being read" once it passes 30% from the top.

    document.addEventListener('DOMContentLoaded', function () {
        var body = document.querySelector('.wp-block-post-content, .entry-content');
        var toc  = document.querySelector('.lesson-toc');
        if (!body) {
            return;
        }

        /* ---------- Progress UI ---------- */

        var tocProgress = null;
        if (toc) {
            tocProgress = document.createElement('div');
            tocProgress.className = 'lesson-progress';
            tocProgress.innerHTML =
                '<p class="lesson-progress__label" aria-live="polite"></p>' +
                '<div class="lesson-progress__track" role="progressbar" aria-label="Reading progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">' +
                '<span class="lesson-progress__fill"></span></div>';
            var summary = toc.querySelector('.lesson-toc__title');
            var holder  = toc.querySelector('.lesson-toc__details') || toc;
            holder.insertBefore(tocProgress, summary ? summary.nextSibling : holder.firstChild);
        }

        var topBar = document.createElement('div');
        topBar.className = 'lesson-progress-top';
        topBar.setAttribute('aria-hidden', 'true');
        topBar.innerHTML = '<span class="lesson-progress-top__fill"></span>';
        document.body.appendChild(topBar);

        var label   = tocProgress && tocProgress.querySelector('.lesson-progress__label');
        var track   = tocProgress && tocProgress.querySelector('.lesson-progress__track');
        var fills   = document.querySelectorAll('.lesson-progress__fill, .lesson-progress-top__fill');

        /* ---------- What is being read ---------- */

        var target, minutes, headings, links, lastLabel = '';

        function activeTarget() {
            var lecture = body.querySelector('.potw-lecture:not([hidden])');
            return lecture || body;
        }

        function measure() {
            target = activeTarget();
            var clone = target.cloneNode(true);
            clone.querySelectorAll('.potw-lecture-tabs, .potw-lecture-meta, script, style').forEach(function (n) { n.remove(); });
            var words = (clone.textContent || '').trim().split(/\s+/).filter(Boolean).length;
            minutes = Math.max(1, Math.round(words / WORDS_PER_MINUTE));
            // A PDF-only lesson has nothing to "read" on the page: hide the reading time.
            var pdfOnly = words < 60 && !!target.querySelector('.potw-pdf-embed, object, iframe');
            if (tocProgress) { tocProgress.hidden = pdfOnly; }
            topBar.hidden = pdfOnly;

            headings = Array.prototype.slice.call(target.querySelectorAll('h2[id], h3[id]'));
            links = {};
            if (toc) {
                toc.querySelectorAll('a[href^="#"]').forEach(function (a) {
                    links[decodeURIComponent(a.getAttribute('href').slice(1))] = a;
                });
            }
            lastLabel = '';
            update();
        }

        /* ---------- Scroll handling ---------- */

        var ticking = false;
        function onScroll() {
            if (!ticking) {
                ticking = true;
                window.requestAnimationFrame(function () {
                    ticking = false;
                    update();
                });
            }
        }

        function update() {
            if (!target) {
                return;
            }
            var vh   = window.innerHeight;
            var rect = target.getBoundingClientRect();
            var line = vh * READING_LINE;

            // 0 when the text starts at the reading line, 1 when its end passes the bottom of the screen.
            var total = Math.max(1, rect.height - vh * (1 - READING_LINE));
            var p     = Math.min(1, Math.max(0, (line - rect.top) / total));
            var pct   = Math.round(p * 100);

            fills.forEach(function (f) { f.style.transform = 'scaleX(' + p + ')'; });
            topBar.classList.toggle('is-visible', p > 0 && p < 1);

            if (label) {
                var text;
                if (p >= 0.98) {
                    text = 'Finished ✓';
                } else if (p < 0.02) {
                    text = minutes + ' min read';
                } else {
                    text = Math.max(1, Math.ceil(minutes * (1 - p))) + ' min left';
                }
                if (text !== lastLabel) {
                    label.textContent = text;
                    lastLabel = text;
                }
                track.setAttribute('aria-valuenow', String(pct));
                tocProgress.classList.toggle('is-finished', p >= 0.98);
            }

            highlight(line);
        }

        /* ---------- TOC highlight ---------- */

        var current = null;
        function highlight(line) {
            if (!toc) {
                return;
            }
            var active = null;
            for (var i = 0; i < headings.length; i++) {
                if (headings[i].getBoundingClientRect().top - line <= 1) {
                    active = headings[i];
                } else {
                    break;
                }
            }
            // Before the first heading: "Start of lesson" is where you are.
            var link = active ? links[active.id] : links['lesson-start'];
            if (link === current) {
                return;
            }
            toc.querySelectorAll('.is-current, .is-current-parent').forEach(function (el) {
                el.classList.remove('is-current', 'is-current-parent');
            });
            toc.querySelectorAll('[aria-current]').forEach(function (el) { el.removeAttribute('aria-current'); });
            current = link || null;
            if (!link) {
                return;
            }
            link.setAttribute('aria-current', 'location');
            var item = link.closest('.toc-item');
            if (item) {
                item.classList.add('is-current');
                var parent = item.parentElement && item.parentElement.closest('.toc-item--h2');
                if (parent) {
                    parent.classList.add('is-current-parent');
                }
                keepVisible(item);
            }
        }

        // If the TOC is taller than the screen and scrolls on its own, keep the current item in view.
        function keepVisible(item) {
            var box = item.closest('.potw-sticky') || toc;
            if (box.scrollHeight <= box.clientHeight + 2) {
                return;
            }
            var b = box.getBoundingClientRect();
            var r = item.getBoundingClientRect();
            if (r.top < b.top + 40 || r.bottom > b.bottom - 40) {
                box.scrollTop += (r.top - b.top) - b.height / 2;
            }
        }

        /* ---------- Wire up ---------- */

        measure();
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll);
        window.addEventListener('load', measure); // images/fonts change heights.
        document.addEventListener('potw:lecture-change', function () {
            current = null;
            measure();
        });
    });
})();
