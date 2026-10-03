<?php
/**
 * Remove this plugin's per-site settings when explicitly uninstalled.
 *
 * @package Packing_Lists_For_WooCommerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( is_multisite() ) {
	$packing_lists_for_woocommerce_offset = 0;
	do {
		$packing_lists_for_woocommerce_sites = get_sites(
			array(
				'fields'  => 'ids',
				'number'  => 100,
				'offset'  => $packing_lists_for_woocommerce_offset,
				'orderby' => 'id',
				'order'   => 'ASC',
			)
		);
		foreach ( $packing_lists_for_woocommerce_sites as $packing_lists_for_woocommerce_site_id ) {
			switch_to_blog( $packing_lists_for_woocommerce_site_id );
			delete_option( 'packing_lists_for_woocommerce_recipient_email' );
			delete_option( 'packing_lists_for_woocommerce_send_lock' );
			restore_current_blog();
		}
		$packing_lists_for_woocommerce_offset += 100;
		$packing_lists_for_woocommerce_count   = count( $packing_lists_for_woocommerce_sites );
	} while ( 100 === $packing_lists_for_woocommerce_count );
} else {
	delete_option( 'packing_lists_for_woocommerce_recipient_email' );
	delete_option( 'packing_lists_for_woocommerce_send_lock' );
}
