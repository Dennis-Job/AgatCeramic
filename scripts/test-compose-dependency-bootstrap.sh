#!/usr/bin/env bash

set -Eeuo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repository_root"

smoke_project="agatceramic-dependency-smoke-${GITHUB_RUN_ID:-local}-$$"
compose=(docker compose --project-name "$smoke_project" --env-file .env.example)

cleanup() {
    case "$smoke_project" in
        agatceramic-dependency-smoke-*)
            "${compose[@]}" down --volumes --remove-orphans >/dev/null 2>&1 || true
            ;;
        *)
            printf '%s\n' "Refusing to clean unexpected Compose project: $smoke_project" >&2
            ;;
    esac
}
trap cleanup EXIT

run_and_expect() {
    local expected="$1"
    shift
    local output

    if ! output="$("${compose[@]}" run --rm --no-deps "$@" 2>&1)"; then
        printf '%s\n' "$output" >&2
        return 1
    fi

    printf '%s\n' "$output"
    grep -Fq "$expected" <<< "$output" || {
        printf '%s\n' "Expected bootstrap message not found: $expected" >&2
        return 1
    }
}

printf '%s\n' 'Building dependency bootstrap images...'
"${compose[@]}" build backend queue scheduler admin client

printf '%s\n' 'Checking clean named volumes...'
"${compose[@]}" run --rm --no-deps backend cmp \
    composer.lock /opt/agatceramic/dependency-inputs/backend/composer.lock
"${compose[@]}" run --rm --no-deps admin cmp \
    package-lock.json /opt/agatceramic/dependency-inputs/admin/package-lock.json
"${compose[@]}" run --rm --no-deps client cmp \
    package-lock.json /opt/agatceramic/dependency-inputs/client/package-lock.json
run_and_expect 'composer dependencies require installation (clean volume)' \
    backend install-locked-dependencies composer /var/www/backend
run_and_expect 'composer dependencies are current' \
    queue install-locked-dependencies composer /var/www/backend
run_and_expect 'composer dependencies are current' \
    scheduler install-locked-dependencies composer /var/www/backend
run_and_expect 'npm dependencies require installation (clean volume)' \
    admin install-locked-dependencies npm /app
run_and_expect 'npm dependencies require installation (clean volume)' \
    client install-locked-dependencies npm /app

"${compose[@]}" run --rm --no-deps backend sh -ec \
    'test -s vendor/.agatceramic-lock-fingerprint && test -f vendor/autoload.php'
"${compose[@]}" run --rm --no-deps admin sh -ec \
    'test -s node_modules/.agatceramic-lock-fingerprint && test -x node_modules/.bin/vite'
"${compose[@]}" run --rm --no-deps client sh -ec \
    'test -s node_modules/.agatceramic-lock-fingerprint && test -x node_modules/.bin/nuxt'

printf '%s\n' 'Checking stale named volume recovery...'
"${compose[@]}" run --rm --no-deps backend sh -ec \
    "printf '%s\\n' stale > vendor/.agatceramic-lock-fingerprint"
"${compose[@]}" run --rm --no-deps admin sh -ec \
    "printf '%s\\n' stale > node_modules/.agatceramic-lock-fingerprint"
"${compose[@]}" run --rm --no-deps client sh -ec \
    "printf '%s\\n' stale > node_modules/.agatceramic-lock-fingerprint"

run_and_expect 'composer dependencies require installation (stale volume)' \
    queue install-locked-dependencies composer /var/www/backend
run_and_expect 'composer dependencies are current' \
    scheduler install-locked-dependencies composer /var/www/backend
run_and_expect 'npm dependencies require installation (stale volume)' \
    admin install-locked-dependencies npm /app
run_and_expect 'npm dependencies require installation (stale volume)' \
    client install-locked-dependencies npm /app

"${compose[@]}" run --rm --no-deps backend sh -ec \
    'test -s vendor/.agatceramic-lock-fingerprint && test -f vendor/autoload.php'
"${compose[@]}" run --rm --no-deps admin sh -ec \
    'test -s node_modules/.agatceramic-lock-fingerprint && test -x node_modules/.bin/vite'
"${compose[@]}" run --rm --no-deps client sh -ec \
    'test -s node_modules/.agatceramic-lock-fingerprint && test -x node_modules/.bin/nuxt'

printf '%s\n' 'Compose dependency bootstrap smoke tests passed.'
