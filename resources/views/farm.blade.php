<!doctype html>
<html lang="sk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#243624">
<title>Cropfolk</title>
<link rel="stylesheet" href="style.css">
<script src="game.js" defer></script>
</head>
<body>
<header class="bar">
  <div class="wrap brand"><span class="mark" aria-hidden="true">🌱</span> Cropfolk</div>
</header>
<main class="wrap">
  <section id="lobby" class="lobby">
    <div class="landscape" aria-hidden="true"><div class="sun"></div><div class="hill"></div><div class="barn">🏡</div><div class="meadow"></div></div>
    <div class="welcome">
      <h1>Cropfolk</h1>
      <p>Zasaď, počkaj na úrodu a zveľaďuj farmu spolu s kamarátmi.</p>
      <button type="button" id="create" class="primary">Založiť farmu</button>
      <form id="join">
        <label for="room">Máš kód farmy?</label>
        <div class="join-row"><input id="room" maxlength="12" placeholder="Kód farmy" autocomplete="off" required><button>Pripojiť sa</button></div>
      </form>
    </div>
  </section>
  <section id="game" class="game-screen" aria-label="Hracia plocha" hidden>
    <h1 id="game-title" class="visually-hidden" tabindex="-1">Naša farma</h1>
    <div class="field" id="field">
      <div id="world" class="world">
        <div id="terrain" aria-hidden="true"></div>
        <div id="plots" class="plots" aria-label="Záhony"></div>
        <div id="avatars" aria-hidden="true"></div>
      </div>
    </div>

    <div class="screen-top">
      <div class="hud-status">
        <div class="hud-chip coins" id="coins" aria-label="Mince"></div>
        <button type="button" id="seed-toggle" class="hud-chip" aria-controls="farm-panel" aria-expanded="false">
          <span id="selected-seed"></span>
        </button>
      </div>
      <div class="hud-status">
        <span class="hud-chip connection" id="connection"></span>
        <button type="button" id="panel-toggle" class="hud-chip" aria-controls="farm-panel" aria-expanded="false">
          <span>Farma</span><span id="hud-bag-count"></span>
        </button>
      </div>
    </div>

    <aside id="farm-panel" class="farm-panel" aria-label="Informácie o farme" hidden>
      <div class="panel-header">
        <h2>Farma</h2>
        <button type="button" id="panel-close" aria-label="Zavrieť panel farmy">×</button>
      </div>
      <section aria-labelledby="seed-heading">
        <h2 id="seed-heading" tabindex="-1">Semienka</h2>
        <div id="seeds"></div>
      </section>
      <section class="inventory" aria-labelledby="shop-heading">
        <h2 id="shop-heading" tabindex="-1">Obchod</h2>
        <p id="shop-hint" class="inventory-help">Pristúp k vchodu do obchodu.</p>
        <div id="shop-items"></div>
      </section>
      <section class="inventory">
        <h2>Inventár <span id="bag-count"></span></h2>
        <ul class="bag-slots" id="bag-items" aria-label="Priehradky inventára"></ul>
      </section>
      <section class="inventory">
        <h2>Spoločná stodola</h2>
        <ul class="stock-list" id="stock-items" aria-label="Zásoby stodoly"></ul>
        <button type="button" id="sell-stock" disabled>Predať úrodu</button>
        <p class="inventory-help">Úrodu ulož alebo predaj pri dverách stodoly.</p>
        <button type="button" id="buy-wheelbarrow" disabled>Vylepšiť fúrik</button>
        <p class="inventory-help">Fúrik pri stodole zväčší inventár všetkým hráčom.</p>
      </section>
      <div class="invite">
        <h2>Pozvi kamarátov</h2>
        <p>Kód farmy <strong id="code"></strong></p>
        <button type="button" id="share">Zobraziť odkaz</button>
        <input id="invite-link" aria-label="Odkaz na farmu" readonly hidden>
        <button type="button" class="quiet" id="leave">Späť do menu</button>
      </div>
    </aside>

    <div class="screen-bottom">
      <p id="game-message" class="game-message" role="status"></p>
      <div class="screen-controls">
        <div class="direction-pad" aria-label="Ovládanie pohybu">
          <button type="button" data-key="KeyW" aria-label="Hore">▲</button>
          <button type="button" data-key="KeyA" aria-label="Vľavo">◀</button>
          <button type="button" data-key="KeyS" aria-label="Dole">▼</button>
          <button type="button" data-key="KeyD" aria-label="Vpravo">▶</button>
        </div>
        <button type="button" id="work-plot" disabled>Pristúp k záhonu</button>
        <p class="key-hint"><kbd>WASD</kbd> pohyb <kbd>E</kbd> akcia</p>
      </div>
    </div>
  </section>
  <p id="message" role="status"></p>
</main>
</body>
</html>
