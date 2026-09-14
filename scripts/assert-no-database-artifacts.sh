#!/usr/bin/env bash

set -euo pipefail

repository_root=$(git rev-parse --show-toplevel)
cd "$repository_root"

database_artifact_pattern='(^|/)(backups?|dumps?)(/|$)|\.(sql|dump|backup|bak)(\.(gz|bz2|xz|zst|zip))?$'
failure=0

current_paths=$(git ls-files | grep -E "$database_artifact_pattern" || true)
if [[ -n "$current_paths" ]]; then
    echo 'Database export or backup artifacts are tracked in the current tree:' >&2
    printf '%s\n' "$current_paths" >&2
    failure=1
fi

historical_paths=$(
    git rev-list --objects --branches --remotes --tags \
        | awk 'NF > 1 { $1 = ""; sub(/^ /, ""); print }' \
        | grep -E "$database_artifact_pattern" \
        | sort -u \
        || true
)
if [[ -n "$historical_paths" ]]; then
    echo 'Database export or backup artifacts remain reachable from publication refs:' >&2
    printf '%s\n' "$historical_paths" >&2
    failure=1
fi

if [[ "$failure" -ne 0 ]]; then
    echo 'Store recovery archives outside Git in encrypted access-controlled storage.' >&2
    exit 1
fi

echo 'No database export or backup artifacts are reachable from branches, remote refs or tags.'
