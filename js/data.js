const CATEGORIES = {
  cpu: 'Процессор', mb: 'Материнская плата', gpu: 'Видеокарта', ram: 'Оперативная память',
  cooler: 'Охлаждение', case: 'Корпус', psu: 'Блок питания', ssd: 'Накопитель'
};

// Картинок по умолчанию нет. Чтобы показать фото, добавьте товару поле img: 'img/название.jpg'
const PRODUCTS = [
  // Процессоры: socket, tdp
  { id: 'cpu1', cat: 'cpu', name: 'Ryzen 5 5500', spec: '6 ядер · AM4 · 65 Вт · без графики', price: 280, socket: 'AM4', tdp: 65 },
  { id: 'cpu2', cat: 'cpu', name: 'Ryzen 5 5600', spec: '6 ядер · AM4 · 65 Вт · без графики', price: 340, socket: 'AM4', tdp: 65 },
  { id: 'cpu3', cat: 'cpu', name: 'Ryzen 5 5600G', spec: '6 ядер · AM4 · 65 Вт · встроенная графика', price: 400, socket: 'AM4', tdp: 65 },
  { id: 'cpu4', cat: 'cpu', name: 'Ryzen 5 7600', spec: '6 ядер · AM5 · 65 Вт · встроенная графика', price: 650, socket: 'AM5', tdp: 65 },
  { id: 'cpu5', cat: 'cpu', name: 'Ryzen 7 7700', spec: '8 ядер · AM5 · 65 Вт', price: 900, socket: 'AM5', tdp: 65 },
  { id: 'cpu6', cat: 'cpu', name: 'Ryzen 7 7800X3D', spec: '8 ядер · AM5 · 120 Вт · 3D V-Cache', price: 1700, socket: 'AM5', tdp: 120 },
  { id: 'cpu7', cat: 'cpu', name: 'Ryzen 9 7900', spec: '12 ядер · AM5 · 65 Вт', price: 1300, socket: 'AM5', tdp: 65 },
  { id: 'cpu8', cat: 'cpu', name: 'Ryzen 9 7950X', spec: '16 ядер · AM5 · 170 Вт', price: 1900, socket: 'AM5', tdp: 170 },
  { id: 'cpu9', cat: 'cpu', name: 'Core i5-12400F', spec: '6 ядер · LGA1700 · 65 Вт · без графики', price: 420, socket: 'LGA1700', tdp: 65 },
  { id: 'cpu10', cat: 'cpu', name: 'Core i5-14600KF', spec: '14 ядер · LGA1700 · 125 Вт · без графики', price: 900, socket: 'LGA1700', tdp: 125 },
  // Материнские платы: socket, mem, ff
  { id: 'mb1', cat: 'mb', name: 'A520M', spec: 'AM4 · mATX · DDR4', price: 230, socket: 'AM4', mem: 'DDR4', ff: 'mATX' },
  { id: 'mb2', cat: 'mb', name: 'B550', spec: 'AM4 · ATX · DDR4', price: 340, socket: 'AM4', mem: 'DDR4', ff: 'ATX' },
  { id: 'mb3', cat: 'mb', name: 'B650M', spec: 'AM5 · mATX · DDR5', price: 480, socket: 'AM5', mem: 'DDR5', ff: 'mATX' },
  { id: 'mb4', cat: 'mb', name: 'B650', spec: 'AM5 · ATX · DDR5', price: 650, socket: 'AM5', mem: 'DDR5', ff: 'ATX' },
  { id: 'mb5', cat: 'mb', name: 'X870', spec: 'AM5 · ATX · DDR5 · PCIe 5.0', price: 1000, socket: 'AM5', mem: 'DDR5', ff: 'ATX' },
  { id: 'mb6', cat: 'mb', name: 'B760M', spec: 'LGA1700 · mATX · DDR4', price: 380, socket: 'LGA1700', mem: 'DDR4', ff: 'mATX' },
  { id: 'mb7', cat: 'mb', name: 'B760', spec: 'LGA1700 · ATX · DDR5', price: 560, socket: 'LGA1700', mem: 'DDR5', ff: 'ATX' },
  // Видеокарты: tdp, len (мм)
  { id: 'gpu1', cat: 'gpu', name: 'Radeon RX 7600', spec: '8 ГБ GDDR6 · 165 Вт', price: 1300, tdp: 165, len: 240 },
  { id: 'gpu2', cat: 'gpu', name: 'GeForce RTX 5050', spec: '8 ГБ GDDR6 · 130 Вт', price: 1500, tdp: 130, len: 230 },
  { id: 'gpu3', cat: 'gpu', name: 'GeForce RTX 5060', spec: '8 ГБ GDDR7 · 145 Вт', price: 2000, tdp: 145, len: 245 },
  { id: 'gpu4', cat: 'gpu', name: 'GeForce RTX 5060 Ti', spec: '16 ГБ GDDR7 · 180 Вт', price: 2800, tdp: 180, len: 270 },
  { id: 'gpu5', cat: 'gpu', name: 'Radeon RX 9060 XT', spec: '16 ГБ GDDR6 · 160 Вт', price: 2600, tdp: 160, len: 270 },
  { id: 'gpu6', cat: 'gpu', name: 'GeForce RTX 5070', spec: '12 ГБ GDDR7 · 250 Вт', price: 3500, tdp: 250, len: 300 },
  { id: 'gpu7', cat: 'gpu', name: 'GeForce RTX 5070 Ti', spec: '16 ГБ GDDR7 · 300 Вт', price: 5200, tdp: 300, len: 320 },
  { id: 'gpu8', cat: 'gpu', name: 'GeForce RTX 5080', spec: '16 ГБ GDDR7 · 360 Вт', price: 7500, tdp: 360, len: 340 },
  // Память: mem
  { id: 'ram1', cat: 'ram', name: 'DDR4 16 ГБ', spec: '2 × 8 ГБ · 3200 МГц', price: 600, mem: 'DDR4' },
  { id: 'ram2', cat: 'ram', name: 'DDR4 32 ГБ', spec: '2 × 16 ГБ · 3200 МГц', price: 1100, mem: 'DDR4' },
  { id: 'ram3', cat: 'ram', name: 'DDR5 16 ГБ', spec: '2 × 8 ГБ · 5600 МГц', price: 1100, mem: 'DDR5' },
  { id: 'ram4', cat: 'ram', name: 'DDR5 32 ГБ', spec: '2 × 16 ГБ · 6000 МГц', price: 2300, mem: 'DDR5' },
  { id: 'ram5', cat: 'ram', name: 'DDR5 64 ГБ', spec: '2 × 32 ГБ · 6000 МГц', price: 4500, mem: 'DDR5' },
  // Охлаждение: sockets, tdpMax, h (мм высоты)
  { id: 'cool1', cat: 'cooler', name: 'Кулер 92 мм низкий', spec: 'до 95 Вт · высота 70 мм', price: 40, sockets: ['AM4', 'AM5', 'LGA1700'], tdpMax: 95, h: 70 },
  { id: 'cool2', cat: 'cooler', name: 'Башенный кулер 120 мм', spec: 'до 150 Вт · высота 150 мм', price: 70, sockets: ['AM4', 'AM5', 'LGA1700'], tdpMax: 150, h: 150 },
  { id: 'cool3', cat: 'cooler', name: 'Двухбашенный кулер 140 мм', spec: 'до 250 Вт · высота 160 мм', price: 180, sockets: ['AM4', 'AM5', 'LGA1700'], tdpMax: 250, h: 160 },
  { id: 'cool4', cat: 'cooler', name: 'СЖО 240 мм', spec: 'до 250 Вт · радиатор 240 мм', price: 260, sockets: ['AM4', 'AM5', 'LGA1700'], tdpMax: 250, h: 0 },
  { id: 'cool5', cat: 'cooler', name: 'СЖО 360 мм', spec: 'до 350 Вт · радиатор 360 мм', price: 380, sockets: ['AM4', 'AM5', 'LGA1700'], tdpMax: 350, h: 0 },
  // Корпуса: ff (поддерживаемые платы), gpuMax, coolerMax (мм)
  { id: 'case1', cat: 'case', name: 'Корпус mATX мини', spec: 'mATX · видеокарта до 250 мм', price: 110, ff: ['mATX'], gpuMax: 250, coolerMax: 140 },
  { id: 'case2', cat: 'case', name: 'Корпус mATX Compact', spec: 'mATX · видеокарта до 300 мм', price: 150, ff: ['mATX'], gpuMax: 300, coolerMax: 155 },
  { id: 'case3', cat: 'case', name: 'Корпус ATX Mesh', spec: 'ATX · 3 вентилятора', price: 200, ff: ['ATX', 'mATX'], gpuMax: 340, coolerMax: 165 },
  { id: 'case4', cat: 'case', name: 'Корпус ATX RGB', spec: 'ATX · закалённое стекло', price: 260, ff: ['ATX', 'mATX'], gpuMax: 360, coolerMax: 170 },
  { id: 'case5', cat: 'case', name: 'Корпус ATX Premium', spec: 'ATX · стекло · 4 вентилятора', price: 330, ff: ['ATX', 'mATX'], gpuMax: 400, coolerMax: 180 },
  { id: 'case6', cat: 'case', name: 'Корпус Full-Tower', spec: 'ATX · видеокарта до 420 мм', price: 480, ff: ['ATX', 'mATX'], gpuMax: 420, coolerMax: 185 },
  // Блоки питания: watt
  { id: 'psu1', cat: 'psu', name: 'БП 500 Вт', spec: '80+ Bronze', price: 150, watt: 500 },
  { id: 'psu2', cat: 'psu', name: 'БП 650 Вт', spec: '80+ Bronze', price: 210, watt: 650 },
  { id: 'psu3', cat: 'psu', name: 'БП 750 Вт', spec: '80+ Gold · модульный', price: 330, watt: 750 },
  { id: 'psu4', cat: 'psu', name: 'БП 850 Вт', spec: '80+ Gold · модульный', price: 430, watt: 850 },
  { id: 'psu5', cat: 'psu', name: 'БП 1000 Вт', spec: '80+ Gold · модульный', price: 600, watt: 1000 },
  // Накопители
  { id: 'ssd1', cat: 'ssd', name: 'SSD 512 ГБ', spec: 'NVMe · PCIe 3.0', price: 500 },
  { id: 'ssd2', cat: 'ssd', name: 'SSD 1 ТБ', spec: 'NVMe · PCIe 3.0', price: 850 },
  { id: 'ssd3', cat: 'ssd', name: 'SSD 1 ТБ', spec: 'NVMe · PCIe 4.0 · 7000 МБ/с', price: 950 },
  { id: 'ssd4', cat: 'ssd', name: 'SSD 2 ТБ', spec: 'NVMe · PCIe 4.0', price: 1800 }
];

