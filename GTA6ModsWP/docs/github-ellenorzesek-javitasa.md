# GitHub ellenőrzési hibák javításának összefoglalója

## 1. Mobil menü JavaScript betöltése
A `header-menus.js` eddig csak bejelentkezett felhasználóknál töltődött be, így kijelentkezett látogatóknál a hamburger ikon nem reagált. Az enqueue feltételét átszerveztem, hogy a szkript minden látogató számára betöltődjön, miközben a REST adatok lokalizálása csak belépett felhasználóknál történik. A favicon jelvény függőségét is dinamikusan kezelem, hogy vendég módban se hiányozzon betöltéskor.

## 2. Komment permalinks paraméterei
A `gta6mods_normalize_comment_link()` korábbi verziója törölte a WordPress által generált query stringet, emiatt az elfogadásra váró kommentek saját linkjei működésképtelenné váltak. A függvény most már URL-parzolás után úgy tisztítja az útvonalat, hogy közben megőrzi a query paramétereket és a fragmentet is.

## 3. Kommentoldalak lapozása
A sablonban nem alkalmazunk oldalankénti komment-listázást, kizárólag a "load more" megoldást tartjuk meg. Emiatt a permalinkeknél eltávolítottam a `comment-page-N/` szegmens visszaállítását, ugyanakkor született két új filter (`gta6mods_comment_permalink_include_pagination`, `gta6mods_allow_comment_pagination_segments`), amelyeken keresztül opcionálisan visszakapcsolható a lapozási jelölés olyan telepítéseken, ahol erre szükség van. Alapértelmezésben minden permalink továbbra is a `/comments/#comment-ID` mintát követi.

## 4. Hős szekció állapotváltozói
A hős terület kedvelés/mentés gombjai olyan változókat használtak (`$is_user_logged_in`, `$is_liked_main`, `$is_bookmarked_main`, `$like_total_display`), amelyek csak később kaptak értéket, ezért PHP notice keletkezett, és a gombok mindig inaktívak maradtak. A változókat a hős blokk előtt inicializálom, valamint előre kiszámítom a megjelenített kedvelésszámot.

## 5. Függőben lévő frissítések védelme
Az új SQL lekérdezés megkerülte a WordPress jogosultsági szűrőit, így a moderálás alatt álló mod frissítések adatai anonim látogatók számára is láthatóvá váltak. Bevezettem egy jogosultság-ellenőrző segédfüggvényt, visszatértem a `get_posts` alapú lekérdezésre, és csak az arra jogosult felhasználók kapják meg a részletes adatokat. Vendég módban csak egy általános „Pending” sor jelenik meg, így a badge látszik, de érzékeny információ nem szivárog ki.
