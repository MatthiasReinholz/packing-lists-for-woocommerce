<?php
/**
 * PDF rendering and private attachment delivery.
 *
 * @package Packing_Lists_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Generate private PDF attachments and hand them to WordPress mail. */
final class Packing_Lists_For_WooCommerce_Mailer {
	private const LOCK = 'packing_lists_for_woocommerce_send_lock';

	/**
	 * Send one complete selection, releasing resources on every normal failure.
	 *
	 * @param string $selection Valid order selection.
	 * @throws RuntimeException Internally only; caught and returned as a safe WP_Error.
	 * @return bool|WP_Error True means accepted by the mail transport, not delivered.
	 */
	public static function send( string $selection ): bool|WP_Error {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return new WP_Error( 'woocommerce_missing' );
		}
		$recipient = get_option( 'packing_lists_for_woocommerce_recipient_email', '' );
		if ( ! is_string( $recipient ) || ! is_email( $recipient ) ) {
			return new WP_Error( 'missing_recipient' );
		}
		// An atomic per-site option prevents overlapping sends. A crashed request
		// leaves a lock requiring explicit operator recovery, rather than duplicate mail.
		// Keep the value constant: WordPress uses an upsert, so a changing value
		// could let a concurrent loser update the row and incorrectly acquire it.
		if ( ! add_option( self::LOCK, '1', '', false ) ) {
			return new WP_Error( 'busy' );
		}
		$directory = '';
		$state     = (object) array( 'locked' => true );
		register_shutdown_function(
			static function () use ( &$directory, $state ): void {
				if ( $state->locked ) {
					self::cleanup( $directory );
					delete_option( self::LOCK );
				}
			}
		);
		try {
			$orders = Packing_Lists_For_WooCommerce_Orders::all( $selection );
			if ( is_wp_error( $orders ) ) {
				return $orders;
			}
			if ( ! $orders ) {
				return new WP_Error( 'no_orders' );
			}
			$autoload = dirname( __DIR__ ) . '/lib/vendor/autoload.php';
			if ( ! is_readable( $autoload ) ) {
				return new WP_Error( 'pdf_unavailable' );
			}
			require_once $autoload;
			$directory = rtrim( sys_get_temp_dir(), DIRECTORY_SEPARATOR ) . '/packing-lists-for-woocommerce-' . bin2hex( random_bytes( 16 ) );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Private local temp directory must enforce 0700, without remote filesystem credentials.
			if ( ! mkdir( $directory, 0700 ) ) {
				throw new RuntimeException( 'Unable to create private PDF directory.' );
			}
			$html    = Packing_Lists_For_WooCommerce_Document::render( $orders, $selection );
			$options = new PackingListsForWooCommerceVendor\Dompdf\Options();
			$options->setIsRemoteEnabled( false );
			$options->setIsFontSubsettingEnabled( true );
			$options->setIsPhpEnabled( false );
			$options->setIsJavascriptEnabled( false );
			$options->setChroot( $directory );
			$options->setTempDir( $directory );
			$options->setFontCache( $directory );
			$options->setDefaultFont( 'DejaVu Sans' );
			$pdf = new PackingListsForWooCommerceVendor\Dompdf\Dompdf( $options );
			$pdf->setPaper( 'A4' );
			$pdf->loadHtml( $html, 'UTF-8' );
			$pdf->render();
			$attachment = $directory . '/packing-list.pdf';
			$bytes      = $pdf->output();
			// Match the deployed transport's per-file ceiling before attempting delivery.
			if ( strlen( $bytes ) > 524288 ) {
				return new WP_Error( 'pdf_too_large' );
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents,WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Private local attachment, never a public upload or plugin write.
			if ( strlen( $bytes ) !== file_put_contents( $attachment, $bytes ) || ! chmod( $attachment, 0600 ) ) {
				throw new RuntimeException( 'Unable to write private PDF.' );
			}
			/* translators: %s: site name. */
			$subject    = sanitize_text_field( sprintf( __( 'Packing list (%s)', 'packing-lists-for-woocommerce' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ) );
			$mailer     = WC()->mailer();
			$from_email = sanitize_email( $mailer->get_from_address() );
			$from_name  = sanitize_text_field( $mailer->get_from_name() );
			$headers    = array( 'Content-Type: text/html; charset=UTF-8', 'From: ' . $from_name . ' <' . $from_email . '>' );
			$body       = '<p>' . esc_html__( 'The packing list is attached as a PDF.', 'packing-lists-for-woocommerce' ) . '</p>';
			return wp_mail( $recipient, $subject, $body, $headers, array( $attachment ) ) ? true : new WP_Error( 'mail_failed' );
		} catch ( Throwable $error ) {
			// Avoid writing customer information or PDF contents into logs/notices.
			return new WP_Error( 'generation_failed' );
		} finally {
			try {
				self::cleanup( $directory );
			} finally {
				delete_option( self::LOCK );
				$state->locked = false;
			}
		}
	}

	/**
	 * Remove private local files without allowing deletion-path filters.
	 *
	 * @param string $directory Request-owned temporary directory.
	 */
	private static function cleanup( string $directory ): void {
		if ( '' === $directory || ! is_dir( $directory ) ) {
			return;
		}
		foreach ( new DirectoryIterator( $directory ) as $file ) {
			if ( $file->isFile() ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unfiltered removal of sensitive request-owned local temporary files.
				unlink( $file->getPathname() );
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Request-owned local temporary directory.
		rmdir( $directory );
	}
}
