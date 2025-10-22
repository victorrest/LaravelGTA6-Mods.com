# Kategória URL-ek alap nélküli átírása

- A `term_link` filteren keresztül minden kategória permalinkből eltűnik a `category` alap, így a frontenden a `/mods/` jellegű slugs közvetlenül a gyökér alatt érhetők el.
- Új átírási szabályok épülnek a `category_rewrite_rules` szűrőn, amelyek csak egyszer, átírás-frissítéskor generálódnak, ezért nincs futásidejű teljesítménybüntetés.
- A régi `/category/...` hivatkozások 301-es átirányítással jutnak az új kanonikus URL-re, így a SEO jelzések nem vesznek el.
- Admin oldalon automatikus átírás-frissítés történik, valahányszor új kategória jön létre, szerkesztjük vagy töröljük, illetve amikor a sablont aktiváljuk.
