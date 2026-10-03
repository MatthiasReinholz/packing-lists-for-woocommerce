# Packing Lists for WooCommerce

A regular, per-site WooCommerce plugin that emails a consolidated PDF packing
list from the WordPress dashboard. Requires PHP 8.1+, WordPress 6.5+, and
WooCommerce. Network activation is intentionally blocked.

## Using the feature

1. Open **Settings → Packing Lists** and save the recipient email address.
   Administrators and shop managers with `manage_woocommerce` can view and save
   the setting. An invalid address leaves the previous address intact; clearing
   the field intentionally disables sending.
2. In the **Packing Lists** dashboard widget, choose **Processing** or
   **Completed today**, then press **Update selection**.
3. Review the preview and press **Send packing list**. The PDF includes all
   orders matching that selection when sending starts, not just the preview.

“Completed today” means orders currently completed whose WooCommerce completion
date falls between local midnight and the next midnight in the site's configured
timezone. Daylight-saving days can be 23 or 25 hours. It does not use creation or
last-modified dates. Processing remains the default.

The dashboard displays the first 20 orders and the total count. The PDF includes
aggregated item quantities and each order's number, customer name, historical
line-item details, and shipping country. WooCommerce formats visible metadata using its current attribute labels and term
names; those labels are not an immutable historical snapshot. Recorded quantity refunds are subtracted;
fully refunded items are omitted and fractional quantities are preserved. An
amount-only refund does not identify returned quantities and is not subtracted.

## Delivery and data handling

- The recipient is stored in the per-site `packing_lists_for_woocommerce_recipient_email`
  option. The plugin creates no customer tables and does not modify orders.
- The PDF is generated with isolated Dompdf dependencies and Unicode fonts.
  Remote resource loading, embedded PHP, and JavaScript are disabled. Dynamic
  order/customer text is escaped before rendering.
- A random directory in the operating-system temporary directory has mode 0700;
  the attachment has mode 0600. The PDF is never placed in public uploads.
  Normal completion, exceptions, and shutdown remove request-owned files.
- WordPress `wp_mail` receives the attachment synchronously and uses the
  WooCommerce sender name/address. A success notice means the transport accepted
  the message, not confirmation of final inbox delivery. A mail-queue integration
  must copy attachment bytes before returning, as with other temporary WordPress
  attachments.
- A non-autoloaded, atomic per-site option prevents overlapping sends. There are
  no automatic retries, which could duplicate fulfillment instructions.
- Queries use WooCommerce CRUD APIs for HPOS and legacy order storage. IDs are
  snapshotted, then hydrated in batches of 100 and rechecked against the selection.
  An order disappearing or becoming ineligible aborts the entire send.
- Synchronous PDF generation is bounded at 1,000 orders and 10,000 line items;
  the generated attachment is limited to 512 KiB. Ensure your mail transport
  accepts attachments of that size.
  An oversized selection fails explicitly; no partial packing list is sent.
  Larger-volume workflows need a background export design before increasing
  these limits. The snapshot is not a transaction across concurrent order edits.

## Operations

Install the plugin at `packing-lists-for-woocommerce/packing-lists-for-woocommerce.php`
and activate it separately on each site. Settings are isolated per site.

If a process is forcibly killed, its shutdown handler cannot run. After confirming
that no packing-list send is running, an operator can delete only the affected
site's `packing_lists_for_woocommerce_send_lock` option and remove old `packing-lists-for-woocommerce-*` temporary
directories owned by that WordPress service. Do not remove a lock while a send is
running. Deactivation retains settings; uninstall removes this plugin's options
from every site in bounded batches.

## Organization and maintenance

- `packing-lists-for-woocommerce.php`: plugin metadata, dependency bootstrap, HPOS declaration.
- `includes/class-packing-lists-for-woocommerce.php`: capability-protected settings, preview,
  selection control, nonce-protected POST handler.
- `includes/class-packing-lists-for-woocommerce-orders.php`: selection and bounded order loading.
- `includes/class-packing-lists-for-woocommerce-document.php`: escaped document presentation and
  historical item aggregation.
