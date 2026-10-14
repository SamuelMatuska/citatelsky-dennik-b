# Čitateľský denník B – prototyp

Prototyp dokazuje, že funguje stack **Laravel 13 + React (Vite) + MariaDB 11** v Dockeri.

Spustenie: `docker compose up --build` (pri novom klone najprv `cp .env.example .env` a nastav `APP_KEY` a `DB_PASSWORD`).
Pri štarte sa automaticky spustia migrácie aj seed (5 kníh).

- http://localhost:8000 – React stránka s tabuľkou kníh načítanou z API
- http://localhost:8000/api/books – JSON zo servera (Laravel → MariaDB)

React je vo `frontend/`, pri builde sa skompiluje do Laravel `public/`, takže všetko beží na jednom porte.
