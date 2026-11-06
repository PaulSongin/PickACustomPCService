window.PAGES = window.PAGES || {};
PAGES.reviews = (function () {
  var KEY = 'pcbuild_reviews';
  var seed = [
    { name: 'Андрей К.', rating: 5, text: 'Собрал игровую сборку за полчаса, все детали подошли друг к другу сразу — не пришлось ничего гуглить.' },
    { name: 'Марина С.', rating: 5, text: 'Удобно, что сразу видно итоговую цену. Заказ оформил через сайт, менеджер написал в тот же день.' },
    { name: 'Дмитрий В.', rating: 4, text: 'Не разбираюсь в железе, взял готовую сборку из рекомендаций — всё работает отлично.' },
    { name: 'Илья П.', rating: 5, text: 'Конструктор сам подсказал, что блока питания на 550 Вт мне не хватит. Сэкономил кучу нервов.' }
  ];
  var reviews = [];

  function load() {
    try { return JSON.parse(localStorage.getItem(KEY)) || seed; } catch (e) { return seed; }
  }
  function esc(s) {
    return s.replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function stars(n) { return '★'.repeat(n) + '☆'.repeat(5 - n); }
  function plural(n) {
    var m = n % 10, h = n % 100;
    if (m === 1 && h !== 11) return 'отзыва';
    return 'отзывов';
  }

  function render() {
    var sum = reviews.reduce(function (s, r) { return s + r.rating; }, 0);
    var avg = reviews.length ? sum / reviews.length : 0;

    document.getElementById('rvScore').textContent = avg.toFixed(1).replace('.', ',');
    document.getElementById('rvStars').textContent = stars(Math.round(avg));
    document.getElementById('rvCount').textContent = 'На основе ' + reviews.length + ' ' + plural(reviews.length);

    document.getElementById('rvList').innerHTML = reviews.slice().reverse().map(function (r) {
      return '<article class="review-card"><span class="review-card__stars" aria-label="Оценка ' + r.rating + ' из 5">' +
        stars(r.rating) + '</span><p class="review-card__text">' + esc(r.text) +
        '</p><span class="review-card__author">' + esc(r.name) + '</span></article>';
    }).join('');
  }

  function init() {
    document.getElementById('rvForm').addEventListener('submit', function (e) {
      e.preventDefault();
      var msg = document.getElementById('rvMsg');
      var name = this.rvName.value.trim(), text = this.rvText.value.trim();

      if (name.length < 2) { msg.textContent = 'Укажите имя.'; msg.className = 'form-msg form-msg--err'; return; }
      if (text.length < 10) { msg.textContent = 'Отзыв слишком короткий (от 10 символов).'; msg.className = 'form-msg form-msg--err'; return; }

      reviews.push({ name: name, rating: +this.rvRating.value, text: text });
      localStorage.setItem(KEY, JSON.stringify(reviews));
      this.reset();
      render();
      msg.textContent = 'Спасибо! Отзыв добавлен.';
      msg.className = 'form-msg form-msg--ok';
    });
  }

  function show() {
    reviews = load();
    var msg = document.getElementById('rvMsg');
    msg.textContent = ''; msg.className = 'form-msg';
    render();
  }

  return { init: init, show: show };
})();
