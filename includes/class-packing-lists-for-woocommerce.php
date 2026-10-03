<?php
/**
 * Packing list services.
 *
 * @package Packing_Lists_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Admin settings, dashboard preview and authorized send endpoint. */
final class Packing_Lists_For_WooCommerce {
	private const OPTION_RECIPIENT_EMAIL = 'packing_lists_for_woocommerce_recipient_email';
	private const PAGE_SLUG              = 'packing-lists-for-woocommerce';
	private const DASHBOARD_WIDGET_ID    = 'packing_lists_for_woocommerce_dashboard_widget';
	private const CAPABILITY             = 'manage_woocommerce';

	/**
	 * Use the same capability for displaying and saving settings.
	 */
	public static function settings_capability(): string {
		return self::CAPABILITY;
	}

	/**
	 * Register the admin-only integration hooks.
	 */
	public static function init(): void {
		add_filter( 'option_page_capability_packing_lists_for_woocommerce', array( __CLASS__, 'settings_capability' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_show_admin_notices' ) );

		add_filter( 'plugin_action_links_' . plugin_basename( PACKING_LISTS_FOR_WOOCOMMERCE_FILE ), array( __CLASS__, 'add_settings_link' ) );

		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register_dashboard_widget' ) );
		add_action( 'admin_post_packing_lists_for_woocommerce_send', array( __CLASS__, 'handle_send_packing_list' ) );
	}

	/**
	 * Prevent network-activation in multisite.
	 *
	 * @param bool $network_wide Whether the plugin is being network activated.
	 */
	public static function activate( $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			wp_die(
				esc_html__( 'Packing Lists for WooCommerce cannot be network-activated. Please activate it per-site.', 'packing-lists-for-woocommerce' ),
				esc_html__( 'Activation Error', 'packing-lists-for-woocommerce' ),
				array( 'back_link' => true )
			);
		}

		if ( null === get_option( self::OPTION_RECIPIENT_EMAIL, null ) ) {
			add_option( self::OPTION_RECIPIENT_EMAIL, '', '', false );
		}
	}

	/**
	 * Check that the WooCommerce order API is loaded.
	 */
	private static function is_woocommerce_available(): bool {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_orders' ) && function_exists( 'wc_get_order' );
	}

	/**
	 * Explain a missing WooCommerce dependency to administrators.
	 */
	public static function maybe_show_admin_notices(): void {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( self::is_woocommerce_available() ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Packing Lists for WooCommerce requires WooCommerce to be installed and active.', 'packing-lists-for-woocommerce' );
		echo '</p></div>';
	}

	/**
	 * Add a shortcut from the plugin list.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array
	 */
	public static function add_settings_link( array $links ): array {
		$url           = add_query_arg( array( 'page' => self::PAGE_SLUG ), admin_url( 'options-general.php' ) );
		$settings_link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'packing-lists-for-woocommerce' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Register the site-specific recipient settings page.
	 */
	public static function add_settings_page(): void {
		add_options_page(
			esc_html__( 'Packing Lists', 'packing-lists-for-woocommerce' ),
			esc_html__( 'Packing Lists', 'packing-lists-for-woocommerce' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Register the recipient option and accessible settings field.
	 */
	public static function register_settings(): void {
		register_setting(
			'packing_lists_for_woocommerce',
			self::OPTION_RECIPIENT_EMAIL,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_recipient_email' ),
				'default'           => '',
			)
		);

		add_settings_section(
			'packing_lists_for_woocommerce_section_main',
			esc_html__( 'Email recipient', 'packing-lists-for-woocommerce' ),
			function (): void {
				echo '<p>' . esc_html__( 'Configure where the packing list email should be sent.', 'packing-lists-for-woocommerce' ) . '</p>';
			},
			self::PAGE_SLUG
		);

		add_settings_field(
			'recipient_email',
			esc_html__( 'Recipient email address', 'packing-lists-for-woocommerce' ),
			array( __CLASS__, 'render_field_recipient_email' ),
			self::PAGE_SLUG,
			'packing_lists_for_woocommerce_section_main',
			array( 'label_for' => self::OPTION_RECIPIENT_EMAIL )
		);
	}

	/**
	 * Validate without silently altering or discarding an existing recipient.
	 *
	 * @param mixed $value Submitted option value.
	 * @return string
	 */
	public static function sanitize_recipient_email( $value ): string {
		if ( ! is_string( $value ) ) {
			add_settings_error( self::OPTION_RECIPIENT_EMAIL, 'invalid_email', __( 'Please enter a valid recipient email address.', 'packing-lists-for-woocommerce' ) );
			return (string) get_option( self::OPTION_RECIPIENT_EMAIL, '' );
		}
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}

		if ( ! is_email( $value ) || sanitize_email( $value ) !== $value ) {
			add_settings_error(
				self::OPTION_RECIPIENT_EMAIL,
				'invalid_email',
				esc_html__( 'Please enter a valid recipient email address.', 'packing-lists-for-woocommerce' )
			);
			return (string) get_option( self::OPTION_RECIPIENT_EMAIL, '' );
		}

		return $value;
	}

	/**
	 * Render the recipient input and sender information.
	 */
	public static function render_field_recipient_email(): void {
		$value = get_option( self::OPTION_RECIPIENT_EMAIL, '' );
		echo '<input id="packing_lists_for_woocommerce_recipient_email" type="email" class="regular-text" name="' . esc_attr( self::OPTION_RECIPIENT_EMAIL ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr__( 'warehouse@example.com', 'packing-lists-for-woocommerce' ) . '" />';
		echo '<p class="description">' . esc_html__( 'The packing list will be emailed to this address when you click “Send packing list” on the Dashboard widget.', 'packing-lists-for-woocommerce' ) . '</p>';

		$from = self::get_woocommerce_from();
		echo '<p class="description">';
		echo esc_html__( 'From address comes from WooCommerce → Settings → Emails → Email sender options:', 'packing-lists-for-woocommerce' ) . ' ';
		echo '<code>' . esc_html( $from['name'] . ' <' . $from['email'] . '>' ) . '</code>';
		echo '</p>';
	}

	/**
	 * Render the capability-protected settings form.
	 */
	public static function render_settings_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'packing-lists-for-woocommerce' ) );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Packing Lists', 'packing-lists-for-woocommerce' ) . '</h1>';
		echo '<form method="post" action="options.php">';
		settings_errors();
		settings_fields( 'packing_lists_for_woocommerce' );
		do_settings_sections( self::PAGE_SLUG );
		submit_button();
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Expose the widget only to authorized WooCommerce operators.
	 */
	public static function register_dashboard_widget(): void {
		if ( ! self::is_woocommerce_available() ) {
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		wp_add_dashboard_widget(
			self::DASHBOARD_WIDGET_ID,
			esc_html__( 'Packing Lists', 'packing-lists-for-woocommerce' ),
			array( __CLASS__, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render a bounded preview and a nonce-protected send form.
	 */
	public static function render_dashboard_widget(): void {
		if ( ! self::is_woocommerce_available() ) {
			echo '<p>' . esc_html__( 'WooCommerce is required to display this widget.', 'packing-lists-for-woocommerce' ) . '</p>';
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only dashboard notices and allowlisted preview filter.
		$sent  = isset( $_GET['packing_lists_for_woocommerce_sent'] ) && is_string( $_GET['packing_lists_for_woocommerce_sent'] ) ? sanitize_text_field( wp_unslash( $_GET['packing_lists_for_woocommerce_sent'] ) ) : '';
		$error = isset( $_GET['packing_lists_for_woocommerce_error'] ) && is_string( $_GET['packing_lists_for_woocommerce_error'] ) ? sanitize_text_field( wp_unslash( $_GET['packing_lists_for_woocommerce_error'] ) ) : '';

		if ( '1' === $sent ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Packing list email accepted for delivery.', 'packing-lists-for-woocommerce' ) . '</p></div>';
		} elseif ( '1' === $error ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Failed to send packing list email.', 'packing-lists-for-woocommerce' ) . '</p></div>';
		} elseif ( '' !== $error ) {
			$message = self::error_message_from_code( $error );
			echo '<div class="notice notice-error inline"><p>' . esc_html( $message ) . '</p></div>';
		}

		$selection = self::get_selection( $_GET );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		self::render_selection( $selection );
		try {
			$result = Packing_Lists_For_WooCommerce_Orders::preview( $selection );
		} catch ( Throwable $error ) {
			echo '<p>' . esc_html__( 'Unable to load orders. Please try again.', 'packing-lists-for-woocommerce' ) . '</p>';
			return;
		}
		$orders = $result->orders;

		if ( empty( $orders ) ) {
			echo '<p>' . esc_html__( 'No orders match this selection.', 'packing-lists-for-woocommerce' ) . '</p>';
		} else {
			echo '<table class="widefat striped" style="margin-top: 10px;">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__( 'Order number', 'packing-lists-for-woocommerce' ) . '</th>';
			echo '<th>' . esc_html__( 'Name', 'packing-lists-for-woocommerce' ) . '</th>';
			echo '<th>' . esc_html__( 'Shipping country', 'packing-lists-for-woocommerce' ) . '</th>';
			echo '</tr></thead>';
			echo '<tbody>';

			foreach ( $orders as $order ) {
				$order_number   = (string) $order->get_order_number();
				$shipping_name  = Packing_Lists_For_WooCommerce_Document::get_order_shipping_name( $order );
				$country_code   = (string) $order->get_shipping_country();
				$country_name   = Packing_Lists_For_WooCommerce_Document::format_country_name( $country_code );
				$edit_order_url = $order->get_edit_order_url();

				echo '<tr>';
				echo '<td>';
				if ( $edit_order_url ) {
					echo '<a href="' . esc_url( $edit_order_url ) . '">#' . esc_html( $order_number ) . '</a>';
				} else {
					echo '#' . esc_html( $order_number );
				}
				echo '</td>';
				echo '<td>' . esc_html( $shipping_name ) . '</td>';
				echo '<td>' . esc_html( $country_name ) . '</td>';
				echo '</tr>';
			}

			echo '</tbody>';
			echo '</table>';
		}

		/* translators: 1: displayed order count, 2: matching order count. */
		echo '<p>' . esc_html( sprintf( __( 'Showing %1$d of %2$d matching orders. The PDF includes all matching orders at send time.', 'packing-lists-for-woocommerce' ), count( $orders ), $result->total ) ) . '</p>';

		$recipient_email = (string) get_option( self::OPTION_RECIPIENT_EMAIL, '' );
		$settings_url    = add_query_arg( array( 'page' => self::PAGE_SLUG ), admin_url( 'options-general.php' ) );

		echo '<p style="margin-top: 12px;">';
		if ( '' === $recipient_email ) {
			echo '<strong>' . esc_html__( 'Recipient email not configured.', 'packing-lists-for-woocommerce' ) . '</strong> ';
			echo '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Configure in Settings → Packing Lists.', 'packing-lists-for-woocommerce' ) . '</a>';
		} else {
			echo esc_html__( 'Recipient:', 'packing-lists-for-woocommerce' ) . ' <code>' . esc_html( $recipient_email ) . '</code>';
		}
		echo '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="packing_lists_for_woocommerce_send" />';
		echo '<input type="hidden" name="selection" value="' . esc_attr( $selection ) . '" />';
		wp_nonce_field( 'packing_lists_for_woocommerce_send' );
		submit_button( esc_html__( 'Send packing list', 'packing-lists-for-woocommerce' ), 'primary', 'submit', false );
		echo '</form>';
	}

	/**
	 * Map safe public error codes to translated messages.
	 *
	 * @param string $code Error identifier.
	 * @return string
	 */
	private static function error_message_from_code( string $code ): string {
		switch ( $code ) {
			case 'pdf_too_large':
				return __( 'The PDF exceeds the email attachment size limit. No packing list was sent.', 'packing-lists-for-woocommerce' );
			case 'pdf_unavailable':
				return __( 'The PDF library is unavailable. Please contact the site administrator.', 'packing-lists-for-woocommerce' );
			case 'orders_changed':
				return __( 'Orders changed while preparing the packing list. Please try again.', 'packing-lists-for-woocommerce' );
			case 'too_large':
				return __( 'The selection exceeds the safe PDF size limit. No partial packing list was sent.', 'packing-lists-for-woocommerce' );
			case 'busy':
				return __( 'A packing list is already being sent. Please wait before trying again.', 'packing-lists-for-woocommerce' );
			case 'missing_recipient':
				return __( 'Recipient email is not configured. Please set it in Settings → Packing Lists.', 'packing-lists-for-woocommerce' );
			case 'no_orders':
				return __( 'No matching orders found to include in the packing list.', 'packing-lists-for-woocommerce' );
			case 'woocommerce_missing':
				return __( 'WooCommerce is required to send the packing list.', 'packing-lists-for-woocommerce' );
			default:
				return __( 'Something went wrong while sending the packing list.', 'packing-lists-for-woocommerce' );
		}
	}

	/**
	 * Resolve the configured sender for the settings help text.
	 *
	 * @return array
	 */
	private static function get_woocommerce_from(): array {
		$mailer = self::is_woocommerce_available() ? WC()->mailer() : null;
		$email  = $mailer ? sanitize_email( $mailer->get_from_address() ) : '';
		$name   = $mailer ? sanitize_text_field( $mailer->get_from_name() ) : '';

		if ( '' === $name ) {
			$name = sanitize_text_field( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		}

		if ( '' === $email || ! is_email( $email ) ) {
			$email = sanitize_email( (string) get_bloginfo( 'admin_email' ) );
		}

		return array(
			'email' => $email,
			'name'  => $name,
		);
	}

	/**
	 * Normalize a read-only dashboard filter to the supported choices.
	 *
	 * @param array $request Query parameters.
	 * @return string
	 */
	public static function get_selection( array $request ): string {
		$value = $request['selection'] ?? 'processing';
		return is_string( $value ) && Packing_Lists_For_WooCommerce_Orders::is_valid_selection( $value ) ? $value : 'processing';
	}

	/**
	 * Render the accessible order selection control.
	 *
	 * @param string $selection Current order selection.
	 */
	private static function render_selection( string $selection ): void {
		echo '<form method="get" action="' . esc_url( admin_url( 'index.php' ) ) . '">';
		echo '<p><label for="packing-lists-for-woocommerce-selection">' . esc_html__( 'Include orders', 'packing-lists-for-woocommerce' ) . '</label> ';
		echo '<select id="packing-lists-for-woocommerce-selection" name="selection">';
		foreach ( Packing_Lists_For_WooCommerce_Orders::selections() as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $selection, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> ';
		submit_button( __( 'Update selection', 'packing-lists-for-woocommerce' ), 'secondary', '', false );
		echo '</p></form>';
		echo '<p>' . esc_html__( 'Completed today uses this store’s timezone and the order completion date.', 'packing-lists-for-woocommerce' ) . '</p>';
	}

	/**
	 * Authorize and validate a POST request before generating a PDF.
	 */
	public static function handle_send_packing_list(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to send packing lists.', 'packing-lists-for-woocommerce' ), '', array( 'response' => 403 ) );
		}
		if ( 'post' !== ( isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
			wp_die( esc_html__( 'Please submit the dashboard form.', 'packing-lists-for-woocommerce' ), '', array( 'response' => 405 ) );
		}
		check_admin_referer( 'packing_lists_for_woocommerce_send' );
		$selection = isset( $_POST['selection'] ) && is_string( $_POST['selection'] ) ? sanitize_key( wp_unslash( $_POST['selection'] ) ) : '';
		if ( ! is_string( $selection ) || ! Packing_Lists_For_WooCommerce_Orders::is_valid_selection( $selection ) ) {
			wp_die( esc_html__( 'Invalid order selection.', 'packing-lists-for-woocommerce' ), '', array( 'response' => 400 ) );
		}
		$result = Packing_Lists_For_WooCommerce_Mailer::send( $selection );
		$args   = array( 'selection' => $selection );
		if ( is_wp_error( $result ) ) {
			$args['packing_lists_for_woocommerce_error'] = $result->get_error_code();
		} else {
			$args['packing_lists_for_woocommerce_sent'] = '1';
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'index.php' ) ) );
		exit;
	}
}
