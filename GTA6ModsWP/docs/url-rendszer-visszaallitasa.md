# URL rendszer visszaállítása a mod oldalakhoz

- A `gta6_mods_get_single_mod_tab_url()` függvény most ismét elsődlegesen a komment- és changelog-fülek szép URL-jeit (`/comments/`, `/changelogs/`) állítja elő. Csak akkor esik vissza a `?tab=` alapú megoldásra, ha a WordPress-ben nincsenek engedélyezve a csinos permalinkek.
- A `template-parts/single-default.php` sablonban a biztonsági tartalék URL-építés is a fenti végződéseket használja, így a gombok minden körülmények között a megfelelő útvonalakra mutatnak.
- A módosítások kompatibilisek maradnak a komment fület érintő extra query paraméterekkel, ezért a cachelés és a REST API végpontok is zavartalanul működnek.
- A hozzászólás permalinke-kből eltávolítottuk a `comment-page-N` szegmenseket, így a "Copy link" és az időbélyegek is mindig a `/comments/#comment-ID` mintát követik.
