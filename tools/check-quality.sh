#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
export WP_PLUGIN_BASE_ROOT="$PWD"
validation_log="$(mktemp)"
config="$(mktemp --suffix=.neon)"
trap 'rm -f "$validation_log" "$config"' EXIT
if ! bash .wp-plugin-base/scripts/ci/validate_project.sh > "$validation_log" 2>&1; then
  cat "$validation_log"
  exit 1
fi
tail -5 "$validation_log"
# Capture the exact foundation-generated package, not a stale dist directory.
source .wp-plugin-base/scripts/lib/package_generation.sh
package_result="$(mktemp)"
trap 'rm -f "$validation_log" "$config" "$package_result"' EXIT
WP_PLUGIN_BASE_PACKAGE_RESULT_FILE="$package_result" bash .wp-plugin-base/scripts/ci/build_zip.sh
wp_plugin_base_capture_package "$package_result"
php -r 'require "tools/check-package.php"; packing_lists_for_woocommerce_check_package($argv[1], false);' "$WP_PLUGIN_BASE_PACKAGE_DIR"
composer install --working-dir=.wp-plugin-base-quality-pack --no-interaction --prefer-dist
composer audit --locked --no-dev --no-interaction
composer audit --working-dir=.wp-plugin-base-quality-pack --locked --no-interaction
php .wp-plugin-base-quality-pack/vendor/bin/phpcs --standard=.phpcs.xml.dist
php .wp-plugin-base-quality-pack/vendor/bin/phpcs --standard=.phpcs-security.xml.dist
cat > "$config" <<EOF
includes:
  - '$PWD/.wp-plugin-base-quality-pack/vendor/szepeviktor/phpstan-wordpress/extension.neon'
  - '$PWD/phpstan.neon.dist'
  - '$PWD/phpstan.neon'
EOF
php .wp-plugin-base-quality-pack/vendor/bin/phpstan analyse --configuration="$config" --no-progress --memory-limit=1G
php .wp-plugin-base-quality-pack/vendor/bin/phpunit --configuration=phpunit.xml.dist
