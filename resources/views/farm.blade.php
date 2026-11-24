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
        <button type="button" id="seed-toggle" class="hud-chip" aria-keyshortcuts="Q" aria-controls="seed-picker" aria-expanded="false">
          <span id="selected-seed"></span>
        </button>
      </div>
      <div class="hud-status">
        <span class="hud-chip weather" id="weather" aria-live="polite"></span>
        <span class="hud-chip water" id="water" aria-label="Voda v krhle"></span>
        <span class="visually-hidden" id="connection" role="status"></span>
        <button type="button" id="panel-toggle" class="hud-chip" aria-keyshortcuts="I" aria-controls="farm-panel" aria-expanded="false">
          <span>Batoh</span><span id="hud-bag-count"></span>
        </button>
        <button type="button" id="menu-toggle" class="hud-chip" aria-keyshortcuts="M" aria-controls="game-menu" aria-expanded="false">Menu</button>
      </div>
    </div>

    <div id="tutorial" class="tutorial-strip" role="status" hidden>
      <span id="tutorial-number"></span>
      <span id="tutorial-text"></span>
    </div>

    <aside id="seed-picker" class="farm-panel seed-picker" aria-labelledby="seed-heading" hidden>
      <div class="panel-header">
        <h2 id="seed-heading" tabindex="-1">Aktívne semienko</h2>
        <button type="button" id="seed-close" aria-label="Zavrieť výber semienka">×</button>
      </div>
      <div id="seeds" class="panel-content"></div>
      <p class="menu-keys">W/S alebo ↑/↓ výber · E potvrdiť · Esc zavrieť</p>
    </aside>

    <aside id="shop-panel" class="farm-panel" aria-labelledby="shop-heading" hidden>
      <div class="panel-header">
        <h2 id="shop-heading" tabindex="-1">Obchod</h2>
        <button type="button" id="shop-close" aria-label="Zavrieť obchod">×</button>
      </div>
      <div class="panel-tabs" role="tablist" aria-label="Ponuka obchodu">
        <button id="tab-shop-buy" type="button" role="tab" aria-controls="pane-shop-buy" aria-selected="true">Semienka</button>
        <button id="tab-shop-sell" type="button" role="tab" aria-controls="pane-shop-sell" aria-selected="false" tabindex="-1">Predaj</button>
        <button id="tab-shop-upgrade" type="button" role="tab" aria-controls="pane-shop-upgrade" aria-selected="false" tabindex="-1">Vylepšenia</button>
      </div>
      <div class="panel-content">
        <section id="pane-shop-buy" role="tabpanel" aria-labelledby="tab-shop-buy"><div id="shop-items"></div></section>
        <section id="pane-shop-sell" role="tabpanel" aria-labelledby="tab-shop-sell" hidden>
          <h2>Úroda v batohu</h2>
          <ul id="shop-bag" class="stock-list" aria-label="Úroda na predaj"></ul>
          <button type="button" id="sell-bag" aria-disabled="true">Predať úrodu z batoha</button>
        </section>
        <section id="pane-shop-upgrade" role="tabpanel" aria-labelledby="tab-shop-upgrade" hidden>
          <div class="shop-upgrades">
            <section id="irrigation-upgrades" aria-label="Závlaha"></section>
            <div><button type="button" id="upgrade-hoe" disabled>Motyka</button><p id="hoe-tier" class="inventory-help"></p></div>
            <div><button type="button" id="upgrade-can" disabled>Krhla</button><p id="can-tier" class="inventory-help"></p></div>
            <div><button type="button" id="buy-wheelbarrow" disabled>Fúrik</button><p class="inventory-help">Zväčší batoh všetkým hráčom na 12 kusov.</p></div>
            <div><button type="button" id="open-east" disabled>Východné pole</button><p class="inventory-help">Sprístupní ďalších osem záhonov.</p></div>
            <div><button type="button" id="expand-shop" disabled>Rozšíriť obchod</button><p class="inventory-help">Odomkne zemiaky, jahody a balenia po 10 kusov.</p></div>
            <div><button type="button" id="open-south" disabled>Obnoviť južnú bránu</button><p class="inventory-help">Po odomknutí východného poľa a rozšírení obchodu pridá osem záhonov a tekvice.</p></div>
          </div>
        </section>
      </div>
      <p class="menu-keys">WASD alebo šípky · záložky na hornom riadku · E potvrdiť · Esc zavrieť</p>
    </aside>

    <aside id="farm-panel" class="farm-panel" aria-label="Informácie o farme" hidden>
      <div class="panel-header">
        <h2>Farma</h2>
        <button type="button" id="panel-close" aria-label="Zavrieť panel farmy">×</button>
      </div>
      <div class="panel-content">
        <h2>Inventár <span id="bag-count"></span></h2>
        <ul class="bag-slots" id="bag-items" aria-label="Priehradky inventára"></ul>
        <div class="inventory">
        <h2>Spoločná stodola</h2>
        <ul class="stock-list" id="stock-items" aria-label="Zásoby stodoly"></ul>
        <p class="inventory-help">Pri stodole stlač E pre uloženie, predaj a objednávky.</p>
        </div>
      </div>
      <p class="menu-keys">W/S výber · E potvrdiť · Esc zavrieť</p>
    </aside>

    <aside id="barn-panel" class="farm-panel" aria-labelledby="barn-heading" hidden>
      <div class="panel-header">
        <h2 id="barn-heading" tabindex="-1">Spoločná stodola</h2>
        <button type="button" id="barn-close" aria-label="Zavrieť stodolu">×</button>
      </div>
      <div class="panel-content">
        <section class="order-card" aria-labelledby="order-name">
          <p class="order-caption">Aktívna objednávka</p>
          <h2 id="order-name"></h2>
          <ul id="order-progress" class="stock-list" aria-label="Postup objednávky"></ul>
          <button type="button" id="fulfill-order" aria-disabled="true">Odovzdať objednávku</button>
        </section>
        <section class="inventory">
          <h2>Zásoby stodoly</h2>
          <ul id="barn-stock" class="stock-list" aria-label="Zásoby na predaj"></ul>
          <div class="stock-actions">
            <button type="button" id="deposit-stock" aria-disabled="true">Uložiť úrodu z batoha</button>
            <button type="button" id="sell-stock" aria-disabled="true">Predať celý sklad</button>
          </div>
        </section>
      </div>
      <p class="menu-keys">W/S výber · E potvrdiť · Esc zavrieť</p>
    </aside>

    <aside id="game-menu" class="farm-panel" aria-labelledby="game-menu-heading" hidden>
      <div class="panel-header">
        <h2 id="game-menu-heading" tabindex="-1">Menu</h2>
        <button type="button" id="menu-close" aria-label="Zavrieť menu">×</button>
      </div>
      <div class="panel-content">
        <h2>Pozvi kamarátov</h2>
        <p>Kód farmy <strong id="code"></strong></p>
        <button type="button" id="share">Skopírovať odkaz</button>
        <input id="invite-link" aria-label="Odkaz na farmu" readonly hidden>
        <button type="button" class="quiet" id="tutorial-toggle">Preskočiť zaučenie</button>
        <button type="button" class="quiet" id="leave">Späť do úvodnej obrazovky</button>
      </div>
      <p class="menu-keys">W/S výber · E potvrdiť · Esc zavrieť</p>
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
        <button type="button" id="work-plot" disabled hidden></button>
        <p class="key-hint"><kbd>WASD</kbd> pohyb <kbd>E</kbd> akcia <kbd>Q</kbd> semienka <kbd>I</kbd> batoh <kbd>M</kbd> menu</p>
      </div>
    </div>
  </section>
  <p id="message" role="status"></p>
</main>
</body>
</html>
