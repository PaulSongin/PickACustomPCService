window.PAGES = window.PAGES || {};
PAGES.builder = (function () {
  // Если сайт открыт двойным кликом по файлу, заказы уходят на локальный сервер бота
  var API = location.protocol === 'file:' ? 'http://localhost:3000' : '';
  var NEED = ['cpu', 'mb', 'ram', 'case', 'psu', 'ssd'];                         // обязательные детали для заказа
  var CODES = ['15', '16', '17', '21', '22', '23', '25', '29', '33', '44'];      // города + мобильные операторы
  var keys = Object.keys(CATEGORIES);
  var slots, phone;

  function formatPhone(d) {
    return [d.slice(0, 2), d.slice(2, 5), d.slice(5, 7), d.slice(7, 9)].filter(Boolean).join('-');
  }

  function render() {
    var build = getBuild(), count = 0;

    slots.innerHTML = keys.map(function (cat) {
      var p = getProduct(build[cat]);
      if (p) count++;
      return '<div class="slot' + (p ? ' slot--done' : '') + '">' +
        (p && imgOf(p) ? '<img class="slot__img" src="' + imgOf(p) + '" alt="">' : '') +
        '<div class="slot__info"><span class="slot__label">' + CATEGORIES[cat] + '</span>' +
        '<span class="slot__name">' + (p ? p.name + ' · ' + fmt(p.price) : 'Не выбрано') + '</span></div>' +
        '<div class="slot__actions"><a href="#/catalog?cat=' + cat + '" class="btn btn--light">' +
        (p ? 'Изменить' : 'Выбрать') + '</a>' +
        (p ? '<button class="btn btn--light" data-remove="' + cat + '">Убрать</button>' : '') +
        '</div></div>';
    }).join('');

    var errors = checkCompat(build);
    var status = document.getElementById('sumCompat');
    status.textContent = errors.length ? 'Есть конфликты' : 'Без конфликтов';
    status.style.color = errors.length ? '#FF7A7A' : '';
    document.getElementById('sumErrors').innerHTML =
      errors.map(function (e) { return '<li>' + e.text + '</li>'; }).join('');
    document.getElementById('sumCount').textContent = count + ' из ' + keys.length;
    document.getElementById('sumTotal').textContent = fmt(buildTotal(build));
  }

  function init() {
    slots = document.getElementById('slots');
    phone = document.getElementById('phone');

    // телефон: +375- уже в поле, вводятся только 9 цифр
    phone.addEventListener('input', function () {
      var d = phone.value.replace(/\D/g, '');
      if (d.length > 9) {                       // вставили номер целиком
        if (d.indexOf('375') === 0) d = d.slice(3);
        else if (d.indexOf('80') === 0) d = d.slice(2);
      }
      phone.value = formatPhone(d.slice(0, 9));
    });

    slots.addEventListener('click', function (e) {
      var btn = e.target.closest('button[data-remove]');
      if (!btn) return;
      var build = getBuild();
      delete build[btn.dataset.remove];
      saveBuild(build);
      render();
    });

    document.getElementById('orderForm').addEventListener('submit', function (e) {
      e.preventDefault();
      var msg = document.getElementById('orderMsg');
      function fail(text) { msg.textContent = text; msg.className = 'form-msg form-msg--err'; }
      var build = getBuild(), name = document.getElementById('custName').value.trim();
      var digits = phone.value.replace(/\D/g, '');

      if (!Object.keys(build).length) return fail('Сначала выберите комплектующие.');
      if (checkCompat(build).length) return fail('Исправьте конфликты совместимости.');
      var miss = NEED.filter(function (c) { return !build[c]; });
      if (miss.length) return fail('Для заказа не хватает: ' + miss.map(function (c) { return CATEGORIES[c].toLowerCase(); }).join(', ') + '.');
      if (name.length < 2) return fail('Укажите имя.');
      if (digits.length !== 9) return fail('Введите все 9 цифр номера после +375.');
      if (CODES.indexOf(digits.slice(0, 2)) === -1)
        return fail('Неверный код: допустимы 25, 29, 33, 44 или городские 15, 16, 17, 21, 22, 23.');

      var form = this, btn = form.querySelector('button[type=submit]');
      btn.disabled = true;
      msg.className = 'form-msg';
      msg.textContent = 'Отправляем заказ…';

      fetch(API + '/api/orders', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: name, phone: '+375-' + formatPhone(digits), items: build })
      })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (res) {
          if (!res.ok) return fail(res.j.error || 'Не удалось оформить заказ.');
          saveBuild({});
          form.reset();
          render();
          msg.className = 'form-msg form-msg--ok';
          msg.innerHTML = 'Заказ №' + res.j.id + ' принят. <a href="' + res.j.trackUrl +
            '" target="_blank" rel="noopener">Следить за заказом в Telegram</a>';
        })
        .catch(function () { fail('Сервер заказов недоступен. Запущен ли бот (node server.js)?'); })
        .then(function () { btn.disabled = false; });
    });
  }

  function show(params) {
    var preset = PRESETS.find(function (p) { return p.id === params.get('preset'); });
    if (preset) {
      saveBuild(Object.assign({}, preset.items));
      history.replaceState(null, '', '#/builder');   // убираем ?preset из адреса, чтобы не загружать сборку повторно
    }
    var msg = document.getElementById('orderMsg');
    msg.textContent = ''; msg.className = 'form-msg';
    render();
  }

  return { init: init, show: show };
})();
