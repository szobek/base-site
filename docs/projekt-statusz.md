# Projekt státusz

Utolsó frissítés: 2026-09-25. Olvasó: a következő munkamenet. A felhasználói leírás a `docs/hasznalat.md` fájlban van.

Ez egy újrafelhasználható weboldal-alap, nem egy kész ügyféloldal. Norbert kéri, hogy a felület és a validációs üzenetek magyarok legyenek, a kód angol. Commit csak külön kérésre. Webes változás után a böngészőben kell ellenőrizni a viselkedést, nem csak a kinézetet.

## Stack

- `backend/`: Laravel 13, PHP 8.3+ (a gépen 8.4), Sanctum 4 Bearer token, SQLite fájl `backend/database/database.sqlite`.
- `frontend/`: Angular 17, standalone komponensek, signal. API cím: `frontend/src/environments/environment.ts` → `http://localhost:8000/api`.
- Token: `localStorage` kulcs `auth_token` (`TokenStorage`). Az interceptor nem injektálhat `AuthService`-t, körkörös `HttpClient`.
- Alap guard a backendben `web`. Az API `auth:sanctum`. A login nem `Auth::attempt`, hanem keresés + `Hash::check`.
- JSON resource-ok `data` kulcs alatt jönnek. Kivétel: login és register `{ token, user }`.
- Dev: `php artisan serve --host=127.0.0.1 --port=8000` és `npx ng serve --host localhost --port 4200`. A `.env` változás után a Laravel folyamatot újra kell indítani.
- Tesztek: `cd backend` majd `php artisan test`. SQLite memóriában, mail `array`. Pint csak konkrét útvonalakra (`app`, `routes`, `database`, `tests`, `config`). A `bootstrap` cache-t ne formázd. A `--dirty` nem működik, ha a backend nem git repo.
- PowerShell: a `curl.exe -d "{json}"` megeszi az idézőjeleket. JSON-t UTF-8 fájlból, BOM nélkül, `--data-binary @fájl`. Törlés: `curl.exe -X DELETE`, ne `Invoke-WebRequest -Method Delete`.

## Kész

- Auth: regisztráció, login, logout, logout-all, me, profil, jelszó, elfelejtett és reset jelszó, email megerősítés. A `User` `MustVerifyEmail`. A megerősítő link aláírt `GET /api/auth/email/verify/{id}/{hash}`, siker után `{FRONTEND_URL}/verify-email?verified=1`. A reset URL-t az `AppServiceProvider` írja a felületre.
- Szerep: `App\Enums\UserRole` (`admin` / `user`), nem tölthető mass assignmenttel. `creating` hook `user`-re állítja, ha üres. Middleware alias `role`. Seed: `admin@example.com` / `password` (`DatabaseSeeder` + factory).
- Média: polimorf `media`, `HasMedia`, `MediaService`, `MediaLimits`. Lemez `MEDIA_DISK=public`, `php artisan storage:link` már megvolt. Gyűjtemények a `config/media.php`-ban: `default`, `images`, `avatar`, `documents`. Globális plafon `MEDIA_MAX_KILOBYTES` (10240) és `MEDIA_MAX_FILES` (200). Az avatar csere nem foglal új helyet. `GET /api/media/limits` a `media/{media}` elé van regisztrálva.
- Hírek: `posts`, slug a címből, a `new` slug `hir` lesz, az ütközés `-2`. A slug update-nél stabil, a route key a slug. Állapot `draft` / `published`. A body szanitált HTML (`HtmlSanitizer`): p, br, strong, b, em, i, u, h2, h3, ul, ol, li, blockquote, a, img. A `div` `p` lesz. Kép osztály: csak `size-25|50|75|100` és `align-left|center|right`. A script, a style és az on* attribútum kiesik.
- Képelrendezés: nem float. A kép blokk, a méret szélesség, az igazítás margin. A következő szöveg a sor elején kezdődik. Ugyanez a CSS a szerkesztőben és a `.post-body`-n.
- Bevezető: ha a mező üres, `PostService` a body szövegéből gyártja, blokkok között szóközzel, 180 karakter. A cikkoldalon (`post-view.component.ts` `lead()`) csak akkor látszik, ha nem a body szövegének az eleje. A listán mindig látszik.
- Admin: `GET/PATCH /api/admin/users`, szerepváltás. Az utolsó admint nem lehet lefokozni. `GET /api/settings` nyilvános, `PUT /api/admin/settings` csak az oldal nevét menti (`site_name`). A lábléc szövege kikerült a beállításból.
- Lábléc: egy komponens, `frontend/src/app/layout/footer.component.ts`. A HTML-t ott kell személyre szabni, nem adatbázisból.
- Angular útvonalak: vendég login/register/forgot; nyilvános reset és verify; a shell `authGuard` mögött. `posts/new` a `posts/:slug` előtt van. Admin: `adminGuard`.

## Szándékos döntések

- Fejlesztés és teszt: SQLite. Éles weboldal: MySQL vagy MariaDB, csak `.env`.
- Nincs Spatie permission, Filament, tevékenységnapló, grafikonos irányítópult, többnyelvűség.
- A queue `database`, de a megerősítő levél szinkron megy. Worker nem kell hozzá.
- `MAIL_MAILER=log`, amíg nincs Gmail alkalmazásjelszó. Ne kapcsold smtp-re üres jelszóval. Laravel 13-ban a titkosítás `MAIL_SCHEME`. Gmailhez `smtp.gmail.com`, port 465, `smtps` (üres scheme + 465 port magától `smtps` lesz). A feladó cím egyezzen a Gmail fiókkal. A megjegyzett minta a `backend/.env`-ben és a `.env.example`-ben van.
- CORS: `config/cors.php`, origin a `CORS_ALLOWED_ORIGINS` / alapból `http://localhost:4200`.

## Még nincs meg

Ezt Norbert a következőnek mondta, de még nem kérte a megépítését:

1. Nyilvános oldal. A `GET /api/posts` vendégnek a közzétett híreket adja, a felület viszont az egész shellt `authGuard` mögé teszi. Kell egy vendég elrendezés: kezdőlap, hírlista, egy hír. A szerkesztő, a média és az admin maradjon belépés után.
2. Két fix oldal, impresszum és adatvédelem, ugyanazzal a szanitált szerkesztővel, link a láblécből.
3. Kapcsolatfelvétel csak utána: név, email, üzenet, admin lista.

## Hol a kód

- API útvonalak: `backend/routes/api.php`
- Auth, profil, jelszó, email: `backend/app/Http/Controllers/Api/`
- Hír: `PostController`, `PostService`, `HtmlSanitizer`, `PostPolicy`
- Média: `MediaController`, `MediaService`, `MediaLimits`, `config/media.php`
- Beállítás: `SettingsController`, `SiteSettings`, `settings` tábla, csak `site_name`
- Felület: `frontend/src/app/pages/`, keret `layout/shell.component.ts`, lábléc `layout/footer.component.ts`
- Stílus: `frontend/src/styles.scss`
- Feature tesztek: `backend/tests/Feature/AuthTest.php`, `MediaTest.php`, `PostTest.php`, `AdminTest.php`

## Helyi adat

A sqlite-ban van egy seed admin (`admin@example.com`) és egy felhasználó `kunszt.norbert@gmail.com` szerepe `user`. Volt egy `teszt` című vázlat. Ezek helyi adatok, ne tekintsd a sablon részének.
