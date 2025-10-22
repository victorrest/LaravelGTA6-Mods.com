# Kategória URL bootstrappelési hiba javítása

A GitHub figyelmeztetés arra hívta fel a figyelmet, hogy egy friss telepítésen a `/category/` alap eltávolítása után a régi átírási szabályok maradhatnak aktívak, ha senki nem nyitja meg az admin felületet kategóriamódosítás vagy sabloncsere után. Ilyenkor az új, alap nélküli kategória linkek 404-re futottak volna a permalinks-ek kézi frissítéséig.

A problémát úgy oldottam meg, hogy az új segédfüggvény (`gta6mods_bootstrap_category_rewrite_flush_flag`) az első sablonbetöltéskor ellenőrzi, készült-e már sikeres flush, és ha nem, automatikusan beállít egy flush zászlót. Az admin inicializáláskor lefutó rutin egyszer üríti a rewrite szabályokat, majd rögzíti, hogy a bootstrap már megtörtént, így nem lesz felesleges ismétlődő flush. Ezzel garantált, hogy az új linkstruktúra az első admin oldalbetöltés után mindenhol működőképes legyen, kézi beavatkozás nélkül.
