-- =====================================================================
--  PCBUILD — начальные данные: справочники, каталог, готовые сборки, отзывы
--  Выполнять после schema.sql
-- =====================================================================

SET NAMES utf8mb4;

INSERT INTO categories (code, name, is_required, sort_order) VALUES
  ('cpu',    'Процессор',          1, 1),
  ('mb',     'Материнская плата',  1, 2),
  ('gpu',    'Видеокарта',         0, 3),
  ('ram',    'Оперативная память', 1, 4),
  ('cooler', 'Охлаждение',         0, 5),
  ('case',   'Корпус',             1, 6),
  ('psu',    'Блок питания',       1, 7),
  ('ssd',    'Накопитель',         1, 8);

INSERT INTO sockets (code) VALUES ('AM4'), ('AM5'), ('LGA1700');
INSERT INTO memory_types (code) VALUES ('DDR4'), ('DDR5');
INSERT INTO form_factors (code) VALUES ('ATX'), ('mATX');

-- Процессоры: socket, tdp_w
INSERT INTO products (code, category_code, name, spec, price, stock, socket, tdp_w) VALUES
  ('cpu1',  'cpu', 'Ryzen 5 5500',    '6 ядер · AM4 · 65 Вт · без графики',        280,  15, 'AM4',     65),
  ('cpu2',  'cpu', 'Ryzen 5 5600',    '6 ядер · AM4 · 65 Вт · без графики',        340,  20, 'AM4',     65),
  ('cpu3',  'cpu', 'Ryzen 5 5600G',   '6 ядер · AM4 · 65 Вт · встроенная графика', 400,  12, 'AM4',     65),
  ('cpu4',  'cpu', 'Ryzen 5 7600',    '6 ядер · AM5 · 65 Вт · встроенная графика', 650,  14, 'AM5',     65),
  ('cpu5',  'cpu', 'Ryzen 7 7700',    '8 ядер · AM5 · 65 Вт',                      900,  10, 'AM5',     65),
  ('cpu6',  'cpu', 'Ryzen 7 7800X3D', '8 ядер · AM5 · 120 Вт · 3D V-Cache',        1700,  6, 'AM5',    120),
  ('cpu7',  'cpu', 'Ryzen 9 7900',    '12 ядер · AM5 · 65 Вт',                     1300,  8, 'AM5',     65),
  ('cpu8',  'cpu', 'Ryzen 9 7950X',   '16 ядер · AM5 · 170 Вт',                    1900,  5, 'AM5',    170),
  ('cpu9',  'cpu', 'Core i5-12400F',  '6 ядер · LGA1700 · 65 Вт · без графики',    420,  18, 'LGA1700', 65),
  ('cpu10', 'cpu', 'Core i5-14600KF', '14 ядер · LGA1700 · 125 Вт · без графики',  900,   9, 'LGA1700', 125);

-- Материнские платы: socket, memory_type, form_factor
INSERT INTO products (code, category_code, name, spec, price, stock, socket, memory_type, form_factor) VALUES
  ('mb1', 'mb', 'A520M', 'AM4 · mATX · DDR4',              230,  12, 'AM4',     'DDR4', 'mATX'),
  ('mb2', 'mb', 'B550',  'AM4 · ATX · DDR4',               340,  10, 'AM4',     'DDR4', 'ATX'),
  ('mb3', 'mb', 'B650M', 'AM5 · mATX · DDR5',              480,  11, 'AM5',     'DDR5', 'mATX'),
  ('mb4', 'mb', 'B650',  'AM5 · ATX · DDR5',               650,   9, 'AM5',     'DDR5', 'ATX'),
  ('mb5', 'mb', 'X870',  'AM5 · ATX · DDR5 · PCIe 5.0',    1000,  4, 'AM5',     'DDR5', 'ATX'),
  ('mb6', 'mb', 'B760M', 'LGA1700 · mATX · DDR4',          380,   8, 'LGA1700', 'DDR4', 'mATX'),
  ('mb7', 'mb', 'B760',  'LGA1700 · ATX · DDR5',           560,   7, 'LGA1700', 'DDR5', 'ATX');

