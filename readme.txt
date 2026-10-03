=== Packing Lists for WooCommerce ===
Contributors: matthiasreinholz
Tags: woocommerce, packing-list, fulfillment, pdf
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Requires Plugins: woocommerce
Stable tag: 0.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Email consolidated WooCommerce packing lists as PDF attachments from your dashboard.

== Description ==

Select processing orders or orders completed today in the site's timezone. Configure the recipient under Settings > Packing Lists. Each site's settings and orders are isolated. Requires WooCommerce and PHP's DOM and MBString extensions.

== Installation ==

1. Upload the packing-lists-for-woocommerce directory to wp-content/plugins.
2. Activate separately on each store, with WooCommerce active.
3. Configure Settings > Packing Lists and use the dashboard widget.

== Frequently Asked Questions ==

= Which timezone defines today? =
The site's WordPress timezone, including daylight-saving transitions.

= Does this send data to a PDF service? =
No. PDF rendering happens locally with bundled, isolated Dompdf dependencies.

== Changelog ==

= 0.3.1 =
* Update - Initialize Packing Lists for WooCommerce.


= 0.3.0 =
* Public release with a consistent Packing Lists for WooCommerce identity.
* Consolidated PDF packing lists for processing orders or orders completed today.
* Per-site recipient settings, PHP 8.1+ support and WooCommerce HPOS compatibility.
