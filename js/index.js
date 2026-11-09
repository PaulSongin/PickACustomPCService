window.PAGES = window.PAGES || {};
PAGES.index = {
  show: function () {
    document.querySelectorAll('[data-preset-price]').forEach(function (el) {
      var p = PRESETS.find(function (x) { return x.id === el.dataset.presetPrice; });
      if (p) el.textContent = 'от ' + fmt(buildTotal(p.items));
    });
    document.querySelectorAll('[data-preset-specs]').forEach(function (el) {
      var p = PRESETS.find(function (x) { return x.id === el.dataset.presetSpecs; });
      if (p) el.textContent = presetSpecs(p);
    });

    // три свежих отзыва из базы (если сервер не ответит — останутся отзывы из разметки)
    API.get('reviews?limit=3').then(function (d) {
      if (d.items.length) document.getElementById('homeReviews').innerHTML = d.items.map(reviewCard).join('');
    }, function () {});
  }
};