-- Видеокарты: tdp_w, length_mm
INSERT INTO products (code, category_code, name, spec, price, stock, tdp_w, length_mm) VALUES
  ('gpu1', 'gpu', 'Radeon RX 7600',      '8 ГБ GDDR6 · 165 Вт',  1300, 7, 165, 240),
  ('gpu2', 'gpu', 'GeForce RTX 5050',    '8 ГБ GDDR6 · 130 Вт',  1500, 9, 130, 230),
  ('gpu3', 'gpu', 'GeForce RTX 5060',    '8 ГБ GDDR7 · 145 Вт',  2000, 8, 145, 245),
  ('gpu4', 'gpu', 'GeForce RTX 5060 Ti', '16 ГБ GDDR7 · 180 Вт', 2800, 6, 180, 270),
  ('gpu5', 'gpu', 'Radeon RX 9060 XT',   '16 ГБ GDDR6 · 160 Вт', 2600, 5, 160, 270),
  ('gpu6', 'gpu', 'GeForce RTX 5070',    '12 ГБ GDDR7 · 250 Вт', 3500, 6, 250, 300),
  ('gpu7', 'gpu', 'GeForce RTX 5070 Ti', '16 ГБ GDDR7 · 300 Вт', 5200, 3, 300, 320),
  ('gpu8', 'gpu', 'GeForce RTX 5080',    '16 ГБ GDDR7 · 360 Вт', 7500, 2, 360, 340);

-- Оперативная память: memory_type
INSERT INTO products (code, category_code, name, spec, price, stock, memory_type) VALUES
  ('ram1', 'ram', 'DDR4 16 ГБ', '2 × 8 ГБ · 3200 МГц',  600,  25, 'DDR4'),
  ('ram2', 'ram', 'DDR4 32 ГБ', '2 × 16 ГБ · 3200 МГц', 1100, 15, 'DDR4'),
  ('ram3', 'ram', 'DDR5 16 ГБ', '2 × 8 ГБ · 5600 МГц',  1100, 20, 'DDR5'),
  ('ram4', 'ram', 'DDR5 32 ГБ', '2 × 16 ГБ · 6000 МГц', 2300, 14, 'DDR5'),
  ('ram5', 'ram', 'DDR5 64 ГБ', '2 × 32 ГБ · 6000 МГц', 4500,  6, 'DDR5');

-- Охлаждение: max_tdp_w, height_mm (+ поддерживаемые сокеты в cooler_sockets)
INSERT INTO products (code, category_code, name, spec, price, stock, max_tdp_w, height_mm) VALUES
  ('cool1', 'cooler', 'Кулер 92 мм низкий',         'до 95 Вт · высота 70 мм',     40,  30, 95,  70),
  ('cool2', 'cooler', 'Башенный кулер 120 мм',      'до 150 Вт · высота 150 мм',   70,  25, 150, 150),
  ('cool3', 'cooler', 'Двухбашенный кулер 140 мм',  'до 250 Вт · высота 160 мм',   180, 12, 250, 160),
  ('cool4', 'cooler', 'СЖО 240 мм',                 'до 250 Вт · радиатор 240 мм', 260, 10, 250, 0),
  ('cool5', 'cooler', 'СЖО 360 мм',                 'до 350 Вт · радиатор 360 мм', 380,  6, 350, 0);

-- Корпуса: max_gpu_length_mm, max_cooler_height_mm (+ форм-факторы плат в case_form_factors)
INSERT INTO products (code, category_code, name, spec, price, stock, max_gpu_length_mm, max_cooler_height_mm) VALUES
  ('case1', 'case', 'Корпус mATX мини',    'mATX · видеокарта до 250 мм',  110, 10, 250, 140),
  ('case2', 'case', 'Корпус mATX Compact', 'mATX · видеокарта до 300 мм',  150, 12, 300, 155),
  ('case3', 'case', 'Корпус ATX Mesh',     'ATX · 3 вентилятора',          200, 15, 340, 165),
  ('case4', 'case', 'Корпус ATX RGB',      'ATX · закалённое стекло',      260, 11, 360, 170),
  ('case5', 'case', 'Корпус ATX Premium',  'ATX · стекло · 4 вентилятора', 330,  7, 400, 180),
  ('case6', 'case', 'Корпус Full-Tower',   'ATX · видеокарта до 420 мм',   480,  4, 420, 185);

-- Блоки питания: power_w
INSERT INTO products (code, category_code, name, spec, price, stock, power_w) VALUES
  ('psu1', 'psu', 'БП 500 Вт',  '80+ Bronze',           150, 20, 500),
  ('psu2', 'psu', 'БП 650 Вт',  '80+ Bronze',           210, 18, 650),
  ('psu3', 'psu', 'БП 750 Вт',  '80+ Gold · модульный', 330, 12, 750),
  ('psu4', 'psu', 'БП 850 Вт',  '80+ Gold · модульный', 430,  9, 850),
  ('psu5', 'psu', 'БП 1000 Вт', '80+ Gold · модульный', 600,  5, 1000);