- `includes/class-packing-lists-for-woocommerce-mailer.php`: private PDF lifecycle and delivery.
- `lib/vendor/`: packaged, namespace-isolated PDF dependencies; never hand-edit.
- `tests/`: behavioral and PDF integration checks.

### Source, packages and production

This repository owns plugin source, tests, developer documentation and releases.
The repository, installed directory and text domain all use
`packing-lists-for-woocommerce`. The main file is `packing-lists-for-woocommerce.php`.

The full managed GitHub automation from `wp-plugin-base` is generated locally:
CI, secret/security scanning, release preparation, release publication, foundation
updates and dependency maintenance. See [CONTRIBUTING.md](CONTRIBUTING.md).
The plugin does not include its own update service. Install a verified release ZIP
through WordPress's plugin upload flow or your chosen deployment tooling.

Developer documentation, tests and build tools stay in the source repository.
`PACKAGE_INCLUDE` and `PACKAGE_EXCLUDE` define the installable ZIP, which includes
the standard WordPress `readme.txt`. Required licenses, author notices, fonts and
editable dependency source remain in every package. `tools/check-package.php`
verifies this distribution contract.

### Publishing and installing a release

1. Enable GitHub Actions pull-request creation in repository settings, with default
   workflow permissions kept read-only. Protect `main` and review release PRs.
2. Run `prepare-release`, review its generated release PR and merge it after checks
   pass. `finalize-release` publishes the ZIP, SBOM and Sigstore signature bundle.
3. Verify the ZIP's signing identity and digest before installation. The release
   asset is `packing-lists-for-woocommerce.zip`; a repository source archive is
   not an installable release package.
4. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**, upload the
   release ZIP and activate it on the intended site. Configure its recipient under
   **Settings → Packing Lists**.
5. When upgrading, use your normal plugin-update process and verify activation,
   recipient settings and PDF generation. Keep backups appropriate to your site.

The plugin adopts the verified `wp-plugin-base` foundation with its managed
GitHub automation profile. Foundation-managed files are generated from the
pinned upstream templates. See foundation configuration, provenance and
third-party notices in this directory for reproducible maintenance instructions.

Application code and bundled dependencies are distinguished in
[THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md). Dompdf and its dependencies are
third-party software; their PDF engine is not represented as original plugin code.

## Validation

The quality toolchain needs PHP, Composer, Node.js, Ruby, rsync, zip, jq and
Python. Rebuilding the isolated PDF vendor tree and development test dependencies with
`bash tools/build-runtime.sh`
requires Docker for the foundation’s digest-pinned Composer build container.
The host tooling and generated plugin runtime support PHP 8.1+. Lockfiles and license notices must be retained when updating.

From this plugin directory:

```sh
bash tools/check-quality.sh
php tests/behavior.php
php tests/pdf.php
```

The quality script checks foundation conformance and the installable archive,
WordPress coding/security standards, PHP 8.1 compatibility, PHPStan, PHPUnit and
locked dependency advisories. The PDF test intercepts mail locally and verifies
Unicode text and private-file cleanup. Its locked, development-only PDF parser
makes text-level assertions mandatory.
`tests/woocommerce-integration.php` additionally exercises real WooCommerce CRUD
and Settings API behavior; it refuses to run unless the isolated site's home URL
is exactly `http://packing.test`. Never run synthetic-order tests on production.

Deployment tooling should verify the installed package's runtime dependencies and
required notices. Run source tests from this repository, not from an installed
plugin directory where development tooling is intentionally absent.

The managed WordPress readiness job runs Plugin Check, coding standards, PHPStan,
PHPUnit and the foundation security pack on hosted CI, including Semgrep and locked
dependency advisories. `plugin-tests.yml` adds direct PHP runtime and real WooCommerce
integration coverage. Local machines unable to run the pinned Semgrep binary must
use hosted security results as the acceptance gate; do not disable that gate.
