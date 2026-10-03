#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
# PHP-Scoper runs in a pinned build container; the host and plugin support PHP 8.1+.
composer install --working-dir=tools/analysis --no-interaction --prefer-dist --no-plugins --no-scripts
composer install --working-dir=tools/pdf-tests --no-interaction --prefer-dist --no-plugins --no-scripts
bash tools/build-pdf.sh
# Build and analysis dependencies are development-only, but still reviewed for advisories.
for dependency_dir in tools/analysis tools/pdf-tests tools/build-tools; do
  composer audit --working-dir="$dependency_dir" --locked --no-interaction
done
