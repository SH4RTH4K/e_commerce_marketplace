/* Shopzy Laravel admin shell — mobile sidebar + scroll chaining */
(function () {
  var sb = document.getElementById('sidebar');
  var bd = document.getElementById('backdrop');
  var btn = document.getElementById('menuBtn');
  var closeBtn = document.getElementById('sidebarClose');
  var nav = sb ? sb.querySelector('.sidebar-nav') : null;

  function openSidebar() {
    if (!sb) return;
    sb.classList.remove('-translate-x-full');
    if (bd) bd.classList.remove('hidden');
    document.body.classList.add('admin-sidebar-open');
  }

  function closeSidebar() {
    if (!sb) return;
    sb.classList.add('-translate-x-full');
    if (bd) bd.classList.add('hidden');
    document.body.classList.remove('admin-sidebar-open');
  }

  if (btn) btn.addEventListener('click', openSidebar);
  if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
  if (bd) bd.addEventListener('click', closeSidebar);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeSidebar();
  });

  window.addEventListener('resize', function () {
    if (window.innerWidth >= 1024) closeSidebar();
  });

  // When the pointer is over the sidebar and the nav can't scroll further
  // (or has nothing to scroll), forward the wheel to the page.
  if (sb) {
    sb.addEventListener('wheel', function (e) {
      if (document.body.classList.contains('admin-sidebar-open')) return;

      var target = nav && nav.contains(e.target) ? nav : null;
      if (!target) {
        window.scrollBy({ top: e.deltaY, left: 0, behavior: 'auto' });
        e.preventDefault();
        return;
      }

      var atTop = target.scrollTop <= 0;
      var atBottom = target.scrollTop + target.clientHeight >= target.scrollHeight - 1;
      var scrollingDown = e.deltaY > 0;
      var scrollingUp = e.deltaY < 0;

      if ((scrollingDown && atBottom) || (scrollingUp && atTop) || target.scrollHeight <= target.clientHeight + 1) {
        window.scrollBy({ top: e.deltaY, left: 0, behavior: 'auto' });
        e.preventDefault();
      }
    }, { passive: false });
  }
})();
