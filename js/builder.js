window.PAGES = window.PAGES || {};
PAGES.builder = (function () {
  var CODES = ['15', '16', '17', '21', '22', '23', '25', '29', '33', '44'];      // города + мобильные операторы
  var slots, phone;

  function formatPhone(d) {
    return [d.slice(0, 2), d.slice(2, 5), d.slice(5, 7), d.slice(7, 9)].filter(Boolean).join('-');
  }

  function setMsg(el, text, kind) {
    el.textContent = text;
    el.className = 'form-msg' + (kind ? ' form-msg--' + kind : '');
  }

  // сообщение со ссылкой: текст + <a>, без вставки HTML из ответа сервера
  function setMsgLink(el, text, href, linkText, external) {
    setMsg(el, text + ' ', 'ok');
    var a = document.createElement('a');
    a.href = href;
    a.textContent = linkText;
    if (external) { a.target = '_blank'; a.rel = 'noopener'; }
    el.appendChild(a);
  }

  function render() {
    var build = getBuild(), count = 0, keys = Object.keys(CATEGORIES);

    slots.innerHTML = keys.map(function (cat) {
      var p = getProduct(build[cat]);
      if (p) count++;
      return '<div class="slot' + (p ? ' slot--done' : '') + '">' +
        (p && imgOf(p) ? '<img class="slot__img" src="' + esc(imgOf(p)) + '" alt="">' : '') +
        '<div class="slot__info"><span class="slot__label">' + esc(CATEGORIES[cat]) + '</span>' +
        '<span class="slot__name">' + (p ? esc(p.name) + ' · ' + fmt(p.price) : 'Не выбрано') + '</span></div>' +
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
      errors.map(function (e) { return '<li>' + esc(e.text) + '</li>'; }).join('');
    document.getElementById('sumCount').textContent = count + ' из ' + keys.length;
    document.getElementById('sumTotal').textContent = fmt(buildTotal(build));
  }

  // Сохранить сборку на сервере и скопировать ссылку
  function share() {
    var msg = document.getElementById('shareMsg'), btn = document.getElementById('shareBtn');
    var build = getBuild();
    if (!Object.keys(build).length) return setMsg(msg, 'Сначала выберите комплектующие.', 'err');

    btn.disabled = true;
    setMsg(msg, 'Сохраняем сборку…');
    API.post('builds', { items: build })
      .then(function (res) {
        var url = location.href.split('#')[0] + '#/builder?build=' + encodeURIComponent(res.code);
        var copied = navigator.clipboard && window.isSecureContext
          ? navigator.clipboard.writeText(url).then(function () { return true; }, function () { return false; })
          : Promise.resolve(false);
        return copied.then(function (ok) {
          setMsgLink(msg, ok ? 'Ссылка скопирована:' : 'Ссылка на сборку:', url, url, false);
        });
      })
      .catch(function (e) { setMsg(msg, e.message, 'err'); })
      .then(function () { btn.disabled = false; });
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

    document.getElementById('shareBtn').addEventListener('click', share);

    document.getElementById('orderForm').addEventListener('submit', function (e) {
      e.preventDefault();
      var msg = document.getElementById('orderMsg');
      function fail(text) { setMsg(msg, text, 'err'); }
      var build = getBuild(), name = document.getElementById('custName').value.trim();
      var digits = phone.value.replace(/\D/g, '');

      if (!Object.keys(build).length) return fail('Сначала выберите комплектующие.');
      if (checkCompat(build).length) return fail('Исправьте конфликты совместимости.');
      var miss = REQUIRED.filter(function (c) { return !build[c]; });
      if (miss.length) return fail('Для заказа не хватает: ' + miss.map(function (c) { return CATEGORIES[c].toLowerCase(); }).join(', ') + '.');
      if (name.length < 2) return fail('Укажите имя.');
      if (digits.length !== 9) return fail('Введите все 9 цифр номера после +375.');
      if (CODES.indexOf(digits.slice(0, 2)) === -1)
        return fail('Неверный код: допустимы 25, 29, 33, 44 или городские 15, 16, 17, 21, 22, 23.');

      var form = this, btn = form.querySelector('button[type=submit]');
      btn.disabled = true;
      setMsg(msg, 'Отправляем заказ…');

      // total — цена, которую видит покупатель: если на сервере она другая, заказ не пройдёт (409)
      API.post('orders', { name: name, phone: '+375-' + formatPhone(digits), items: build, total: buildTotal(build) })
        .then(function (res) {
          saveBuild({});
          form.reset();
          render();
          if (res.trackUrl) setMsgLink(msg, 'Заказ №' + res.id + ' принят.', res.trackUrl, 'Следить за заказом в Telegram', true);
          else setMsg(msg, 'Заказ №' + res.id + ' принят. Менеджер свяжется с вами по телефону.', 'ok');
        })
        .catch(function (err) {
          if (err.status === 409 || (err.status === 422 && /наличии|снят/.test(err.message))) {
            // цены или наличие изменились — обновляем каталог, чтобы покупатель увидел актуальное
            return loadCatalog().then(render, function () {}).then(function () { fail(err.message); });
          }
          fail(err.message);
        })
        .then(function () { btn.disabled = false; });
    });
  }

  function show(params) {
    var msg = document.getElementById('orderMsg');
    setMsg(msg, '');
    setMsg(document.getElementById('shareMsg'), '');

    var preset = PRESETS.find(function (p) { return p.id === params.get('preset'); });
    var shared = params.get('build');
    if (preset) {
      saveBuild(Object.assign({}, preset.items));
      history.replaceState(null, '', '#/builder');   // убираем ?preset из адреса, чтобы не загружать сборку повторно
    } else if (shared) {
      history.replaceState(null, '', '#/builder');
      API.get('builds/' + encodeURIComponent(shared))
        .then(function (b) {
          saveBuild(b.items);
          render();
          setMsg(msg, 'Сборка загружена по ссылке.', 'ok');
        })
        .catch(function (e) { setMsg(msg, e.message, 'err'); });
    }
    render();
  }

  return { init: init, show: show };
})();
