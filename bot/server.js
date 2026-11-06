'use strict';
/* PCBUILD: сервер заказов + Telegram-бот (клиент / сборщик). Без внешних библиотек, Node.js 18+ */
const http = require('http'), fs = require('fs'), path = require('path'), crypto = require('crypto');

/* ---------- настройки (.env) ---------- */
(function loadEnv() {
  const f = path.join(__dirname, '.env');
  if (!fs.existsSync(f)) return;
  fs.readFileSync(f, 'utf8').split(/\r?\n/).forEach(function (l) {
    const m = l.match(/^\s*([A-Z_]+)\s*=\s*(.*?)\s*$/);
    if (m && !l.trim().startsWith('#') && !(m[1] in process.env)) process.env[m[1]] = m[2].replace(/^["']|["']$/g, '');
  });
})();

const TOKEN = process.env.BOT_TOKEN;
const BUILDERS = (process.env.BUILDER_IDS || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean).map(Number);
const PORT = +process.env.PORT || 3000;
const TG = (process.env.TELEGRAM_API || 'https://api.telegram.org') + '/bot' + TOKEN + '/';
const DB_FILE = process.env.DB_FILE || path.join(__dirname, 'db.json');
const SITE_DIR = path.resolve(__dirname, '..');
const DATA_FILE = process.env.DATA_FILE || path.join(SITE_DIR, 'js', 'data.js');

if (!TOKEN || !BUILDERS.length || BUILDERS.some(isNaN)) {
  console.error('Заполните BOT_TOKEN и BUILDER_IDS (только цифры) в файле .env');
  process.exit(1);
}

// Каталог и правила совместимости берём из того же data.js, что и сайт — один источник правды
const D = new Function(fs.readFileSync(DATA_FILE, 'utf8') +
  ';return { CATEGORIES, getProduct, buildTotal, checkCompat, fmt };')();

/* ---------- база (JSON-файл) ---------- */
let db = { nextId: 1, orders: [], msgMap: {} };
if (fs.existsSync(DB_FILE)) db = JSON.parse(fs.readFileSync(DB_FILE, 'utf8'));
function save() { const t = DB_FILE + '.tmp'; fs.writeFileSync(t, JSON.stringify(db)); fs.renameSync(t, DB_FILE); }
function setMap(key, chat) {
  db.msgMap[key] = chat;
  const k = Object.keys(db.msgMap);
  if (k.length > 3000) k.slice(0, 1000).forEach(function (x) { delete db.msgMap[x]; });
  save();
}
const byId = function (id) { return db.orders.find(function (o) { return o.id === id; }); };

/* ---------- Telegram API ---------- */
async function tg(method, params) {
  const r = await fetch(TG + method, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(params || {}) });
  const j = await r.json();
  if (!j.ok) throw new Error(method + ': ' + j.description);
  return j.result;
}
const send = function (chat, text, extra) {
  return tg('sendMessage', Object.assign({ chat_id: chat, text: text, parse_mode: 'HTML', disable_web_page_preview: true }, extra));
};
const safe = function (p) { return p.catch(function (e) { if (!/not modified/.test(e.message)) console.error(e.message); }); };
const esc = function (s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); };
const nameOf = function (u) { return [u.first_name, u.username && '@' + u.username].filter(Boolean).join(' '); };

/* ---------- статусы ---------- */
const STATUS = { new: '🆕 Новый', building: '🛠 Сборка', built: '🔧 Собран', delivery: '🚚 Доставка', done: '📦 Выполнен', rejected: '❌ Отклонён' };
const NEXT = { building: ['built', '🔧 Собран'], built: ['delivery', '🚚 Передать в доставку'], delivery: ['done', '📦 Доставлен'] };
const CLIENT_TEXT = {
  building: '✅ Заказ №%id% принят и передан в сборку.',
  built: '🔧 Заказ №%id% собран.',
  delivery: '🚚 Заказ №%id% передан в доставку.',
  done: '📦 Заказ №%id% выполнен. Спасибо, что выбрали нас!'
};
const MENU = { reply_markup: { keyboard: [[{ text: '📦 Мои заказы' }, { text: '💬 Поддержка' }]], resize_keyboard: true } };
const awaitReason = {}; // сборщик -> id заказа, по которому ждём причину отказа

function orderText(o) {
  return '<b>Заказ №' + o.id + '</b> · ' + STATUS[o.status] + '\n👤 ' + esc(o.name) + ' · ' + esc(o.phone) + '\n\n' +
    o.lines.map(function (l) { return esc(l); }).join('\n') + '\n\n<b>Итого: ' + D.fmt(o.total) + '</b>' +
    (o.reason ? '\n\n❗ Причина отказа: ' + esc(o.reason) : '');
}

