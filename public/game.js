const crops = {
  radish: {name: 'Reďkovka', icon: '🌱', cost: 2, reward: 5, seconds: 12},
  carrot: {name: 'Mrkva', icon: '🥕', cost: 5, reward: 11, seconds: 25},
  corn: {name: 'Kukurica', icon: '🌽', cost: 10, reward: 23, seconds: 45},
};
const $ = id => document.getElementById(id);
let farm = null;
let selected = 'radish';
let busy = false;
let polling = null;
let generation = 0;
let serverClock = 0;
let clockReceived = 0;
function getPlayerId() {
  const key = 'cropfolk-player';
  const fresh = Array.from(crypto.getRandomValues(new Uint8Array(16)),
    byte => byte.toString(16).padStart(2, '0')).join('');
  try {
    const saved = sessionStorage.getItem(key);
    if (/^[0-9a-f]{32}$/.test(saved ?? '')) return saved;
    sessionStorage.setItem(key, fresh);
  } catch {
    // The player can still use an inventory for this page when storage is unavailable.
  }
  return fresh;
}

const playerId = getPlayerId();
const avatarLayer = $('avatars');
const field = $('field');
const world = $('world');
const panel = $('farm-panel');
const seedToggle = $('seed-toggle');
const panelToggle = $('panel-toggle');
let panelOpener = null;
const sellButton = $('sell-stock');
const wheelbarrowButton = $('buy-wheelbarrow');
const shopButtons = [];
const workButton = $('work-plot');
let nearestPlot = -1;
let inventoryView = null;
const peers = new Map();
const keys = new Set();
const touchKeys = new Map();
const directions = {
  KeyW: 'KeyW', ArrowUp: 'KeyW', KeyA: 'KeyA', ArrowLeft: 'KeyA',
  KeyS: 'KeyS', ArrowDown: 'KeyS', KeyD: 'KeyD', ArrowRight: 'KeyD',
};
let position = {x: 0.5, y: 0.9};
let sentPosition = null;
let sentAt = 0;
let lastFrame = performance.now();

function makeAvatar(label, mine = false) {
  const element = document.createElement('div');
  element.className = mine ? 'avatar mine' : 'avatar';
  element.textContent = '🧑‍🌾';
  const name = document.createElement('span');
  name.textContent = label;
  element.append(name);
  avatarLayer.append(element);
  return element;
}
const ownAvatar = makeAvatar('Ty', true);

function syncPeers(players) {
  const active = new Set();
  for (const player of players) {
    if (player.self) continue;
    active.add(player.id);
    const peer = peers.get(player.id);
    if (peer) {
      peer.target = player;
    } else {
      const element = makeAvatar('Hráč ' + player.id.slice(0, 4).toUpperCase());
      element.style.background = 'hsl(' + (parseInt(player.id.slice(0, 4), 16) % 360) + ' 55% 80%)';
      peers.set(player.id, {element, x: player.x, y: player.y, target: player});
    }
  }
  for (const [id, peer] of peers) {
    if (active.has(id)) continue;
    peer.element.remove();
    peers.delete(id);
  }
}

function drawAvatar(element, point, width, height) {
  element.style.transform = 'translate(' + point.x * width + 'px,' + point.y * height + 'px)';
}

function walkable(x, y, map) {
  const {width, height, radius, obstacles} = map;
  if (x < radius || x > width - radius || y < radius || y > height - radius) return false;
  return !obstacles.some(wall =>
    x > wall.x - radius && x < wall.x + wall.w + radius &&
    y > wall.y - radius && y < wall.y + wall.h + radius);
}

function cameraOffset(position, viewport, worldSize) {
  return viewport >= worldSize ? (viewport - worldSize) / 2
    : -Math.max(0, Math.min(worldSize - viewport, position - viewport / 2));
}

