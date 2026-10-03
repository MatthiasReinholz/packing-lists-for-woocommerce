#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
source .wp-plugin-base/scripts/lib/wordpress_tooling.sh
# Install from manifests outside Git so runtime metadata never embeds a checkout's
# branch, commit reference or local path. The pinned image also isolates build tools
# that require a newer interpreter than the PHP 8.1-compatible plugin runtime.
build_context="$(mktemp -d)"
trap 'rm -rf "$build_context"' EXIT
mkdir "$build_context/runtime" "$build_context/tools"
cp composer.json composer.lock "$build_context/runtime/"
cp tools/build-tools/composer.json tools/build-tools/composer.lock "$build_context/tools/"
docker run --rm --user "$(id -u):$(id -g)" \
  -e COMPOSER_HOME=/tmp/composer-home -e COMPOSER_ROOT_VERSION=dev-main \
  -v "$build_context:/build-context" -v "$PWD:/plugin" -w /plugin \
  --entrypoint sh "$WP_PLUGIN_BASE_COMPOSER_IMAGE" -c '
    set -eu
    composer install --working-dir=/build-context/runtime --no-dev --prefer-dist --no-interaction --no-plugins --no-scripts
    composer install --working-dir=/build-context/tools --prefer-dist --no-interaction --no-plugins --no-scripts
    php /build-context/tools/vendor/bin/php-scoper add-prefix /build-context/runtime/vendor --config=scoper-config.php --output-dir=lib/vendor --force --no-interaction
    composer dump-autoload --working-dir=lib --no-dev --classmap-authoritative --no-plugins --no-scripts
  '
php tests/pdf.php
