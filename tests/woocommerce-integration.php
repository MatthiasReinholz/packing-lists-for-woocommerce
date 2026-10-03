<?php
/**
 * Run only against a disposable WordPress/WooCommerce installation:
 * wp eval-file /plugin/tests/woocommerce-integration.php
 * Creates/deletes synthetic orders and modifies test-site settings.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'http://packing.test' !== get_option( 'home' ) ) {
	throw new RuntimeException( 'This test requires the isolated packing.test installation.' );
}
add_filter( 'pre_wp_mail', '__return_true' );
function packing_assert( bool $condition, string $description ): void {
	if ( ! $condition ) { throw new RuntimeException( $description ); }
	echo 'PASS: ' . $description . PHP_EOL;
}
$start = ( new DateTimeImmutable( 'now', wp_timezone() ) )->setTime( 0, 0 );
$end = $start->modify( '+1 day' );
$orders = array();
try {
	foreach ( array(
		'first' => array( 'completed', $start->getTimestamp() ),
		'last' => array( 'completed', $end->getTimestamp() - 1 ),
		'yesterday' => array( 'completed', $start->getTimestamp() - 1 ),
		'tomorrow' => array( 'completed', $end->getTimestamp() ),
		'processing' => array( 'processing', null ),
	) as $key => [ $status, $completed ] ) {
		$order = new WC_Order();
		$order->set_status( $status );
		$order->set_date_created( $start->modify( '-5 days' )->getTimestamp() );
		if ( null !== $completed ) { $order->set_date_completed( $completed ); }
		$order->save();
		$orders[ $key ] = $order;
	}
	$selected = Packing_Lists_For_WooCommerce_Orders::all( 'completed_today' );
	packing_assert( ! is_wp_error( $selected ), 'Real CRUD completed query succeeds' );
	$ids = array_map( static fn( $order ) => $order->get_id(), $selected );
	sort( $ids );
	$expected = array( $orders['first']->get_id(), $orders['last']->get_id() );
	sort( $expected );
	packing_assert( $ids === $expected, 'Completion date includes exact midnight boundaries and excludes adjacent dates' );
	$selected = Packing_Lists_For_WooCommerce_Orders::all( 'processing' );
	packing_assert( 1 === count( $selected ) && $selected[0]->get_id() === $orders['processing']->get_id(), 'Processing query remains independent of completion date' );
	$preview = Packing_Lists_For_WooCommerce_Orders::preview( 'completed_today' );
	packing_assert( 2 === (int) $preview->total, 'Real pagination reports completed selection total' );
	$user_id = wp_create_user( 'packing-manager', 'test-only-password', 'manager@example.org' );
	if ( is_wp_error( $user_id ) ) { $user_id = username_exists( 'packing-manager' ); }
	$user = new WP_User( $user_id );
	$user->set_role( 'shop_manager' );
	wp_set_current_user( $user_id );
	packing_assert( current_user_can( apply_filters( 'option_page_capability_packing_lists_for_woocommerce', 'manage_options' ) ), 'Shop manager is authorized for settings save capability' );
	$_GET['selection'] = 'completed_today';
	ob_start();
	Packing_Lists_For_WooCommerce::render_dashboard_widget();
	$widget = ob_get_clean();
	packing_assert( str_contains( $widget, 'value="completed_today"  selected=' ) || str_contains( $widget, 'value="completed_today" selected=' ), 'Widget selects Completed today' );
	packing_assert( str_contains( $widget, 'name="selection" value="completed_today"' ), 'Send form preserves selected scope' );
	packing_assert( str_contains( $widget, 'Showing 2 of 2 matching orders' ), 'Widget counts exclude neighboring completion dates' );
	packing_assert( str_contains( $widget, 'Include orders' ) && str_contains( $widget, 'Completed today' ), 'Widget exposes labeled scope selector' );
	unset( $_GET['selection'] );
	Packing_Lists_For_WooCommerce::register_settings();
	update_option( 'packing_lists_for_woocommerce_recipient_email', 'warehouse@example.org' );
	packing_assert( 'warehouse@example.org' === get_option( 'packing_lists_for_woocommerce_recipient_email' ), 'Settings API saves valid recipient' );
	update_option( 'packing_lists_for_woocommerce_recipient_email', "invalid\r\nBcc: bad@example.org" );
	packing_assert( 'warehouse@example.org' === get_option( 'packing_lists_for_woocommerce_recipient_email' ), 'Settings API preserves recipient after invalid update' );
	packing_assert( str_contains( $orders['first']->get_edit_order_url(), 'action=edit' ), 'CRUD edit URL works with active order storage' );
	delete_option( 'packing_lists_for_woocommerce_send_lock' );
	$race_writer = static function ( $option, $value ) {
		if ( 'packing_lists_for_woocommerce_send_lock' !== $option ) { return; }
		global $wpdb;
		// Simulate another request acquiring the row after add_option's existence check.
		$wpdb->insert( $wpdb->options, array( 'option_name' => $option, 'option_value' => '1', 'autoload' => 'off' ) );
	};
	add_action( 'add_option', $race_writer, 10, 2 );
	try {
		$race_result = Packing_Lists_For_WooCommerce_Mailer::send( 'processing' );
		packing_assert( is_wp_error( $race_result ) && 'busy' === $race_result->get_error_code(), 'Concurrent SQL lock winner prevents second sender entering' );
		global $wpdb;
		$winner_lock = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'packing_lists_for_woocommerce_send_lock' ) );
		packing_assert( '1' === $winner_lock, 'Losing sender preserves concurrent winner lock' );
	} finally {
		remove_action( 'add_option', $race_writer, 10 );
		// Read SQL directly: add_option's failed race can retain a notoptions cache entry.
		$wpdb->delete( $wpdb->options, array( 'option_name' => 'packing_lists_for_woocommerce_send_lock' ) );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( 'packing_lists_for_woocommerce_send_lock', 'options' );
	}
	echo 'Storage mode: ' . get_option( 'woocommerce_custom_orders_table_enabled', 'no' ) . PHP_EOL;
} finally {
	foreach ( $orders as $order ) { $order->delete( true ); }
}
