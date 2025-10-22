# Váróterem rendszer fejlesztési összefoglaló

Ez a dokumentum a GTA6 Mods sablonban megvalósított váróterem és verziókezelési rendszer részletes állapotjelentése. A cél a lehető leggyorsabb, cache-elt és progresszíven dekouplált megoldások biztosítása.

## Készre vitt feladatok

- **`wp_mod_versions` adatmodell és szolgáltatósztály:** létrehoztuk az új verziótáblát, indexekkel és migrációval együtt. A `inc/class-mod-versions.php` kezeli a táblalétrehozást, a tranzakciós jelöléseket, a cache-invalidationt, valamint a letöltésszámláló atomikus frissítését és a toplista statisztikák gyűjtését.
- **Letöltési útvonalak és routing:** a `functions.php` új rewrite szabályokkal biztosítja a `/{slug}/download/{version_id}` és `/{slug}/download/latest` útvonalakat, miközben a legacy linkeket kompatibilisen tartja. A kontextus alapján bekapcsolja a váróterem asseteket és a REST tokengenerálást.
- **Váróterem sablon és assetek:** a `page-template-waiting-room.php` minimális overheaddel renderel, inline kritikus CSS-sel és célzott dequeueléssel. A `assets/css/waiting-room.css` és `assets/js/waiting-room.js` csak a szükséges komponenseket töltik be; a JavaScript REST tokent kér, 5 másodperces visszaszámlálást futtat, sessionStorage-ban védi a folyamatot és időzített HMAC tokent használ a letöltéshez.
- **Biztonságos letöltéskezelő és REST végpont:** az `inc/download-handler.php` tokent validál, IP-alapú rate limitet alkalmaz, majd Redis-alapú sorba helyezi a letöltésszámlálást (objektum cache hiányában szinkron fallbackkel), miközben azonnal visszaadja a fájlt. Az `inc/rest-api/tracking-endpoints.php` `/wp-json/gta6/v1/generate-download-token` végpontja nonce-ot és verzióazonosítót ellenőriz, cache-elt tokent ad vissza CDN-barát letöltési URL-lel és cache-fejlécekkel.
- **Front-end gombok és sablon helper frissítések:** a letöltési gombok (`assets/js/single-mod.js`, `template-parts/single/mod-actions.php`) most a váróterembe visznek. A sablon- és admin-oldali helper függvények (`inc/template-helpers.php`, `inc/upload-functions.php`, `inc/mod-update-functions.php`, `inc/ajax-functions.php`) már az új adatmodellt használják verziólekérdezésre, cache-elésre és állapotváltásokra.
- **Adminisztrációs eszközök:** az `inc/admin-functions.php` verziótörténet metaboxa táblázatosan mutatja a verziókat, letöltéseket, és jelölhető a „deprecated” státusz. A dashboard widget cache-elt aggregált adatokat és Chart.js grafikont használ a legnépszerűbb verziók megjelenítésére.

## Legutóbbi frissítések

- **Váróterem UI és fordíthatóság:** a `page-template-waiting-room.php` és a kapcsolódó CSS/JS teljesen a megadott Tailwind mintához igazodik, minden felirat angol alapszöveggel kerül ki, így könnyen fordíthatóvá vált a rendszer.
- **Biztonsági kiegészítés:** a `functions.php` váróterem kontextus feloldása most ellenőrzi a poszt státuszát és jelszavas védelmét, így a rewrite nem kerülheti meg a WordPress láthatósági szabályait.
- **Changelog gombok váróteremre kötése:** a `inc/mod-update-functions.php` kiegészítő logikája melléklet-azonosító alapján is párosít a `wp_mod_versions` sorokra, ezért a verziótörténet minden gombja a váróterem URL-t használja.
- **Statikus váróterem cache + Cloudflare purge:** a `functions.php` beépített HTML cache-t szolgál ki Cloudflare-barát fejlécekkel, és verziófrissítéskor automatikusan purgeli a kapcsolódó URL-eket.
- **Nginx X-Accel-Redirect és Redis rate limit:** a letöltéskezelő támogatja az Nginx belső átirányítását és Redis-alapú IP limitet, így csökken a PHP-FPM terhelése nagy forgalomnál is.
- **Redis soros letöltésszámláló:** a letöltések számlálása Redisben gyűlik, amelyet a `wp gta6mods process-downloads` WP-CLI parancs kötegelve írja vissza az adatbázisba, minimalizálva a MySQL írásokat.
- **HMAC tokenek és no-JS fallback:** a `inc/download-handler.php` most `hash_hmac` aláírást használ, ujjlenyomat alapú rate limitet alkalmaz, valamint JavaScript nélküli linket biztosít ellenőrzött várótermi engedéllyel.
- **Kézi verzió migráció:** az `inc/class-mod-versions.php` admin figyelmeztetést és `admin-post.php?action=gta6mods_run_version_migration` végpontot ad, illetve WP-CLI parancsot (`wp gta6mods migrate-versions`) a batch-alapú adatfeltöltéshez.

