# Használati útmutató

Ez egy újrahasználható weboldal-alap. Helyben két cím van:

- felület: http://localhost:4200/
- API: http://localhost:8000/api

A felület magyar. A főoldal a közzétett cikkeket belépés nélkül mutatja. A szerkesztő, a média és az admin csak belépés után érhető el.

## Indítás

Két terminál kell.

```text
cd backend
php artisan serve --host=127.0.0.1 --port=8000
```

```text
cd frontend
npx ng serve --host localhost --port 4200
```

Az első admin, ha lefutott a seeder: `admin@example.com` / `password`. Éles használat előtt cseréld le.

## Belépés és fiók

- **Regisztráció** és **Belépés** a nyitó űrlapokon van.
- **Elfelejtett jelszó** emailt kér. Helyben a levél nem a postaládába megy, hanem a `backend/storage/logs/laravel.log` fájlba.
- **Profil**: név, email, jelszó. Email csere után a cím újra megerősítésre vár.
- A felső sávban, amíg az email nincs megerősítve, van egy sáv a megerősítő újraküldésére. A link ugyanabba a logfájlba kerül.

Gmailre küldéshez a `backend/.env` tetején lévő MAIL sorokat a fájlban lévő Gmail-megjegyzés szerint kell cserélni. A jelszó Google alkalmazásjelszó, nem a fiók belépési jelszava. Utána a Laravel szervert újra kell indítani.

## Főoldal

A nyitó cím, http://localhost:4200/ , a közzétett cikkeket listázza. Egy cikk a `/hir/` útvonalon nyílik. A vázlat ide nem kerül. Belépés után az alkalmazás a `/app` címen van.

## Hírek

A **Hírek** menüpont a lista. **Új bejegyzés** nyitja a szerkesztőt.

- **Cím** kötelező. Ebből készül a cím a linkben, és később nem változik.
- **Bevezető** nem kötelező. Ha üres, a szöveg elejéből készül, és csak a hírlistán látszik. Ha te írod meg, a cikk tetején is megjelenik.
- **Állapot**: vázlat vagy közzétéve. A vázlatot a szerző és az admin látja.
- **Szöveg**: félkövér, dőlt, aláhúzás, címsor, alcím, lista, számozás, link, kép.
- Képre kattintva áll a méret (25, 50, 75, 100%) és az igazítás (balra, középre, jobbra). A kép a saját során áll, az utána írt szöveg a sor elején kezdődik.

Saját bejegyzést a szerző és az admin szerkeszthet vagy törölhet.

## Média

A **Média** könyvtár a feltöltött fájlok. A gyűjtemény szabja meg a típust, a méretet és a darabszámot. A határ a képernyőn is látszik, a szerver dönti el véglegesen.

A hírbe beszúrt kép az `images` gyűjteménybe kerül.

## Admin

Az **Admin** menüpont csak adminnak jelenik meg.

- **Oldal neve**: a fejlécben, a belépő oldalakon és a böngésző címsorában.
- **Cím, telefonszám, e-mail**: a kapcsolat oldalon jelenik meg. Üres mező nem látszik.
- **Felhasználók**: név, email, meg van-e erősítve a cím, szerep (`admin` vagy `felhasználó`). Az utolsó admin szerepét nem lehet elvenni.
- Innen lehet a hírekre és a médiára lépni.

## Impresszum, adatvédelem, kapcsolat

Ezek belépés nélkül nyílnak, a láblécből is:

- http://localhost:4200/impresszum
- http://localhost:4200/adatvedelem
- http://localhost:4200/kapcsolat

Az impresszum és az adatvédelem HTML-je a komponensfájlban van: `frontend/src/app/pages/impresszum.component.ts` és `adatvedelem.component.ts`. A kapcsolat oldalon a cím, a telefonszám és az e-mail az admin **Oldal** űrlapjáról jön. A kapcsolat űrlap neve, emailje és üzenete az **Üzenetek** listában jelenik meg. Email nem megy ki róla.

## Lábléc

A lábléc nem az admin űrlapon szerkeszthető. A HTML a `frontend/src/app/layout/footer.component.ts` fájlban van. Új oldalra másolva ezt a fájlt kell átírni.
