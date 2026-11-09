// Категории, каталог и готовые сборки хранятся в базе данных и загружаются при старте сайта (loadCatalog).
// Картинка товара — поле image в таблице products (приходит как img: 'img/название.jpg').
var CATEGORIES = {};   // код → название, в порядке слотов конструктора
var REQUIRED = [];     // обязательные для заказа категории
var PRODUCTS = [];
var PRESETS = [];

function loadCatalog() {
  return API.get('catalog').then(function (d) {
    CATEGORIES = {};
    REQUIRED = [];
    d.categories.forEach(function (c) {
      CATEGORIES[c.code] = c.name;
      if (c.required) REQUIRED.push(c.code);
    });
    PRODUCTS = d.products;
    PRESETS = d.presets;

    // убираем из сохранённой сборки товары, которых больше нет в каталоге
    var build = getBuild(), changed = false;
    Object.keys(build).forEach(function (cat) {
      var p = getProduct(build[cat]);
      if (!p || p.cat !== cat) { delete build[cat]; changed = true; }
    });
    if (changed) saveBuild(build);
  });
}

function getProduct(id) { return PRODUCTS.find(function (p) { return p.id === id; }); }
function getBuild() { try { return JSON.parse(localStorage.getItem('pcbuild')) || {}; } catch (e) { return {}; } }
function saveBuild(b) { localStorage.setItem('pcbuild', JSON.stringify(b)); }
function fmt(n) { return n.toLocaleString('ru-RU') + ' BYN'; }
function imgOf(p) { return p.img || ''; }
function esc(s) {
  return String(s).replace(/[&<>"']/g, function (c) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
  });
}

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
// Те же правила повторно проверяются на сервере: backend/src/Services/CompatibilityService.php
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
  if (p.stock === 0) return 'Нет в наличии';
  var hyp = Object.assign({}, build);
  hyp[p.cat] = p.id;
  var errs = checkCompat(hyp).filter(function (x) { return x.cats.indexOf(p.cat) !== -1; });
  return errs.length ? errs[0].text : '';
}
