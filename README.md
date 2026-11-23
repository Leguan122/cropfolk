# Cropfolk

Kooperatívna 2D farmárska klikačka. Avatarom sa po farme pohybuješ cez **WASD**, šípky alebo smerové tlačidlá pod záhonmi. Na sadenie a zber klikni na záhon. Hráči s rovnakým odkazom alebo kódom vidia navzájom svoje avatary a zdieľajú 16 záhonov, tri druhy plodín a mince.

## Lokálne spustenie

Potrebuješ PHP 8.1+ s rozšírením `pdo_sqlite`. V priečinku `farma-php` spusti:

```sh
php -S 127.0.0.1:8001 -t public
```

Otvor [http://127.0.0.1:8001](http://127.0.0.1:8001). Port 8001 umožňuje nechať pôvodný Python server na porte 8000. Databáza vznikne pri prvej požiadavke v `data/farm.sqlite3`.

## Hosting

Nastav webový koreň na `public/` a povoľ spúšťanie PHP pre `api.php`. Databázu drž mimo webového koreňa v trvalom zapisovateľnom priečinku. Vlastnú cestu môžeš nastaviť premennou `FARM_DB`. Na verejnej doméne použi HTTPS. Produkcia môže používať PHP-FPM alebo bežný PHP hosting bez WebSocket servera. Vstavaný server PHP slúži na lokálne testovanie.

## Prenos pôvodnej farmy

Zastav Python server a skopíruj jeho `farm.sqlite3` do `data/farm.sqlite3` v PHP projekte ešte pred prvým spustením. Ak databázu kopíruješ počas behu, použi SQLite `.backup`. PHP verzia používa tú istú tabuľku fariem a doplní tabuľku hráčov. Pôvodné kódy, mince, záhony aj časy dozrievania zostanú zachované. Pozvánky potom zdieľaj z novej adresy.

Polohy sa medzi prehliadačmi synchronizujú približne päťkrát za sekundu. Avatar hráča, ktorý prestal komunikovať, zmizne do približne desiatich sekúnd.
