# GTA6 Nexus Laravel telepítési útmutató

Az alábbi lépések egy Ubuntu 22.04 LTS + Nginx + PHP 8.4 + MySQL környezetben készítik fel a szervert a GTA6 Nexus Laravel alkalmazás futtatására, és a `gta6-mods.com` domainhez rendelik.

> **Megjegyzés:** Minden parancs külön blokkon szerepel és rövid magyarázatot kap, hogy pontosan milyen változtatást végez a szerveren.

## 1. Rendszer előkészítése

Frissítsd az alapcsomagokat:

```bash
sudo apt update
```
- Ez a parancs frissíti az Ubuntu csomaglistáját, hogy a legújabb csomagverziók legyenek elérhetők.

```bash
sudo apt upgrade -y
```
- Telepíti az összes elérhető frissítést, így biztonságos, naprakész rendszeren dolgozunk.

Állítsd be a `ufw` tűzfalat az Nginx és az SSH engedélyezéséhez:

```bash
sudo ufw allow OpenSSH
```
- Engedélyezi az SSH (22-es port), hogy ne zárd ki magad a szerverről.

```bash
sudo ufw allow 'Nginx Full'
```
- Megnyitja a 80-as (HTTP) és 443-as (HTTPS) portot a webforgalomnak.

```bash
sudo ufw enable
```
- Aktiválja a tűzfalat a fenti szabályokkal.

## 2. Nginx telepítése

```bash
sudo apt install nginx -y
```
- Telepíti az Nginx webszervert.

Ellenőrizd a szolgáltatás státuszát:

```bash
sudo systemctl status nginx
```
- Bizonyosodj meg róla, hogy az Nginx fut. Kilépéshez nyomd meg a `q` gombot.

## 3. PHP 8.4 és kiegészítők telepítése

Add hozzá az Ondřej-féle PHP tárolót (PHP 8.4 innen érhető el):

```bash
sudo add-apt-repository ppa:ondrej/php -y
```
- Felveszi a PHP csomagokhoz szükséges PPA-t.

```bash
sudo apt update
```
- Frissíti a csomaglistát az új tárolóval.

Telepítsd a PHP 8.4 FPM-et és a szükséges bővítményeket:

```bash
sudo apt install php8.4-fpm php8.4-cli php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-gd php8.4-intl php8.4-mysql -y
```
- Ezek a modulok kellenek a Laravelhez (adatbázis, képkezelés, nemzetközi támogatás stb.).

Állítsd be a PHP konfigurációt produkciós értékekre:

```bash
sudo sed -i 's/^upload_max_filesize = .*/upload_max_filesize = 64M/' /etc/php/8.4/fpm/php.ini
```
- 64 MB-ra emeli a feltölthető fájlok maximális méretét (mod fájlok miatt hasznos).

```bash
sudo sed -i 's/^post_max_size = .*/post_max_size = 64M/' /etc/php/8.4/fpm/php.ini
```
- Igazítja a POST kérések maximális méretét a feltöltésekhez.

```bash
sudo sed -i 's/^memory_limit = .*/memory_limit = 512M/' /etc/php/8.4/fpm/php.ini
```
- Növeli a PHP memória keretet a nagyobb feldolgozásokhoz.

Indítsd újra a PHP-FPM szolgáltatást:

```bash
sudo systemctl restart php8.4-fpm
```
- Az új beállítások azonnal érvénybe lépnek.

## 4. MySQL telepítése és adatbázis előkészítés

```bash
sudo apt install mysql-server -y
```
- Telepíti a MySQL szervert.

Biztonsági beállítások lefuttatása:

```bash
sudo mysql_secure_installation
```
- Segédprogram a root jelszó és biztonsági opciók beállításához. Kövesd a képernyőn megjelenő utasításokat.

Lépj be a MySQL shellbe rootként:

```bash
sudo mysql
```
- Belépés az adatbáziskezelőbe további parancsokhoz.

Hozd létre az adatbázist és a dedikált felhasználót:

