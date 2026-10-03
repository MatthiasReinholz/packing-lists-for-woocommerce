# Third-party notices

The application code is GPL-2.0-or-later. The combined distribution uses the GPL-3.0-compatible option permitted by GPL-2.0-or-later, because php-svg-lib is LGPL-3.0. Third-party libraries retain their own licenses and copyrights; they are not represented as original application work.

## PDF runtime

| Package | Locked version | Declared license | Source |
| --- | --- | --- | --- |
| dompdf/dompdf | v3.1.6 | LGPL-2.1 | https://github.com/dompdf/dompdf.git |
| dompdf/php-font-lib | 1.0.2 | LGPL-2.1-or-later | https://github.com/dompdf/php-font-lib.git |
| dompdf/php-svg-lib | 1.0.2 | LGPL-3.0-or-later | https://github.com/dompdf/php-svg-lib.git |
| masterminds/html5 | 2.11.0 | MIT | https://github.com/Masterminds/html5-php.git |
| sabberworm/php-css-parser | v9.5.0 | MIT | https://github.com/MyIntervals/PHP-CSS-Parser.git |

The complete PHP library source, original copyright headers, license files, AUTHORS files, font notices, and color profile license are distributed under `lib/vendor/`. Dompdf's Adobe core font metric distribution notice is `lib/vendor/dompdf/dompdf/lib/fonts/mustRead.html`. DejaVu font license text is additionally distributed in `lib/licenses/DejaVu-LICENSE.txt`. The original font binaries retain their embedded copyright and license records. Composer's autoloader retains its MIT license at `lib/vendor/composer/LICENSE`. LGPL-3.0's accompanying GPL-3.0 text is supplied in `lib/licenses/GPL-3.0.txt`.

The runtime libraries are modified by PHP-Scoper to use the `PackingListsForWooCommerceVendor` namespace. Dompdf's dynamic frame/positioner class strings are patched by `scoper-config.php`; Composer regenerates the class map. These changes isolate the library from other WordPress plugins. No upstream algorithms are claimed as original. The editable scoped PHP source is included; users may replace or modify it and regenerate the class map using the source repository’s `lib/composer.json`. No technical restriction prevents modifying or debugging these LGPL components. The build manifests and tools are maintained in the [plugin source directory](https://github.com/MatthiasReinholz/packing-lists-for-woocommerce), rather than the installable plugin. In that source tree, `composer.lock` identifies the exact unmodified upstream source revisions, and `tools/build-pdf.sh` reproduces the transformation.

## Foundation and build tools

The development-only `.wp-plugin-base/` directory is the complete, signed and verified v1.10.2 release of [wp-plugin-base](https://github.com/MatthiasReinholz/wp-plugin-base), commit `6c342a8f634c6a480ee78f6303118cea18b2356d`. Its GPL-3.0 license and bundled component notices remain in that directory. Foundation source and PHP-Scoper build dependencies are excluded from the installable plugin; they are not loaded by WordPress. PHP-Scoper 0.18.19 is MIT licensed, pinned with its dependencies in `tools/build-tools/composer.lock`.

## Application code

The plugin's application code is maintained by Matthias Reinholz and is licensed
under GPL-2.0-or-later. The bundled PDF engine, its dependencies and the development
foundation are third-party works attributed above. They are not represented as
original application code. Attribution and license files must remain intact when
redistributing this plugin or modified versions.
