(function () {
  'use strict';

  const form = document.getElementById('register-form');
  if (!form) return;

  form.noValidate = true;

  const MIN_AGE = 14;
  const MAX_AGE = 106;
  const INTERESTS_MAX = 5;

  const $ = (id) => document.getElementById(id);

  const fields = {
    login: $('login'),
    password: $('password'),
    passwordConfirm: $('password-confirm'),
    fio: $('fio'),
    birthdate: $('birthdate'),
    country: $('country'),
    about: $('about'),
    agree: $('agree'),
  };
  const genderInputs = form.querySelectorAll('input[name="gender"]');
  const interestInputs = form.querySelectorAll('input[name="interests"]');

  function toISODate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }

  function yearsAgo(years) {
    const d = new Date();
    d.setFullYear(d.getFullYear() - years);
    return d;
  }

  function getAge(birth) {
    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const hadBirthday =
      today.getMonth() > birth.getMonth() ||
      (today.getMonth() === birth.getMonth() && today.getDate() >= birth.getDate());
    if (!hadBirthday) age--;
    return age;
  }

  fields.birthdate.max = toISODate(yearsAgo(MIN_AGE));
  fields.birthdate.min = toISODate(yearsAgo(MAX_AGE));

  const validators = {
    login() {
      const v = fields.login.value.trim();
      if (!v) return 'Введите логин';
      if (v.length < 3 || v.length > 20) return 'Логин должен быть от 3 до 20 символов';
      if (!/^[A-Za-z]/.test(v)) return 'Логин должен начинаться с латинской буквы';
      if (!/^[A-Za-z0-9_]+$/.test(v)) return 'Допустимы только латинские буквы, цифры и «_»';
      return '';
    },

    password() {
      const v = fields.password.value;
      if (!v) return 'Введите пароль';
      if (v.length < 8) return `Слишком короткий пароль: ${v.length} из 8 символов`;
      if (v.length > 64) return 'Пароль не должен быть длиннее 64 символов';
      if (/\s/.test(v)) return 'Пароль не должен содержать пробелы';
      if (!/[a-zа-яё]/.test(v)) return 'Добавьте строчную букву';
      if (!/[A-ZА-ЯЁ]/.test(v)) return 'Добавьте заглавную букву';
      if (!/\d/.test(v)) return 'Добавьте цифру';
      if (fields.login.value.trim() && v.toLowerCase().includes(fields.login.value.trim().toLowerCase())) {
        return 'Пароль не должен содержать логин';
      }
      return '';
    },

    passwordConfirm() {
      const v = fields.passwordConfirm.value;
      if (!v) return 'Повторите пароль';
      if (v !== fields.password.value) return 'Пароли не совпадают';
      return '';
    },

    fio() {
      const v = fields.fio.value.trim().replace(/\s+/g, ' ');
      if (!v) return 'Введите ФИО';
      if (/[^A-Za-zА-Яа-яЁё\s-]/.test(v)) return 'ФИО может содержать только буквы, пробелы и дефис';
      const parts = v.split(' ');
      if (parts.length < 2) return 'Укажите как минимум фамилию и имя';
      if (parts.length > 3) return 'Укажите не более трёх слов: фамилию, имя и отчество';
      if (parts.some((p) => p.replace(/-/g, '').length < 2)) return 'Каждая часть ФИО — минимум 2 буквы';
      const hasLatin = /[A-Za-z]/.test(v);
      const hasCyrillic = /[А-Яа-яЁё]/.test(v);
      if (hasLatin && hasCyrillic) return 'Не смешивайте русские и латинские буквы';
      return '';
    },

    gender() {
      return [...genderInputs].some((r) => r.checked) ? '' : 'Выберите пол';
    },

    birthdate() {
      const v = fields.birthdate.value;
      if (!v) return 'Укажите дату рождения';
      const birth = new Date(v + 'T00:00:00');
      if (Number.isNaN(birth.getTime())) return 'Некорректная дата';
      if (birth > new Date()) return 'Дата рождения не может быть в будущем';
      const age = getAge(birth);
      if (age < MIN_AGE) return `Регистрация доступна с ${MIN_AGE} лет`;
      if (age > MAX_AGE) return 'Проверьте год рождения';
      return '';
    },

    country() {
      return fields.country.value ? '' : 'Выберите страну';
    },

    interests() {
      const count = [...interestInputs].filter((c) => c.checked).length;
      if (count === 0) return 'Выберите хотя бы один вариант';
      if (count > INTERESTS_MAX) return `Можно выбрать не более ${INTERESTS_MAX} вариантов`;
      return '';
    },

    about() {
      const v = fields.about.value.trim();
      if (!v) return '';
      if (v.length < 10) return `Слишком коротко: ${v.length} из 10 символов`;
      if (v.length > 500) return 'Не более 500 символов';
      if (/[<>]/.test(v)) return 'Символы < и > использовать нельзя';
      return '';
    },

    agree() {
      return fields.agree.checked ? '' : 'Необходимо принять условия использования';
    },
  };

  const targets = {
    login: { inputs: [fields.login], error: 'login-error' },
    password: { inputs: [fields.password], error: 'password-error' },
    passwordConfirm: { inputs: [fields.passwordConfirm], error: 'password-confirm-error' },
    fio: { inputs: [fields.fio], error: 'fio-error' },
    gender: { inputs: [...genderInputs], error: 'gender-error' },
    birthdate: { inputs: [fields.birthdate], error: 'birthdate-error' },
    country: { inputs: [fields.country], error: 'country-error' },
    interests: { inputs: [...interestInputs], error: 'interests-error', group: $('interests') },
    about: { inputs: [fields.about], error: 'about-error' },
    agree: { inputs: [fields.agree], error: 'agree-error' },
  };

  const touched = new Set();

  function showResult(name, message) {
    const t = targets[name];
    const errorEl = $(t.error);
    errorEl.textContent = message;
    t.inputs.forEach((input) => {
      input.classList.toggle('form__input--invalid', Boolean(message));
      input.setAttribute('aria-invalid', message ? 'true' : 'false');
    });
    if (t.group) t.group.classList.toggle('form__group--invalid', Boolean(message));

    const single = t.inputs.length === 1 ? t.inputs[0] : null;
    if (single && single.type !== 'checkbox') {
      single.classList.toggle('form__input--valid', !message && single.value.trim() !== '');
    }
  }

  function validate(name) {
    const message = validators[name]();
    showResult(name, message);
    return message === '';
  }

  const strengthBar = $('strength-bar');
  function updateStrength() {
    const v = fields.password.value;
    let score = 0;
    if (v.length >= 8) score++;
    if (v.length >= 12) score++;
    if (/[a-zа-яё]/.test(v) && /[A-ZА-ЯЁ]/.test(v)) score++;
    if (/\d/.test(v)) score++;
    if (/[^A-Za-zА-Яа-яЁё0-9]/.test(v)) score++;
    const levels = ['', 'weak', 'weak', 'medium', 'good', 'strong'];
    strengthBar.dataset.level = v ? levels[score] || 'weak' : '';
  }

  const counter = $('about-counter');
  function updateCounter() {
    counter.textContent = `${fields.about.value.length} / 500`;
  }

  function bind(name, inputs, events) {
    inputs.forEach((input) => {
      events.forEach((evt) => {
        input.addEventListener(evt, () => {
          if (evt === 'blur' || evt === 'change') touched.add(name);
          if (touched.has(name)) validate(name);
        });
      });
    });
  }

  bind('login', [fields.login], ['blur', 'input']);
  bind('password', [fields.password], ['blur', 'input']);
  bind('passwordConfirm', [fields.passwordConfirm], ['blur', 'input']);
  bind('fio', [fields.fio], ['blur', 'input']);
  bind('gender', [...genderInputs], ['change']);
  bind('birthdate', [fields.birthdate], ['blur', 'change']);
  bind('country', [fields.country], ['blur', 'change']);
  bind('interests', [...interestInputs], ['change']);
  bind('about', [fields.about], ['blur', 'input']);
  bind('agree', [fields.agree], ['change']);

  fields.password.addEventListener('input', () => {
    updateStrength();
    if (touched.has('passwordConfirm')) validate('passwordConfirm');
  });
  fields.login.addEventListener('input', () => {
    if (touched.has('password')) validate('password');
  });
  fields.about.addEventListener('input', updateCounter);

  form.querySelectorAll('[data-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const input = $(btn.dataset.toggle);
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.textContent = show ? 'Скрыть' : 'Показать';
      btn.setAttribute('aria-label', show ? 'Скрыть пароль' : 'Показать пароль');
    });
  });

  form.addEventListener('submit', (event) => {
    let firstInvalid = null;
    Object.keys(validators).forEach((name) => {
      touched.add(name);
      if (!validate(name) && !firstInvalid) firstInvalid = targets[name].inputs[0];
    });

    if (firstInvalid) {
      event.preventDefault();
      firstInvalid.focus();
      firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    fields.login.value = fields.login.value.trim();
    fields.fio.value = fields.fio.value.trim().replace(/\s+/g, ' ');
    fields.about.value = fields.about.value.trim();

  });

  form.addEventListener('reset', () => {

    setTimeout(() => {
      touched.clear();
      Object.keys(targets).forEach((name) => {
        showResult(name, '');
        targets[name].inputs.forEach((i) => i.classList.remove('form__input--valid'));
      });
      form.querySelectorAll('[data-toggle]').forEach((btn) => {
        $(btn.dataset.toggle).type = 'password';
        btn.textContent = 'Показать';
      });
      updateStrength();
      updateCounter();
      fields.login.focus();
    });
  });
})();