function builderView(o, chat) {
  let text = orderText(o), kb = [];
  const mine = o.builderId === chat;
  if (o.builderId && !mine) text += '\n\n👷 Ведёт: ' + esc(o.builderName);
  if (o.status === 'new') kb = [[{ text: '✅ Принять в сборку', callback_data: 'ok:' + o.id }, { text: '❌ Отклонить', callback_data: 'no:' + o.id }]];
  else if (mine && NEXT[o.status]) kb = [[{ text: NEXT[o.status][1], callback_data: 'next:' + o.id }]];
  return { text: text, reply_markup: { inline_keyboard: kb } };
}

async function refresh(o) {
  for (const m of o.msgs) {
    const v = builderView(o, m.chat);
    await safe(tg('editMessageText', { chat_id: m.chat, message_id: m.id, text: v.text, parse_mode: 'HTML', reply_markup: v.reply_markup }));
  }
}

async function showOrder(chat, o) {
  const v = builderView(o, chat);
  const r = await send(chat, v.text, { reply_markup: v.reply_markup });
  o.msgs.push({ chat: chat, id: r.message_id });
  save();
}

async function announce(o) {
  for (const b of BUILDERS) {
    try { await showOrder(b, o); }
    catch (e) { console.error('Не удалось отправить заказ сборщику ' + b + ' (он нажимал /start у бота?): ' + e.message); }
  }
}

const notifyClient = function (o, text) { return o.chatId ? safe(send(o.chatId, text)) : null; };

/* ---------- сообщения ---------- */
async function onMessage(m) {
  if (m.chat.type !== 'private') return;
  const chat = m.chat.id, text = (m.text || '').trim(), isB = BUILDERS.indexOf(m.from.id) !== -1;
  if (/^\/start/.test(text)) return cmdStart(m, text.split(/\s+/)[1], isB);
  return isB ? builderMessage(m, text) : clientMessage(m, text);
}

async function cmdStart(m, token, isB) {
  const chat = m.chat.id;
  if (isB) return send(chat, '👷 Вы вошли как <b>сборщик</b>.\nНовые заказы приходят сюда с кнопками.\n\n/orders — активные заказы\n/order 12 — открыть заказ №12\nЧтобы ответить клиенту, ответьте <b>реплаем</b> на его сообщение.');
  if (token) {
    const o = db.orders.find(function (x) { return x.token === token; });
    if (!o) return send(chat, 'Заказ по этой ссылке не найден.', MENU);
    if (o.chatId && o.chatId !== chat) return send(chat, 'Этот заказ уже привязан к другому аккаунту.', MENU);
    o.chatId = chat; save();
    return send(chat, '✅ Заказ №' + o.id + ' привязан. Статус: ' + STATUS[o.status] + '\nМы сообщим здесь о каждом изменении. Есть вопросы — просто напишите сообщение.', MENU);
  }
  return send(chat, 'Здравствуйте! Это бот PCBUILD.\n📦 «Мои заказы» — статусы ваших заказов\n💬 Любое сообщение уйдёт в поддержку.', MENU);
}

async function clientMessage(m, text) {
  const chat = m.chat.id;
  if (text === '/orders' || text === '📦 Мои заказы') {
    const mine = db.orders.filter(function (o) { return o.chatId === chat; });
    return send(chat, mine.length
      ? mine.map(function (o) { return '№' + o.id + ' — ' + STATUS[o.status] + ' — ' + D.fmt(o.total); }).join('\n')
      : 'Привязанных заказов пока нет. Оформите заказ на сайте и перейдите по ссылке после оформления.');
  }
  if (text === '💬 Поддержка') return send(chat, 'Напишите сообщение — оно уйдёт сборщикам, ответ придёт сюда.');
  if (!text) return send(chat, 'Пока поддерживаются только текстовые сообщения.');

  const active = db.orders.filter(function (o) { return o.chatId === chat && o.status !== 'done' && o.status !== 'rejected'; });
  const label = active.length ? 'заказ ' + active.map(function (o) { return '№' + o.id; }).join(', ') : 'без активного заказа';
  let sent = 0;
  for (const b of BUILDERS) {
    try {
      const r = await send(b, '💬 <b>' + esc(nameOf(m.from)) + '</b> (' + label + ')\n\n' + esc(text));
      setMap(b + ':' + r.message_id, chat); sent++;
    } catch (e) { console.error(e.message); }
  }
  return send(chat, sent ? '✉️ Сообщение передано сборщикам.' : 'Поддержка сейчас недоступна, попробуйте позже.');
}

