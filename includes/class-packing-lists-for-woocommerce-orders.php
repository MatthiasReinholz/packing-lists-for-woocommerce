<?php
/**
 * Storage-independent order selection.
 *
 * @package Packing_Lists_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Query WooCommerce orders through its storage-independent CRUD API. */
final class Packing_Lists_For_WooCommerce_Orders {
	public const MAX_ORDERS = 1000;

	/**
	 * Available dashboard selection labels.
	 *
	 * @return array<string,string> Selection labels.
	 */
	public static function selections(): array {
		return array(
			'processing'      => __( 'Processing', 'packing-lists-for-woocommerce' ),
			'completed_today' => __( 'Completed today', 'packing-lists-for-woocommerce' ),
		);
	}

	/**
	 * Check the strict selection allowlist.
	 *
	 * @param string $selection Requested selection.
	 * @return bool Whether the selection exists.
	 */
	public static function is_valid_selection( string $selection ): bool {
		return isset( self::selections()[ $selection ] );
	}

	/**
	 * Build completion-date boundaries in the store timezone, including DST.
	 *
	 * @param string                 $selection Requested selection.
	 * @param DateTimeImmutable|null $now Optional current instant for deterministic tests.
	 * @return array<string,mixed> WooCommerce CRUD query arguments.
	 * @throws InvalidArgumentException For unsupported selections.
	 */
	public static function query_args( string $selection, ?DateTimeImmutable $now = null ): array {
		if ( ! self::is_valid_selection( $selection ) ) {
			throw new InvalidArgumentException( 'Invalid packing list selection.' );
		}
		$args = array(
			'type'    => 'shop_order',
			'status'  => 'processing' === $selection ? 'processing' : 'completed',
			'orderby' => 'ID',
			'order'   => 'ASC',
		);
		if ( 'completed_today' === $selection ) {
			$now                    = $now ?? new DateTimeImmutable( 'now', wp_timezone() );
			$start                  = $now->setTimezone( wp_timezone() )->setTime( 0, 0 );
			$end                    = $start->modify( '+1 day' );
			$args['date_completed'] = $start->getTimestamp() . '...' . ( $end->getTimestamp() - 1 );
		}
		return $args;
	}

	/**
	 * Load the bounded dashboard preview and total matching count.
	 *
	 * @param string $selection Requested selection.
	 * @return object Paginated WooCommerce result.
	 */
	public static function preview( string $selection ): object {
		return wc_get_orders(
			self::query_args( $selection ) + array(
				'limit'    => 20,
				'paginate' => true,
			)
		);
	}

	/**
	 * Snapshot and hydrate a bounded selection; never return a partial export.
	 *
	 * @param string $selection Requested selection.
	 * @return WC_Order[]|WP_Error Orders or a size/concurrent-change failure.
	 */
	public static function all( string $selection ): array|WP_Error {
		$args = self::query_args( $selection );
		// Bound the ID snapshot before loading expensive order objects. Never send a truncated list.
		$ids = wc_get_orders(
			$args + array(
				'limit'  => self::MAX_ORDERS + 1,
				'return' => 'ids',
			)
		);
		if ( count( $ids ) > self::MAX_ORDERS ) {
			return new WP_Error( 'too_large' );
		}
		$orders     = array();
		$item_count = 0;
		foreach ( array_chunk( $ids, 100 ) as $batch ) {
			$loaded = wc_get_orders(
				$args + array(
					'include' => $batch,
					'limit'   => count( $batch ),
				)
			);
			if ( count( $loaded ) !== count( $batch ) ) {
				return new WP_Error( 'orders_changed' );
			}
			foreach ( $loaded as $order ) {
				$item_count += count( $order->get_items( 'line_item' ) );
				if ( $item_count > 10000 ) {
					return new WP_Error( 'too_large' );
				}
				$orders[] = $order;
			}
		}
		return $orders;
	}
}
