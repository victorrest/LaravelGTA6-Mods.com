# Hozzászólás összecsukás javítása

## Mi volt a hiba?
A válaszfák összecsukásáért felelős JavaScript a `.comment-replies` konténer összes leszármazott elemére rákeresett, amelyek rendelkeztek a `gta6-reply-hidden` osztállyal. Ez azt eredményezte, hogy egy külsőbb szinthez tartozó „Show more replies” gomb a belső szinteken elrejtett hozzászólásokat is egyszerre láthatóvá tette.

## Hogyan javítottam?
Bevezettem egy segédfüggvényt, amely csak a közvetlen gyermek elemek közül gyűjti ki a `gta6-reply-hidden` osztállyal rendelkezőket. A gomb létrehozását és a megjelenítésért/eltüntetésért felelős logika mostantól ezt használja, így minden gomb kizárólag a saját szintjéhez tartozó rejtett válaszokat kezeli.

## Eredmény
A „Show more replies” gombok egymástól függetlenül működnek, és nem jelennek meg váratlanul más szintekhez tartozó válaszok.
