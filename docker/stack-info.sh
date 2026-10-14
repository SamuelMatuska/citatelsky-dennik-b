#!/bin/sh
# Výpis bežiaceho stacku (na screenshot do DNR): kontajnery, verzie, ukážka API.
# Spustenie z koreňa projektu: sh docker/stack-info.sh

echo "== Docker kontajnery =="
docker compose ps --format "table {{.Name}}\t{{.Image}}\t{{.Status}}\t{{.Ports}}"

echo
echo "== Backend: Laravel + PHP =="
docker compose exec -T app php artisan about --only=environment,drivers | grep -E "Laravel Version|PHP Version|Environment  |Database"

echo
echo "== Databáza: MariaDB =="
docker compose exec -T db mariadb --version

echo
echo "== Frontend: React + Vite (frontend/package.json) =="
grep -E '^ *"(react|react-dom|vite)":' frontend/package.json | tr -d ' ,'

echo
echo "== API: GET http://localhost:8000/api/books =="
curl -s http://localhost:8000/api/books | docker compose exec -T app php -r '
foreach (json_decode(stream_get_contents(STDIN), true) as $b) {
    echo "  ", mb_str_pad($b["title"], 18), mb_str_pad($b["author"], 18), $b["publish_year"], "  ",
        mb_str_pad($b["status"], 9), str_pad($b["percent"], 3, " ", STR_PAD_LEFT), " %", PHP_EOL;
}'
echo
