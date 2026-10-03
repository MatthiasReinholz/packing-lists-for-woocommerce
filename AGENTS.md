<!-- wp-plugin-base:agents-start -->
# Agent Operating Contract

This project consumes `wp-plugin-base` as vendored foundation source under `.wp-plugin-base/`.

## Working Rules

- Treat `.wp-plugin-base/` as generated/vendor foundation code. Prefer fixing reusable behavior upstream in `wp-plugin-base`, then resync this project.
- Managed files are listed by `bash .wp-plugin-base/scripts/ci/list_managed_files.sh --mode validate`. Do not permanently patch those files in place unless you are intentionally diverging from the foundation.
- Project-specific test bootstrap code belongs in `tests/wp-plugin-base/bootstrap-child.php`, not in managed `tests/bootstrap.php`.
- Public endpoint suppressions belong in `.wp-plugin-base-security-suppressions.json` and must keep the exact scanner-reported `kind`, `identifier`, and repo-relative `path`.

## Validation

After foundation or template updates, run:

```bash
bash .wp-plugin-base/scripts/update/sync_child_repo.sh
bash .wp-plugin-base/scripts/ci/validate_project.sh
```

For release changes, also run the release preparation workflow or local release checks documented in `CONTRIBUTING.md` before merging.
<!-- wp-plugin-base:agents-end -->

## Plugin ownership and distribution

This repository owns all plugin source, tests, developer documentation and releases.
Consumers install release ZIPs; development belongs in this repository. Keep
the directory and text domain `packing-lists-for-woocommerce` and the plugin title
`Packing Lists for WooCommerce` consistent. Use generic examples in public code,
tests and documentation. Preserve published settings and capabilities unless an
explicit migration is approved.
Keep source documentation out of packages except the standard readme and required
license/attribution notices. Use `tools/check-package.php` to verify the payload.
Rebuild scoped PDF libraries through `tools/build-runtime.sh`; never hand-edit them.
