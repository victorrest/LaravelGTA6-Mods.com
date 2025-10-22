# Cache és Infrastruktúra Beállítási Útmutató

Ez a dokumentum lépésről lépésre bemutatja, hogyan kell a `gta6modswp` sablonhoz tartozó gyorsítótárakat, külső API hitelesítő adatokat és szerver oldali komponenseket konfigurálni. A cél az, hogy a Cloudflare edge, a Redis objektum cache és az Nginx + PHP-FPM réteg összehangoltan, minimális adatbázis terheléssel szolgálja ki a látogatókat.

> **Fontos:** A példák Ubuntu 22.04 LTS + Nginx + PHP 8.3/8.4 környezetre készültek. Ha eltérő verziót használsz, igazítsd a parancsokat a saját környezetedhez.

## 1. Cloudflare API hitelesítők konfigurálása

A sablon minden Cloudflare API hívása a `gta6_get_cloudflare_credentials()` helperen keresztül történik. Ez kizárólag a `wp-config.php`-ban definiált konstansokra támaszkodik, ezért nélkülözhetetlen ezek beállítása.

1. Lépj be a szerverre SSH-n:
   ```bash
   ssh ubuntu@your-server-ip
   ```
2. Nyisd meg a WordPress gyökérkönyvtárban található `wp-config.php` fájlt:
   ```bash
   sudo nano /var/www/topiku.hu/wp-config.php
   ```
3. Illeszd be (vagy frissítsd) az alábbi sorokat **a `/* That's all, stop editing! */` komment elé**:
   ```php
   define( 'CLOUDFLARE_ZONE_ID', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' );
   define( 'CLOUDFLARE_API_TOKEN', 'cf_api_token_with_cache_purge_permissions' );
   ```

   *A `CLOUDFLARE_API_TOKEN` egy Cloudflare API Token legyen, amely rendelkezik legalább `Zone.Cache Purge` és `Zone.Zone` olvasási jogosultsággal. Token létrehozása: Cloudflare dashboard → **My Profile** → **API Tokens** → **Create Custom Token**.*

4. Mentsd a módosításokat, majd szükség esetén töltsd újra a PHP-FPM szolgáltatást:
   ```bash
   sudo systemctl reload php8.3-fpm
   ```

> **Tipp:** Ha `.env` fájlt használsz, exportáld a változókat és hivatkozz rájuk a `wp-config.php`-ban, pl. `define( 'CLOUDFLARE_ZONE_ID', getenv( 'CLOUDFLARE_ZONE_ID' ) );`.

## 2. Cloudflare edge cache szabályok

A sablon Cache-Control fejléceket küld a fórum fő, taxonómia és egyedi thread oldalaira. Ahhoz, hogy ezeket a Cloudflare minél tovább a peremhálózatban tartsa, állítsd be az alábbiakat:

1. **Cache Rules**
   - Cloudflare Dashboard → **Websites** → Válaszd ki a zónát → **Caching** → **Cache Rules** → **Create Rule**.
   - Feltétel: `Hostname` equals `topiku.hu` **AND** `URI Path` starts with `/forum`
   - Művelet: `Cache Eligible for Cache`, `Edge TTL: Respect Existing Headers`, `Browser TTL: Respect Existing Headers`.

2. **Always Online / APO**
   - Ha használod az Automatic Platform Optimization (APO) funkciót, engedélyezd a WordPress pluginban is, hogy a Cloudflare Worker és a cache TTL-ek összhangban legyenek.

3. **Purge ellenőrzés**
   - Ha manuálisan szeretnéd tesztelni a purge-et, futtasd a WordPress környezetben:
     ```bash
     wp eval "gta6_forum_purge_cloudflare_cache(123);"
     ```
     A `123` helyére egy valódi `forum_thread` bejegyzés ID kerüljön.

## 3. Redis objektum cache telepítése és konfigurálása

A sablon a `wp_cache_*` API-n keresztül kommunikál Redis-szel. Az alábbi lépések biztosítják, hogy ez működjön.

### 3.1 Redis szerver telepítése Ubuntu-n

```bash
sudo apt update
sudo apt install redis-server -y
```

A telepítés után módosítsd a konfigurációt, hogy a Redis Unix socketet használjon és háttérben fusson:

```bash
sudo nano /etc/redis/redis.conf
```

Ajánlott változtatások:
```
port 0
unixsocket /var/run/redis/redis-server.sock
unixsocketperm 770
supervised systemd
```

Engedélyezd a PHP-FPM felhasználót (pl. `www-data`) a sockethez:

```bash
sudo usermod -aG redis www-data
sudo systemctl restart redis-server
```

### 3.2 PHP Redis extension és object cache drop-in

```bash
sudo apt install php-redis -y
sudo systemctl reload php8.3-fpm
```

A WordPress gyökérben telepítsd a Redis Object Cache plugint (Composer vagy WP-CLI segítségével):

```bash
wp plugin install redis-cache --activate
wp redis enable --force
```