function renderMap(map) {
  world.style.width = map.width + 'px';
  world.style.height = map.height + 'px';
  $('terrain').replaceChildren();
  for (const item of [...map.landmarks, ...map.obstacles]) {
    const tile = document.createElement('div');
    tile.className = 'terrain ' + item.kind + (item.h > item.w ? ' vertical' : '');
    Object.assign(tile.style, {
      left: item.x + 'px', top: item.y + 'px', width: item.w + 'px', height: item.h + 'px',
    });
    if (item.text) {
      const label = document.createElement('span');
      label.textContent = item.text;
      tile.append(label);
    }
    $('terrain').append(tile);
  }
  for (const [id, place, label] of [
    ['barn', map.barnDoor, 'Vchod do stodoly'],
    ['shop', map.shopDoor, 'Vchod do obchodu'],
  ]) {
    const door = document.createElement('div');
    door.className = 'barn-door ' + id + '-entrance';
    door.style.left = place.x + 'px';
    door.style.top = place.y + 'px';
    door.textContent = label;
    $('terrain').append(door);
  }
  map.plots.forEach(({x, y}, index) => {
    const plot = $('plots').children[index];
    plot.style.left = x + 'px';
    plot.style.top = y + 'px';
  });
}

function animate(frame) {
  const elapsed = Math.min(0.05, (frame - lastFrame) / 1000);
  lastFrame = frame;
  if (farm) {
    const pressed = new Set([...Array.from(keys, key => directions[key]), ...touchKeys.values()]);
    const dx = Number(pressed.has('KeyD')) - Number(pressed.has('KeyA'));
    const dy = Number(pressed.has('KeyS')) - Number(pressed.has('KeyW'));
    const {width, height} = farm.layout;
    if (width && height) {
      const distance = 235 * elapsed / (Math.hypot(dx, dy) || 1);
      let x = position.x * width;
      const y = position.y * height;
      if (dx && walkable(x + dx * distance, y, farm.layout)) {
        x += dx * distance;
        position.x = x / width;
      }
      if (dy && walkable(x, y + dy * distance, farm.layout)) {
        position.y = (y + dy * distance) / height;
      }
      const left = cameraOffset(x, field.clientWidth, width);
      const top = cameraOffset(position.y * height, field.clientHeight, height);
      world.style.transform = 'translate(' + left + 'px,' + top + 'px)';
      drawAvatar(ownAvatar, position, width, height);
      updateTarget();
      const blend = 1 - Math.exp(-elapsed * 16);
      for (const peer of peers.values()) {
        peer.x += (peer.target.x - peer.x) * blend;
        peer.y += (peer.target.y - peer.y) * blend;
        drawAvatar(peer.element, peer, width, height);
      }
    }
  }
  requestAnimationFrame(animate);
}
requestAnimationFrame(animate);

function closePanel(restoreFocus = true) {
  panel.hidden = true;
  panelToggle.setAttribute('aria-expanded', 'false');
  seedToggle.setAttribute('aria-expanded', 'false');
  if (restoreFocus) panelOpener?.focus();
  panelOpener = null;
}

function openPanel(opener, focusTarget) {
  if (!panel.hidden && panelOpener === opener) {
    closePanel();
    return;
  }
  panelOpener = opener;
  panel.hidden = false;
  panelToggle.setAttribute('aria-expanded', 'true');
  seedToggle.setAttribute('aria-expanded', 'true');
  focusTarget.focus();
}

seedToggle.onclick = () => openPanel(seedToggle, $('seed-heading'));
panelToggle.onclick = () => openPanel(panelToggle, $('panel-close'));
$('panel-close').onclick = () => closePanel();

