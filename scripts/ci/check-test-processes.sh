#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
worker_path="${project_root}/tests/Support/workforce_concurrency_worker.php"
matches="$(ps -axo pid=,command= | awk -v worker="${worker_path}" 'index($2, "php") && index($0, worker) { print }')"

if [[ -n "${matches}" ]]; then
    echo "Known project test worker remains active; refusing to continue." >&2
    echo "${matches}" | awk '{ print "pid=" $1 " worker=workforce_concurrency_worker.php" }' >&2
    exit 1
fi

echo "Known project test workers: 0"
