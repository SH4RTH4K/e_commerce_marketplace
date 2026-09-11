/**
 * Admin bulk selection: select-all + row checkboxes + toolbar.
 * Form: form[data-bulk-form]#id — checkboxes may use form="id".
 */
(function () {
  function rowsFor(form) {
    var id = form.id;
    var list = Array.from(form.querySelectorAll('[data-bulk-row]'));
    if (id) {
      document.querySelectorAll('[data-bulk-row][form="' + id + '"]').forEach(function (cb) {
        if (list.indexOf(cb) === -1) list.push(cb);
      });
    }
    return list.filter(function (cb) {
      // Skip checkboxes in hidden mobile/desktop alternate layouts
      return !!(cb.offsetParent || (cb.getClientRects && cb.getClientRects().length));
    });
  }

  function selectAllFor(form) {
    var id = form.id;
    var el = form.querySelector('[data-bulk-select-all]');
    if (!el && id) el = document.querySelector('[data-bulk-select-all][form="' + id + '"]');
    return el;
  }

  function belongsToForm(el, form) {
    if (!el) return false;
    if (form.contains(el)) return true;
    return form.id && el.getAttribute('form') === form.id;
  }

  function initForm(form) {
    var bar = form.querySelector('[data-bulk-bar]');
    var countEl = form.querySelector('[data-bulk-count]');
    var applyBtn = form.querySelector('[data-bulk-apply]');
    var actionSelect = form.querySelector('[name="bulk_action"]');

    function selected() {
      return rowsFor(form).filter(function (cb) { return cb.checked; });
    }

    function sync() {
      var all = rowsFor(form);
      var picked = selected();
      var n = picked.length;
      var selectAll = selectAllFor(form);
      if (countEl) countEl.textContent = String(n);
      if (bar) bar.classList.remove('hidden');
      if (applyBtn) {
        var needsAction = actionSelect && actionSelect.tagName === 'SELECT' && !actionSelect.value;
        applyBtn.disabled = n === 0 || !!needsAction;
      }
      if (selectAll) {
        selectAll.checked = all.length > 0 && picked.length === all.length;
        selectAll.indeterminate = picked.length > 0 && picked.length < all.length;
      }
    }

    var selectAll = selectAllFor(form);
    if (selectAll) {
      selectAll.addEventListener('change', function () {
        rowsFor(form).forEach(function (cb) { cb.checked = selectAll.checked; });
        sync();
      });
    }

    document.addEventListener('change', function (e) {
      var t = e.target;
      if (!t) return;
      if (t.matches('[data-bulk-row]') && belongsToForm(t, form)) sync();
      if (t === actionSelect) sync();
    });

    form.addEventListener('submit', function (e) {
      var picked = selected();
      if (!picked.length) {
        e.preventDefault();
        return;
      }
      var msg = '';
      if (actionSelect && actionSelect.tagName === 'SELECT') {
        var opt = actionSelect.options[actionSelect.selectedIndex];
        if (opt && opt.getAttribute('data-confirm')) {
          msg = opt.getAttribute('data-confirm').replace(':count', String(picked.length));
        }
      }
      if (!msg && applyBtn && applyBtn.getAttribute('data-confirm')) {
        msg = applyBtn.getAttribute('data-confirm').replace(':count', String(picked.length));
      }
      if (msg && !window.confirm(msg)) {
        e.preventDefault();
      }
    });

    sync();
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-bulk-form]').forEach(initForm);
  });
})();
