# Čitateľský denník B

Zjednodušená verzia aplikácie podľa DNR: **Laravel 13 + React (Vite) + MariaDB 11** v Dockeri.

Spustenie: `docker compose up --build` → http://localhost:8000 (migrácie a ukážkové knihy sa nahrajú samy).
Pri novom klone najprv `cp .env.example .env` a nastav `APP_KEY` a `DB_PASSWORD`.

Čo vie: zoznam kníh, pridanie ručne alebo vyhľadaním v Open Library (názov / ISBN), úprava, zmazanie,
stav knihy, prečítané strany a percento dokončenia (stav sa prepne sám), polica s prehľadom.

Testy: `docker run --rm -v "$PWD":/app -w /app composer:2 php artisan test`
