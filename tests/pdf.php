<?php
/** Real scoped PDF rendering with a local mail interceptor; never sends email. */
require __DIR__ . '/support/bootstrap.php';
require dirname( __DIR__ ) . '/tools/pdf-tests/vendor/autoload.php';
require dirname( __DIR__ ) . '/includes/class-packing-lists-for-woocommerce-orders.php';
require dirname( __DIR__ ) . '/includes/class-packing-lists-for-woocommerce-document.php';
require dirname( __DIR__ ) . '/includes/class-packing-lists-for-woocommerce-mailer.php';
function WC() {
	return new class() {
		public $countries = null;
		public function mailer() {
			return new class() {
				public function get_from_address() { return 'sender@example.org'; }
				public function get_from_name() { return 'Example Store'; }
			};
		}
	};
}
function wp_mail( $recipient, $subject, $body, $headers, $attachments ) {
	check( 'warehouse@example.org' === $recipient, 'Configured recipient used' );
	check( 1 === count( $attachments ), 'One complete PDF attached' );
	$file = $attachments[0];
	$GLOBALS['test_attachment'] = $file;
	check( str_starts_with( file_get_contents( $file ), '%PDF-' ), 'Real renderer produces PDF bytes' );
	check( 0600 === ( fileperms( $file ) & 0777 ), 'Attachment permission is 0600' );
	check( 0700 === ( fileperms( dirname( $file ) ) & 0777 ), 'Private directory permission is 0700' );
	$text = ( new Smalot\PdfParser\Parser() )->parseFile( $file )->getText();
	check( str_contains( $text, 'Müller' ) && str_contains( $text, 'Crème' ), 'PDF preserves Unicode name and product text' );
	check( str_contains( $text, 'Completed today' ), 'PDF includes selected scope' );
	check( substr_count( $text, 'Final item 120' ) >= 2, 'Long order retains final item in summary and paginated order detail' );
	check( 2 === substr_count( $text, 'Long description end' ) && 2400 === substr_count( $text, 'Customization' ), 'Oversized single description remains complete in summary and order detail' );
	if ( 'exception' === $GLOBALS['test_mail_outcome'] ) { throw new RuntimeException( 'Mail transport failed' ); }
	return $GLOBALS['test_mail_outcome'];
}
$GLOBALS['test_options']['packing_lists_for_woocommerce_recipient_email'] = 'warehouse@example.org';
$item = new WC_Order_Item_Product( 1, 'Crème coffee', 3.5 );
$items = array( $item );
for ( $index = 2; $index <= 120; ++$index ) { $items[] = new WC_Order_Item_Product( $index, 'Final item ' . $index, 1 ); }
$items[] = new WC_Order_Item_Product( 121, 'Long description start ' . str_repeat( 'Customization words ', 1200 ) . ' Long description end', 1 );
$order = new WC_Order( 1, $items, array( 1 => -1 ) );
$GLOBALS['test_query_callback'] = static fn( $args ) => isset( $args['include'] ) ? array( $order ) : array( 1 );
foreach ( array( true, false, 'exception' ) as $outcome ) {
	$GLOBALS['test_mail_outcome'] = $outcome;
	$result = Packing_Lists_For_WooCommerce_Mailer::send( 'completed_today' );
	check( true === $outcome ? true === $result : is_wp_error( $result ), 'Mail outcome returns accurate success/failure' );
	check( ! file_exists( $GLOBALS['test_attachment'] ) && ! is_dir( dirname( $GLOBALS['test_attachment'] ) ), 'Attachment and temporary directory removed after mail outcome' );
	check( ! isset( $GLOBALS['test_options']['packing_lists_for_woocommerce_send_lock'] ), 'Mail outcome releases lock' );
}
check( ! class_exists( 'Dompdf\\Dompdf', false ) && class_exists( 'PackingListsForWooCommerceVendor\\Dompdf\\Dompdf', false ), 'PDF runtime remains namespace-isolated from other plugins' );
echo "Real PDF integration checks passed.\n";
