<?php
/**
 * Storefront checkout picker: enqueues the widget, serves classifier AJAX,
 * persists the office choice into the WC session and onto the order.
 *
 * @package CcUkrposhtaWC
 */

namespace CatCode\UkrposhtaWC\Checkout;

use CatCode\UkrposhtaWC\Api\Cache;
use CatCode\UkrposhtaWC\Core\Settings;

defined( 'ABSPATH' ) || exit;

class Picker {

	public function register_hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'woocommerce_after_order_notes', array( $this, 'render_root' ) );
		add_action( 'woocommerce_before_order_notes', array( $this, 'render_root' ) );

		// The chosen office (and whether the order is cash-on-delivery) drives
		// the tariff but is not part of the package, so WooCommerce would serve
		// the first calculated rate forever. Folding both into the package makes
		// the rate hash change with the customer's choice. Must be
		// `cart_shipping_packages` (built before rating), not `shipping_packages`
		// — the latter fires after the rates are already calculated.
		add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'tag_packages' ) );

		add_action( 'woocommerce_checkout_process', array( $this, 'validate_classic' ) );
		add_filter( 'woocommerce_get_order_item_totals', array( $this, 'order_totals_row' ), 10, 2 );

		add_action( 'wp_ajax_upwc_regions', array( $this, 'ajax_regions' ) );
		add_action( 'wp_ajax_nopriv_upwc_regions', array( $this, 'ajax_regions' ) );
		add_action( 'wp_ajax_upwc_cities', array( $this, 'ajax_cities' ) );
		add_action( 'wp_ajax_nopriv_upwc_cities', array( $this, 'ajax_cities' ) );
		add_action( 'wp_ajax_upwc_offices', array( $this, 'ajax_offices' ) );
		add_action( 'wp_ajax_nopriv_upwc_offices', array( $this, 'ajax_offices' ) );
		add_action( 'wp_ajax_upwc_set', array( $this, 'ajax_set' ) );
		add_action( 'wp_ajax_nopriv_upwc_set', array( $this, 'ajax_set' ) );

		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_order_meta' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'save_order_meta_blocks' ), 10, 1 );
	}

	public function enqueue(): void {
		// is_checkout() is still true on the "order received" page, where a
		// picker would let the customer edit a choice that no longer matters.
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}
		$ver = UPWC_VERSION . '-' . (int) @filemtime( UPWC_DIR . 'assets/js/picker.js' );
		wp_register_script( 'upwc-picker', UPWC_URL . 'assets/js/picker.js', array( 'jquery' ), $ver, true );
		$accent = (string) Settings::get( 'accent_color', '#374151' );
		if ( ! preg_match( '/^#[0-9a-fA-F]{6}$/', $accent ) ) {
			$accent = '#374151';
		}
		wp_localize_script(
			'upwc-picker',
			'UPWC',
			array(
				'ajax'     => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'upwc' ),
				'accent'   => $accent,
				'methodId' => 'ukrposhta',
				// The choice lives in the session and keeps driving the tariff
				// after a reload, so the widget has to show it back — otherwise
				// the fields look empty while the customer is being charged for
				// an office they can no longer see.
				'selected' => $this->session_selection(),
				'i18n'     => self::widget_strings(),
			)
		);
		wp_enqueue_script( 'upwc-picker' );
	}

	/**
	 * Every label the checkout widget prints. They live here rather than in the
	 * JS so translators get them through the normal .po workflow.
	 *
	 * @return array<string,string>
	 */
	private static function widget_strings(): array {
		return array(
			'title'          => __( 'Ukrposhta delivery', 'catcode-shipping-with-ukrposhta-for-woocommerce' ),
			'region'         => __( 'Region', 'catcode-shipping-with-ukrposhta-for-woocommerce' ),
			'regionPh'       => __( 'Choose a region…', 'catcode-shipping-with-ukrposhta-for-woocommerce' ),
			'city'           => __( 'City', 'catcode-shipping-with-ukrposhta-for-woocommerce' ),
			'cityPh'         => __( 'Choose a region first', 'catcode-shipping-with-ukrposhta-for-woocommerce' ),
			'cityReady'      => __( 'Start typing the name…', 'catcode-shipping-with-ukrposhta-for-woocommerce' ),
			'office'         => __( 'Post office', 'catcode-shipping-with-ukrposhta-for-woocommerce' ),
			'officePh'       => __( 'Choose a city first', 'catcode-shipping-with-ukrposhta-for-woocommerce' ),
			'officeReady'    => __( 'Pick a post office or an index…', 'catcode-shipping-with-ukrposhta-for-woocommerce' ),
			'searching'      => __( 'Searching…', 'catcode-shipping-with-ukrposhta-for-woocommerce' ),
			'loading'        => __( 'Loading…', 'catcode-shipping-with-ukrposhta-for-woocommerce' ),
			'loadingRegions' => __( 'Loading regions…', 'catcode-shipping-with-ukrposhta-for-woocommerce' ),
			'regionsFail'    => __( 'Regions are unavailable. Check the connection to Ukrposhta.', 'catcode-shipping-with-ukrposhta-for-woocommerce' ),
			'noRegion'       => __( 'Nothing found', 'catcode-shipping-with-ukrposhta-for-woocommerce' ),
			'noCity'         => __( 'Nothing found', 'catcode-shipping-with-ukrposhta-for-woocommerce' ),
			'noOffice'       => __( 'No post offices here', 'catcode-shipping-with-ukrposhta-for-woocommerce' ),
			'fallbackName'   => __( 'Ukrposhta', 'catcode-shipping-with-ukrposhta-for-woocommerce' ),
		);
	}

	private $rendered = false;

	public function render_root(): void {
		if ( $this->rendered ) {
			return;
		}
		$this->rendered = true;
		echo '<div id="upwc-picker-root"></div>';
	}

	// ---- AJAX ----

	private function verify(): void {
		check_ajax_referer( 'upwc', 'nonce' );
	}

	public function ajax_regions(): void {
		$this->verify();
		$client = Settings::client();
		wp_send_json( array( 'regions' => $client ? Cache::regions( $client ) : array() ) );
	}

	public function ajax_cities(): void {
		$this->verify();
		$region_id = isset( $_POST['region_id'] ) ? sanitize_text_field( wp_unslash( $_POST['region_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify() runs check_ajax_referer() first.
		$query     = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify() runs check_ajax_referer() first.
		$client    = Settings::client();
		wp_send_json( array( 'cities' => $client ? Cache::cities( $client, $region_id, $query ) : array() ) );
	}

	public function ajax_offices(): void {
		$this->verify();
		$city_id     = isset( $_POST['city_id'] ) ? sanitize_text_field( wp_unslash( $_POST['city_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify() runs check_ajax_referer() first.
		$district_id = isset( $_POST['district_id'] ) ? sanitize_text_field( wp_unslash( $_POST['district_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify() runs check_ajax_referer() first.
		$region_id   = isset( $_POST['region_id'] ) ? sanitize_text_field( wp_unslash( $_POST['region_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify() runs check_ajax_referer() first.
		if ( '' === $city_id ) {
			wp_send_json( array( 'offices' => array() ) );
		}
		$client = Settings::client();
		wp_send_json( array( 'offices' => $client ? Cache::offices( $client, $city_id, $district_id, $region_id ) : array() ) );
	}

	public function ajax_set(): void {
		$this->verify();
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			wp_send_json( array( 'ok' => false ) );
		}
		$fields = array( 'region_id', 'region_name', 'city_id', 'city_name', 'office_postindex', 'office_name' );
		foreach ( $fields as $f ) {
			$val = isset( $_POST[ $f ] ) ? sanitize_text_field( wp_unslash( $_POST[ $f ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify() runs check_ajax_referer() first.
			WC()->session->set( 'upwc_' . $f, $val );
		}
		wp_send_json( array( 'ok' => true ) );
	}

	/**
	 * What the customer picked, as stored in the session.
	 *
	 * @return array<string,string>
	 */
	private function session_selection(): array {
		$out    = array();
		$fields = array( 'region_id', 'region_name', 'city_id', 'city_name', 'office_postindex', 'office_name' );
		foreach ( $fields as $field ) {
			$out[ $field ] = ( function_exists( 'WC' ) && WC()->session )
				? (string) WC()->session->get( 'upwc_' . $field, '' )
				: '';
		}
		return $out;
	}

	// ---- rate cache busting + validation ----

	/** Is Ukrposhta the chosen shipping method for this checkout? */
	public static function selected(): bool {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return false;
		}
		foreach ( (array) WC()->session->get( 'chosen_shipping_methods', array() ) as $chosen ) {
			if ( 0 === strpos( (string) $chosen, 'ukrposhta' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Fold the customer's choice into the package so WooCommerce recalculates
	 * the rate instead of replaying the cached one.
	 *
	 * @param array<int,array<string,mixed>> $packages Shipping packages.
	 * @return array<int,array<string,mixed>>
	 */
	public function tag_packages( $packages ) {
		if ( ! is_array( $packages ) || ! function_exists( 'WC' ) || ! WC()->session ) {
			return $packages;
		}
		$postindex = (string) WC()->session->get( 'upwc_office_postindex', '' );
		$payment   = (string) WC()->session->get( 'chosen_payment_method', '' );
		if ( '' === $postindex && '' === $payment ) {
			return $packages;
		}
		foreach ( $packages as $key => $package ) {
			$packages[ $key ]['upwc_postindex'] = $postindex;
			$packages[ $key ]['upwc_payment']   = $payment;
		}
		return $packages;
	}

	/**
	 * Without an office the tariff silently falls back to the flat rate and the
	 * merchant gets an order with no delivery address — so refuse the checkout.
	 */
	public function validate_classic(): void {
		if ( ! self::selected() ) {
			return;
		}
		$postindex = ( WC()->session ) ? (string) WC()->session->get( 'upwc_office_postindex', '' ) : '';
		if ( '' === $postindex ) {
			wc_add_notice( __( 'Please choose a region, a city and an Ukrposhta post office.', 'catcode-shipping-with-ukrposhta-for-woocommerce' ), 'error' );
		}
	}

	// ---- persist to order ----

	public function save_order_meta( int $order_id ): void {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		$postindex = (string) WC()->session->get( 'upwc_office_postindex', '' );
		if ( '' === $postindex ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$order->update_meta_data( '_upwc_postindex', $postindex );
		$order->update_meta_data( '_upwc_city', (string) WC()->session->get( 'upwc_city_name', '' ) );
		$order->update_meta_data( '_upwc_office', (string) WC()->session->get( 'upwc_office_name', '' ) );
		$order->save();
	}

	/** Blocks / Store API checkout path. */
	public function save_order_meta_blocks( $order ): void {
		if ( ! is_object( $order ) || ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		$postindex = (string) WC()->session->get( 'upwc_office_postindex', '' );
		if ( '' === $postindex ) {
			return;
		}
		$order->update_meta_data( '_upwc_postindex', $postindex );
		$order->update_meta_data( '_upwc_city', (string) WC()->session->get( 'upwc_city_name', '' ) );
		$order->update_meta_data( '_upwc_office', (string) WC()->session->get( 'upwc_office_name', '' ) );
	}

	/**
	 * Show the chosen office on the thank-you page, in the customer's account
	 * and in the order e-mails — the meta keys are underscore-prefixed, so
	 * without this row nobody outside the code ever sees the choice.
	 *
	 * @param array<string,array<string,string>> $rows  Total rows.
	 * @param mixed                              $order Order object.
	 * @return array<string,array<string,string>>
	 */
	public function order_totals_row( $rows, $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return $rows;
		}
		$office = (string) $order->get_meta( '_upwc_office' );
		if ( '' === $office ) {
			return $rows;
		}
		$city      = (string) $order->get_meta( '_upwc_city' );
		$postindex = (string) $order->get_meta( '_upwc_postindex' );

		$rows['upwc_office'] = array(
			'label' => __( 'Ukrposhta post office:', 'catcode-shipping-with-ukrposhta-for-woocommerce' ),
			'value' => esc_html( trim( $city . ', ' . $office . ( '' !== $postindex ? ' (' . $postindex . ')' : '' ), ', ' ) ),
		);
		return $rows;
	}
}