-- Накопители
INSERT INTO products (code, category_code, name, spec, price, stock) VALUES
  ('ssd1', 'ssd', 'SSD 512 ГБ', 'NVMe · PCIe 3.0',              500,  25),
  ('ssd2', 'ssd', 'SSD 1 ТБ',   'NVMe · PCIe 3.0',              850,  20),
  ('ssd3', 'ssd', 'SSD 1 ТБ',   'NVMe · PCIe 4.0 · 7000 МБ/с',  950,  16),
  ('ssd4', 'ssd', 'SSD 2 ТБ',   'NVMe · PCIe 4.0',              1800, 10);

-- Все кулеры поддерживают все сокеты
INSERT INTO cooler_sockets (product_id, socket)
SELECT p.id, s.code
FROM products p
CROSS JOIN sockets s
WHERE p.category_code = 'cooler';

-- Корпуса mATX — только mATX-платы, остальные — ATX и mATX
INSERT INTO case_form_factors (product_id, form_factor)
SELECT p.id, f.code
FROM products p
JOIN form_factors f ON f.code = 'mATX' OR p.code NOT IN ('case1', 'case2')
WHERE p.category_code = 'case';

-- Готовые сборки
INSERT INTO presets (code, tag, title, purpose, sort_order) VALUES
  ('office', 'Офис',            'Для работы и учёбы',     'office',    1),
  ('budget', 'Бюджетная',       'Для учёбы и лёгких игр', 'gaming',    2),
  ('fhd',    'Игровая',         'Full HD на максимуме',   'gaming',    3),
  ('gaming', 'Игровая',         'Для современных игр',    'gaming',    4),
  ('stream', 'Для стриминга',   'Игры + запись + монтаж', 'streaming', 5),
  ('pro',    'Рабочая станция', '3D и видеомонтаж',       'graphics',  6);

INSERT INTO preset_items (preset_id, category_code, product_id)
SELECT pr.id, p.category_code, p.id
FROM presets pr
JOIN products p
WHERE (pr.code, p.code) IN (
  ('office', 'cpu3'), ('office', 'mb1'), ('office', 'ram1'), ('office', 'cool1'),
  ('office', 'case1'), ('office', 'psu1'), ('office', 'ssd1'),

  ('budget', 'cpu2'), ('budget', 'mb2'), ('budget', 'gpu2'), ('budget', 'ram1'),
  ('budget', 'cool2'), ('budget', 'case3'), ('budget', 'psu2'), ('budget', 'ssd2'),

  ('fhd', 'cpu4'), ('fhd', 'mb3'), ('fhd', 'gpu3'), ('fhd', 'ram3'),
  ('fhd', 'cool2'), ('fhd', 'case2'), ('fhd', 'psu2'), ('fhd', 'ssd3'),

  ('gaming', 'cpu5'), ('gaming', 'mb4'), ('gaming', 'gpu6'), ('gaming', 'ram4'),
  ('gaming', 'cool3'), ('gaming', 'case4'), ('gaming', 'psu3'), ('gaming', 'ssd3'),

  ('stream', 'cpu7'), ('stream', 'mb4'), ('stream', 'gpu4'), ('stream', 'ram4'),
  ('stream', 'cool4'), ('stream', 'case4'), ('stream', 'psu3'), ('stream', 'ssd4'),

  ('pro', 'cpu8'), ('pro', 'mb5'), ('pro', 'gpu8'), ('pro', 'ram5'),
  ('pro', 'cool5'), ('pro', 'case6'), ('pro', 'psu5'), ('pro', 'ssd4')
);

-- Отзывы (уже прошли модерацию)
INSERT INTO reviews (author_name, rating, text, status, moderated_by, moderated_at, created_at) VALUES
  ('Андрей К.',  5, 'Собрал игровую сборку за полчаса, все детали подошли друг к другу сразу — не пришлось ничего гуглить.',  'approved', 'seed', NOW(), NOW() - INTERVAL 30 DAY),
  ('Марина С.',  5, 'Удобно, что сразу видно итоговую цену. Заказ оформил через сайт, менеджер написал в тот же день.',     'approved', 'seed', NOW(), NOW() - INTERVAL 21 DAY),
  ('Дмитрий В.', 4, 'Не разбираюсь в железе, взял готовую сборку из рекомендаций — всё работает отлично.',                 'approved', 'seed', NOW(), NOW() - INTERVAL 12 DAY),
  ('Илья П.',    5, 'Конструктор сам подсказал, что блока питания на 550 Вт мне не хватит. Сэкономил кучу нервов.',        'approved', 'seed', NOW(), NOW() - INTERVAL 4 DAY);
