#!/bin/sh

set -eu

log() {
    printf '%s\n' "[dependency-bootstrap] $*"
}

fail() {
    printf '%s\n' "[dependency-bootstrap] ERROR: $*" >&2
    exit 1
}

usage() {
    fail "usage: install-locked-dependencies <composer|npm> <project-directory>"
}

[ "$#" -eq 2 ] || usage

installer="$1"
project_directory="$2"

case "$installer" in
    composer)
        manifest="$project_directory/composer.json"
        lock_file="$project_directory/composer.lock"
        dependency_directory="$project_directory/vendor"
        installed_sentinel="$dependency_directory/autoload.php"
        ;;
    npm)
        manifest="$project_directory/package.json"
        lock_file="$project_directory/package-lock.json"
        dependency_directory="$project_directory/node_modules"
        installed_sentinel="$dependency_directory/.package-lock.json"
        ;;
    *)
        usage
        ;;
esac

[ -f "$manifest" ] || fail "manifest not found: $manifest"
[ -f "$lock_file" ] || fail "lock file not found: $lock_file"
[ -d "$project_directory" ] || fail "project directory not found: $project_directory"

command -v flock >/dev/null 2>&1 || fail "flock is required by the dependency bootstrap image"
command -v sha256sum >/dev/null 2>&1 || fail "sha256sum is required by the dependency bootstrap image"

mkdir -p "$dependency_directory"
marker="$dependency_directory/.agatceramic-lock-fingerprint"

fingerprint() {
    {
        printf '%s\n' 'agatceramic-dependency-bootstrap-v1'
        sha256sum "$manifest" "$lock_file"
        uname -m

        case "$installer" in
            composer)
                php -r 'echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION, PHP_EOL;'
                composer --version --no-ansi 2>/dev/null
                ;;
            npm)
                node --version
                npm --version
                ;;
        esac
    } | sha256sum | awk '{print $1}'
}

# Backend, queue, and scheduler share one vendor volume. Locking the source-controlled input
# file serializes their installers without placing a lock inside a directory that
# npm ci is allowed to remove.
exec 9<"$lock_file"
flock -x 9

expected_fingerprint="$(fingerprint)"
installed_fingerprint=""
if [ -f "$marker" ]; then
    installed_fingerprint="$(tr -d '\r\n' < "$marker")"
fi

if [ "${DEPENDENCY_BOOTSTRAP_FORCE:-0}" != "1" ] \
    && [ -f "$installed_sentinel" ] \
    && [ "$installed_fingerprint" = "$expected_fingerprint" ]; then
    log "$installer dependencies are current for $project_directory"
    exit 0
fi

if [ "${DEPENDENCY_BOOTSTRAP_FORCE:-0}" = "1" ]; then
    reason="forced recovery"
elif [ -n "$installed_fingerprint" ]; then
    reason="stale volume"
elif [ -e "$installed_sentinel" ]; then
    reason="untracked volume"
else
    reason="clean volume"
fi

log "$installer dependencies require installation ($reason)"
rm -f "$marker"

case "$installer" in
    composer)
        if ! (cd "$project_directory" && composer install --no-interaction --prefer-dist --no-progress); then
            fail "composer install failed; the volume remains stale and no application process was started"
        fi
        ;;
    npm)
        if ! (cd "$project_directory" && npm ci --no-audit --no-fund); then
            fail "npm ci failed; the volume remains stale and no application process was started"
        fi
        ;;
esac

[ -f "$installed_sentinel" ] \
    || fail "$installer completed without creating the expected dependency metadata: $installed_sentinel"

verified_fingerprint="$(fingerprint)"
[ "$verified_fingerprint" = "$expected_fingerprint" ] \
    || fail "dependency inputs changed during installation; rerun docker compose up --build"

temporary_marker="$marker.tmp.$$"
printf '%s\n' "$verified_fingerprint" > "$temporary_marker"
mv -f "$temporary_marker" "$marker"

log "$installer dependencies installed and fingerprinted for $project_directory"
