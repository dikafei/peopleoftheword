(function () {
  var STORAGE_KEY = 'postViewMode';
  var DEFAULT_MODE = 'list';
  var root = document.documentElement; // <html> is never replaced by pagination

  function getSavedMode() {
    try {
      var saved = localStorage.getItem(STORAGE_KEY);
      return saved === 'grid' || saved === 'list' ? saved : DEFAULT_MODE;
    } catch (e) {
      return DEFAULT_MODE;
    }
  }

  function saveMode(mode) {
    try {
      localStorage.setItem(STORAGE_KEY, mode);
    } catch (e) {}
  }

  // Highlight the active button and update accessibility state
  function updateButtons(mode) {
    var items = document.querySelectorAll('.view-switch__grid, .view-switch__list');
    items.forEach(function (item) {
      var isGridButton = item.classList.contains('view-switch__grid');
      var isActive = (isGridButton && mode === 'grid') || (!isGridButton && mode === 'list');
      item.classList.toggle('is-active', isActive);

      var link = item.querySelector('.wp-block-button__link');
      if (link) {
        link.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      }
    });
  }

  function applyMode(mode) {
    root.classList.remove('view-grid', 'view-list');
    root.classList.add('view-' + mode);
    updateButtons(mode);
  }

  // Apply the saved mode immediately, before the page paints (prevents flicker)
  root.classList.add('view-' + getSavedMode());

  document.addEventListener('DOMContentLoaded', function () {
    // Make the link-less buttons behave like real buttons
    var links = document.querySelectorAll(
      '.view-switch__grid .wp-block-button__link, .view-switch__list .wp-block-button__link'
    );
    links.forEach(function (link) {
      link.setAttribute('role', 'button');
      link.setAttribute('tabindex', '0');
    });

    updateButtons(getSavedMode());
  });

  // Event delegation: keeps working even if page content is swapped by AJAX
  function handleSwitch(event) {
    var item = event.target.closest('.view-switch__grid, .view-switch__list');
    if (!item) return;

    event.preventDefault();
    var mode = item.classList.contains('view-switch__grid') ? 'grid' : 'list';
    saveMode(mode);
    applyMode(mode);
  }

  document.addEventListener('click', handleSwitch);

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' || event.key === ' ') {
      handleSwitch(event);
    }
  });
})();