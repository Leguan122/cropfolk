const crops = {
  radish: {name: 'Reďkovka', icon: '🌱', cost: 2, reward: 5, seconds: 12},
  carrot: {name: 'Mrkva', icon: '🥕', cost: 5, reward: 11, seconds: 25},
  corn: {name: 'Kukurica', icon: '🌽', cost: 10, reward: 23, seconds: 45},
};
const $ = id => document.getElementById(id);
let farm = null;
let selected = 'radish';
let busy = false;
let polling = false;
let generation = 0;
let serverClock = 0;
let clockReceived = 0;
const playerId = Array.from(crypto.getRandomValues(new Uint8Array(16)),
  byte => byte.toString(16).padStart(2, '0')).join('');
const avatarLayer = $('avatars');
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
    if (player.id === playerId) continue;
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

function keepInside(value, margin) {
  return Math.min(1 - margin, Math.max(margin, value));
}

function animate(frame) {
  const elapsed = Math.min(0.05, (frame - lastFrame) / 1000);
  lastFrame = frame;
  if (farm) {
    const pressed = new Set([...Array.from(keys, key => directions[key]), ...touchKeys.values()]);
    const dx = Number(pressed.has('KeyD')) - Number(pressed.has('KeyA'));
    const dy = Number(pressed.has('KeyS')) - Number(pressed.has('KeyW'));
    const width = avatarLayer.clientWidth;
    const height = avatarLayer.clientHeight;
    if (width && height) {
      const distance = 185 * elapsed / (Math.hypot(dx, dy) || 1);
      position.x = keepInside(position.x + dx * distance / width, 20 / width);
      position.y = Math.min(1 - 22 / height, Math.max(39 / height, position.y + dy * distance / height));
      drawAvatar(ownAvatar, position, width, height);
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

window.addEventListener('keydown', event => {
  if (!farm || !directions[event.code] || event.ctrlKey || event.metaKey || event.altKey ||
      event.target.closest('input,textarea,[contenteditable]')) return;
  event.preventDefault();
  keys.add(event.code);
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
    headers: {Accept: 'application/json', ...(payload && {'Content-Type': 'application/json'})},
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
  $('message').textContent = value;
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
  position = {x: 0.12 + Math.random() * 0.76, y: 0.9};
  sentPosition = null;
  $('lobby').hidden = true;
  $('game').hidden = false;
  $('code').textContent = data.code;
  $('invite-link').hidden = true;
  history.replaceState(null, '', '?farma=' + data.code);
  record(data);
  $('game').querySelector('h1').setAttribute('tabindex', '-1');
  $('game').querySelector('h1').focus();
}

function remaining(plant) {
  return Math.max(0, Math.ceil((plant.ready - serverClock - (performance.now() - clockReceived)) / 1000));
}

function render() {
  if (!farm) return;
  $('coins').textContent = '🪙 ' + farm.coins;
  farm.plots.forEach((plant, index) => {
    const button = $('plots').children[index];
    const seconds = plant ? remaining(plant) : 0;
    const crop = plant ? crops[plant.crop] : crops[selected];
    button.classList.toggle('ready', Boolean(plant && !seconds));
    button.firstElementChild.textContent = plant ? (seconds ? '🌱' : crop.icon) : '＋';
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
    crop.name + '<small>🪙 ' + crop.cost + ' · predaj ' + crop.reward + ' · ' + crop.seconds + ' s</small></span>';
  button.onclick = () => {
    selected = id;
    for (const seed of $('seeds').children) seed.setAttribute('aria-pressed', seed === button);
    message();
    render();
  };
  $('seeds').append(button);
}
for (let index = 0; index < 16; index++) {
  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'plot';
  button.innerHTML = '<span class="plot-icon" aria-hidden="true"></span><small aria-hidden="true"></small>';
  button.onclick = () => run(async () => {
    const plant = farm.plots[index];
    if (plant && remaining(plant)) {
      message('Úroda ešte dozrieva.');
      return;
    }
    const current = ++generation;
    const data = await api(farm.code, {
      plot: index, crop: selected, planted: plant?.planted, action: plant ? 'harvest' : 'plant',
    });
    if (generation === current) record(data);
  });
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
  $('lobby').hidden = false;
  history.replaceState(null, '', location.pathname);
  message();
  $('room').focus();
};
setInterval(async () => {
  if (!farm || busy || polling) return;
  polling = true;
  const current = generation;
  try {
    const next = {...position};
    const send = !sentPosition || sentPosition.x !== next.x || sentPosition.y !== next.y ||
      performance.now() - sentAt > 2000;
    const data = await api(farm.code, send ? {action: 'position', id: playerId, ...next} : undefined);
    if (generation === current) {
      if (send) {
        sentAt = performance.now();
        sentPosition = next;
      }
      record(data);
    }
  } catch {
    if (generation === current) $('connection').textContent = 'Obnovujem spojenie…';
  } finally {
    polling = false;
  }
}, 200);
setInterval(render, 300);

const invitation = new URLSearchParams(location.search).get('farma');
if (invitation && /^[A-Fa-f0-9]{12}$/.test(invitation)) {
  $('room').value = invitation.toUpperCase();
  $('join').requestSubmit();
}