window.addEventListener('keydown', event => {
  if (event.code === 'Escape' && !panel.hidden) {
    closePanel();
    return;
  }
  if (!farm || event.ctrlKey || event.metaKey || event.altKey ||
      event.target.closest('input,textarea,[contenteditable]')) return;
  if (event.code === 'KeyE') {
    event.preventDefault();
    if (!event.repeat) interact();
  } else if (directions[event.code]) {
    event.preventDefault();
    keys.add(event.code);
  }
});
window.addEventListener('keyup', event => keys.delete(event.code));
window.addEventListener('blur', () => {
  keys.clear();
  touchKeys.clear();
});
for (const button of document.querySelectorAll('[data-key]')) {
  button.addEventListener('pointerdown', event => {
    button.setPointerCapture(event.pointerId);
    touchKeys.set(event.pointerId, button.dataset.key);
  });
  for (const type of ['pointerup', 'pointercancel', 'lostpointercapture']) {
    button.addEventListener(type, event => touchKeys.delete(event.pointerId));
  }
}

async function api(code = '', payload) {
  const response = await fetch('/api/farms' + (code ? '/' + code : ''), {
    headers: {Accept: 'application/json', 'X-Cropfolk-Player': playerId, ...(payload && {'Content-Type': 'application/json'})},
    ...(payload && {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
    signal: AbortSignal.timeout(6000),
  });
  const data = await response.json();
  if (!response.ok) throw new Error(data.error);
  return data;
}

function message(value = '') {
  const inGame = !$('game').hidden;
  $('message').textContent = inGame ? '' : value;
  $('game-message').textContent = inGame ? value : '';
}

function record(data) {
  farm = data;
  serverClock = data.time;
  clockReceived = performance.now();
  syncPeers(data.players);
  $('connection').textContent = 'Online';
  render();
}

function open(data) {
  generation++;
  nearestPlot = -1;
  $('plots').querySelector('.in-reach')?.classList.remove('in-reach');
  const {width, height, spawn} = data.layout;
  position = {x: (spawn.x + Math.random() * 60 - 30) / width, y: spawn.y / height};
  renderMap(data.layout);
  sentPosition = null;
  $('lobby').hidden = true;
  $('game').hidden = false;
  document.body.classList.add('playing');
  closePanel(false);
  $('code').textContent = data.code;
  $('invite-link').hidden = true;
  history.replaceState(null, '', '?farma=' + data.code);
  record(data);
  $('game-title').focus();
}

function remaining(plant) {
  return Math.max(0, Math.ceil((plant.ready - serverClock - (performance.now() - clockReceived)) / 1000));
}

function mapDistance(target, point = position) {
  const {width, height} = farm.layout;
  return Math.hypot(point.x * width - target.x, point.y * height - target.y);
}

function total(contents) {
  return Object.values(contents).reduce((sum, count) => sum + count, 0);
}

function atBarn() {
  const {barnDoor} = farm.layout;
  return mapDistance(barnDoor) <= barnDoor.reach;
}

function atShop() {
  const {shopDoor} = farm.layout;
  return mapDistance(shopDoor) <= shopDoor.reach;
}

function interact() {
  if (atShop()) {
    if (panel.hidden || panelOpener !== workButton) {
      openPanel(workButton, $('shop-heading'));
    } else {
      $('shop-heading').focus();
    }
  } else if (atBarn()) {
    if (total(farm.bag)) workBarn('deposit');
    else if (total(farm.barn)) workBarn('sell');
    else message('Stodola je prázdna.');
  } else {
    workPlot(nearestPlot);
  }
}

function updateTarget() {
  let target = -1;
  let closest = farm.layout.reach;
  for (let index = 0; index < farm.plots.length; index++) {
    const distance = mapDistance(farm.layout.plots[index]);
    if (distance <= closest) {
      target = index;
      closest = distance;
    }
  }
  if (target !== nearestPlot) {
    $('plots').children[nearestPlot]?.classList.remove('in-reach');
    $('plots').children[target]?.classList.add('in-reach');
    nearestPlot = target;
  }

  const plant = farm.plots[target];
  const growing = Boolean(plant && remaining(plant));
  const nearBarn = atBarn();
  const nearShop = atShop();
  const noSeeds = !plant && !farm.seeds[selected];
  const hasBag = total(farm.bag) > 0;
  const hasStock = total(farm.barn) > 0;
  const value = Object.entries(farm.barn).reduce((sum, [id, count]) => sum + crops[id].reward * count, 0);
  const label = nearShop ? 'E · Otvoriť obchod' : nearBarn
    ? (hasBag ? 'E · Uložiť úrodu' : hasStock ? 'E · Predať úrodu za ' + value + ' 🪙' : 'Stodola je prázdna')
    : target < 0 ? 'Pristúp k záhonu, stodole alebo obchodu' : growing
      ? crops[plant.crop].name + ' dozrieva'
      : noSeeds ? 'Semienka kúpiš v obchode' : 'E · ' + (plant ? 'Zožať' : 'Zasadiť (' + crops[selected].name + ')');
  if (workButton.textContent !== label) workButton.textContent = label;
  workButton.disabled = busy || (!nearShop && (nearBarn ? !hasBag && !hasStock : target < 0 || growing || noSeeds));
  sellButton.disabled = busy || !nearBarn || !hasStock;
  const sellLabel = hasStock ? 'Predať za ' + value + ' 🪙' : 'Predať úrodu';
  if (sellButton.textContent !== sellLabel) sellButton.textContent = sellLabel;
  const wheelbarrowLabel = farm.wheelbarrow ? 'Fúrik zakúpený'
    : 'Kúpiť fúrik za ' + farm.layout.wheelbarrow.cost + ' 🪙';
  if (wheelbarrowButton.textContent !== wheelbarrowLabel) wheelbarrowButton.textContent = wheelbarrowLabel;
  wheelbarrowButton.disabled = busy || !nearBarn || farm.wheelbarrow || farm.coins < farm.layout.wheelbarrow.cost;
  const hint = nearShop ? 'Vyber plodinu a množstvo.' : 'Pristúp k vchodu do obchodu.';
  if ($('shop-hint').textContent !== hint) $('shop-hint').textContent = hint;
  for (const {button, cost} of shopButtons) {
    button.disabled = busy || !nearShop || farm.coins < cost;
  }
  $('terrain').querySelector('.barn-entrance').classList.toggle('in-reach', nearBarn);
  $('terrain').querySelector('.shop-entrance').classList.toggle('in-reach', nearShop);
}

async function actAtPosition(payload) {
  const point = {...position};
  const code = farm.code;
  const current = ++generation;
  await polling;
  if (generation !== current) return;
  await api(code, {action: 'position', ...point});
  if (generation !== current) return;
  sentPosition = point;
  sentAt = performance.now();
  const data = await api(code, payload);
  if (generation !== current) return;
  record(data);
  return data;
}

function workPlot(index) {
  return run(async () => {
    if (index < 0 || mapDistance(farm.layout.plots[index]) > farm.layout.reach) {
      message('Najprv pristúp bližšie k záhonu.');
      return;
    }
    const plant = farm.plots[index];
    if (plant && remaining(plant)) {
      message('Úroda ešte dozrieva.');
      return;
    }
    if (!plant && !farm.seeds[selected]) {
      message('Toto semienko najprv kúp v obchode.');
      return;
    }
    const data = await actAtPosition({
      plot: index, crop: selected, planted: plant?.planted,
      action: plant ? 'harvest' : 'plant',
    });
    if (data && plant) message('Úroda je v inventári. Odnes ju do stodoly.');
  });
}

function workBarn(action) {
  return run(async () => {
    if (!atBarn()) {
      message('Najprv pristúp k dverám stodoly.');
      return;
    }
    const data = await actAtPosition({action});
    if (data) {
      const notices = {
        deposit: 'Úroda je v spoločnej stodole.',
        sell: 'Úroda je predaná. Mince pribudli do pokladnice.',
        'buy-wheelbarrow': 'Fúrik je váš. Každý hráč teraz unesie 12 kusov úrody.',
      };
      message(notices[action]);
    }
  });
}

workButton.onclick = interact;
sellButton.onclick = () => workBarn('sell');
wheelbarrowButton.onclick = () => workBarn('buy-wheelbarrow');

function buySeed(crop, quantity) {
  return run(async () => {
    if (!atShop()) {
      message('Najprv pristúp k obchodu.');
      return;
    }
    if (await actAtPosition({action: 'buy-seed', crop, quantity})) {
      message('Semienka sú v zásobe.');
    }
  });
}

function cargoItem(id, quantity = null) {
  const item = document.createElement('li');
  const crop = crops[id];
  const icon = document.createElement('span');
  icon.className = 'cargo-icon';
  icon.setAttribute('aria-hidden', 'true');
  icon.textContent = crop?.icon ?? '';
  item.append(icon);

  const label = document.createElement('span');
  if (quantity === null) {
    label.className = 'visually-hidden';
    label.textContent = crop?.name ?? 'Voľná priehradka';
    if (crop) item.title = crop.name;
  } else {
    label.className = 'stock-label';
    label.textContent = crop.name;
    const count = document.createElement('strong');
    count.textContent = String(quantity);
    item.append(label, count);
    return item;
  }
  item.append(label);
  return item;
}

function renderInventory() {
  const capacity = farm.layout.bagCapacity;
  const bag = Object.keys(crops).flatMap(id => Array(farm.bag[id] ?? 0).fill(id));
  const stock = Object.keys(crops).filter(id => farm.barn[id]);
  const signature = JSON.stringify([capacity, bag, stock.map(id => [id, farm.barn[id]])]);
  if (inventoryView === signature) return;
  inventoryView = signature;

  $('bag-count').textContent = bag.length + ' / ' + capacity;
  $('hud-bag-count').textContent = bag.length + '/' + capacity;
  $('bag-items').replaceChildren(...Array.from({length: capacity}, (_, index) => {
    const item = cargoItem(bag[index]);
    item.classList.toggle('filled', Boolean(bag[index]));
    return item;
  }));
  const empty = document.createElement('li');
  empty.className = 'stock-empty';
  empty.textContent = 'Stodola je prázdna.';
  $('stock-items').replaceChildren(...(stock.length ? stock.map(id => cargoItem(id, farm.barn[id])) : [empty]));
}

function render() {
  if (!farm) return;
  $('coins').textContent = '🪙 ' + farm.coins;
  const seed = crops[selected];
  $('selected-seed').textContent = seed.icon + ' ' + seed.name + ' × ' + (farm.seeds[selected] ?? 0);
  for (const [id, crop] of Object.entries(crops)) {
    const summary = $('seed-count-' + id);
    const label = 'V zásobe ' + (farm.seeds[id] ?? 0) + ' · ' + crop.seconds + ' s';
    if (summary.textContent !== label) summary.textContent = label;
  }
  renderInventory();
  farm.plots.forEach((plant, index) => {
    const button = $('plots').children[index];
    const seconds = plant ? remaining(plant) : 0;
    const crop = plant ? crops[plant.crop] : crops[selected];
    button.classList.toggle('ready', Boolean(plant && !seconds));
    button.classList.toggle('planted', Boolean(plant));
    button.firstElementChild.textContent = plant ? (seconds ? '🌱' : crop.icon) : '';
    button.lastElementChild.textContent = plant ? (seconds ? seconds + ' s' : 'Zožať') : 'Zasadiť';
    const label = plant ? crop.name + (seconds ? ', dozrieva' : ', zožať') : 'zasadiť ' + crop.name;
    button.setAttribute('aria-label', 'Záhon ' + (index + 1) + ', ' + label);
  });
}

async function run(operation) {
  if (busy) return;
  busy = true;
  message();
  $('create').disabled = true;
  $('join').querySelector('button').disabled = true;
  try {
    await operation();
  } catch (error) {
    message(error instanceof TypeError || error.name === 'TimeoutError' ? 'Spojenie so serverom sa prerušilo.' : error.message);
  } finally {
    busy = false;
    $('create').disabled = false;
    $('join').querySelector('button').disabled = false;
  }
}

for (const [id, crop] of Object.entries(crops)) {
  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'seed';
  button.setAttribute('aria-pressed', id === selected);
  button.innerHTML = '<span class="seed-icon" aria-hidden="true">' + crop.icon + '</span><span>' +
    crop.name + '<small id="seed-count-' + id + '"></small></span>';
  button.onclick = () => {
    selected = id;
    for (const seed of $('seeds').children) seed.setAttribute('aria-pressed', seed === button);
    message();
    render();
    closePanel();
  };
  $('seeds').append(button);

  const listing = document.createElement('div');
  listing.className = 'shop-listing';
  const name = document.createElement('strong');
  name.textContent = crop.icon + ' ' + crop.name;
  listing.append(name);
  for (const quantity of [1, 5]) {
    const cost = crop.cost * quantity;
    const buy = document.createElement('button');
    buy.type = 'button';
    buy.disabled = true;
    buy.textContent = quantity + ' ks · ' + cost + ' 🪙';
    buy.setAttribute('aria-label', 'Kúpiť ' + quantity + ' ks: ' + crop.name + ' za ' + cost + ' mincí');
    buy.onclick = () => buySeed(id, quantity);
    listing.append(buy);
    shopButtons.push({button: buy, cost});
  }
  $('shop-items').append(listing);
}
for (let index = 0; index < 16; index++) {
  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'plot';
  button.innerHTML = '<span class="plot-icon" aria-hidden="true"></span><small aria-hidden="true"></small>';
  button.onclick = () => workPlot(index);
  $('plots').append(button);
}
$('create').onclick = () => run(async () => open(await api('', {})));
$('join').onsubmit = event => {
  event.preventDefault();
  const code = $('room').value.trim().toUpperCase();
  if (!/^[A-F0-9]{12}$/.test(code)) {
    message('Zadaj 12 znakov z kódu farmy.');
    return;
  }
  run(async () => open(await api(code)));
};
$('share').onclick = async () => {
  const input = $('invite-link');
  input.value = location.href;
  input.hidden = false;
  input.select();
  try {
    await navigator.clipboard.writeText(input.value);
    message('Odkaz je skopírovaný.');
  } catch {
    message('Odkaz môžeš skopírovať z poľa.');
  }
};
$('leave').onclick = () => {
  generation++;
  $('room').value = farm.code;
  farm = null;
  keys.clear();
  touchKeys.clear();
  syncPeers([]);
  $('connection').textContent = '';
  $('game').hidden = true;
  document.body.classList.remove('playing');
  closePanel(false);
  $('lobby').hidden = false;
  history.replaceState(null, '', location.pathname);
  message();
  $('room').focus();
};
async function poll() {
  const current = generation;
  try {
    const next = {...position};
    const send = !sentPosition || sentPosition.x !== next.x || sentPosition.y !== next.y ||
      performance.now() - sentAt > 2000;
    const data = await api(farm.code, send ? {action: 'position', ...next} : undefined);
    if (generation === current) {
      if (send) {
        sentAt = performance.now();
        sentPosition = next;
      }
      record(data);
    }
  } catch {
    if (generation === current) $('connection').textContent = 'Obnovujem spojenie…';
  }
}

setInterval(() => {
  if (!farm || busy || polling) return;
  polling = poll().finally(() => { polling = null; });
}, 200);
setInterval(render, 300);

const invitation = new URLSearchParams(location.search).get('farma');
if (invitation && /^[A-Fa-f0-9]{12}$/.test(invitation)) {
  $('room').value = invitation.toUpperCase();
  $('join').requestSubmit();
}
