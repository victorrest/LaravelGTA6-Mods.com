# Komment permalinke-k végleges viselkedése

## Összefoglaló
- A permalink építőből eltávolítottam a `comment-page-N/` szegmens automatikus hozzáadását, mert a sablon mindenhol végtelenített "load more" kommentbetöltést használ.
- A normalizáló függvény alapértelmezésben kiszűri a korábbról megmaradt lapozási darabokat (`/comments/comment-page-N/`), így a "Copy link" és az időbélyegek mindig a `/comments/#comment-ID` formát adják vissza.
- Két új filter biztosít lehetőséget arra, hogy szükség esetén visszakapcsoljuk a lapozási jelöléseket egyedi telepítéseken, anélkül hogy a főoldali sablon viselkedése megváltozna.

## Részletek
1. **Permalink építés** – A `gta6mods_comment_permalink_include_pagination` filter alapértelmezésben `false`, ezért a `gta6mods_get_comment_permalink()` nem fűz `comment-page-N/` szegmenst az URL végére. Ha egy telepítésen mégis szükség van erre, elég a filtert `true`-ra állítani.
2. **Normalizálás** – A `gta6mods_allow_comment_pagination_segments` filterrel szabályozható, hogy a `gta6mods_normalize_comment_link()` megtartsa-e az útvonalban a lapozási részeket. Alapesetben eltávolítja őket, így nem fordul elő dupla `comments` rész sem.
3. **Visszamenőleges kompatibilitás** – A query string és a fragment változatlanul megmarad, tehát a moderációs paraméterek (pl. `?unapproved=`) továbbra is működnek, és az e-mail értesítésekből érkező linkek sem törnek el.

