#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$root_dir"

patterns='(-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----|AKIA[0-9A-Z]{16}|gh[pousr]_[A-Za-z0-9]{30,}|xox[baprs]-[A-Za-z0-9-]{20,})'
if git grep -IEn "$patterns" -- ':!composer.lock' ':!package-lock.json'; then
    echo 'Potential secret found in tracked files.' >&2
    exit 1
fi

echo 'Tracked-file secret scan passed.'
