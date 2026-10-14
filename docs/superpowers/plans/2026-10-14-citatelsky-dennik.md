# Čitateľský denník B – implementačný plán

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Z prototypu spraviť funkčnú aplikáciu podľa DNR v1.1 (všetky požiadavky Must S.01–S.60).

**Architecture:** Laravel 13 JSON API (session auth, same-origin) + React/TS SPA zbuildovaná do `public/`,
MariaDB, plánovač ako samostatný kontajner, Mailpit na e-maily. Doménové prechody stavov knihy na jednom
mieste (`App\Services\BookStateService`).

**Tech Stack:** PHP 8.4, Laravel 13.17, MariaDB 11, pragmarx/google2fa, React 19, TypeScript, Vite 8, react-router 7.

**Spec:** `docs/superpowers/specs/2026-10-14-citatelsky-dennik-design.md` (API kontrakt, dátový model, pravidlá).
Funkčné zadanie: DNR (zhrnuté v spec; čísla S.xx).

## Global Constraints

- Rozhranie a všetky chybové hlášky len po slovensky (E.06, SW.21).
- Časové pásmo `Europe/Bratislava` (E.07).
- Žáner len z pevného zoznamu 11 hodnôt v spec (S.08).
- Stavy `unread|reading|read|to_read`; všetky prechody len cez `BookStateService`.
- Každý dopyt obmedzený na prihláseného používateľa; cudzí zdroj = 404 (E.09).
- Žiadne heslá v gite: `.env` je ignorovaný, `.env.example` má zástupné hodnoty.
- Bez UI knižníc na frontende. Bez Won't funkcií (SW.01–SW.21).
- Testy backendu: `docker run --rm -v "$PWD":/app -w /app composer:2 php artisan test` (SQLite in-memory podľa `phpunit.xml`).
  Composer: `docker run --rm -v "$PWD":/app -w /app composer:2 composer …`. Lokálne PHP nie je.
