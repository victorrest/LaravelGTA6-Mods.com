# Author beállítások nézet javítás összegzése

- A tabkezelés most már minden `data-tab-content` elemre kiterjed, így a kitűzött mod doboz is elrejthető, amikor másik fület választ az író.
- A `switchToTab` hívások most a lokalizált tab URL-eket használják azonnali history frissítéshez, ezért a címsorban megjelenik a megfelelő `/settings/` vagy `page-N/` útvonal.
- A `loadAuthorTabContent` és a gyorselérő gombok a fő tartalmi panelt célozzák, így a görgetés és a lapozás ugyanúgy működik, mint a tabs-wrapperből nyitva.
