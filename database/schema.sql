-- =====================================================================
--  PCBUILD — схема базы данных (MySQL 8 / MariaDB 10.4+)
--
--  ВНИМАНИЕ: скрипт удаляет и создаёт таблицы заново (все данные пропадут).
--  Порядок установки: schema.sql → seed.sql
--  или одной командой: php backend/bin/install.php
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP VIEW  IF EXISTS v_product_ratings;
DROP VIEW  IF EXISTS v_orders_report;
DROP TABLE IF EXISTS rate_limits;
DROP TABLE IF EXISTS app_state;
DROP TABLE IF EXISTS tg_pending_actions;
DROP TABLE IF EXISTS tg_support_messages;
DROP TABLE IF EXISTS tg_messages;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS order_status_history;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS build_items;
DROP TABLE IF EXISTS builds;
DROP TABLE IF EXISTS preset_items;
DROP TABLE IF EXISTS presets;
DROP TABLE IF EXISTS case_form_factors;
DROP TABLE IF EXISTS cooler_sockets;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS form_factors;
DROP TABLE IF EXISTS memory_types;
DROP TABLE IF EXISTS sockets;
DROP TABLE IF EXISTS categories;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
--  Справочники
-- ---------------------------------------------------------------------

-- Категории комплектующих (слоты конструктора)
CREATE TABLE categories (
  code        VARCHAR(16)  NOT NULL,
  name        VARCHAR(64)  NOT NULL,
  is_required TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'обязательна для оформления заказа',
  sort_order  SMALLINT     NOT NULL DEFAULT 0,
  PRIMARY KEY (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Сокеты процессоров
CREATE TABLE sockets (
  code VARCHAR(16) NOT NULL,
  PRIMARY KEY (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Типы оперативной памяти
CREATE TABLE memory_types (
  code VARCHAR(8) NOT NULL,
  PRIMARY KEY (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Форм-факторы материнских плат
CREATE TABLE form_factors (
  code VARCHAR(16) NOT NULL,
  PRIMARY KEY (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Каталог
-- ---------------------------------------------------------------------

-- Товары. Характеристики для проверки совместимости хранятся в типизированных
-- колонках; если характеристика не относится к категории — NULL.
CREATE TABLE products (
  id                   INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  code                 VARCHAR(32)       NOT NULL COMMENT 'артикул, используется во фронтенде и API',
  category_code        VARCHAR(16)       NOT NULL,
  name                 VARCHAR(120)      NOT NULL,
  spec                 VARCHAR(255)      NOT NULL DEFAULT '' COMMENT 'краткое описание для карточки',
  price                DECIMAL(10,2)     NOT NULL,
  stock                INT UNSIGNED      NOT NULL DEFAULT 0 COMMENT 'остаток на складе',
  image                VARCHAR(255)      NULL,
  is_active            TINYINT(1)        NOT NULL DEFAULT 1 COMMENT '0 — снят с продажи (удалять товары с заказами нельзя)',
  socket               VARCHAR(16)       NULL COMMENT 'cpu, mb',
  memory_type          VARCHAR(8)        NULL COMMENT 'mb, ram',
  form_factor          VARCHAR(16)       NULL COMMENT 'mb',
  tdp_w                SMALLINT UNSIGNED NULL COMMENT 'cpu, gpu: тепловыделение / потребление, Вт',
  length_mm            SMALLINT UNSIGNED NULL COMMENT 'gpu: длина, мм',
  height_mm            SMALLINT UNSIGNED NULL COMMENT 'cooler: высота, мм (0 — СЖО)',
  max_tdp_w            SMALLINT UNSIGNED NULL COMMENT 'cooler: рассеиваемая мощность, Вт',
  max_gpu_length_mm    SMALLINT UNSIGNED NULL COMMENT 'case: макс. длина видеокарты, мм',
  max_cooler_height_mm SMALLINT UNSIGNED NULL COMMENT 'case: макс. высота кулера, мм',
  power_w              SMALLINT UNSIGNED NULL COMMENT 'psu: мощность, Вт',
  created_at           DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_code (code),
  UNIQUE KEY uq_products_id_category (id, category_code) COMMENT 'для составных внешних ключей',
  KEY ix_products_category_price (category_code, price),
  CONSTRAINT fk_products_category    FOREIGN KEY (category_code) REFERENCES categories (code),
  CONSTRAINT fk_products_socket      FOREIGN KEY (socket)        REFERENCES sockets (code),
  CONSTRAINT fk_products_memory_type FOREIGN KEY (memory_type)   REFERENCES memory_types (code),
  CONSTRAINT fk_products_form_factor FOREIGN KEY (form_factor)   REFERENCES form_factors (code),
  CONSTRAINT ck_products_price CHECK (price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Какие сокеты поддерживает кулер (многозначный атрибут → отдельная таблица)
CREATE TABLE cooler_sockets (
  product_id INT UNSIGNED NOT NULL,
  socket     VARCHAR(16)  NOT NULL,
  PRIMARY KEY (product_id, socket),
  CONSTRAINT fk_cooler_sockets_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT fk_cooler_sockets_socket  FOREIGN KEY (socket)     REFERENCES sockets (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Какие форм-факторы плат помещаются в корпус
CREATE TABLE case_form_factors (
  product_id  INT UNSIGNED NOT NULL,
  form_factor VARCHAR(16)  NOT NULL,
  PRIMARY KEY (product_id, form_factor),
  CONSTRAINT fk_case_ff_product     FOREIGN KEY (product_id)  REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT fk_case_ff_form_factor FOREIGN KEY (form_factor) REFERENCES form_factors (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Готовые сборки (рекомендации) и сохранённые сборки пользователей
-- ---------------------------------------------------------------------

CREATE TABLE presets (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code       VARCHAR(32)  NOT NULL,
  tag        VARCHAR(64)  NOT NULL,
  title      VARCHAR(120) NOT NULL,
  purpose    ENUM('office','gaming','streaming','graphics') NOT NULL COMMENT 'назначение',
  sort_order SMALLINT     NOT NULL DEFAULT 0,
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_presets_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Состав готовой сборки: в каждой категории — не больше одного товара.
-- Составной FK гарантирует, что товар действительно из этой категории.
CREATE TABLE preset_items (
  preset_id     INT UNSIGNED NOT NULL,
  category_code VARCHAR(16)  NOT NULL,
  product_id    INT UNSIGNED NOT NULL,
  PRIMARY KEY (preset_id, category_code),
  CONSTRAINT fk_preset_items_preset  FOREIGN KEY (preset_id) REFERENCES presets (id) ON DELETE CASCADE,
  CONSTRAINT fk_preset_items_product FOREIGN KEY (product_id, category_code) REFERENCES products (id, category_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Сборки, которыми поделились по ссылке (#/builder?build=CODE)
CREATE TABLE builds (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code       CHAR(8)      NOT NULL,
  ip_address VARCHAR(45)  NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_builds_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE build_items (
  build_id      INT UNSIGNED NOT NULL,
  category_code VARCHAR(16)  NOT NULL,
  product_id    INT UNSIGNED NOT NULL,
  PRIMARY KEY (build_id, category_code),
  CONSTRAINT fk_build_items_build   FOREIGN KEY (build_id) REFERENCES builds (id) ON DELETE CASCADE,
  CONSTRAINT fk_build_items_product FOREIGN KEY (product_id, category_code) REFERENCES products (id, category_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Заказы
-- ---------------------------------------------------------------------

-- Номер заказа — случайное 6-значное число (задаётся в OrderModel::create), а не счётчик:
-- по номеру своего заказа нельзя узнать, сколько заказов было у магазина.
CREATE TABLE orders (
  id             INT UNSIGNED  NOT NULL COMMENT 'случайный номер 100000–999999',
  public_token   CHAR(24)      NOT NULL COMMENT 'для ссылки t.me/<бот>?start=<token> и GET /api/orders/<token>',
  customer_name  VARCHAR(60)   NOT NULL,
  phone          VARCHAR(20)   NOT NULL,
  total          DECIMAL(10,2) NOT NULL,
  status         ENUM('new','building','built','delivery','done','rejected') NOT NULL DEFAULT 'new',
  reject_reason  VARCHAR(500)  NULL,
  client_chat_id BIGINT        NULL COMMENT 'Telegram-чат клиента (после перехода по ссылке)',
  builder_tg_id  BIGINT        NULL COMMENT 'сборщик, который ведёт заказ',
  builder_name   VARCHAR(120)  NULL,
  ip_address     VARCHAR(45)   NULL,
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_orders_token (public_token),
  KEY ix_orders_status (status),
  KEY ix_orders_client_chat (client_chat_id),
  KEY ix_orders_created (created_at),
  CONSTRAINT ck_orders_total CHECK (total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Состав заказа. Название и цена копируются на момент заказа,
-- чтобы последующее изменение каталога не меняло старые заказы.
CREATE TABLE order_items (
  order_id      INT UNSIGNED  NOT NULL,
  category_code VARCHAR(16)   NOT NULL,
  product_id    INT UNSIGNED  NOT NULL,
  product_name  VARCHAR(120)  NOT NULL,
  price         DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (order_id, category_code),
  CONSTRAINT fk_order_items_order   FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_order_items_product FOREIGN KEY (product_id, category_code) REFERENCES products (id, category_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- История смены статусов заказа
CREATE TABLE order_status_history (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id   INT UNSIGNED NOT NULL,
  status     ENUM('new','building','built','delivery','done','rejected') NOT NULL,
  comment    VARCHAR(500) NULL,
  changed_by VARCHAR(120) NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_history_order (order_id),
  CONSTRAINT fk_history_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Отзывы (с премодерацией)
-- ---------------------------------------------------------------------

CREATE TABLE reviews (
  id           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  product_id   INT UNSIGNED     NULL COMMENT 'NULL — отзыв о сервисе в целом',
  author_name  VARCHAR(60)      NOT NULL,
  rating       TINYINT UNSIGNED NOT NULL,
  text         VARCHAR(1000)    NOT NULL,
  status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  ip_address   VARCHAR(45)      NULL,
  moderated_by VARCHAR(120)     NULL,
  moderated_at DATETIME         NULL,
  created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_reviews_status_created (status, created_at),
  KEY ix_reviews_product (product_id),
  CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL,
  CONSTRAINT ck_reviews_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Telegram-бот
-- ---------------------------------------------------------------------

-- Карточки заказов/отзывов, отправленные сборщикам (чтобы обновлять их у всех)
CREATE TABLE tg_messages (
  chat_id     BIGINT       NOT NULL,
  message_id  INT          NOT NULL,
  entity_type ENUM('order','review') NOT NULL,
  entity_id   INT UNSIGNED NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (chat_id, message_id),
  KEY ix_tg_messages_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Сообщения клиентов в поддержку: какое сообщение у сборщика → какому клиенту отвечать
CREATE TABLE tg_support_messages (
  builder_chat_id BIGINT   NOT NULL,
  message_id      INT      NOT NULL,
  client_chat_id  BIGINT   NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (builder_chat_id, message_id),
  KEY ix_tg_support_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ожидаемое от сборщика действие (например, причина отказа по заказу)
CREATE TABLE tg_pending_actions (
  tg_user_id BIGINT       NOT NULL,
  action     VARCHAR(32)  NOT NULL,
  entity_id  INT UNSIGNED NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (tg_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Служебное
-- ---------------------------------------------------------------------

-- Ключ-значение: смещение опроса Telegram, кэш имени бота и т. п.
CREATE TABLE app_state (
  name       VARCHAR(64)  NOT NULL,
  value      VARCHAR(255) NOT NULL,
  updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ограничение частоты запросов (заказы, отзывы, сохранение сборок)
CREATE TABLE rate_limits (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  action     VARCHAR(32)     NOT NULL,
  ip_address VARCHAR(45)     NOT NULL,
  created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_rate_limits_lookup (action, ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Представления
-- ---------------------------------------------------------------------

-- Средний рейтинг товаров по одобренным отзывам
CREATE VIEW v_product_ratings AS
SELECT product_id,
       ROUND(AVG(rating), 1) AS avg_rating,
       COUNT(*)              AS reviews_count
FROM reviews
WHERE status = 'approved' AND product_id IS NOT NULL
GROUP BY product_id;

-- Сводка по заказам для отчётов
CREATE VIEW v_orders_report AS
SELECT o.id,
       o.created_at,
       o.customer_name,
       o.phone,
       o.status,
       o.builder_name,
       COUNT(i.category_code) AS items_count,
       o.total
FROM orders o
LEFT JOIN order_items i ON i.order_id = o.id
GROUP BY o.id, o.created_at, o.customer_name, o.phone, o.status, o.builder_name, o.total;