```sql
CREATE DATABASE gta6mods CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
- Létrehozza a Laravel alkalmazás adatbázisát.

```sql
CREATE USER 'gta6mods'@'localhost' IDENTIFIED BY 'erős-jelszó';
```
- Létrehoz egy felhasználót csak a helyi kapcsolatokhoz. Cseréld le az `erős-jelszó` részt.

```sql
GRANT ALL PRIVILEGES ON gta6mods.* TO 'gta6mods'@'localhost';
```
- Teljes hozzáférést ad az új felhasználónak a saját adatbázisához.

```sql
FLUSH PRIVILEGES;
```
- Érvényesíti a jogosultságokat.

```sql
EXIT;
```
- Kilép a MySQL shellből.

## 5. Alkalmazás forráskódjának beállítása

Másold a kódot a kívánt mappába (példa: `/var/www/gta6mods`):

```bash
sudo mkdir -p /var/www/gta6mods
```
- Létrehozza a webalkalmazás gyökérkönyvtárát.

```bash
sudo chown $USER:$USER /var/www/gta6mods
```
- Tulajdonjogot ad az aktuális SSH felhasználónak, hogy futtatni tudd a telepítési parancsokat.

Másold át vagy klónozd a Laravel projektet ebbe a mappába (példában feltételezzük, hogy a forráskód már a szerveren van és SCP-vel másolod át).

Telepítsd a PHP függőségeket composerrel:

```bash
cd /var/www/gta6mods
```
- Belépés a projekt mappájába.

```bash
composer install --no-dev --optimize-autoloader
```
- Telepíti a Laravelhez szükséges csomagokat, fejlesztői csomagok nélkül, optimalizálva az autoloadert.

Állítsd be a könyvtár jogosultságait a Laravel számára:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
```
- A webszolgáltatás felhasználójának tulajdonába adja a gyorsítótár és log könyvtárakat.

```bash
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
```
- Biztosítja, hogy ezek a mappák írhatók legyenek a PHP folyamat számára.

## 6. Nginx virtuális host konfigurálása

Hozz létre egy új szerver blokkot a domainhez:

```bash
sudo nano /etc/nginx/sites-available/gta6mods.conf
```
- Megnyit egy új konfigurációs fájlt a `nano` szerkesztőben.

