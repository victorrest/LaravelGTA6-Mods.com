# Hibajavítási összefoglaló

Az alábbi problémákat találtam még fennállónak, és a mostani módosításokkal orvosoltam őket:

- **REST értesítések duplikált eseménykezelője:** Az inline script már dinamikusan ellenőrzi, hogy a REST-alapú modul elérhető-e, így a hagyományos értesítés-bővítés nem kötődik be feleslegesen.
- **Értékelések REST hívásainak hitelesítése:** A `fetch` hívások mostantól `credentials: 'same-origin'` beállítással mennek, így a sütik is elküldésre kerülnek és a hitelesítés nem hibázik.
- **Kapcsolódó modok egyedi tartalomtípusokhoz:** A REST végpont és a payload előállító függvény most már a `gta6mods_get_mod_post_types()` listát használja, így a custom post type alapú modokhoz is működik az ajánló.
- **Nézet- és letöltésszámlálók védelme:** Új, univerzális REST nonce került bevezetésre (`X-GTA6-Nonce`), amelyet minden követő endpoint és front-end kérés használ a visszaélés megakadályozására.
- **ETag normalizálás kompatibilitása:** A `str_starts_with` hiányában is működik most már a normalizáló függvény, ezért PHP 7.4 környezetben sem omlik össze a REST réteg.
- **Single mod REST gyorsítótárazása:** Ha kommenteket is kér a kliens, a válasz privát cache-fejléceket kap, így a bejelentkezett felhasználók egyedi űrlapjai nem kerülnek véletlenül publikus cache-be.
- **Login/Regisztráció átirányítás:** A `redirect_to` paraméter dupla kódolása megszűnt, ezért visszaáll az eredeti céloldalra irányítás.
- **Mod frissítések és verziók kezelése egyedi post típusoknál:** Az AJAX és REST logika mostantól a konfigurálható mod post típus listát használja, így az új `gta_mod` típusra is működik a verziókezelés.
- **REST számlálók globális header támogatása:** A `buildRestHeaders` segédfüggvény automatikusan hozzáadja a biztonsági tokent, így minden modul egységesen védi a számlálókat.
- **Keresési naplózás mbstring nélküli környezetben:** A keresési normalizálás és a kapcsolódó ajánló logika visszaesik a natív `strtolower`/`strlen` függvényekre, ha az mbstring kiterjesztés nem elérhető.

Ezekkel a változtatásokkal a naplóban szereplő aktív hibák kijavításra kerültek.
