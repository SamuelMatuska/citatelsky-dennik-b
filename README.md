# Čitateľský denník B

Zjednodušená verzia aplikácie podľa DNR: **Laravel 13 + React (Vite) + MariaDB 11**, všetko v Dockeri.

## Spustenie

Treba len [Docker Desktop](https://www.docker.com/products/docker-desktop/). PHP, Composer ani Node netreba.

```bash
git clone https://github.com/SamuelMatuska/citatelsky-dennik-b.git
cd citatelsky-dennik-b
docker compose up --build
```

Potom otvor http://localhost:8000. Prvý build trvá pár minút. Migrácie a 5 ukážkových kníh sa nahrajú samy.
Súbor `.env` netreba, vývojové hodnoty sú v `docker-compose.yml`.

- Zastavenie: `Ctrl+C`, alebo `docker compose down`
- Vymazanie databázy (návrat k ukážkovým knihám): `docker compose down -v`
- Po zmene kódu znova `docker compose up --build`

## Čo aplikácia vie

- zoznam kníh, pridanie ručne alebo vyhľadaním v Open Library (podľa názvu alebo ISBN), úprava, zmazanie
- stav knihy (neprečítaná, čítam, prečítaná, na zozname na prečítanie)
- prečítané strany a percento dokončenia, stav sa podľa strán prepne sám
- polica s prehľadom kníh, svetlý aj tmavý režim, funguje aj na mobile

## Štruktúra

| Kde | Čo |
|---|---|
| `routes/api.php` | API: `GET/POST /api/books`, `PUT/DELETE /api/books/{id}`, `GET /api/catalog/search` |
| `app/Http/Controllers/Api/` | `BookController` (knihy), `CatalogController` (Open Library) |
| `app/Models/Book.php` | žánre, stavy a pravidlá pre stav a prečítané strany |
| `database/` | migrácia tabuľky `books` a ukážkové knihy |
| `frontend/` | React aplikácia; pri builde v Dockeri sa skompiluje do `public/` |
| `tests/Feature/` | testy API |

## Testy

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 sh -c "composer install -q && php artisan test"
```
