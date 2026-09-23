(function () {
  'use strict';

  const $ = (id) => document.getElementById(id);
  const params = new URLSearchParams(window.location.search);

  const data = {
    login: (params.get('login') || '').trim(),
    fio: (params.get('fio') || '').trim().replace(/\s+/g, ' '),
    gender: params.get('gender') || '',
    birthdate: params.get('birthdate') || '',
    country: params.get('country') || '',
    interests: params.getAll('interests'),
    about: (params.get('about') || '').trim(),
  };

  function plural(n, one, few, many) {
    const mod10 = n % 10;
    const mod100 = n % 100;
    if (mod10 === 1 && mod100 !== 11) return one;
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return few;
    return many;
  }

  function parseISODate(str) {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(str);
    if (!m) return null;
    const date = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
    if (date.getMonth() !== Number(m[2]) - 1) return null;
    return date;
  }

  function birthdayInYear(birth, year) {
    const d = new Date(year, birth.getMonth(), birth.getDate());
    if (d.getMonth() !== birth.getMonth()) return new Date(year, birth.getMonth() + 1, 0);
    return d;
  }

  function daysBetween(from, to) {
    const a = Date.UTC(from.getFullYear(), from.getMonth(), from.getDate());
    const b = Date.UTC(to.getFullYear(), to.getMonth(), to.getDate());
    return Math.round((b - a) / 86400000);
  }

  function getBirthdayInfo(birth) {
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());

    let next = birthdayInYear(birth, today.getFullYear());
    if (next < today) next = birthdayInYear(birth, today.getFullYear() + 1);

    return {
      days: daysBetween(today, next),
      date: next,
      turns: next.getFullYear() - birth.getFullYear(),
    };
  }

  const birth = parseISODate(data.birthdate);
  const fioParts = data.fio ? data.fio.split(' ') : [];
  const isValid =
    /^[A-Za-z][A-Za-z0-9_]{2,19}$/.test(data.login) &&
    fioParts.length >= 2 &&
    birth !== null &&
    birth <= new Date();

  if (!isValid) {
    $('welcome-empty').hidden = false;
    return;
  }

  const capitalize = (s) => s.charAt(0).toUpperCase() + s.slice(1).toLowerCase();
  const firstName = capitalize(fioParts[1]);
  const patronymic = fioParts[2] ? capitalize(fioParts[2]) : '';
  const greetingName = patronymic ? `${firstName} ${patronymic}` : firstName;

  $('welcome-title').textContent = `Добро пожаловать, ${greetingName}!`;
  $('welcome-login').textContent = data.login;
  document.title = `Добро пожаловать, ${firstName} — Сборка ПК онлайн`;

  const info = getBirthdayInfo(birth);
  const dateText = info.date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', weekday: 'long' });
  const turnsText = `${info.turns} ${plural(info.turns, 'год', 'года', 'лет')}`;

  if (info.days === 0) {
    $('birthday').classList.add('birthday--today');
    $('birthday-number').textContent = '🎉';
    $('birthday-unit').textContent = 'сегодня';
    $('birthday-text').textContent =
      `С днём рождения! Сегодня вам исполняется ${turnsText}. ` +
      'Дарим скидку 10% на любую сборку — она уже в вашем аккаунте.';
  } else {
    $('birthday-number').textContent = info.days;
    $('birthday-unit').textContent = plural(info.days, 'день', 'дня', 'дней');
    const left = plural(info.days, 'Остался', 'Осталось', 'Осталось');
    $('birthday-text').textContent = info.days === 1
      ? `Уже завтра (${dateText}) ваш день рождения — вам исполнится ${turnsText}. ` +
        'Завтра мы пришлём скидку 10% на сборку ПК.'
      : `${left} до вашего дня рождения (${dateText}), вам исполнится ${turnsText}. ` +
        'В этот день мы пришлём скидку 10% на сборку ПК.';
  }

  const genders = { male: 'Мужской', female: 'Женский' };

  $('summary-fio').textContent = fioParts.map(capitalize).join(' ');
  $('summary-gender').textContent = genders[data.gender] || '—';
  $('summary-birthdate').textContent = birth.toLocaleDateString('ru-RU', {
    day: 'numeric', month: 'long', year: 'numeric',
  });
  $('summary-country').textContent = data.country || '—';

  const list = $('summary-interests');
  if (data.interests.length) {
    data.interests.forEach((interest) => {
      const li = document.createElement('li');
      li.className = 'tags__item';
      li.textContent = interest;
      list.appendChild(li);
    });
  } else {
    list.replaceWith(document.createTextNode('—'));
  }

  if (data.about) {
    $('summary-about').textContent = data.about;
  } else {
    $('summary-about-row').hidden = true;
  }

  $('welcome').hidden = false;
})();