Másold be a következő konfigurációt (illeszd a valódi útvonalakat, ha eltérnek):

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name gta6-mods.com www.gta6-mods.com;

    root /var/www/gta6mods/public;
    index index.php index.html;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.(php|phtml)$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }

    location ~* \.(jpg|jpeg|png|gif|css|js|ico|svg)$ {
        expires 30d;
        access_log off;
    }

    location ~ /\. {
        deny all;
    }

    client_max_body_size 64M;
}
```
- A `root` direktíva a Laravel `public` könyvtárára mutat, a `fastcgi_pass` pedig a PHP 8.4 FPM socketre. A `client_max_body_size` megegyezik a PHP feltöltési limitjével.

Aktiváld a konfigurációt:

```bash
sudo ln -s /etc/nginx/sites-available/gta6mods.conf /etc/nginx/sites-enabled/
```
- Szimbolikus linket hoz létre az elérhető konfigurációról az engedélyezett mappába.

Ellenőrizd, hogy nincs-e szintaktikai hiba:

```bash
sudo nginx -t
```
- Ha `syntax is ok`, akkor folytathatsz.

Indítsd újra az Nginx-et:

```bash
sudo systemctl reload nginx
```
- Betölti az új virtuális host beállításokat.

## 7. HTTPS (Let’s Encrypt) beállítás

Amennyiben a DNS már a szerverre mutat, telepítsd a Certbotot:

```bash
sudo apt install certbot python3-certbot-nginx -y
```
- Telepíti a Let’s Encrypt kliensét és az Nginx plugint.

```bash
sudo certbot --nginx -d gta6-mods.com -d www.gta6-mods.com
```
- Automatikusan kér tanúsítványt és módosítja az Nginx konfigurációt HTTPS-re.

Állíts be automatikus megújítást:

```bash
sudo systemctl status certbot.timer
```
- Ellenőrizd, hogy a megújítás időzítő aktív.

## 8. Laravel alkalmazás konfigurálása

Készíts `.env` fájlt a mappában:

```bash
cp .env.example .env
```
- Kiindulási konfiguráció a környezeti változókhoz.

Nyisd meg szerkesztésre az `.env` fájlt és töltsd ki a következő kulcsokat:

- `APP_NAME`, `APP_URL`, `DB_*` – a projekted egyedi adatai.
- `GTA6_ASSET_SOURCE` – annak a mappának az elérési útja, ahol a WordPress téma statikus fájljai találhatók.

> **Tipp:** ha a Laravel alkalmazást ugyanabba a repositoryba klónozod, ahol a régi WordPress sablon is megtalálható, akkor a példában szereplő `../` érték megfelelő, mert így a `assets/` és `img/` könyvtárak a gyökérből lesznek átmásolva.

Fuss le minden cache és kulcs generálás:

```bash
php artisan key:generate
```
- Egyedi alkalmazás kulcsot hoz létre.

```bash
php artisan storage:link
```
- Létrehozza a `public/storage` szimbolikus linket a feltöltött fájlok kiszolgálásához.

```bash
php artisan gta6:link-assets --force
```
- Átmásolja a WordPress téma ikonjait és háttérképeit a Laravel `public/` könyvtárába az előző lépésben megadott forrásból.

```bash
php artisan config:clear
```
- Törli az esetleges korábbi cache-elt konfigurációkat.

Most a böngészőből futtatható telepítőt használjuk. Nyisd meg:

```
https://gta6-mods.com/install
```
- Az űrlap bekéri az adatbázis- és adminisztrátori adatokat, majd automatikusan lefuttatja a migrációkat és a seeder-t. A sikeres telepítés után létrejön a `storage/app/installed` fájl, ami letiltja a további telepítési kísérleteket.

> **Biztonsági tipp:** Ha újra futtatnád az installert, töröld a `storage/app/installed` fájlt. Produkciós környezetben javasolt ezt a fájlt és az `/install` útvonalat eltávolítani, miután elkészült a beállítás.

## 9. Kézi parancsok (ha az installert parancssorból futtatnád)

Ha inkább CLI-ből szeretnéd végrehajtani a telepítést, használd az alábbi parancsokat a `.env` kitöltése után:

```bash
php artisan migrate --force
```
- Létrehozza az adatbázis táblákat produkciós módban.

```bash
php artisan db:seed --force
```
- Feltölti a kezdeti kategória, mod, fórum és hír adatokat.

```bash
php artisan gta6:link-assets --force
```
- Kézzel is lefuttatható az asset másolás, ha a böngészős telepítőt nem használod.

```bash
php artisan config:cache
```
- Optimalizálja a konfigurációs betöltést.

```bash
php artisan route:cache
```
- Gyorsítótárazza az útvonalakat, gyorsítva az alkalmazást.

## 10. Queue és scheduler (opcionális, de ajánlott)

Ha később háttérfeladatokat szeretnél futtatni (pl. e-mail értesítők), állíts be Supervisor szolgáltatást:

```bash
sudo apt install supervisor -y
```
- Telepíti a Supervisor daemon-t.

Hozz létre egy konfigurációt a queue workerhez:

```bash
sudo nano /etc/supervisor/conf.d/gta6mods-queue.conf
```
- Új Supervisor program definíció.

```ini
[program:gta6mods-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/gta6mods/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/gta6mods-queue.log
```
- Egy darab queue worker futtatása, logolással.

Aktiváld a konfigurációt:

```bash
sudo supervisorctl reread
sudo supervisorctl update
```
- Betölti az új Supervisor konfigurációt és elindítja a queue munkást.

A Laravel időzített feladataihoz add hozzá a cron-hoz:

```bash
* * * * * www-data php /var/www/gta6mods/artisan schedule:run >> /dev/null 2>&1
```
- Fut a Laravel scheduler minden percben a webes felhasználóval.

## 11. Domain és DNS beállítása

Állítsd be a `gta6-mods.com` és `www.gta6-mods.com` DNS rekordjait, hogy a szerver publikus IP címére mutassanak. Az A rekord frissülését követően a Let’s Encrypt és az Nginx konfiguráció már a megfelelő domain alatt szolgálja ki az alkalmazást.

## 12. Telepítés ellenőrzése

Nyisd meg a böngészőben:

```
https://gta6-mods.com
```
- Ellenőrizd, hogy betölt-e a kezdőlap és működnek-e az alap funkciók (mod lista, fórum, hírek).

Jelentkezz be az admin fiókkal, amelyet az installerben megadtál, és próbálj feltölteni egy teszt modot vagy hozzászólást.

## 13. Karbantartási tippek

- Rendszeresen futtasd a `composer update` parancsot fejlesztői környezetben, majd telepítsd élesben a tesztelt verziót.
- Figyeld a `storage/logs/laravel.log` fájlt az esetleges hibák miatt.
- Készíts adatbázis biztonsági mentéseket (pl. `mysqldump` használatával).
- Használj monitorozást (pl. UptimeRobot) a domain folyamatos elérésének ellenőrzéséhez.

Ezekkel a lépésekkel egy produkcióra kész, gyors és biztonságos Laravel alapú GTA6 Nexus platformot kapsz, amely ugyanazt a vizuális élményt és funkciókat kínálja, mint az eredeti WordPress sablon, de modern Laravel ökoszisztémával.
