window.PAGES = window.PAGES || {};
PAGES.catalog = (function () {
  var list, boxes, minI, maxI, search;

  function render() {
    var cats = Array.from(boxes).filter(function (b) { return b.checked; })
                    .map(function (b) { return b.dataset.cat; });
    var min = +minI.value || 0, max = +maxI.value || Infinity;
    var q = search.value.trim().toLowerCase();
    var build = getBuild();

    var items = PRODUCTS.filter(function (p) {
      return cats.indexOf(p.cat) !== -1 && p.price >= min && p.price <= max &&
             (p.name + ' ' + p.spec + ' ' + CATEGORIES[p.cat]).toLowerCase().indexOf(q) !== -1;
    });

    list.innerHTML = items.length ? items.map(function (p) {
      var inBuild = build[p.cat] === p.id;
      var reason = inBuild ? '' : incompatReason(p, build);
      return '<article class="product' + (reason ? ' product--off' : '') + '">' +
        (imgOf(p) ? '<div class="product__img"><img src="' + imgOf(p) + '" alt="' + p.name + '" loading="lazy"></div>' : '') +
        '<h3>' + p.name + '</h3><p>' + p.spec + '</p>' +
        (reason ? '<p class="product__warn">' + reason + '</p>' : '') +
        '<div class="product__foot"><span class="product__price">' + fmt(p.price) + '</span>' +
        '<button class="btn btn--light" data-id="' + p.id + '"' + (reason ? ' disabled' : '') + '>' +
        (reason ? 'Несовместимо' : (inBuild ? 'В сборке' : 'В сборку')) + '</button></div></article>';
    }).join('') : '<p class="empty">Ничего не найдено — измените поиск или фильтры.</p>';
  }

  // один раз при запуске сайта: ищем элементы и вешаем обработчики
  function init() {
    list = document.getElementById('products');
    boxes = document.querySelectorAll('.filters input[data-cat]');
    minI = document.getElementById('priceMin');
    maxI = document.getElementById('priceMax');
    search = document.getElementById('search');

    list.addEventListener('click', function (e) {
      var btn = e.target.closest('button[data-id]');
      if (!btn || btn.disabled) return;
      var p = getProduct(btn.dataset.id), build = getBuild();
      if (incompatReason(p, build)) return;
      build[p.cat] = p.id;
      saveBuild(build);
      location.hash = '#/builder';
    });

    boxes.forEach(function (b) { b.addEventListener('change', render); });
    [minI, maxI, search].forEach(function (el) { el.addEventListener('input', render); });
  }

  // при каждом открытии страницы
  function show(params) {
    var cat = params.get('cat');
    boxes.forEach(function (b) { b.checked = cat ? b.dataset.cat === cat : true; });
    minI.value = maxI.value = search.value = '';
    render();
  }

  return { init: init, show: show };
})();
