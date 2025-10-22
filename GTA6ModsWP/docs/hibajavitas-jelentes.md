# Hibajavítási jelentés

A GitHubon talált hibajegyzék pontjait végignéztem, és az alábbi problémákról derült ki, hogy továbbra is fennálltak. Ezeket a módosításokat most elvégeztem:

- **1. pont:** a függő frissítéseket listázó lekérdezés eddig a WordPress jogosultság-ellenőrzésén fennakadt, ezért anonim látogatók nem látták a jelvényeket. Közvetlen adatbázis-lekérdezéssel, majd PHP oldali lapozással oldottam meg, így mindenki számára megjelennek a függő verziók.
- **2-3. pontok:** a hozzászólások fül alapértelmezetté válik, ha a kérés kommenttel kapcsolatos paramétereket tartalmaz (pl. `cpage`, `replytocom`). Emellett megőrzöm ezeket a paramétereket a fül URL-jében, így a lapozás és a közvetlen komment linkek ismét működnek.
- **4-5. pontok:** bevezettem egy egyszeri rewrite-flusht a single mod lapfülek endpointjaihoz, illetve ha a szükséges rewrite-szabály nem elérhető, automatikusan visszaesünk a `?tab=` alapú URL-re. Így nem fordul elő 404-es hiba frissen telepített vagy frissített példányokon sem.
- **16. pont:** a Steam-profil linkek most már numeric ID esetén a `profiles/` útvonalat használják, így nem törnek meg a nem vanity ID-s fiókok.
- **23/32/33/34. pontok:** az author-oldalak rewrite szabályait leszűkítettem a tényleges tab kulcsokra, és a szerzői bázist a WordPress aktuális beállításából veszem át. Így nem nyeljük le a core feed/pagináció URL-eket, és a testreszabott author base is támogatott.
- **47. pont:** a MySQL 5.7 inkompatibilis `CREATE INDEX IF NOT EXISTS ... DESC` hívását lecseréltem egy előzetes index-ellenőrzésre, majd szabványos index létrehozásra.
- **36. pont:** a nyitóoldal transiensébe immár nyers (nem HTML-escapelt) szövegek kerülnek, így a rendereléskor nem duplázódnak az entitások.

A többi, listában szereplő problémát is átnéztem. A REST-végpontok jogosultságai, a követés/értesítés funkciók, a szolgáltatói cache-elés és a status frissítés kezelése már a jelenlegi kódbázisban rendben vannak, így további beavatkozást nem igényeltek.

*Frissítve: 2024-06-05.*