## Függő feladatok / TODO-k

- **Külső forrású verziók támogatása:** jelenleg a váróterem feltételezi, hogy minden verzió WordPress médiatáron belüli csomaghoz kapcsolódik. A jövőben a `inc/class-mod-versions.php` sémáját és a `inc/download-handler.php` logikáját bővíteni kellene külső linkek kezelése érdekében.
- **Redis queue monitorozása:** éles környezetben szükséges egy supervisord/WP-CLI cron beállítás, amely percenként futtatja a `wp gta6mods process-downloads` parancsot, és figyeli a queue esetleges torlódását.
- **REST nonce anoním felhasználóknak:** kijelentkezett felhasználók esetén validálni kell, hogy a `wp_create_nonce( 'wp_rest' )` által generált nonce elfogadható-e, vagy extra validációs réteget kell implementálni a `inc/rest-api/tracking-endpoints.php` végpontjában.

## Tesztelési fókuszpontok

- **Váróterem UX és token kezelés:** manuális tesztek szükségesek több böngészőben, gyors frissítéssel és több füllel, hogy a sessionStorage logika és a rate limiting stabilan működjön (`assets/js/waiting-room.js`, `inc/download-handler.php`).
- **Redis-alapú letöltésszámláló:** integrációs teszt a `wp gta6mods process-downloads` parancs periodikus futtatásával, illetve objektum cache nélküli fallback ellenőrzése.
- **Admin dashboard grafikon:** ellenőrizni kell, hogy Chart.js CDN-ről betöltődve is működik zárt vagy korlátozott környezetben (`inc/admin-functions.php`).

## Teljesítmény és biztonság

- Transziensek és Redis-kompatibilis objektum cache használata az új adatmodell kulcs lekérdezéseire.
- IP-alapú token bucket rate limiter a REST végpontokra és a letöltéskezelőre, 429-es válasszal túlterheléskor.
- `hash_hmac`-szel aláírt, 600 másodpercig (10 perc) érvényes tokenek és ujjlenyomat alapú rate limiter a hotlink-elkerüléshez; első sikeres ellenőrzés után a felhasználó ugyanabból a böngészőből korlátlanul újragenerálhatja a letöltést a teljes érvényességi idő alatt.
- Nginx `X-Accel-Redirect` támogatás (ha elérhető) a fájlszolgáltatás gyorsításához, fallbackkel közvetlen fájlstreamekre vagy CDN-redirectre.
- Adatbázis-lekérdezések mindenhol `$wpdb->prepare()` és limitált oszlopszettekkel készülnek.

## Felhasználói élmény

- A letöltések a váróteremből indulnak, ahol 5 másodperces visszaszámláló és letöltés előtti hirdetés jelenik meg.
- A sablon csak a szükséges asseteket tölti be, inline kritikus stílusokat és vanilla JavaScriptet használ a gyors betöltés érdekében.

## Következő lépések (opcionális)

- Külső linkes vagy többfájlos verziók támogatása.
- Letöltési események külön naplózása részletes analitika céljából.
- Cloudflare R2 és hard cache logok integrálása a token visszaélések mélyebb elemzéséhez.
