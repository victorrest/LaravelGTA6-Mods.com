Tailwind CSS 4.0 lokális generálása WordPress sablonhoz - Ubuntu Nginx szerveren
Előfeltételek ellenőrzése
1. Node.js és npm telepítése/ellenőrzése
bashnode -v
npm -v
Ha nincs telepítve:
bashcurl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs
Megjegyzés: Ha repository hibákat kapsz, futtasd ezt:
bashsudo apt update --allow-releaseinfo-change

Telepítés lépésről lépésre
2. Navigálj a WordPress téma könyvtárába
bashcd /útvonal/a/témádhoz
Példa:
bashcd /home/ashley/topiku.hu/public/wp-content/themes/GTA6ModsWP-codex-integrate-video-thumbnails-into-gallery-l8f2b8/
3. npm projekt inicializálása
bashnpm init -y
Ez létrehoz egy package.json fájlt.
4. Tailwind CSS 4.0 és PostCSS telepítése
bashnpm install -D @tailwindcss/postcss postcss postcss-cli tailwindcss
5. PostCSS konfigurációs fájl létrehozása
bashnano postcss.config.js
Írd bele ezt:
javascriptmodule.exports = {
  plugins: {
    '@tailwindcss/postcss': {},
  }
}
Mentés: Ctrl + X, Y, Enter
6. Könyvtárstruktúra létrehozása
bashmkdir -p assets/css/src
7. Tailwind CSS input fájl létrehozása
bashnano assets/css/src/main.css
FONTOS: Tailwind 4.0-hoz használd ezt a formátumot:
css@import "tailwindcss";
NE használd a régi 3.x formátumot:
css@tailwind base;
@tailwind components;
@tailwind utilities;
Mentés: Ctrl + X, Y, Enter
8. package.json scripts beállítása
bashnano package.json
Add hozzá vagy módosítsd a scripts részt:
json"scripts": {
  "build": "postcss ./assets/css/src/main.css -o ./assets/css/theme.min.css --minify",
  "watch": "postcss ./assets/css/src/main.css -o ./assets/css/theme.min.css --watch"
}
Teljes példa package.json:
json{
  "name": "your-theme-name",
  "version": "1.0.0",
  "description": "",
  "main": "index.js",
  "scripts": {
    "build": "postcss ./assets/css/src/main.css -o ./assets/css/theme.min.css --minify",
    "watch": "postcss ./assets/css/src/main.css -o ./assets/css/theme.min.css --watch"
  },
  "keywords": [],
  "author": "",
  "license": "ISC",
  "devDependencies": {
    "@tailwindcss/postcss": "^4.0.0",
    "postcss": "^8.4.0",
    "postcss-cli": "^10.0.0",
    "tailwindcss": "^4.0.0"
  }
}
Mentés: Ctrl + X, Y, Enter
9. CSS generálása
Production build (minifikált):
bashnpm run build
Fejlesztési mód (automatikus újragenerálás):
bashnpm run watch
10. Ellenőrzés
bashls -lh assets/css/theme.min.css
Ha látod a fájlt és van mérete (pl. 50K+), sikeres volt! ✅

WordPress integráció
11. functions.php módosítása
bashnano functions.php
Add hozzá ezt a kódot (vagy módosítsd a meglévő style enqueue függvényt):
phpfunction theme_enqueue_styles() {
    // Távolítsd el vagy kommenteld ki a Tailwind CDN-t!
    
    // Lokális Tailwind CSS betöltése
    wp_enqueue_style(
        'tailwind-css', 
        get_template_directory_uri() . '/assets/css/theme.min.css', 
        array(), 
        '1.0.0', 
        'all'
    );
}
add_action('wp_enqueue_scripts', 'theme_enqueue_styles');
12. CDN link eltávolítása
Nyisd meg a header.php fájlt vagy ahol a Tailwind CDN-t betöltötted:
bashnano header.php
Töröld ki vagy kommenteld ki a sort, ami így néz ki:
html<link href="https://cdn.tailwindcss.com" rel="stylesheet">
vagy hasonló CDN linket.
13. WordPress cache ürítése (ha van cache plugin)
Ürítsd ki a cache-t, hogy a változások érvényesüljenek.
14. Tesztelés

Nyisd meg a WordPress oldaladat böngészőben
Nyomd meg az F12-t (Developer Tools)
Menj a Network fülre
Frissítsd az oldalt (Ctrl + F5)
Keresd meg a theme.min.css fájlt a betöltött fájlok között
Ellenőrizd, hogy a Tailwind osztályok (pl. flex, bg-blue-500) működnek-e


Hasznos parancsok
CSS újragenerálása minden változtatás után:
bashnpm run build
Automatikus újragenerálás fejlesztés közben:
bashnpm run watch
(Megállítás: Ctrl + C)
Tailwind verzió ellenőrzése:
bashnpm list tailwindcss

Tailwind 4.0 testreszabása (opcionális)
A main.css fájlban tudsz testreszabni:
css@import "tailwindcss";

@theme {
  --font-display: "Your Font", sans-serif;
  --color-brand: #ff6b6b;
  --breakpoint-3xl: 1920px;
}

/* Saját CSS-eid */
.custom-class {
  /* ... */
}

.gitignore (opcionális, ha Git-et használsz)
bashnano .gitignore
```
```
node_modules/
assets/css/theme.min.css
package-lock.json

Főbb különbségek Tailwind 3.x és 4.0 között
Tailwind 3.xTailwind 4.0@tailwind base;<br>@tailwind components;<br>@tailwind utilities;@import "tailwindcss";tailwind.config.js (JavaScript)@theme { } a CSS-bennpx tailwindcss CLIPostCSS plugin (@tailwindcss/postcss)content: [] konfiguráció szükségesAutomatikus content detection

Troubleshooting
Ha nem generálódik a CSS:

Ellenőrizd, hogy a main.css tartalma @import "tailwindcss";
Ellenőrizd a postcss.config.js fájlt
Futtasd újra: npm install
Próbáld verbose móddal: npx postcss ./assets/css/src/main.css -o ./assets/css/theme.min.css --verbose

Ha a WordPress oldalon nem látszanak a stílusok:

Ellenőrizd a böngésző Developer Tools-ban, hogy betöltődik-e a theme.min.css
Ürítsd ki a böngésző cache-t (Ctrl + Shift + Del)
Ürítsd ki a WordPress cache-t (ha van cache plugin)
Ellenőrizd a functions.php-ban az útvonalat


Kész! Sikeresen lokalizáltad a Tailwind CSS-t! 🎉