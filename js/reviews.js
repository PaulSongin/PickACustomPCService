window.PAGES = window.PAGES || {};
PAGES.reviews = (function () {
  var data = { items: [], count: 0, avg: 0 };

  function stars(n) { return '★'.repeat(n) + '☆'.repeat(5 - n); }
  function plural(n) {
    var m = n % 10, h = n % 100;
    if (m === 1 && h !== 11) return 'отзыва';
    return 'отзывов';
  }
  function setMsg(text, kind) {
    var msg = document.getElementById('rvMsg');
    msg.textContent = text;
    msg.className = 'form-msg' + (kind ? ' form-msg--' + kind : '');
  }

  function render() {
    document.getElementById('rvScore').textContent = data.avg.toFixed(1).replace('.', ',');
    document.getElementById('rvStars').textContent = stars(Math.round(data.avg));
    document.getElementById('rvCount').textContent = 'На основе ' + data.count + ' ' + plural(data.count);
    document.getElementById('rvList').innerHTML = data.items.map(reviewCard).join('');
  }

  function load() {
    return API.get('reviews?limit=50').then(function (d) { data = d; render(); }, function (e) {
      document.getElementById('rvList').innerHTML = '<p class="empty">' + esc(e.message) + '</p>';
    });
  }

  function init() {
    document.getElementById('rvForm').addEventListener('submit', function (e) {
      e.preventDefault();
      var form = this, btn = form.querySelector('button[type=submit]');
      var name = form.rvName.value.trim(), text = form.rvText.value.trim();

      if (name.length < 2) return setMsg('Укажите имя.', 'err');
      if (text.length < 10) return setMsg('Отзыв слишком короткий (от 10 символов).', 'err');

      btn.disabled = true;
      setMsg('Отправляем…');
      API.post('reviews', { name: name, rating: +form.rvRating.value, text: text, website: form.website.value })
        .then(function (res) {
          form.reset();
          setMsg(res.message, 'ok');
          if (res.status === 'approved') return load();
        })
        .catch(function (err) { setMsg(err.message, 'err'); })
        .then(function () { btn.disabled = false; });
    });
  }

  function show() {
    setMsg('');
    render();
    load();
  }

  return { init: init, show: show };
})();

// Карточка отзыва — общая для страницы отзывов и главной
function reviewCard(r) {
  return '<article class="review-card"><span class="review-card__stars" aria-label="Оценка ' + r.rating + ' из 5">' +
    '★'.repeat(r.rating) + '☆'.repeat(5 - r.rating) + '</span><p class="review-card__text">' + esc(r.text) +
    '</p><span class="review-card__author">' + esc(r.name) + '</span></article>';
}
