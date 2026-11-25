#!/bin/sh
# Prepares .env for Docker. Safe to run again: it only adds what is missing and
# never changes a value you already have (changing APP_KEY would make saved
# two-step sign-in keys and integration secrets unreadable).
set -eu
cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
    cp .env.example .env
    echo "Created .env from .env.example."
fi

value_of() {
    grep -E "^$1=" .env | tail -n 1 | cut -d= -f2- || true
}

set_value() {
    if grep -qE "^$1=" .env; then
        tmp=$(mktemp)
        awk -v key="$1" -v val="$2" 'BEGIN { FS = OFS = "=" } $1 == key { print key "=" val; next } { print }' .env > "$tmp"
        cat "$tmp" > .env
        rm -f "$tmp"
    else
        printf '%s=%s\n' "$1" "$2" >> .env
    fi
}

if [ -z "$(value_of APP_KEY)" ]; then
    set_value APP_KEY "base64:$(openssl rand -base64 32)"
    echo "Added a new APP_KEY. Keep it: it encrypts secrets saved in the database."
fi

if [ -z "$(value_of DOCKER_DB_PASSWORD)" ]; then
    set_value DOCKER_DB_PASSWORD "$(openssl rand -hex 24)"
    echo "Added DOCKER_DB_PASSWORD for the MySQL container."
fi

if [ -z "$(value_of DOCKER_APP_URL)" ]; then
    set_value DOCKER_APP_URL "http://localhost:8080"
fi

echo "Ready. Start with: docker compose up -d   then open $(value_of DOCKER_APP_URL)"
