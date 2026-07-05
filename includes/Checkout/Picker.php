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
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
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
				'ajax'   => admin_url( 'admin-ajax.php' ),
				'nonce'  => wp_create_nonce( 'upwc' ),
				'accent' => $accent,
			)
		);
		wp_enqueue_script( 'upwc-picker' );
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
		$region_id = isset( $_POST['region_id'] ) ? sanitize_text_field( wp_unslash( $_POST['region_id'] ) ) : '';
		$query     = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
		$client    = Settings::client();
		wp_send_json( array( 'cities' => $client ? Cache::cities( $client, $region_id, $query ) : array() ) );
	}

	public function ajax_offices(): void {
		$this->verify();
		$city_id     = isset( $_POST['city_id'] ) ? sanitize_text_field( wp_unslash( $_POST['city_id'] ) ) : '';
		$district_id = isset( $_POST['district_id'] ) ? sanitize_text_field( wp_unslash( $_POST['district_id'] ) ) : '';
		$region_id   = isset( $_POST['region_id'] ) ? sanitize_text_field( wp_unslash( $_POST['region_id'] ) ) : '';
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
		$fields = array( 'region_id', 'city_id', 'city_name', 'office_postindex', 'office_name' );
		foreach ( $fields as $f ) {
			$val = isset( $_POST[ $f ] ) ? sanitize_text_field( wp_unslash( $_POST[ $f ] ) ) : '';
			WC()->session->set( 'upwc_' . $f, $val );
		}
		wp_send_json( array( 'ok' => true ) );
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
}