async function builderMessage(m, text) {
  const chat = m.chat.id, uid = m.from.id;
  if (text === '/cancel') { delete awaitReason[uid]; return send(chat, 'Отменено.'); }

  if (text === '/orders') {
    const act = db.orders.filter(function (o) { return o.status !== 'done' && o.status !== 'rejected'; });
    if (!act.length) return send(chat, 'Активных заказов нет.');
    for (const o of act.slice(-10)) await showOrder(chat, o);
    return;
  }
  const mm = text.match(/^\/order\s+(\d+)/);
  if (mm) { const o = byId(+mm[1]); return o ? showOrder(chat, o) : send(chat, 'Заказ не найден.'); }

  if (awaitReason[uid] && text && text[0] !== '/') {
    const o = byId(awaitReason[uid]); delete awaitReason[uid];
    if (!o || o.status !== 'new') return send(chat, 'Этот заказ уже обработан.');
    o.status = 'rejected'; o.reason = text; o.builderId = uid; o.builderName = nameOf(m.from); save();
    notifyClient(o, '❌ Заказ №' + o.id + ' отклонён.\nПричина: ' + esc(text) + '\nЕсть вопросы — напишите сюда.');
    await refresh(o);
    return send(chat, 'Заказ №' + o.id + ' отклонён, клиент уведомлён.');
  }

  if (m.reply_to_message && text) {
    const target = db.msgMap[chat + ':' + m.reply_to_message.message_id];
    if (target) { await send(target, '👷 <b>Сборщик:</b>\n' + esc(text)); return send(chat, '✉️ Отправлено клиенту.'); }
  }
  return send(chat, 'Чтобы ответить клиенту, ответьте реплаем (Reply) на его сообщение.\nКоманды: /orders, /order 12');
}

async function onCallback(q) {
  const ans = function (t, alert) { return safe(tg('answerCallbackQuery', { callback_query_id: q.id, text: t, show_alert: !!alert })); };
  if (BUILDERS.indexOf(q.from.id) === -1) return ans('Нет доступа', true);
  const parts = (q.data || '').split(':'), act = parts[0], o = byId(+parts[1]);
  if (!o) return ans('Заказ не найден', true);

  if (act === 'ok') {
    if (o.status !== 'new') return ans('Заказ уже обработан', true);
    o.status = 'building'; o.builderId = q.from.id; o.builderName = nameOf(q.from); save();
    notifyClient(o, CLIENT_TEXT.building.replace('%id%', o.id));
    await refresh(o);
    return ans('Заказ принят в сборку');
  }
  if (act === 'no') {
    if (o.status !== 'new') return ans('Заказ уже обработан', true);
    awaitReason[q.from.id] = o.id;
    await ans();
    return send(q.from.id, 'Напишите причину отказа по заказу №' + o.id + ' одним сообщением (или /cancel).');
  }
  if (act === 'next') {
    if (o.builderId !== q.from.id) return ans('Заказ ведёт другой сборщик', true);
    const n = NEXT[o.status];
    if (!n) return ans('Дальше менять нечего', true);
    o.status = n[0]; save();
    notifyClient(o, CLIENT_TEXT[o.status].replace('%id%', o.id));
    await refresh(o);
    return ans('Статус обновлён');
  }
}

async function poll() {
  let offset = 0;
  for (;;) {
    try {
      const ups = await tg('getUpdates', { offset: offset, timeout: 25, allowed_updates: ['message', 'callback_query'] });
      for (const u of ups) {
        offset = u.update_id + 1;
        try { if (u.message) await onMessage(u.message); else if (u.callback_query) await onCallback(u.callback_query); }
        catch (e) { console.error('Ошибка обработки:', e.message); }
      }
    } catch (e) { console.error('Ошибка опроса Telegram:', e.message); await new Promise(function (r) { setTimeout(r, 3000); }); }
  }
}

/* ---------- HTTP: приём заказов + раздача сайта ---------- */
let botName = '';
const PHONE = /^\+375-(15|16|17|21|22|23|25|29|33|44)-\d{3}-\d{2}-\d{2}$/;
const REQUIRED = ['cpu', 'mb', 'ram', 'case', 'psu', 'ssd'];
const hits = {};
// Не больше 5 принятых заказов в час с одного адреса (ошибки ввода в лимит не идут)
function limited(ip) {
  const now = Date.now();
  hits[ip] = (hits[ip] || []).filter(function (t) { return now - t < 3600000; });
  return hits[ip].length >= 5;
}
function countHit(ip) { (hits[ip] = hits[ip] || []).push(Date.now()); }

