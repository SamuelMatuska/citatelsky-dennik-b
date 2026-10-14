# Čitateľský denník B – technický návrh

Funkčné zadanie je DNR v1.1 (S.01 – S.60, E.01 – E.14, Won't SW.01 – SW.21). Tento dokument určuje
len technické riešenie a API kontrakt medzi backendom a frontendom. Čísla v zátvorkách odkazujú na DNR.

## Architektúra

- **Docker Compose:** `db` (mariadb:11), `app` (Laravel, `php artisan serve` na :8000), `scheduler`
  (ten istý image, `php artisan schedule:work`, `restart: unless-stopped`, S.58/S.60), `mailpit`
  (axllent/mailpit, SMTP :1025, web UI http://localhost:8025 – v dev sa e-maily dajú pozrieť).
- **Backend:** Laravel 13, API pod `/api`, JSON. Časové pásmo aplikácie `Europe/Bratislava` (E.07),
  `APP_LOCALE=sk`. Mail cez SMTP na `mailpit`. Fronty `sync`.
- **Auth:** session cookie (same-origin SPA, bez Sanctumu). `routes/api.php` je registrované so skupinou
  middleware `web` a prefixom `api` (session + CSRF). Frontend posiela hlavičku `X-XSRF-TOKEN` z cookie
  `XSRF-TOKEN` a `Accept: application/json`. Login throttle 5/min.
- **2FA:** voliteľné TOTP (balík `pragmarx/google2fa`). Passkeys sa nerobia: DNR SW.14 povoľuje len
  e-mail + heslo. TOTP je druhý faktor k heslu a v rozpore s DNR nie je.
- **Frontend:** React 19 + TypeScript + Vite + `react-router` (BrowserRouter). Bez UI knižnice, vlastné
  CSS. Build ide do Laravel `public/` (`index.html` + `assets/`). Laravel `Route::fallback` vracia
  `public/index.html` pre všetky ne-API cesty.
- **Testy:** PHPUnit feature testy (SQLite in-memory), `Http::fake()` pre Open Library, `Mail::fake()`.

## Dátový model (MariaDB)

Migrácie prototypu sa prepíšu (DB sa dá zmazať, nič ostré).

| Tabuľka | Stĺpce |
|---|---|
| `users` | id, email (unique), password, two_factor_secret (text null, encrypted cast), two_factor_confirmed_at (null), remember_token, timestamps. **Bez `name`.** |
| `libraries` | id, user_id → users cascade, name varchar(100), timestamps, unique(user_id, name) |
| `books` | id, user_id → users cascade (denormalizované = library.user_id), library_id → libraries cascade, title, author, publish_year smallint, genre varchar(40), pages int unsigned (≥1), isbn varchar(13) null, status varchar(10) default `unread`, pages_read int unsigned default 0, finished_at date null, target_date date null, priority int unsigned null, timestamps. Index (user_id, status), (user_id, isbn). |
| `notes` | id, book_id → books cascade, text text, timestamps (created_at = dátum a čas vytvorenia) |
| `reminders` | id, book_id → books cascade, remind_at datetime, timestamps, index(remind_at) |
| `recommendation_lists` | id, user_id → users cascade, name varchar(100), timestamps |
| `recommendation_list_book` | recommendation_list_id cascade, book_id cascade, unique pár |
| `recommendation_list_recipient` | recommendation_list_id cascade, user_id cascade, unique pár |

Plus štandardné Laravel tabuľky `sessions`, `cache`, `jobs`, `password_reset_tokens`.

**Hodnoty:**
- `status`: `unread` (neprečítaná), `reading` (čítam), `read` (prečítaná), `to_read` (na zozname na prečítanie).
- `genre` presne jedna z: `beletria`, `detektívka`, `fantasy`, `sci-fi`, `romantika`,
  `historická literatúra`, `biografia`, `poézia`, `odborná literatúra`, `detská literatúra`, `iný` (S.08).
- Úroveň zdieľania zoznamu sa počíta: `shared` ak má ≥1 príjemcu, inak `private` (S.54).

## Doménové pravidlá (BookStateService – jedno miesto pre všetky prechody)

- **Zmena stavu** (S.24–S.28, S.30, E.13):
  - → `unread`: pages_read = 0.
  - → `read`: pages_read = pages, finished_at = dnes.
  - → `to_read`: priority = max(priority používateľa) + 1 (S.30).
  - Odchod z `to_read`: priority = null a zvyšok zoznamu sa prečísluje 1..n (E.14, S.32).
  - Odchod z `reading`: zmazať všetky pripomienky knihy (S.28).
  - Odchod z `read`: finished_at = null (E.13).
- **Prečítané strany** (S.37–S.39, E.12): 0 ≤ n ≤ pages, inak 422. Ak n ≥ 1 a stav je `unread`/`to_read`
  → `reading`. Ak n == pages → `read` (s pravidlami vyššie). Ak je stav `read` a n < pages → `reading`.
- **Percento** = floor(pages_read / pages × 100) (S.40), vracia ho API ako `percent`.
- **Úprava knihy** (S.18): `pages` nesmie klesnúť pod `pages_read` (422). Ak je kniha `read`, pages_read = pages.
- **Cieľový dátum** (S.41): ≥ dnes alebo null.
- **Duplicita** (S.17) pri vytváraní: rovnaké ISBN, alebo pri chýbajúcom ISBN rovnaký názov + autor
  (bez ohľadu na veľkosť písmen) v ktorejkoľvek knižnici používateľa → 409, kým klient nepošle
  `confirm_duplicate: true`.
- **Limit** (E.08): 10 000 kníh na používateľa → 422 „Dosiahli ste limit 10 000 kníh.“
- **Knižnica:** používateľ má vždy aspoň jednu. Pri registrácii vznikne „Moja knižnica“. Zmazanie
  poslednej knižnice → 422.
- **Súkromie** (E.09, S.36): každý dopyt je obmedzený na `user_id` prihláseného používateľa. Cudzí
  zdroj → 404.

## Plánovač

- `reminders:send` každú minútu: pošle e-mail „Pripomienka: <názov knihy>“ za každú pripomienku
  s `remind_at <= now`, potom ju zmaže (S.58, S.59).
- `targets:notify` denne o 08:00 Europe/Bratislava: knihy s `target_date = dnes + 3` a stavom ≠ `read`
  → e-mail s názvom knihy (S.42).

## Open Library (S.10–S.16, S.46–S.48)

- Hlavička `User-Agent: CitatelskyDennikB/1.0 (školský projekt)`, timeout 5 s.
- Vyhľadávanie: `https://openlibrary.org/search.json?{title|isbn}=…&limit=10&fields=title,author_name,first_publish_year,number_of_pages_median,isbn`.
  Mapovanie: `author` = author_name[0], `publish_year` = first_publish_year, `pages` =
  number_of_pages_median, `isbn` = pri hľadaní cez ISBN zadané ISBN, inak prvé 13-ciferné z `isbn[]`
  (inak prvé, inak null).
- ISBN pred hľadaním: odstrániť medzery a pomlčky. Musí mať 10 alebo 13 číslic (ISBN-10 môže končiť `X`), inak 422 (S.12).
- Odporúčania podľa žánru: `https://openlibrary.org/subjects/{subject}.json?limit=50`, mapovanie
  žánrov: beletria→fiction, detektívka→mystery, fantasy→fantasy, sci-fi→science_fiction,
  romantika→romance, historická literatúra→historical_fiction, biografia→biography, poézia→poetry,
  odborná literatúra→science, detská literatúra→juvenile_fiction, iný→fiction.
- Odporúčania podľa autora: `search.json?author=…&limit=50&fields=title,author_name,first_publish_year`.
- Vylúčenie vlastných kníh (S.48): podľa normalizovaného názvu (lowercase, trim). Výsledok max 10.
  Cache 1 h na používateľa a kľúč (žáner/autor).
- Výber žánru/autora: najviac kníh `read`, pri zhode ten z naposledy prečítanej (finished_at desc, id desc).

## API kontrakt

Všetky odpovede sú JSON. Chyby validácie: 422 `{message, errors:{field:[msg]}}` (slovenské texty).
Ostatné chyby: `{message}`. Neprihlásený: 401.

### Objekty
```ts
User    { id, email, two_factor_enabled: boolean }
Library { id, name, books_count }
Book    { id, library_id, library_name, title, author, publish_year, genre, pages, isbn|null,
          status, pages_read, percent, finished_at|null ("YYYY-MM-DD"), target_date|null,
          priority|null, created_at }
BookDetail = Book & { notes: Note[], reminders: Reminder[] }
Note    { id, text, created_at, updated_at }          // ISO 8601 s posunom
Reminder{ id, remind_at }                              // ISO 8601 s posunom
List    { id, name, sharing_level: "private"|"shared", books_count,
          recipients: {id,email}[] }
ListDetail = List & { books: Book[] }
SharedList { id, name, owner_email, books_count }
SharedListDetail { id, name, owner_email, books: {title,author,publish_year,genre}[] }
CatalogResult { title, author|null, publish_year|null, pages|null, isbn|null }
RecBook { title, author|null, publish_year|null, cover_url|null }
```

### Auth a účet
| Metóda a cesta | Telo | Odpoveď |
|---|---|---|
| GET `/api/me` | – | `User` alebo 401 |
| POST `/api/register` | email, password (min 8), password_confirmation | 201 `User`, prihlási (S.01–S.03) |
| POST `/api/login` | email, password | `User`, alebo `{two_factor_required:true}` ak má 2FA (S.04) |
| POST `/api/login/two-factor` | code | `User` |
| POST `/api/logout` | – | 204 (S.05) |
| DELETE `/api/account` | password | 204, zmaže všetko (S.06) |
| POST `/api/two-factor/setup` | – | `{secret, otpauth_url}` |
| POST `/api/two-factor/confirm` | code | `User` |
| DELETE `/api/two-factor` | password | `User` |

Pred prvým POST frontend zavolá `GET /api/me` (nastaví cookie `XSRF-TOKEN`).

### Knižnice a knihy
| Metóda a cesta | Telo / query | Odpoveď |
|---|---|---|
| GET `/api/libraries` | – | `Library[]` |
| POST `/api/libraries` | name | 201 `Library` (S.20) |
| PATCH `/api/libraries/{id}` | name | `Library` (S.21) |
| DELETE `/api/libraries/{id}` | – | 204 (S.22) |
| GET `/api/libraries/{id}/books` | – | `Book[]` (S.23) |
| GET `/api/books` | `status?` | `Book[]` všetkých knižníc |
| POST `/api/books` | library_id, title, author, publish_year, genre, pages, isbn?, status?, confirm_duplicate? | 201 `Book`; 409 `{message, duplicate:true, existing: Book}` (S.07, S.09, S.17) |
| GET `/api/books/{id}` | – | `BookDetail` |
| PATCH `/api/books/{id}` | title, author, publish_year, genre, pages, isbn | `Book` (S.18) |
| DELETE `/api/books/{id}` | – | 204 (S.19) |
| PUT `/api/books/{id}/status` | status | `Book` |
| PUT `/api/books/{id}/progress` | pages_read | `Book` |
| PUT `/api/books/{id}/target-date` | target_date (null = zrušiť) | `Book` |
| GET `/api/reading-list` | – | `Book[]` podľa priority (S.29) |
| PUT `/api/reading-list/{bookId}` | position (1..n) | `Book[]` po prečíslovaní (S.31, S.32) |

### Poznámky a pripomienky
| POST `/api/books/{id}/notes` | text (1–5000) | 201 `Note` |
|---|---|---|
| PATCH `/api/notes/{id}` | text | `Note` |
| DELETE `/api/notes/{id}` | – | 204 |
| POST `/api/books/{id}/reminders` | remind_at (budúci dátum a čas, kniha musí byť `reading`) | 201 `Reminder` |
| DELETE `/api/reminders/{id}` | – | 204 |

### Katalóg, štatistiky, odporúčania
| GET `/api/catalog/search` | `title=` alebo `isbn=` | `{results: CatalogResult[]}`; 422 neplatné ISBN; 404 `{message}` nič nenájdené; 503 `{message}` katalóg neodpovedá |
|---|---|---|
| GET `/api/stats` | `year`, `month` | `{books_in_month, total_pages_read, by_genre: {genre: count}}` (všetky žánre aj s 0) |
| GET `/api/recommendations` | – | `{available:false, message}` alebo `{available:true, by_genre:{genre, books:RecBook[], error|null}, by_author:{author, books:RecBook[], error|null}}` |

### Zoznamy odporúčaní
| GET `/api/lists` | – | `List[]` |
|---|---|---|
| POST `/api/lists` | name (1–100) | 201 `List` |
| GET `/api/lists/{id}` | – | `ListDetail` |
| PATCH `/api/lists/{id}` | name | `List` |
| DELETE `/api/lists/{id}` | – | 204 |
| POST `/api/lists/{id}/books` | book_id (vlastná kniha) | `ListDetail` |
| DELETE `/api/lists/{id}/books/{bookId}` | – | `ListDetail` |
| POST `/api/lists/{id}/recipients` | email | `List`; 422 „Používateľ s týmto e-mailom neexistuje.“ (S.53), 422 seba |
| DELETE `/api/lists/{id}/recipients/{userId}` | – | `List` |
| GET `/api/shared-lists` | – | `SharedList[]` |
| GET `/api/shared-lists/{id}` | – | `SharedListDetail` (len na čítanie, S.56) |

## Frontend – obrazovky

Prihlásenie (+ krok 2FA), Registrácia, Knižnice (prehľad knižníc + knihy vybranej knižnice, stav,
percento), Detail knihy (úprava, stav, prečítané strany, cieľový dátum, poznámky, pripomienky,
zmazanie), Pridať knihu (záložky Katalóg / Ručne, duplicita cez potvrdzovací dialóg), Na prečítanie
(poradie, posun hore/dole a na pozíciu), Štatistiky (výber mesiaca), Odporúčania, Moje zoznamy
(vytvorenie, knihy, zdieľanie), Zdieľané so mnou, Účet (2FA, odhlásenie, zrušenie účtu). Všetky
deštruktívne akcie s potvrdením. Rozhranie len po slovensky.

Vizuál: „čitateľský denník“ – teplý papierový podklad, serifové nadpisy, atramentový akcent,
karty, čitateľná typografia, funguje na mobile aj desktope.

## Demo dáta

`DatabaseSeeder` vytvorí (idempotentne) dvoch demo používateľov, ak je v `.env` `DEMO_PASSWORD`:
`demo@citatelsky.test` (knižnice „Osobné knihy“ a „Štúdium“, ~12 kníh vo všetkých stavoch, poznámky,
zoznam odporúčaní zdieľaný s druhým) a `kamarat@citatelsky.test`.