const PRESETS = [
  { id: 'office', tag: 'Офис', title: 'Для работы и учёбы',
    items: { cpu: 'cpu3', mb: 'mb1', ram: 'ram1', cooler: 'cool1', case: 'case1', psu: 'psu1', ssd: 'ssd1' } },
  { id: 'budget', tag: 'Бюджетная', title: 'Для учёбы и лёгких игр',
    items: { cpu: 'cpu2', mb: 'mb2', gpu: 'gpu2', ram: 'ram1', cooler: 'cool2', case: 'case3', psu: 'psu2', ssd: 'ssd2' } },
  { id: 'fhd', tag: 'Игровая', title: 'Full HD на максимуме',
    items: { cpu: 'cpu4', mb: 'mb3', gpu: 'gpu3', ram: 'ram3', cooler: 'cool2', case: 'case2', psu: 'psu2', ssd: 'ssd3' } },
  { id: 'gaming', tag: 'Игровая', title: 'Для современных игр',
    items: { cpu: 'cpu5', mb: 'mb4', gpu: 'gpu6', ram: 'ram4', cooler: 'cool3', case: 'case4', psu: 'psu3', ssd: 'ssd3' } },
  { id: 'stream', tag: 'Для стриминга', title: 'Игры + запись + монтаж',
    items: { cpu: 'cpu7', mb: 'mb4', gpu: 'gpu4', ram: 'ram4', cooler: 'cool4', case: 'case4', psu: 'psu3', ssd: 'ssd4' } },
  { id: 'pro', tag: 'Рабочая станция', title: '3D и видеомонтаж',
    items: { cpu: 'cpu8', mb: 'mb5', gpu: 'gpu8', ram: 'ram5', cooler: 'cool5', case: 'case6', psu: 'psu5', ssd: 'ssd4' } }
];