// Возвращает { error } или { name, phone, items } — всё проверяется заново на сервере
function validate(b) {
  if (!b || typeof b !== 'object') return { error: 'Неверный запрос' };
  const name = String(b.name || '').trim();
  if (name.length < 2 || name.length > 60) return { error: 'Укажите имя (2–60 символов)' };
  if (!PHONE.test(String(b.phone || ''))) return { error: 'Неверный формат телефона' };
  if (!b.items || typeof b.items !== 'object') return { error: 'Сборка пуста' };
  const items = {};
  for (const c of Object.keys(b.items)) {
    const p = D.getProduct(b.items[c]);
    if (!D.CATEGORIES[c] || !p || p.cat !== c) return { error: 'В сборке неизвестный товар' };
    items[c] = p.id;
  }
  const miss = REQUIRED.filter(function (c) { return !items[c]; });
  if (miss.length) return { error: 'В сборке не хватает: ' + miss.map(function (c) { return D.CATEGORIES[c].toLowerCase(); }).join(', ') };
  const errs = D.checkCompat(items);
  if (errs.length) return { error: 'Сборка несовместима: ' + errs[0].text };
  return { name: name, phone: b.phone, items: items };
}

async function createOrder(body) {
  const v = validate(body);
  if (v.error) return { code: 400, body: { error: v.error } };
  const lines = Object.keys(D.CATEGORIES).filter(function (c) { return v.items[c]; }).map(function (c) {
    const p = D.getProduct(v.items[c]); return D.CATEGORIES[c] + ': ' + p.name + ' — ' + D.fmt(p.price);
  });
  const o = {
    id: db.nextId++, token: crypto.randomBytes(12).toString('hex'), name: v.name, phone: v.phone,
    items: v.items, lines: lines, total: D.buildTotal(v.items), status: 'new', chatId: null,
    builderId: null, builderName: '', createdAt: new Date().toISOString(), msgs: []
  };
  db.orders.push(o); save();
  await announce(o);
  return { code: 201, body: { id: o.id, trackUrl: 'https://t.me/' + botName + '?start=' + o.token } };
}

const MIME = { '.html': 'text/html; charset=utf-8', '.css': 'text/css; charset=utf-8', '.js': 'text/javascript; charset=utf-8',
  '.svg': 'image/svg+xml', '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.webp': 'image/webp', '.ico': 'image/x-icon' };

function serveStatic(p, res) {
  const notFound = function () { res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' }); res.end('Not found'); };
  let rel;
  try { rel = decodeURIComponent(p); } catch (e) { return notFound(); }
  if (rel.slice(-1) === '/') rel += 'index.html';
  const file = path.normalize(path.join(SITE_DIR, rel));
  // нельзя выходить за папку сайта, читать папку бота (.env, db.json) и скрытые файлы
  if (file.indexOf(SITE_DIR + path.sep) !== 0 || file.indexOf(__dirname + path.sep) === 0 || path.basename(file)[0] === '.') return notFound();
  fs.readFile(file, function (e, buf) {
    if (e) return notFound();
    res.writeHead(200, { 'Content-Type': MIME[path.extname(file).toLowerCase()] || 'application/octet-stream' });
    res.end(buf);
  });
}

const server = http.createServer(function (req, res) {
  const cors = { 'Access-Control-Allow-Origin': '*', 'Access-Control-Allow-Headers': 'Content-Type', 'Access-Control-Allow-Methods': 'POST, OPTIONS' };
  const json = function (code, obj) { res.writeHead(code, Object.assign({ 'Content-Type': 'application/json; charset=utf-8' }, cors)); res.end(JSON.stringify(obj)); };
  if (req.method === 'OPTIONS') { res.writeHead(204, cors); return res.end(); }
  const url = new URL(req.url, 'http://localhost');

  if (req.method === 'POST' && url.pathname === '/api/orders') {
    const ip = req.socket.remoteAddress;
    if (limited(ip)) return json(429, { error: 'Слишком много заказов, попробуйте позже' });
    let raw = '';
    req.on('data', function (c) { raw += c; if (raw.length > 20000) req.destroy(); });
    req.on('end', async function () {
      try {
        let body; try { body = JSON.parse(raw); } catch (e) { return json(400, { error: 'Неверный запрос' }); }
        const r = await createOrder(body);
        if (r.code === 201) countHit(ip);
        json(r.code, r.body);
      } catch (e) { console.error(e); json(500, { error: 'Ошибка сервера' }); }
    });
    return;
  }
  if (req.method === 'GET') return serveStatic(url.pathname, res);
  res.writeHead(404); res.end();
});

(async function main() {
  try { botName = (await tg('getMe')).username; }
  catch (e) { console.error('Не удалось подключиться к Telegram (проверьте BOT_TOKEN и интернет): ' + e.message); process.exit(1); }
  server.listen(PORT, function () {
    console.log('Бот @' + botName + ' запущен. Сборщиков: ' + BUILDERS.length);
    console.log('Сайт: http://localhost:' + PORT);
  });
  poll();
})();
