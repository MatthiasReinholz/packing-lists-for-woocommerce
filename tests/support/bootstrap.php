<?php
/** Offline behavioral doubles. Never connects to mail, WordPress or a database. */
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['test_options'] = array();
$GLOBALS['test_queries'] = array();
function __( $value, $domain = '' ) { return $value; }
function esc_html__( $value, $domain = '' ) { return esc_html( $value ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function wp_kses( $value, $allowed ) { return $value; }
function wp_date( $format ) { return '2026-10-02 12:00'; }
function wp_timezone() { return new DateTimeZone( 'Europe/Zurich' ); }
function wp_unslash( $value ) { return $value; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function sanitize_text_field( $value ) { return strip_tags( $value ); }
function sanitize_email( $value ) { return filter_var( $value, FILTER_SANITIZE_EMAIL ); }
function is_email( $value ) { return filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function get_option( $key, $default = false ) { return $GLOBALS['test_options'][ $key ] ?? $default; }
function add_option( $key, $value, $deprecated = '', $autoload = null ) {
	$GLOBALS['test_last_option_value'][ $key ] = $value;
	if ( isset( $GLOBALS['test_options'][ $key ] ) ) { return false; }
	$GLOBALS['test_options'][ $key ] = $value;
	return true;
}
function delete_option( $key ) { unset( $GLOBALS['test_options'][ $key ] ); return true; }
function get_bloginfo( $field ) { return 'admin_email' === $field ? 'admin@example.org' : 'Example Store'; }
function wp_specialchars_decode( $value, $flags = ENT_NOQUOTES ) { return htmlspecialchars_decode( $value, $flags ); }
function wc_get_orders( $args ) { $GLOBALS['test_queries'][] = $args; return ( $GLOBALS['test_query_callback'] )( $args ); }
function wc_get_order( $id ) { return $GLOBALS['test_orders'][ $id ] ?? false; }
function check( $condition, $description ) { if ( ! $condition ) { throw new RuntimeException( $description ); } echo 'PASS: ' . $description . PHP_EOL; }
class WP_Error {
	public function __construct( public string $code, public string $message = '' ) {}
	public function get_error_code() { return $this->code; }
}
class WC_Order_Item_Product {
	public function __construct( public int $id, public string $name, public float $quantity, public array $metadata = array() ) {}
	public function get_id() { return $this->id; }
	public function get_name() { return $this->name; }
	public function get_quantity() { return $this->quantity; }
	public function get_product_id() { return 10; }
	public function get_variation_id() { return 11; }
	public function get_formatted_meta_data( $hide, $include ) { return $this->metadata; }
}
class WC_Order {
	public function __construct( public int $id = 1, public array $items = array(), public array $refunds = array() ) {}
	public function get_id() { return $this->id; }
	public function get_order_number() { return '100<script>alert(1)</script>'; }
	public function get_shipping_first_name() { return '<img src=x onerror=alert(1)>'; }
	public function get_shipping_last_name() { return 'Müller'; }
	public function get_billing_first_name() { return 'Billing'; }
	public function get_billing_last_name() { return 'Fallback'; }
	public function get_shipping_country() { return 'CH'; }
	public function get_items( $types ) { return $this->items; }
	public function get_qty_refunded_for_item( $id ) { return $this->refunds[ $id ] ?? 0; }
}
function current_user_can( $capability ) { return $GLOBALS['test_authorized'] ?? false; }
function wp_die( $message, $title = '', $args = array() ) { throw new RuntimeException( $message, $args['response'] ?? 500 ); }
function check_admin_referer( $action ) { if ( ! ( $GLOBALS['test_nonce_valid'] ?? false ) ) { throw new RuntimeException( 'Invalid nonce', 403 ); } }
function add_settings_error( $setting, $code, $message ) { $GLOBALS['test_settings_errors'][] = $code; }