function getProduct(id) { return PRODUCTS.find(function (p) { return p.id === id; }); }
function getBuild() { try { return JSON.parse(localStorage.getItem('pcbuild')) || {}; } catch (e) { return {}; } }
function saveBuild(b) { localStorage.setItem('pcbuild', JSON.stringify(b)); }
function fmt(n) { return n.toLocaleString('ru-RU') + ' BYN'; }
function imgOf(p) { return p.img || ''; }

function buildTotal(items) {
  return Object.keys(items).reduce(function (s, cat) {
    var p = getProduct(items[cat]);
    return s + (p ? p.price : 0);
  }, 0);
}

function presetSpecs(p) {
  var i = p.items;
  return [getProduct(i.cpu).name, i.gpu ? getProduct(i.gpu).name : 'встроенная графика',
          getProduct(i.ram).name, getProduct(i.ssd).name].join(' · ');
}

// Все правила совместимости. Возвращает список конфликтов: { cats: [затронутые категории], text }
function checkCompat(b) {
  var cpu = getProduct(b.cpu), mb = getProduct(b.mb), ram = getProduct(b.ram), gpu = getProduct(b.gpu),
      cool = getProduct(b.cooler), cs = getProduct(b.case), psu = getProduct(b.psu), e = [];
  function add(cats, text) { e.push({ cats: cats, text: text }); }

  if (cpu && mb && cpu.socket !== mb.socket)
    add(['cpu', 'mb'], 'Сокет процессора (' + cpu.socket + ') не подходит к плате (' + mb.socket + ')');
  if (mb && ram && mb.mem !== ram.mem)
    add(['mb', 'ram'], 'Плата работает с ' + mb.mem + ', а память — ' + ram.mem);
  if (mb && cs && cs.ff.indexOf(mb.ff) === -1)
    add(['mb', 'case'], 'Плата ' + mb.ff + ' не помещается в этот корпус');
  if (gpu && cs && gpu.len > cs.gpuMax)
    add(['gpu', 'case'], 'Видеокарта (' + gpu.len + ' мм) длиннее допустимого для корпуса (' + cs.gpuMax + ' мм)');

  var sock = cpu ? cpu.socket : (mb ? mb.socket : null);
  if (cool && sock && cool.sockets.indexOf(sock) === -1)
    add(['cooler', cpu ? 'cpu' : 'mb'], 'Кулер не поддерживает сокет ' + sock);
  if (cool && cpu && cool.tdpMax < cpu.tdp)
    add(['cooler', 'cpu'], 'Кулер рассчитан на ' + cool.tdpMax + ' Вт, процессор выделяет ' + cpu.tdp + ' Вт');
  if (cool && cs && cool.h > cs.coolerMax)
    add(['cooler', 'case'], 'Кулер (' + cool.h + ' мм) выше допустимого для корпуса (' + cs.coolerMax + ' мм)');

  if (psu && (cpu || gpu)) {
    var need = Math.round(((cpu ? cpu.tdp : 0) + (gpu ? gpu.tdp : 0) + 100) * 1.2);
    if (psu.watt < need) add(['psu', 'cpu', 'gpu'], 'Мощности блока питания не хватит: нужно от ' + need + ' Вт');
  }
  return e;
}

// Почему деталь нельзя добавить в текущую сборку (пустая строка — можно)
function incompatReason(p, build) {
  var hyp = Object.assign({}, build);
  hyp[p.cat] = p.id;
  var errs = checkCompat(hyp).filter(function (x) { return x.cats.indexOf(p.cat) !== -1; });
  return errs.length ? errs[0].text : '';
}
