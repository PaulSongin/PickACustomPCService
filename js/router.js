/* Весь сайт — один index.html. Страницы — блоки <section class="page">, показывается один.
   Адрес вида #/catalog?cat=gpu: кнопки «назад/вперёд» работают, шапка и подвал не перерисовываются. */
(function () {
  var TITLES = {
    index: 'PCBUILD — собери свой компьютер', catalog: 'Каталог — PCBUILD', builder: 'Конструктор — PCBUILD',
    recommendations: 'Рекомендации — PCBUILD', reviews: 'Отзывы — PCBUILD'
  };
  var PAGES = window.PAGES || {};
  var sections = {}, started = {};
  Array.prototype.forEach.call(document.querySelectorAll('.page'), function (s) { sections[s.getAttribute('data-page')] = s; });

  var menu = document.getElementById('mainNav'), toggle = document.getElementById('navToggle');
  function closeMenu() {
    menu.classList.remove('is-open');
    toggle.classList.remove('is-active');
    toggle.setAttribute('aria-expanded', 'false');
  }

  function routeName(href) { return href.replace(/^#\/?/, '').split('?')[0] || 'index'; }

  function route() {
    var raw = location.hash.replace(/^#\/?/, ''), q = raw.indexOf('?');
    var name = (q < 0 ? raw : raw.slice(0, q)) || 'index';
    var params = new URLSearchParams(q < 0 ? '' : raw.slice(q + 1));
    if (!sections[name]) { name = 'index'; params = new URLSearchParams(); if (raw) history.replaceState(null, '', '#/'); }

    Object.keys(sections).forEach(function (n) { sections[n].hidden = (n !== name); });

    var page = PAGES[name];
    if (page) {
      if (!started[name]) { started[name] = true; if (page.init) page.init(); }
      if (page.show) page.show(params);
    }

    document.title = TITLES[name];
    Array.prototype.forEach.call(document.querySelectorAll('.main-nav a'), function (a) {
      if (routeName(a.getAttribute('href')) === name) a.setAttribute('aria-current', 'page');
      else a.removeAttribute('aria-current');
    });
    closeMenu();
    window.scrollTo(0, 0);
    sections[name].focus({ preventScroll: true });
  }

  window.addEventListener('hashchange', route);

  // нажатие на ссылку на ту же самую страницу: обновить страницу и вернуться наверх
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href^="#/"]');
    if (a && a.getAttribute('href') === (location.hash || '#/')) { e.preventDefault(); route(); }
  });

  toggle.addEventListener('click', function () {
    var open = menu.classList.toggle('is-open');
    toggle.classList.toggle('is-active', open);
    toggle.setAttribute('aria-expanded', open);
  });

  route();
})();
