<?php
/**
 * Plugin Name: Packing Lists for WooCommerce
 * Plugin URI: https://github.com/MatthiasReinholz/packing-lists-for-woocommerce
 * Description: Email PDF packing lists for processing orders or orders completed today in WooCommerce.
 * Version: 0.3.1
 * Author: Matthias Reinholz
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: packing-lists-for-woocommerce
 *
 * @package Packing_Lists_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	return;
}

define( 'PACKING_LISTS_FOR_WOOCOMMERCE_VERSION', '0.3.1' );
define( 'PACKING_LISTS_FOR_WOOCOMMERCE_FILE', __FILE__ );

foreach ( array( 'orders', 'document', 'mailer' ) as $packing_lists_for_woocommerce_service ) {
	require_once __DIR__ . '/includes/class-packing-lists-for-woocommerce-' . $packing_lists_for_woocommerce_service . '.php';
}
require_once __DIR__ . '/includes/class-packing-lists-for-woocommerce.php';
unset( $packing_lists_for_woocommerce_service );

add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', PACKING_LISTS_FOR_WOOCOMMERCE_FILE, true );
		}
	}
);
Packing_Lists_For_WooCommerce::init();
register_activation_hook( __FILE__, array( 'Packing_Lists_For_WooCommerce', 'activate' ) );
