<?php
/** Run: php tests/behavior.php (from this plugin). */
require __DIR__ . '/support/bootstrap.php';
require dirname( __DIR__ ) . '/includes/class-packing-lists-for-woocommerce-orders.php';
require dirname( __DIR__ ) . '/includes/class-packing-lists-for-woocommerce-document.php';
require dirname( __DIR__ ) . '/includes/class-packing-lists-for-woocommerce-mailer.php';

foreach ( array( '2026-03-29 12:00:00' => 23, '2026-10-25 12:00:00' => 25, '2026-10-02 12:00:00' => 24 ) as $date => $hours ) {
	$now = new DateTimeImmutable( $date, wp_timezone() );
	$args = Packing_Lists_For_WooCommerce_Orders::query_args( 'completed_today', $now );
	[ $first, $last ] = array_map( 'intval', explode( '...', $args['date_completed'] ) );
	check( $last - $first + 1 === $hours * 3600, 'Local completed day spans ' . $hours . ' hours on ' . $date );
	check( ( new DateTimeImmutable( '@' . $first ) )->setTimezone( wp_timezone() )->format( 'H:i:s' ) === '00:00:00', 'Completed range includes local midnight' );
	check( ( new DateTimeImmutable( '@' . $last ) )->setTimezone( wp_timezone() )->format( 'H:i:s' ) === '23:59:59', 'Completed range excludes next day' );
	check( 'completed' === $args['status'] && 'shop_order' === $args['type'], 'Completed query uses order status and CRUD type' );
}
$now = new DateTimeImmutable( '2026-10-01 23:30:00', new DateTimeZone( 'UTC' ) );
$args = Packing_Lists_For_WooCommerce_Orders::query_args( 'completed_today', $now );
check( str_starts_with( $args['date_completed'], (string) strtotime( '2026-10-02 00:00 Europe/Zurich' ) ), 'UTC instant resolves to site-local date' );
check( ! isset( Packing_Lists_For_WooCommerce_Orders::query_args( 'processing' )['date_completed'] ), 'Processing has no completion-date filter' );
foreach ( array( '', 'COMPLETED_TODAY', 'completed', '../processing', 'processing<script>' ) as $bad ) {
	try { Packing_Lists_For_WooCommerce_Orders::query_args( $bad ); throw new RuntimeException( 'Invalid selection accepted' ); }
	catch ( InvalidArgumentException $error ) { check( true, 'Reject selection ' . $bad ); }
}
$GLOBALS['test_query_callback'] = static fn( $args ) => (object) array( 'orders' => array(), 'total' => 21 );
$preview = Packing_Lists_For_WooCommerce_Orders::preview( 'processing' );
$query = end( $GLOBALS['test_queries'] );
check( 20 === $query['limit'] && true === $query['paginate'] && 21 === $preview->total, 'Dashboard bounds preview and preserves full count' );
$GLOBALS['test_query_callback'] = static fn( $args ) => range( 1, 1001 );
$result = Packing_Lists_For_WooCommerce_Orders::all( 'processing' );
check( is_wp_error( $result ) && 'too_large' === $result->get_error_code(), 'Oversized exports fail instead of silently truncating' );
$GLOBALS['test_query_callback'] = static fn( $args ) => isset( $args['include'] ) ? array( new WC_Order( 1, array_fill( 0, 10001, new WC_Order_Item_Product( 1, 'Item', 1 ) ) ) ) : array( 1 );
$result = Packing_Lists_For_WooCommerce_Orders::all( 'processing' );
check( is_wp_error( $result ) && 'too_large' === $result->get_error_code(), 'Oversized line-item count rejects entire export' );
$GLOBALS['test_queries'] = array();
$GLOBALS['test_query_callback'] = static function ( $args ) {
	if ( 'ids' === ( $args['return'] ?? '' ) ) { return range( 1, 201 ); }
	return array_map( static fn( $id ) => new WC_Order( $id ), $args['include'] );
};
$orders = Packing_Lists_For_WooCommerce_Orders::all( 'completed_today' );
check( 201 === count( $orders ) && 4 === count( $GLOBALS['test_queries'] ), 'Snapshot hydrates orders in batches of 100' );
foreach ( array_slice( $GLOBALS['test_queries'], 1 ) as $query ) {
	check( $query['limit'] <= 100 && 'completed' === $query['status'] && isset( $query['date_completed'] ), 'Hydration rechecks selection and remains bounded' );
}
$GLOBALS['test_query_callback'] = static fn( $args ) => isset( $args['include'] ) ? array() : array( 1 );
$result = Packing_Lists_For_WooCommerce_Orders::all( 'processing' );
check( is_wp_error( $result ) && 'orders_changed' === $result->get_error_code(), 'Concurrent removed or ineligible orders abort entire export' );
$item = new WC_Order_Item_Product( 1, 'Coffee <script>alert(1)</script>', 3.5, array( (object) array( 'display_key' => 'Size', 'display_value' => '<b>Original XL</b>' ) ) );
$refunded = new WC_Order_Item_Product( 2, 'Fully refunded product', 2 );
$order = new WC_Order( 1, array( $item, $refunded ), array( 1 => -1, 2 => -2 ) );
$html = Packing_Lists_For_WooCommerce_Document::render( array( $order ), 'completed_today' );
check( str_contains( $html, '2.5 × Coffee' ), 'Partial refund preserves fractional net quantity' );
check( ! str_contains( $html, 'Fully refunded product' ), 'Fully refunded items are omitted from detail and totals' );
check( ! str_contains( $html, '<script>' ) && ! str_contains( $html, '<img' ) && str_contains( $html, '&lt;script&gt;' ), 'Customer, product and order text is escaped' );
check( str_contains( $html, 'Size: Original XL' ), 'Recorded line metadata is included through WooCommerce formatting' );
check( str_contains( $html, 'Completed today' ), 'Document names selected orders' );
$GLOBALS['test_options'] = array();
$result = Packing_Lists_For_WooCommerce_Mailer::send( 'processing' );
check( is_wp_error( $result ) && 'missing_recipient' === $result->get_error_code(), 'Missing recipient prevents sending' );
$GLOBALS['test_options']['packing_lists_for_woocommerce_recipient_email'] = 'warehouse@example.org';
$GLOBALS['test_options']['packing_lists_for_woocommerce_send_lock'] = time();
$result = Packing_Lists_For_WooCommerce_Mailer::send( 'processing' );
check( is_wp_error( $result ) && 'busy' === $result->get_error_code(), 'Concurrent send is rejected' );
check( '1' === $GLOBALS['test_last_option_value']['packing_lists_for_woocommerce_send_lock'], 'Atomic lock always inserts identical value so duplicate SQL updates cannot win' );
unset( $GLOBALS['test_options']['packing_lists_for_woocommerce_send_lock'] );
$GLOBALS['test_query_callback'] = static fn( $args ) => array();
$result = Packing_Lists_For_WooCommerce_Mailer::send( 'processing' );
check( is_wp_error( $result ) && 'no_orders' === $result->get_error_code(), 'Empty selection sends nothing' );
check( ! isset( $GLOBALS['test_options']['packing_lists_for_woocommerce_send_lock'] ), 'Early return releases send lock' );
$GLOBALS['test_query_callback'] = static function ( $args ) { throw new RuntimeException( 'Private order data' ); };
$result = Packing_Lists_For_WooCommerce_Mailer::send( 'processing' );
check( is_wp_error( $result ) && 'generation_failed' === $result->get_error_code(), 'Unexpected data-layer exception becomes safe error' );
check( ! isset( $GLOBALS['test_options']['packing_lists_for_woocommerce_send_lock'] ), 'Exception releases send lock' );
echo "Behavior checks passed.\n";
require dirname( __DIR__ ) . '/includes/class-packing-lists-for-woocommerce.php';
foreach ( array( array(), array( 'selection' => array( 'completed_today' ) ), array( 'selection' => 'unknown' ) ) as $request ) {
	check( 'processing' === Packing_Lists_For_WooCommerce::get_selection( $request ), 'Malformed dashboard selection safely defaults to processing' );
}
check( 'completed_today' === Packing_Lists_For_WooCommerce::get_selection( array( 'selection' => 'completed_today' ) ), 'Dashboard retains valid selected scope' );
foreach ( array(
	array( false, 'POST', true, 'processing', 403 ),
	array( true, 'GET', true, 'processing', 405 ),
	array( true, 'POST', false, 'processing', 403 ),
	array( true, 'POST', true, array( 'processing' ), 400 ),
	array( true, 'POST', true, 'all', 400 ),
) as [ $authorized, $method, $nonce, $selection, $status ] ) {
	$GLOBALS['test_authorized'] = $authorized;
	$GLOBALS['test_nonce_valid'] = $nonce;
	$_SERVER['REQUEST_METHOD'] = $method;
	$_POST['selection'] = $selection;
	try { Packing_Lists_For_WooCommerce::handle_send_packing_list(); throw new RuntimeException( 'Handler accepted unsafe request' ); }
	catch ( RuntimeException $error ) { check( $status === $error->getCode(), 'Send handler enforces capability, method, nonce and input: HTTP ' . $status ); }
}
$GLOBALS['test_options']['packing_lists_for_woocommerce_recipient_email'] = 'existing@example.org';
check( 'existing@example.org' === Packing_Lists_For_WooCommerce::sanitize_recipient_email( "bad@host\r\nBcc:intruder@example.org" ), 'Header-injection email is rejected and existing recipient retained' );
check( 'existing@example.org' === Packing_Lists_For_WooCommerce::sanitize_recipient_email( array( 'intruder@example.org' ) ), 'Malformed recipient arrays preserve existing address' );
check( 'valid@example.org' === Packing_Lists_For_WooCommerce::sanitize_recipient_email( 'valid@example.org' ), 'Valid recipient saves unchanged' );
check( '' === Packing_Lists_For_WooCommerce::sanitize_recipient_email( '' ), 'Explicit empty recipient disables mail' );
check( 'manage_woocommerce' === Packing_Lists_For_WooCommerce::settings_capability(), 'Settings save capability matches shop-manager UI capability' );
echo "Controller checks passed.\n";
