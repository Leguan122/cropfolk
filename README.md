# Cropfolk

Kooperatívna 2D farmárska hra v Laraveli 13. Na farme sa pohybuješ cez WASD, šípky alebo dotykové tlačidlá. Kliknutím na záhon zasadíš alebo zožneš plodinu. Pozvánka pre kamarátov je dostupná priamo v hre.

## Lokálny vývoj

Potrebuješ PHP 8.4.1+, Composer a rozšírenie `pdo_mysql` a MySQL 8.4. Z koreňa projektu spusti:

```sh
composer install --no-dev
cp .env.example .env
php artisan key:generate
# V .env nastav prístup k MySQL a podľa potreby cestu k CA certifikátu.
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8002
```

Otvor [http://127.0.0.1:8002](http://127.0.0.1:8002). Pozvánku otvor aj v druhom okne prehliadača, ak chceš skúsiť multiplayer na jednom počítači. JavaScript a CSS sa podávajú priamo z `public/`.

## Nasadenie

Webový koreň nastav na `public/`. Vytvor produkčný `.env`, vygeneruj `APP_KEY`, vypni `APP_DEBUG` a nastav databázu. V `.env` nastav `DB_CONNECTION=mysql` a údaje databázy. Pri vzdialenom pripojení nastav aj `MYSQL_ATTR_SSL_CA` na cestu k dôveryhodnému CA certifikátu. Produkčné migrácie spusti cez `php artisan migrate --force`.

Laravel verzia používa MySQL. Ak chceš preniesť staré farmy, skopíruj z pôvodnej databázy riadky tabuľky `farms` (stĺpce `code`, `coins`, `plots`) do novej databázy po migrácii. Poloha avatara je dočasná a neprenáša sa.
