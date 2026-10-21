/**
 * Contact / registration form: "I will attend … in-person at:" and
 * "I will attend … via Zoom." are two separate radio fields in the Jetpack
 * form. Make them behave as ONE choice:
 *  - picking a time in one group clears the other group;
 *  - the form can't be sent until one of them is picked.
 *
 * Groups are found by their label ("I will attend…"), so the form can be
 * edited freely as long as both labels keep starting with "I will attend".
 */
(function () {
    'use strict';

    var LABEL = /^\s*I will attend/i;
    var MESSAGE = 'Please choose a time, either in-person or via Zoom.';

    function groupsOf(form) {
        return Array.prototype.filter.call(form.querySelectorAll('fieldset'), function (fs) {
            var legend = fs.querySelector('legend');
            return legend && LABEL.test(legend.textContent) && fs.querySelector('input[type="radio"]');
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form').forEach(function (form) {
            var groups = groupsOf(form);
            if (groups.length < 2) {
                return;
            }
            var syncing = false;
            var error = null;

            function radios(fs) {
                return Array.prototype.slice.call(fs.querySelectorAll('input[type="radio"]'));
            }

            // Clear a group, and tell Jetpack's form script the field is now empty.
            function clear(fs) {
                var list = radios(fs);
                if (!list.some(function (r) { return r.checked; })) {
                    return;
                }
                list.forEach(function (r) { r.checked = false; });
                var first = list[0];
                var value = first.value;
                syncing = true;
                first.value = '';
                first.dispatchEvent(new Event('change', { bubbles: true }));
                first.value = value;
                syncing = false;
            }

            function anyChecked() {
                return groups.some(function (fs) {
                    return radios(fs).some(function (r) { return r.checked; });
                });
            }

            function showError(show) {
                if (show && !error) {
                    error = document.createElement('p');
                    error.className = 'potw-attend-error';
                    error.setAttribute('role', 'alert');
                    error.textContent = MESSAGE;
                    var anchor = groups[0].closest('.wp-block-columns, .wp-block-group, .grunion-field-wrap') || groups[0];
                    anchor.parentNode.insertBefore(error, anchor);
                }
                if (error) {
                    error.hidden = !show;
                }
                groups.forEach(function (fs) {
                    fs.classList.toggle('potw-attend-invalid', !!show);
                });
            }

            groups.forEach(function (fs) {
                fs.addEventListener('change', function (e) {
                    if (syncing || !e.target.matches('input[type="radio"]') || !e.target.checked) {
                        return;
                    }
                    groups.forEach(function (other) {
                        if (other !== fs) {
                            clear(other);
                        }
                    });
                    showError(false);
                });
            });

            // Runs before Jetpack's own submit handler (capture phase).
            form.addEventListener('submit', function (e) {
                if (anyChecked()) {
                    return;
                }
                e.preventDefault();
                e.stopImmediatePropagation();
                showError(true);
                var first = radios(groups[0])[0];
                if (first) {
                    first.focus();
                    (error || first).scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }, true);
        });
    });
})();
