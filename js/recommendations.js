window.PAGES = window.PAGES || {};
PAGES.recommendations = (function () {
  var grid, chips;

  function render(min, max) {
    var items = PRESETS.filter(function (p) {
      var price = buildTotal(p.items);
      return price >= min && price < max;
    });
    grid.innerHTML = items.length ? items.map(function (p) {
      return '<article class="build-card' + (p.id === 'gaming' ? ' build-card' : '') + '">' +
        '<span class="build-card__tag">' + p.tag + '</span><h3>' + p.title + '</h3>' +
        '<p class="build-card__specs">' + presetSpecs(p) + '</p>' +
        '<div class="build-card__footer"><span class="build-card__price">от ' + fmt(buildTotal(p.items)) + '</span>' +
        '<a href="#/builder?preset=' + p.id + '" class="btn btn--light">Подробнее</a></div></article>';
    }).join('') : '<p class="empty">В этом диапазоне пока нет сборок.</p>';
  }

  function init() {
    grid = document.getElementById('recs');
    chips = document.querySelectorAll('#recsChips button');
    chips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        chips.forEach(function (c) { c.className = 'btn btn--light'; });
        chip.className = 'btn btn--accent';
        render(+chip.dataset.min || 0, +chip.dataset.max || Infinity);
      });
    });
  }

  function show() {
    chips.forEach(function (c, i) { c.className = i === 0 ? 'btn btn--accent' : 'btn btn--light'; });
    render(0, Infinity);
  }

  return { init: init, show: show };
})();
