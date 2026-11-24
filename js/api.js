/* Обращения к PHP API. Адрес относительный: сайт и API открываются с одного сервера.
   POST-запросы автоматически получают CSRF-токен (GET api/csrf) и передают его в заголовке. */
var API = (function () {
  var BASE = 'api/';
  var csrf = null;

  function request(method, path, body) {
    var opts = { method: method, headers: { Accept: 'application/json' }, credentials: 'same-origin' };
    if (body !== undefined) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }
    if (csrf && method !== 'GET') opts.headers['X-CSRF-Token'] = csrf;

    return fetch(BASE + path, opts)
      .catch(function () {
        var e = new Error('Сервер недоступен. Проверьте подключение и попробуйте ещё раз.');
        e.status = 0;
        throw e;
      })
      .then(function (r) {
        return r.json().catch(function () { return {}; }).then(function (j) {
          if (!r.ok) {
            var e = new Error(j.error || 'Ошибка сервера (' + r.status + ')');
            e.status = r.status;
            e.data = j;
            throw e;
          }
          return j;
        });
      });
  }

  function token() {
    return csrf ? Promise.resolve(csrf) : request('GET', 'csrf').then(function (j) { csrf = j.token; return csrf; });
  }

  function post(path, body, retried) {
    return token().then(function () { return request('POST', path, body); }).catch(function (e) {
      // сессия истекла — берём новый токен и повторяем один раз
      if (e.status === 403 && e.data && e.data.code === 'csrf' && !retried) {
        csrf = null;
        return post(path, body, true);
      }
      throw e;
    });
  }

  return {
    get: function (path) { return request('GET', path); },
    post: function (path, body) { return post(path, body, false); }
  };
})();
