# Packing Lists tests

Tests intercept mail and never send real email. Run commands from the repository
root. Use `packing-lists-for-woocommerce` consistently as the plugin directory and text domain.

## Behavior and PDF checks

PHP needs DOM and mbstring. Install the locked development-only PDF parser before
running the tests. Every PDF run checks extracted text, including Unicode and
complete pagination; text checks are never silently skipped. No system package
installation or external text-extractor executable is needed.

```bash
composer install --working-dir=tools/pdf-tests --no-interaction --prefer-dist
php tests/behavior.php
php tests/pdf.php
```

There are 53 behavioral assertions and 37 real PDF assertions.
Coverage includes store-local completion boundaries and DST, bounded queries,
refunds, capabilities, nonces, recipient validation, locking, Unicode, a 121-item
order, a description spanning pages in both aggregate and detail sections, and
private-file cleanup after accepted, rejected, or throwing mail transport.

The [Plugin tests workflow](../.github/workflows/plugin-tests.yml) runs these
checks on PHP 8.1 and 8.4. Its separate WooCommerce job exercises real storage.

## Standalone static analysis and foundation quality

```bash
composer install --working-dir=tools/analysis --no-interaction --prefer-dist
bash tools/check-quality.sh
```

`tools/analysis/composer.lock` pins development-only WooCommerce declarations. The separate `tools/pdf-tests/composer.lock` pins test-only PDF
text extraction. Neither dependency set ships in the plugin package.
`phpstan.neon` scans those declarations and the scoped PDF runtime. It does not
require another repository or an adjacent WooCommerce installation. WordPress
analysis extensions and PHPUnit come from the foundation quality pack; do not
modify its managed Composer files to add application dependencies.

Offline doubles live in `tests/support/bootstrap.php`. They intentionally model
only the behavior needed by unit tests; real WooCommerce integration covers the
API and database assumptions. The foundation manages `tests/bootstrap.php`;
application bootstrap hooks belong in `tests/wp-plugin-base/bootstrap-child.php`.

## Real WordPress and WooCommerce integration

The child-owned workflow uses the foundation's locked wp-env tooling with
WordPress 7.1, WooCommerce 11.1.2, PHP 8.1, and a disposable database. It executes
`tests/woocommerce-integration.php` with legacy storage and HPOS, then removes the
environment through an exit trap. After wp-env starts, it resets the generated
WordPress URL constants to `http://packing.test`, because wp-env otherwise appends
its HTTP port and the fixture intentionally rejects that different URL. Update the
pinned WooCommerce fixture and
analysis stub version together when testing a new supported release.

Use the workflow's **Run workflow** action to reproduce this environment. Its
shell step can also run locally from the repository root with Node 22, Python 3,
and Docker installed; every temporary path is created by the step and its cleanup
only targets that environment. No prebuilt local image or sibling repository is
required. The workflow starts WordPress, activates the plugins, sets the test URL
and store timezone, and invokes:

```bash
wp option update woocommerce_custom_orders_table_enabled no
wp eval-file /var/www/html/wp-content/plugins/packing-lists-for-woocommerce/tests/woocommerce-integration.php
wp option update woocommerce_custom_orders_table_enabled yes
wp eval-file /var/www/html/wp-content/plugins/packing-lists-for-woocommerce/tests/woocommerce-integration.php
```

Those commands belong inside the disposable wp-env CLI container. The test
refuses to run unless `WP_CLI` is true and the site's `home` option is exactly
`http://packing.test`. It creates and deletes synthetic orders, changes recipient
settings, and creates a shop-manager account. Never change the guard to a real
store URL or run this fixture against production.

The real integration suite checks completion boundaries, processing selection,
pagination, dashboard selection, recipient validation, shop-manager capability,
order edit links, and the actual SQL lock race. Its race inserts a competing lock
after WordPress's existence check and verifies that a losing request neither sends
mail nor deletes the winner's lock.

## Recorded local audit

On 2026-10-02, all 53 behavioral and 37 real PDF assertions passed under PHP
8.1.34 in a network-disabled container with mail intercepted. The long-description
regression independently checks that the aggregate and detailed sections both
retain every repeated word and the final marker. Hosted results are recorded by
the standalone repository's workflow runs; local results do not establish that
those workflows have run successfully.

The standalone workflow's exact WooCommerce shell step also passed locally on
WordPress 7.0 and then 7.1, with WooCommerce 11.1.2 and PHP 8.1: all 14 real integration checks
passed with legacy storage and all 14 with HPOS. Its disposable containers,
database volumes, and network were cleaned up afterward.