Ellenőrizd, hogy a kapcsolat a socketen történik (a `wp-config.php`-ban):

```php
define( 'WP_REDIS_SCHEME', 'unix' );
define( 'WP_REDIS_PATH', '/var/run/redis/redis-server.sock' );
```

> **Megjegyzés:** A sablon `gta6_forum_get_cached_thread_listing()` függvénye a `gta6_forum_data` csoporton belül tárolja az adatokat 600 másodperces TTL-lel. Amikor `forum_thread` poszt mentés vagy törlés történik, automatikusan meghívódik a csoport flush.

## 4. FastCGI cache és Nginx beállítások

Mivel a sablon már küld `Cache-Control` fejléceket, érdemes egy Nginx szintű FastCGI cache-t is használni a Cloudflare mögött, hogy a ritkábban purge-ölt kérések is gyorsan kiszolgálódjanak.

1. Hozz létre cache mappát:
   ```bash
   sudo mkdir -p /var/cache/nginx/fastcgi
   sudo chown -R www-data:www-data /var/cache/nginx/fastcgi
   ```

2. Globális Nginx konfiguráció (`/etc/nginx/conf.d/fastcgi-cache.conf`):
   ```nginx
   fastcgi_cache_path /var/cache/nginx/fastcgi levels=1:2 keys_zone=WP_FASTCGI:100m inactive=60m max_size=10g;
   fastcgi_cache_key "$scheme$request_method$host$request_uri";
   add_header X-FastCGI-Cache $upstream_cache_status;
   ```

3. Virtuális host blokkban (`/etc/nginx/sites-available/topiku.hu`):
   ```nginx
   set $skip_cache 0;

   if ($request_method = POST) {
       set $skip_cache 1;
   }
   if ($query_string != "") {
       set $skip_cache 1;
   }
   if ($http_cookie ~* "(comment_author|wordpress_logged_in|wp-postpass_)") {
       set $skip_cache 1;
   }

   location ~ \.php$ {
       include snippets/fastcgi-php.conf;
       fastcgi_pass unix:/run/php/php8.3-fpm.sock;
       fastcgi_cache_bypass $skip_cache;
       fastcgi_no_cache $skip_cache;
       fastcgi_cache WP_FASTCGI;
       fastcgi_cache_valid 200 301 302 10m;
   }
   ```

4. Teszteld és töltsd újra az Nginx-et:
   ```bash
   sudo nginx -t && sudo systemctl reload nginx
   ```

## 5. WP-CLI és ütemezett karbantartás

A Cloudflare és Redis integrációk hatékonyságának fenntartásához javasolt ütemezett feladatok:

- **Cache purge ellenőrzés** (pl. napi egyszer):
  ```bash
  0 3 * * * /usr/bin/wp --path=/var/www/topiku.hu/public forum purge-cache --quiet
  ```
  Ha nincs külön WP-CLI parancsod, használhatod a `wp eval` megoldást.

- **Redis statisztika** (opcionális):
  ```bash
  0 * * * * /usr/bin/redis-cli info | logger -t redis-info
  ```

## 6. Gyors hibaelhárítási checklist

| Probléma | Mit ellenőrizz? | Parancs / lépés |
| --- | --- | --- |
| Cloudflare purge nem fut | `error_log` üzenetek a szerveren, `CLOUDFLARE_*` konstansok | `tail -f /var/log/nginx/error.log`
| Redis cache miss minden kérésnél | Redis socket jogosultság, plugin aktív-e | `wp redis status`
| Nginx FastCGI cache nem aktív | `X-FastCGI-Cache` fejlécek | `curl -I https://topiku.hu/forum/`
| TTL nem érvényesül a Cloudflare-ben | Cache Rule beállítások, Page Rule override | Cloudflare Dashboard → **Caching** → **Configuration**

## 7. Mintakonfiguráció összefoglaló

```php
// wp-config.php
define( 'CLOUDFLARE_ZONE_ID', 'your_zone_id_here' );
define( 'CLOUDFLARE_API_TOKEN', 'your_api_token_here' );
define( 'WP_REDIS_SCHEME', 'unix' );
define( 'WP_REDIS_PATH', '/var/run/redis/redis-server.sock' );
```

```nginx
# /etc/nginx/conf.d/fastcgi-cache.conf
fastcgi_cache_path /var/cache/nginx/fastcgi levels=1:2 keys_zone=WP_FASTCGI:100m inactive=60m max_size=10g;
fastcgi_cache_key "$scheme$request_method$host$request_uri";
add_header X-FastCGI-Cache $upstream_cache_status;
```

```bash
# WP-CLI integráció
wp redis enable --force
wp eval "gta6_mods_purge_cloudflare_cache( [ home_url( '/' ) ] );"
```

Ezekkel a beállításokkal a `gta6modswp` sablon képes a háromszintű cache-architektúra teljes kihasználására, minimalizálva az adatbázis lekérdezéseket és biztosítva, hogy a Cloudflare edge gyorsan frissüljön tartalomváltozáskor.
