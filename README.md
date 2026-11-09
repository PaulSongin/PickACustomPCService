# 🖥️ Сборка ПК онлайн

Веб-сервис для сборки компьютера по комплектующим. Можно выбрать процессор, материнскую плату, видеокарту, память, накопители, блок питания, корпус и охлаждение. Сервис **автоматически проверяет совместимость**, **считает стоимость** сборки и отправляет оформленный заказ администраторам в **Telegram**.

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6+-F7DF1E?logo=javascript&logoColor=black)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?logo=css3&logoColor=white)
![Telegram](https://img.shields.io/badge/Telegram_Bot_API-26A5E4?logo=telegram&logoColor=white)

---

## 👥 Команда

**Группа:** `СДП-ПИ-241`

| Участник | Роль |
|---|---|
| Сонгин Павел | Backend (PHP, API), База данных |
| Комар Игнат  | Frontend (HTML, CSS, JS), тестирование |

---

## ✨ Функциональные требования

### Каталог комплектующих
- Просмотр товаров по категориям: CPU, GPU, материнские платы, RAM, накопители, блоки питания, корпуса, охлаждение
- Фильтрация по характеристикам и цене, сортировка, поиск по названию
- Карточка товара: фото, цена, наличие, характеристики, отзывы

### Конструктор ПК
- Пошаговый выбор комплектующих по слотам сборки
- Проверка совместимости при каждом выборе:
  - сокет процессора ↔ сокет материнской платы
  - тип памяти (DDR4/DDR5) ↔ материнская плата
  - форм-фактор платы ↔ корпус
  - длина видеокарты и высота кулера ↔ габариты корпуса
  - энергопотребление сборки ↔ мощность блока питания
  - TDP процессора ↔ кулер
- В списке выбора показываются только совместимые комплектующие
- Итоговая цена и энергопотребление пересчитываются в реальном времени
- Прогресс «выбрано N из M обязательных комплектующих»
- Сборка сохраняется в браузере и доступна по ссылке, которой можно поделиться

### Готовые сборки
- Подбор сборок по бюджету и назначению: офис, игры, графика
- Загрузка готовой сборки в конструктор для доработки

### Заказ
- Форма заказа с контактами клиента
- Повторная проверка совместимости и цены на сервере
- Уведомление администраторов в Telegram: состав, сумма, контакты
- *(опционально)* смена статуса заказа inline-кнопками в Telegram

### Отзывы
- Отзывы с оценкой 1–5, средний рейтинг товара
- Модерация и защита от спама

### Нефункциональные требования
- Адаптивная вёрстка, семантическая разметка HTML5
- Защита от SQL-инъекций, XSS, CSRF, ограничение частоты запросов
- Lighthouse: Performance, Accessibility, SEO не ниже 80

---

## 🛠️ Технологический стек

| Слой | Технологии |
|---|---|
| **Frontend** | HTML5, CSS3 (Flexbox, Grid, media queries, БЭМ), Vanilla JavaScript ES6+ (fetch API, localStorage) |
| **Backend** | PHP 8 без фреймворков: самописный роутер, контроллеры, сервисы, модели, валидаторы |
| **База данных** | MySQL 8, PDO, подготовленные выражения |
| **Интеграции** | Telegram Bot API через cURL |
| **Сервер** | Apache (mod_rewrite) или Nginx + PHP-FPM |
| **Инструменты** | Git, GitHub, VS Code / PhpStorm, Chrome DevTools, Lighthouse, Postman |

### Архитектура

```
Браузер (HTML/CSS/JS)
      │  fetch() → JSON API
      ▼
PHP: Router → Controller → Validator / Service → Model
      │  PDO
      ▼
MySQL: товары, сборки, заказы, отзывы
      │
      ▼
Telegram Bot API: уведомление о новом заказе
```

---

## 📁 Структура репозитория

```
PickACustomPCService/
├── index.html, css/, js/          ← фронтенд (SPA на хеш-роутинге)
│   └── js/api.js                  ← обращения к API + CSRF-токен
├── api/index.php                  ← единая точка входа API (/api/*)
├── backend/
│   ├── bootstrap.php              ← автозагрузка классов, настройки
│   ├── routes.php                 ← карта API
│   ├── config/config.example.php  ← пример настроек (скопировать в config.php)
│   ├── src/
│   │   ├── Core/                  ← Router, Request, Response, Database (PDO), Csrf, App, Logger
│   │   ├── Controllers/           ← Catalog, Product, Preset, Build, Order, Review, Session, Telegram
│   │   ├── Services/              ← CompatibilityService, OrderService, ReviewService, BuildService, Telegram/
│   │   ├── Validators/            ← OrderValidator, ReviewValidator, BuildItemsValidator
│   │   └── Models/                ← Product, Category, Preset, Build, Order, Review, RateLimit, TelegramState
│   ├── bin/
│   │   ├── install.php            ← установка БД
│   │   ├── telegram-poll.php      ← Telegram-бот в режиме опроса
│   │   └── reviews.php            ← модерация отзывов из консоли
│   └── storage/logs/              ← app.log (создаётся автоматически)
├── database/
│   ├── schema.sql                 ← таблицы, связи, представления
│   ├── seed.sql                   ← справочники, 50 товаров, 6 готовых сборок, отзывы
│   └── migrations/                ← изменения для уже установленных баз
├── router.php                     ← маршрутизатор для встроенного сервера PHP
└── .htaccess                      ← маршрутизация для Apache + закрытие служебных папок
```

---

## 🚀 Запуск

Нужны **PHP 8.1+** (расширения `pdo_mysql`, `curl`, `mbstring`) и **MySQL 8** / MariaDB 10.4+. Всё это есть в Open Server Panel.

1. **Настройки.** Скопируйте `backend/config/config.example.php` в `backend/config/config.php` и укажите доступ к БД.
   В Open Server Panel 6 хост MySQL обычно — имя модуля (`MySQL-8.0`), пользователь `root`, пароль пустой.
2. **База данных** — одной командой (создаст БД `pcbuild`, таблицы и данные):
   ```bash
   php backend/bin/install.php
   ```
   Переустановить с нуля: `php backend/bin/install.php --fresh`.
   Или вручную в phpMyAdmin: создать БД `pcbuild` (utf8mb4_unicode_ci) и импортировать `database/schema.sql`, затем `database/seed.sql`.
3. **Сервер.**
   - Быстро, без Apache: `php -S localhost:8000 router.php` → http://localhost:8000
   - Apache (Open Server, XAMPP): корень домена — папка проекта, нужен `mod_rewrite` (правила уже в `.htaccess`).
   - Nginx: `location /api/ { try_files $uri /api/index.php?$query_string; }` и запретить `/backend/`, `/database/`.

> Сайт нужно открывать через сервер, а не двойным кликом по `index.html`: каталог загружается из БД.

### Telegram-бот (необязательно)

Без бота всё работает: заказы сохраняются в БД (таблица `orders`, представление `v_orders_report`).
С ботом сборщики получают карточки заказов с кнопками статусов, а клиент — уведомления.

1. Создайте бота у **@BotFather**, узнайте Telegram ID сборщиков у **@userinfobot**. Каждый сборщик должен нажать Start у вашего бота.
2. В `config.php` заполните `telegram.token` и `telegram.builder_ids` (например `[111111111, 222222222]`).
3. Запустите обработку кнопок и сообщений одним из способов:
   - **локально** — опрос, держите окно открытым: `php backend/bin/telegram-poll.php`
   - **на хостинге с HTTPS** — webhook: задайте `telegram.webhook_secret` и выполните
     `https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://ваш-сайт/api/telegram/webhook&secret_token=<секрет>`

Что умеет бот: сборщик принимает/отклоняет заказ (с причиной), ведёт его по статусам
🆕 Новый → 🛠 Сборка → 🔧 Собран → 🚚 Доставка → 📦 Выполнен; заказ достаётся тому, кто нажал первым.
Клиент привязывает заказ по ссылке после оформления, видит «Мои заказы» и пишет в поддержку — сборщик отвечает реплаем.
Отзывы на модерацию тоже приходят сборщикам с кнопками «Опубликовать / Отклонить»
(без бота: `php backend/bin/reviews.php`, затем `approve ID` или `reject ID`).

---

## 🔌 API

Все ответы — JSON. Ошибки: `{"error": "текст"}` с кодом 4xx/5xx. POST-запросы требуют заголовок `X-CSRF-Token` (токен — `GET /api/csrf`) и `Content-Type: application/json`.

| Метод | Адрес | Назначение |
|---|---|---|
| GET | `/api/catalog` | категории + товары + готовые сборки (загружается фронтендом при старте) |
| GET | `/api/products?cat=cpu,gpu&q=&min=&max=&sort=` | товары с фильтрами; `sort`: `price_asc`, `price_desc`, `name`, `rating` |
| GET | `/api/products/{id}` | один товар |
| GET | `/api/categories` | категории и обязательные слоты |
| GET | `/api/presets?purpose=gaming&min=&max=` | готовые сборки по назначению (`office`, `gaming`, `streaming`, `graphics`) и бюджету |
| POST | `/api/compatibility` | `{items}` → конфликты, недостающие детали, цена, нужная мощность БП |
| POST | `/api/builds` | `{items}` → код сборки для ссылки `#/builder?build=КОД` |
| GET | `/api/builds/{code}` | сборка по коду |
| POST | `/api/orders` | `{name, phone, items, total}` → номер заказа и ссылка на бота |
| GET | `/api/orders/{token}` | статус заказа и история |
| GET | `/api/reviews?limit=&product=` | одобренные отзывы, средняя оценка |
| POST | `/api/reviews` | `{name, rating, text, product?}` → отзыв на модерацию |
| POST | `/api/telegram/webhook` | обновления от Telegram (режим webhook) |

**Защита:** только подготовленные выражения PDO; CSRF-токен в сессии; повторная проверка состава, наличия, совместимости и цены заказа на сервере (если цена изменилась — 409);
лимиты по IP (5 заказов, 3 отзыва, 30 сохранённых сборок в час); honeypot и премодерация отзывов, ссылки в отзыве — всегда на модерацию;
экранирование пользовательского текста на фронтенде и в сообщениях бота; служебные папки закрыты от браузера.

---

## 🗄️ База данных

| Таблица | Что хранит |
|---|---|
| `categories` | категории (слоты конструктора), признак обязательности |
| `sockets`, `memory_types`, `form_factors` | справочники характеристик |
| `products` | товары: цена, остаток, характеристики совместимости (сокет, тип памяти, TDP, габариты, мощность) |
| `cooler_sockets`, `case_form_factors` | многозначные характеристики: сокеты кулера, платы для корпуса |
| `presets`, `preset_items` | готовые сборки и их состав |
| `builds`, `build_items` | сборки, которыми поделились по ссылке |
| `orders`, `order_items`, `order_status_history` | заказы, состав (цена фиксируется на момент заказа), история статусов |
| `reviews` | отзывы со статусом модерации; `product_id` — для отзывов о товаре |
| `tg_messages`, `tg_support_messages`, `tg_pending_actions` | состояние Telegram-бота |
| `rate_limits`, `app_state` | ограничение частоты запросов, служебные значения |

Представления: `v_product_ratings` (средний рейтинг товаров), `v_orders_report` (сводка по заказам).
Составные внешние ключи `(product_id, category_code)` гарантируют, что в слот сборки/заказа попадает товар нужной категории.
Товары с заказами удалить нельзя — их снимают с продажи (`is_active = 0`).
Номер заказа — случайное 6-значное число, а не счётчик: по номеру своего заказа нельзя узнать, сколько заказов у магазина.

Если база уже установлена, а схема изменилась, выполните новые файлы из `database/migrations/` по порядку
(например, в phpMyAdmin или `mysql pcbuild < database/migrations/001_random_order_numbers.sql`).

---

## 🗺️ План разработки

- [x] Анализ конкурентов, требования, стек
- [x] MVP: каталог, конструктор, движок совместимости, заказы в Telegram, отзывы
- [x] Рекомендации готовых сборок по бюджету
- [ ] Веб-админка для каталога и заказов
