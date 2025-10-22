# Szerver módosítások

Az alábbi beállításokat szerver szinten kell végrehajtani, hogy a letöltési rendszer teljesítménye és megbízhatósága garantált legyen.

## 1. Nginx X-Accel-Redirect belső útvonal

A gyors és erőforrás-kímélő fájlkiszolgálás érdekében az Nginx konfigurációban adj hozzá egy belső (`internal`) location blokkot, amely a WordPress feltöltéseket védi és a PHP-FPM helyett közvetlenül Nginx-szel szolgálja ki a fájlokat.

```nginx
location /protected-files/ {
        internal;
        alias /home/ashley/topiku.hu/public/wp-content/uploads/;
}
```

> **Miért szükséges?**
> Így a PHP folyamatok nem terhelődnek letöltés közben, a Cloudflare és az Nginx pedig nagy forgalom mellett is kiszolgálja a fájlokat.

## 2. Valódi cron feladat a letöltési sor feldolgozásához

A letöltés-számláló Redis sorba gyűjti az eseményeket. Bár a sablon perces WP-Cron eseményt is ütemez, erősen ajánlott egy valódi szerver oldali cron feladat, amely percenként lefuttatja a WP-CLI parancsot, biztosítva, hogy alacsony forgalom vagy letiltott WP-Cron esetén se maradjon feldolgozatlan sor.

```
*/1 * * * * /usr/bin/wp --path=/home/ashley/topiku.hu/public --quiet gta6mods process-downloads
```

> **Megjegyzés:** A `--path` értéke mindig a WordPress gyökérmappájára mutasson (ahol a `wp-load.php` található), nem a `wp-content` könyvtárra, különben a WP-CLI nem találja a környezetet.

> **Miért szükséges?**
> A Redis-ben gyűlő letöltés-statisztikák így garantáltan átkerülnek az adatbázisba, még akkor is, ha a Cloudflare cache miatt ritkán érkezik valódi látogató az oldalra.

## 3. Cloudflare purge kulcsok

A verziófrissítési horgok Cloudflare API hívással érvénytelenítik a mod oldalakat és a váróterem HTML cache-t. Győződj meg róla, hogy a szerveren be van állítva a `CLOUDFLARE_ZONE_ID` és a `CLOUDFLARE_API_KEY` környezeti változó vagy wp-config.php konstans, különben a purge kérések nem fognak lefutni.

> **Miért szükséges?**
> A váróterem HTML a Cloudflare edge cache-ből szolgálódik ki. Ha nem törlődik a cache egy új verzió feltöltésekor, a látogatók régi állapotot kapnának.
