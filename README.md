# Cropfolk

Kooperatívna 2D farmárska hra v Laraveli 13. Na farme sa pohybuješ cez WASD, šípky alebo dotykové tlačidlá. Pristúp k záhonu a na jeho obrábanie stlač E, klikni na záhon v dosahu alebo použi tlačidlo pod mapou. Najbližší záhon v dosahu sa zvýrazní. Zo spoločného dvora vedie chodník k bránkam oboch polí. Kamera sleduje avatara a stodola aj ploty bránia prechodu.

Po vstupe do hry mapa vyplní celé okno. Mince a vybrané semienka vidíš hore nad mapou. Tlačidlo Batoh otvorí zásoby. Aktívne semienko vyberieš vľavo hore. Batoh obsahuje inventár a zásoby stodoly. Pozvánky nájdeš pod samostatným tlačidlom Menu. Obchod otvoríš klávesom E v jeho blízkosti a po odchode sa automaticky zavrie. Obchod, stodola aj studňa sú dostupné zo všetkých strán. Dostupná akcia sa zobrazí vedľa avatara.

Semienka kúpiš pri vchode do obchodu na západnej strane dvora. Stlač **E** a vyber jeden alebo päť kusov. Výsadba spotrebuje jedno semienko zo zásoby.

Nová farma začína s ôsmimi záhonmi na západnom poli. V obchode pod záložkou Vylepšenia odomkneš východné pole za 45 spoločných mincí a rozšíriš obchod za 65 mincí. Rozšírenie pridá nové semienka. Pribudnú balenia po 10 kusov, zemiaky s dvojitou úrodou a jahody, ktoré po prvom zbere raz dorastú. Po odomknutí východného poľa a rozšírení obchodu môže tím v obchode za 110 mincí obnoviť južnú bránu. Za ňou je ďalších osem záhonov a druhý priechod vedie späť ku stodole. Obchod začne predávať tekvice.

Zrelá úroda ide najprv do osobného inventára (najviac osem kusov). V obchode môžeš za 40 spoločných mincí kúpiť fúrik, ktorý zvýši kapacitu všetkým na 12 kusov. V obchode pod záložkou Predaj môžeš predať úrodu priamo zo svojho batoha. Pri stodole stlač **E** a vyber uloženie do spoločného skladu. Pri dverách môže ktorýkoľvek hráč predať celý sklad a pripísať mince do spoločnej pokladnice. Inventár zostáva k dispozícii po obnovení tej istej karty. Pozvánka pre kamarátov je dostupná priamo v hre.

## Zaučenie

Po založení farmy sa nad mapou zobrazí nasledujúci krok od nákupu reďkoviek po odovzdanie prvej objednávky. Zvýraznenie na mape ukáže obchod, pole alebo stodolu. Postup je spoločný. V Menu môžeš zaučenie skryť alebo znovu zapnúť pre seba.

## Spoločné objednávky

Pri stodole otvor klávesom E spoločný sklad a aktívnu objednávku. Najprv ulož úrodu z batoha. Každý hráč vidí spoločný postup. Prvá objednávka vyžaduje štyri reďkovky za 26 mincí. Odovzdanie spotrebuje iba požadované kusy a otvorí ďalšiu objednávku. Objednávky nemajú časový limit. V stodole môžeš stále predať celý sklad za bežnú cenu.

## Ovládanie klávesnicou

WASD alebo šípky ovládajú pohyb, E vykoná akciu, Q otvorí výber semienka, I batoh a M menu. V ponukách vyberáš položku cez W/S alebo šípky hore a dole. V obchode sa po cenách pohybuješ vo všetkých smeroch. Záložky meníš cez A/D alebo šípky do strán iba na hornom riadku záložiek. E alebo Enter vybranú položku potvrdí, Esc alebo Q zavrie otvorenú ponuku.

## Pôda a nástroje

Na suchom záhone najprv použi krhlu. Po zbere pôdu najprv obrob motykou. Dážď automaticky zavlaží obrobené záhony, kým prší. Jahody po prvom zbere dorastú na tom istom záhone. Kláves E vždy použije nástroj alebo akciu, ktorú zvýraznený záhon potrebuje.

Každá farma má základnú krhlu a motyku. Vyššie úrovne sú spoločné a kúpiš ich pod záložkou Vylepšenia. Nástroje podľa úrovne ošetria až 1, 3 alebo 5 vhodných záhonov naraz. Krhla má podľa úrovne kapacitu 6, 12 alebo 20 dávok. Každý hráč má vlastnú zásobu vody, ktorá ubúda o jednu dávku na poliaty záhon. Doplniť ju môžeš zadarmo pri studni klávesom E.

## Závlaha

Po odomknutí východného poľa sa v obchode odomkne nákup závlahy. Úroveň 1 stojí 60 mincí na západnom, 75 na východnom a 90 na južnom poli. Ventil spustíš klávesom E a poleje všetky suché obrobené záhony na danom poli.

Úroveň 2 stojí ďalších 45, 55 alebo 65 mincí podľa poľa. Poleje pripravené záhony pri inštalácii a potom automaticky každý záhon po obrábaní motykou. Studňa dopĺňa osobnú krhlu na ručné polievanie.

## Štatistiky fariem

Server zaznamenáva úspešné herné akcie od nasadenia migrácie. Každý záznam obsahuje čas, farmu, pseudonym hráča, akciu, zostatok mincí a detaily ako plodina alebo množstvo. Identifikátor zodpovedá hráčovi v danej relácii prehliadača. Pohyb, synchronizačné požiadavky ani neúspešné pokusy sa nezaznamenávajú.

Prehľad spustíš cez SSH v koreňovom priečinku projektu. Predvolené obdobie je 24 hodín a časy sú v pásme Europe/Bratislava.

```sh
php artisan cropfolk:activity
php artisan cropfolk:activity --farm=KOD_FARMY --since="7 days ago" --limit=100
php artisan cropfolk:activity --since=all --csv --limit=1000 > aktivita.csv
```

Voliteľne pridaj `--player` s úplným identifikátorom z výpisu. Akcie pridania testovacích mincí uvidíš cez `--include-test`.

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

Na lokálne testovanie môžeš do `.env` pridať `CROPFOLK_TEST_COINS=true`. Na farme stlač **Shift+C** a do spoločnej pokladnice pribudne 250 mincí. Server vyžaduje lokálne prostredie a pripojenie z loopback adresy.

## Nasadenie

Webový koreň nastav na `public/`. Vytvor produkčný `.env`, vygeneruj `APP_KEY`, vypni `APP_DEBUG` a nastav databázu. V `.env` nastav `DB_CONNECTION=mysql` a údaje databázy. Pri vzdialenom pripojení nastav aj `MYSQL_ATTR_SSL_CA` na cestu k dôveryhodnému CA certifikátu. Produkčné migrácie spusti cez `php artisan migrate --force`.

Laravel verzia používa MySQL. Ak chceš preniesť staré farmy, skopíruj z pôvodnej databázy riadky tabuľky `farms` (stĺpce `code`, `coins`, `plots`) do novej databázy po migrácii. Poloha avatara je dočasná a neprenáša sa.
