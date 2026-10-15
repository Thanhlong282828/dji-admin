#!/usr/bin/env sh
set -eu

database_directory="${CLAMAV_DATABASE_DIRECTORY:-/var/lib/clamav}"
timeout_seconds="${CLAMAV_FRESHCLAM_TIMEOUT_SECONDS:-300}"
freshclam_config="${CLAMAV_FRESHCLAM_CONFIG:-/etc/clamav/freshclam.conf}"

case "$timeout_seconds" in
    ''|*[!0-9]*)
        echo 'CLAMAV_FRESHCLAM_TIMEOUT_SECONDS must be an integer.' >&2
        exit 64
        ;;
esac

if [ "$timeout_seconds" -lt 30 ] || [ "$timeout_seconds" -gt 900 ]; then
    echo 'CLAMAV_FRESHCLAM_TIMEOUT_SECONDS must be between 30 and 900.' >&2
    exit 64
fi

command -v freshclam >/dev/null 2>&1 || {
    echo 'freshclam is required to initialize official signatures.' >&2
    exit 69
}
command -v clamscan >/dev/null 2>&1 || {
    echo 'clamscan is required to verify downloaded signatures.' >&2
    exit 69
}
command -v timeout >/dev/null 2>&1 || {
    echo 'timeout is required to bound signature initialization.' >&2
    exit 69
}
[ -r "$freshclam_config" ] || {
    echo 'A readable freshclam configuration is required.' >&2
    exit 66
}

umask 027
mkdir -p "$database_directory"
runtime_config="$(mktemp)"
trap 'rm -f "$runtime_config"' EXIT HUP INT TERM

# The immutable application image intentionally has no clamd daemon. Keep all
# mirror/proxy settings from the packaged configuration, but do not ask
# freshclam to notify a daemon that is not part of this low-memory topology.
sed '/^[[:space:]]*NotifyClamd[[:space:]]/d' "$freshclam_config" > "$runtime_config"

timeout "$timeout_seconds" freshclam \
    --config-file="$runtime_config" \
    --stdout \
    --no-warnings \
    --datadir="$database_directory"

signature_file="$(find "$database_directory" -maxdepth 1 -type f \( -name '*.cvd' -o -name '*.cld' \) -size +0c -print -quit)"
if [ -z "$signature_file" ]; then
    echo 'freshclam completed without a non-empty official signature database.' >&2
    exit 65
fi

timeout 30 clamscan \
    --database="$database_directory" \
    --no-summary \
    --version >/dev/null

find "$database_directory" -maxdepth 1 -type f \( -name '*.cvd' -o -name '*.cld' \) -exec chmod 0644 {} +

echo 'ClamAV signature initialization: PASS'
