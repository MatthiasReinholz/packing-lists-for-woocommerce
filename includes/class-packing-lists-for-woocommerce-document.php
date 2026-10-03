<?php
/**
 * Packing list services.
 *
 * @package Packing_Lists_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Render escaped packing content from historical order records. */
final class Packing_Lists_For_WooCommerce_Document {
	/**
	 * Resolve shipping name, falling back to billing.
	 *
	 * @param WC_Order $order Order record.
	 * @return string Recipient name.
	 */
	public static function get_order_shipping_name( WC_Order $order ): string {
		$name = trim( (string) $order->get_shipping_first_name() . ' ' . (string) $order->get_shipping_last_name() );
		if ( '' !== $name ) {
			return $name;
		}

		$name = trim( (string) $order->get_billing_first_name() . ' ' . (string) $order->get_billing_last_name() );
		return '' !== $name ? $name : __( '(No name)', 'packing-lists-for-woocommerce' );
	}

	/**
	 * Resolve a localized country label.
	 *
	 * @param string $country_code ISO country code.
	 * @return string Country label.
	 */
	public static function format_country_name( string $country_code ): string {
		$country_code = strtoupper( trim( $country_code ) );
		if ( '' === $country_code || ! function_exists( 'WC' ) || ! WC()->countries ) {
			return $country_code;
		}

		$countries = WC()->countries->get_countries();
		return isset( $countries[ $country_code ] ) ? (string) $countries[ $country_code ] : $country_code;
	}

	/**
	 * Render the complete document as escaped HTML.
	 *
	 * @param WC_Order[] $orders Order records.
	 * @param string     $selection Valid selection key.
	 * @return string HTML document.
	 */
	public static function render( array $orders, string $selection ): string {
		$generated_at = wp_date( 'Y-m-d H:i' );

		$aggregated_items = self::aggregate_items( $orders );

		ob_start();
		?>
		<!doctype html>
		<html>
		<head>
			<meta charset="utf-8" />
			<title><?php echo esc_html__( 'Packing list', 'packing-lists-for-woocommerce' ); ?></title>
		</head>
		<body>
			<h2 style="margin:0 0 10px;"><?php echo esc_html__( 'Packing list', 'packing-lists-for-woocommerce' ); ?></h2>
			<p style="margin:0 0 16px;">
			<?php
			/* translators: %s: generation time in the store timezone. */
			echo esc_html( sprintf( __( 'Generated: %s', 'packing-lists-for-woocommerce' ), $generated_at ) );
			?>
			</p>

			<h3 style="margin:0 0 8px;"><?php echo esc_html__( 'Aggregated items', 'packing-lists-for-woocommerce' ); ?></h3>
			<?php if ( empty( $aggregated_items ) ) : ?>
				<p><?php echo esc_html__( 'No items found.', 'packing-lists-for-woocommerce' ); ?></p>
			<?php else : ?>
				<?php // Flowing entries preserve long descriptions that cannot fit in a single table row. ?>
				<ul style="margin:0 0 18px;padding-left:18px;">
					<?php foreach ( $aggregated_items as $row ) : ?>
						<li><?php echo esc_html( $row['qty'] . ' × ' . $row['name'] ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<h3 style="margin:0 0 8px;"><?php echo esc_html( Packing_Lists_For_WooCommerce_Orders::selections()[ $selection ] ); ?></h3>
			<?php foreach ( $orders as $order ) : ?>
				<h4 style="margin:16px 0 6px;page-break-after:avoid;">
					<?php echo esc_html( '#' . $order->get_order_number() ); ?>
				</h4>
				<p style="margin:0 0 8px;">
					<?php echo esc_html( self::get_order_shipping_name( $order ) ); ?>
					— <?php echo esc_html( self::format_country_name( (string) $order->get_shipping_country() ) ); ?>
				</p>
				<?php
				// Flowing lists can paginate; a table row containing an entire order cannot.
				echo wp_kses(
					self::build_order_items_html( $order ),
					array(
						'ul' => array( 'style' => true ),
						'li' => array( 'style' => true ),
					)
				);
				?>
			<?php endforeach; ?>
		</body>
		</html>
		<?php
		$html = ob_get_clean();
		return is_string( $html ) ? $html : '';
	}

	/**
	 * List positive, unrefunded item quantities for an order.
	 *
	 * @param WC_Order $order Order record.
	 * @return string Escaped item list.
	 */
	private static function build_order_items_html( WC_Order $order ): string {
		$items = $order->get_items( array( 'line_item' ) );
		if ( empty( $items ) ) {
			return '';
		}

		$list_items = array();
		foreach ( $items as $item ) {
			if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
				continue;
			}

			$qty = max( 0, (float) $item->get_quantity() + (float) $order->get_qty_refunded_for_item( $item->get_id() ) );
			if ( 0.0 === (float) $qty ) {
				continue;
			}
			$name = self::format_order_item_name( $item );

			$list_items[] = '<li>' . esc_html( $qty . ' × ' . $name ) . '</li>';
		}

		if ( empty( $list_items ) ) {
			return '';
		}

		return '<ul style="margin:0;padding-left:18px;">' . implode( '', $list_items ) . '</ul>';
	}

	/**
	 * Describe an item using historical order metadata.
	 *
	 * @param WC_Order_Item_Product $item Order line item.
	 * @return string Plain-text item description.
	 */
	private static function format_order_item_name( WC_Order_Item_Product $item ): string {
		$name     = (string) $item->get_name();
		$metadata = array();
		foreach ( $item->get_formatted_meta_data( '_', true ) as $meta ) {
			$metadata[] = wp_strip_all_tags( $meta->display_key . ': ' . $meta->display_value );
		}
		return $name . ( $metadata ? ' (' . implode( ', ', $metadata ) . ')' : '' );
	}

	/**
	 * Sum net quantities while retaining distinctions in item metadata.
	 *
	 * @param WC_Order[] $orders Order records.
	 * @return array<int,array{name:string,qty:float}> Sorted item totals.
	 */
	private static function aggregate_items( array $orders ): array {
		$totals = array();

		foreach ( $orders as $order ) {
			if ( ! is_a( $order, 'WC_Order' ) ) {
				continue;
			}

			foreach ( $order->get_items( array( 'line_item' ) ) as $item ) {
				if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
					continue;
				}

				$quantity = max( 0, (float) $item->get_quantity() + (float) $order->get_qty_refunded_for_item( $item->get_id() ) );
				if ( 0.0 === (float) $quantity ) {
					continue;
				}
				$item_name    = self::format_order_item_name( $item );
				$product_id   = (int) $item->get_product_id();
				$variation_id = (int) $item->get_variation_id();
				$key          = $product_id . ':' . $variation_id . ':' . $item_name;

				if ( ! isset( $totals[ $key ] ) ) {
					$totals[ $key ] = array(
						'name' => $item_name,
						'qty'  => 0,
					);
				}

				$totals[ $key ]['qty'] += $quantity;
			}
		}

		$rows = array_values( $totals );
		usort(
			$rows,
			function ( array $a, array $b ): int {
				return strcasecmp( (string) $a['name'], (string) $b['name'] );
			}
		);

		return $rows;
	}
}