- Commit po každej úlohe, správa po anglicky alebo slovensky, končí riadkom
  `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Nepushovať.

## Review Focus

1. Prístup k cudzej knihe/poznámke/zoznamu cez ID → musí byť 404 (test v úlohách 2 a 3).
2. Presun v zozname na prečítanie na pozíciu mimo 1..n → 422, a po ľubovoľnej sekvencii zmien stavov sú priority súvislé 1..n (test v úlohe 2).
3. Open Library timeout / 5xx / prázdny výsledok → 503/404 s hláškou, nie 500 (test v úlohe 3).
4. Zmena `pages` pod `pages_read`, záporné / necelé `pages_read` → 422 (test v úlohe 2).
5. Pripomienka v minulosti alebo pre knihu mimo `reading` → 422; po zmene stavu z `reading` pripomienky zmiznú (test v úlohe 3).

---

### Task 1: Základ backendu – infra, schéma, modely, auth, 2FA

**Files:**
- Modify: `docker-compose.yml` (služby `scheduler`, `mailpit`), `Dockerfile` (composer install aj s dev závislosťami nie – nechať `--no-dev`; pridať `restart`), `.env`, `.env.example` (MAIL_* na mailpit, APP_TIMEZONE, APP_LOCALE=sk, DEMO_PASSWORD), `config/app.php` (timezone z env), `bootstrap/app.php` (api routes s middleware `web` + prefix `api`; JSON 401 pre api), `routes/web.php` (fallback na `public/index.html`)
- Replace migrations: `database/migrations/0001_01_01_000000_create_users_table.php` (bez `name`, + 2FA stĺpce), `2026_10_14_000000_create_books_table.php` → nové migrácie pre libraries, books, notes, reminders, recommendation_lists + 2 pivoty
- Create models: `app/Models/{User,Library,Book,Note,Reminder,RecommendationList}.php`, `app/Enums/{BookStatus,Genre}.php`
- Create: `app/Http/Controllers/Api/AuthController.php`, `TwoFactorController.php`, `AccountController.php`, `app/Http/Resources/UserResource.php`
- Test: `tests/Feature/AuthTest.php`, `tests/Feature/TwoFactorTest.php`
- Remove: `tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php`, `database/factories/UserFactory.php` upraviť (bez `name`); pridať factories Library, Book

**Interfaces – Produces:**
- `App\Enums\BookStatus` (string enum: Unread='unread', Reading='reading', Read='read', ToRead='to_read') s `label(): string` (SK)
- `App\Enums\Genre` (string enum, 11 hodnôt presne zo spec)
- Model `Book`: casts `status`→BookStatus, `finished_at`,`target_date`→date; relácie `library()`, `user()`, `notes()`, `reminders()`, `lists()`; accessor `percent`
- Model `User`: `libraries()`, `books()`, `recommendationLists()`, `sharedLists()` (belongsToMany cez `recommendation_list_recipient`); `two_factor_secret` encrypted cast; `hasTwoFactor(): bool`
- Routes v `routes/api.php` pod middleware `auth` (okrem register/login/me/login-2fa)
- Pri registrácii vznikne knižnica „Moja knižnica“

- [ ] Step 1: `composer require pragmarx/google2fa` cez composer:2 image; `composer install` (vendor lokálne pre testy).
- [ ] Step 2: Napísať `AuthTest`: registrácia (201, vznikne „Moja knižnica“), heslo < 8 → 422 (S.02), duplicitný e-mail → 422 (S.03), login OK / zlé heslo 422, `/api/me` 401 bez prihlásenia, logout, zrušenie účtu so zlým heslom 422 a so správnym zmaže používateľa aj knižnice a knihy (S.06).
- [ ] Step 3: Napísať `TwoFactorTest`: setup → confirm platným kódom (google2fa `getCurrentOtp`) → login vráti `two_factor_required`, `/api/login/two-factor` so zlým kódom 422, s dobrým `User`; vypnutie heslom.
- [ ] Step 4: Spustiť testy – musia padnúť.
- [ ] Step 5: Implementovať infra, migrácie, modely, enumy, kontroléry, routes.
- [ ] Step 6: Testy zelené. `docker compose down -v && docker compose up --build -d` a `curl -s localhost:8000/api/me` → 401 JSON.
- [ ] Step 7: Commit.

### Task 2: Knižnice, knihy, stavy, zoznam na prečítanie, poznámky

**Files:**
- Create: `app/Services/BookStateService.php`, `app/Http/Controllers/Api/{LibraryController,BookController,ReadingListController,NoteController}.php`, `app/Http/Resources/{LibraryResource,BookResource,BookDetailResource,NoteResource,ReminderResource}.php`, `app/Http/Requests/{StoreBookRequest,UpdateBookRequest}.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/{LibraryTest,BookTest,BookStateTest,ReadingListTest,NoteTest}.php`, `tests/Unit/BookStateServiceTest.php` (voliteľne)

**Interfaces – Consumes:** modely a enumy z Task 1.
**Produces:**
- `BookStateService::changeStatus(Book $book, BookStatus $to): Book`
- `BookStateService::setPagesRead(Book $book, int $pages): Book`
- `BookStateService::moveInReadingList(User $user, Book $book, int $position): void`
- `BookStateService::renumberReadingList(int $userId): void`
- (všetko v DB transakcii; odchod z `reading` maže pripomienky)

- [ ] Step 1: Testy podľa spec „Doménové pravidlá“ a DNR: S.07, S.08 (neplatný žáner 422), S.09, S.17 (409 a s `confirm_duplicate` 201; ISBN aj názov+autor case-insensitive), S.18 (`pages` < `pages_read` 422), S.19 (zmaže poznámky a pripomienky), S.20–S.23 (unikátny názov, 1–100 znakov, posledná knižnica 422, zmazanie knižnice zmaže knihy), S.24–S.27, S.29–S.32 (presun na 1 a na n, pozícia 0 a n+1 → 422, priority súvislé po odchode zo zoznamu), S.33–S.36 (5001 znakov 422, cudzia poznámka 404), S.37–S.40 (percent floor, n>pages 422, auto `reading`, auto `read` + finished_at), S.41 (včerajší dátum 422, null zruší), E.08 (limit – otestovať s dočasne zníženou konštantou `Book::MAX_PER_USER` cez config `app.max_books_per_user`), E.09 (cudzia kniha/knižnica 404).
- [ ] Step 2: Testy padajú.
- [ ] Step 3: Implementácia.
- [ ] Step 4: Testy zelené (všetky, aj Task 1).
- [ ] Step 5: Commit.

### Task 3: Pripomienky a e-maily, štatistiky, katalóg, odporúčania, zoznamy odporúčaní, demo dáta

**Files:**
- Create: `app/Http/Controllers/Api/{ReminderController,StatsController,CatalogController,RecommendationController,RecommendationListController,SharedListController}.php`, `app/Services/OpenLibraryClient.php`, `app/Services/RecommendationService.php`, `app/Mail/{ReadingReminderMail,TargetDateMail}.php` + markdown/blade views v `resources/views/mail/`, `app/Console/Commands/{SendReminders,NotifyTargetDates}.php`, `database/seeders/DemoSeeder.php`
- Modify: `routes/api.php`, `routes/console.php` (schedule), `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/{ReminderTest,StatsTest,CatalogTest,RecommendationTest,RecommendationListTest}.php`

**Interfaces – Produces:**
- `OpenLibraryClient::search(?string $title, ?string $isbn): array` (CatalogResult[]; hádže `App\Exceptions\CatalogUnavailable` pri timeoute/chybe)
- `OpenLibraryClient::bySubject(string $subject, int $limit=50): array`, `byAuthor(string $author, int $limit=50): array` (RecBook[])
- Artisan `reminders:send` (každú minútu), `targets:notify` (denne 08:00 Europe/Bratislava)

- [ ] Step 1: Testy: pripomienka len pre `reading` a v budúcnosti (S.57), `reminders:send` pošle mail a zmaže pripomienku, budúcu nechá (S.58, S.59, `Mail::fake`, `travelTo`), zmena stavu z `reading` zmaže pripomienky (S.28), `targets:notify` pošle len pre target_date = dnes+3 a stav ≠ read (S.42); štatistiky S.43–S.45 (všetky žánre v `by_genre`); katalóg: title, ISBN s pomlčkami, ISBN 9 číslic → 422 (S.12), max 10 výsledkov (S.13), `Http::fake` s `ConnectionException` → 503, prázdne `docs` → 404 (S.16); odporúčania: bez prečítanej → `available:false` (S.49), výber žánru pri zhode podľa poslednej prečítanej (S.46), autor (S.47), vylúčenie vlastných kníh a max 10 (S.48); zoznamy: S.50–S.56 vrátane neexistujúceho e-mailu 422, `sharing_level` sa prepína, príjemca vidí iba 4 polia, nepríjemca 404, cudzia kniha do zoznamu 404/422.
- [ ] Step 2: Testy padajú.
- [ ] Step 3: Implementácia. Plánovač v `routes/console.php`. Demo seeder podľa spec (len ak `DEMO_PASSWORD` nie je prázdne; idempotentný).
- [ ] Step 4: Všetky testy zelené. `docker compose up --build -d`, `curl` na `/api/catalog/search?title=dune` s prihlásenou session (cookie jar) vráti výsledky.
- [ ] Step 5: Commit.

### Task 4: Frontend (paralelne s Task 1–3, podľa API kontraktu)

**Files:**
- Replace `frontend/` obsah: `package.json` (+ `typescript`, `react-router`, `@types/react`, `@types/react-dom`, `qrcode` pre 2FA QR, voliteľne `@fontsource/*` fonty), `tsconfig.json`, `vite.config.ts`, `index.html`, `src/main.tsx`, `src/api.ts` (fetch wrapper: credentials same-origin, `Accept: application/json`, `X-XSRF-TOKEN` z cookie, 401 → presmerovanie na /prihlasenie, chyby → `ApiError {status, message, errors}`), `src/types.ts`, `src/auth.tsx` (kontext používateľa), `src/labels.ts` (SK popisy stavov, zoznam žánrov), `src/styles.css`, `src/components/*` (Layout s navigáciou, ConfirmDialog, ProgressBar, StatusBadge, BookForm, EmptyState), `src/pages/*` (Login, Register, Libraries, BookDetail, AddBook, ReadingList, Stats, Recommendations, Lists, ListDetail, SharedLists, SharedListDetail, Account)
- Vite: `build.outDir: '../public'`, `emptyOutDir: false`, dev proxy `/api` → `http://localhost:8000`

- [ ] Step 1: Skill `frontend-design` – zvoliť vizuálny smer „čitateľský denník“ (teplý papier, serif nadpisy, atramentový akcent), definovať CSS premenné.
- [ ] Step 2: API klient + typy presne podľa kontraktu v spec.
- [ ] Step 3: Obrazovky podľa spec; všetky deštruktívne akcie cez ConfirmDialog; chyby z 422 pri poliach; katalóg: hľadanie názov/ISBN, chyba ISBN (validácia aj na klientovi 10/13 číslic), pri 404/503 hláška s tlačidlom „Pridať ručne“, výber výsledku predvyplní formulár, žáner povinný; duplicita 409 → dialóg „Naozaj pridať?“ → opakovať s `confirm_duplicate`.
- [ ] Step 4: `npm run build` bez chýb (vrátane `tsc -b`).
- [ ] Step 5: Commit.

### Task 5: Integrácia, review, overenie

- [ ] Step 1: `docker compose down -v && docker compose up --build -d`, počkať na health.
- [ ] Step 2: Reviewer subagent nad celým diffom (spec + DNR), opraviť nálezy.
- [ ] Step 3: Prejsť v prehliadači: registrácia, pridanie z katalógu aj ručne, duplicita, stavy, strany, zoznam na prečítanie, poznámky, pripomienka (Mailpit), štatistiky, odporúčania, zoznam zdieľaný s druhým účtom, zrušenie účtu. Mobil 375 px.
- [ ] Step 4: README (spustenie, adresy, demo účty bez hesla – heslo je v `.env`), commit.
