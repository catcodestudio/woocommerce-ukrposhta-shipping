<?php
/**
 * Pro: the tracking number and link for the customer — in the order e-mails
 * and on the order page of the customer account.
 *
 * @package CcUkrposhtaWC
 */

namespace CatCode\UkrposhtaWC\Pro;

use CatCode\UkrposhtaWC\Core\Settings;

defined( 'ABSPATH' ) || exit;

class Customer {

	public static function register(): void {
		add_action( 'woocommerce_email_order_meta', array( __CLASS__, 'email' ), 20, 4 );
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'account' ), 20 );
	}

	private static function enabled( $order ): bool {
		return $order instanceof \WC_Order
			&& License::can( 'customer' )
			&& 'yes' === Settings::get( 'pro_customer_tracking', 'yes' )
			&& '' !== (string) $order->get_meta( '_upwc_barcode' );
	}

	/**
	 * @param mixed $order         Order.
	 * @param bool  $sent_to_admin Admin copy.
	 * @param bool  $plain_text    Plain text e-mail.
	 */
	public static function email( $order, $sent_to_admin = false, $plain_text = false ): void {
		if ( $sent_to_admin || ! self::enabled( $order ) ) {
			return;
		}
		$barcode = (string) $order->get_meta( '_upwc_barcode' );
		$url     = Shipments::TRACK_URL . rawurlencode( $barcode );
		if ( $plain_text ) {
			/* translators: 1: barcode, 2: tracking link. */
			echo "\n" . esc_html( sprintf( __( 'Ukrposhta tracking number: %1$s — %2$s', 'ukrposhta-shipping-for-woocommerce' ), $barcode, $url ) ) . "\n";
			return;
		}
		echo '<h2>' . esc_html__( 'Ukrposhta delivery', 'ukrposhta-shipping-for-woocommerce' ) . '</h2>';
		/* translators: %s: barcode link. */
		echo '<p>' . wp_kses_post( sprintf( __( 'Tracking number: %s', 'ukrposhta-shipping-for-woocommerce' ), '<a href="' . esc_url( $url ) . '">' . esc_html( $barcode ) . '</a>' ) ) . '</p>';
	}

	/**
	 * @param mixed $order Order.
	 */
	public static function account( $order ): void {
		if ( ! self::enabled( $order ) ) {
			return;
		}
		$barcode = (string) $order->get_meta( '_upwc_barcode' );
		$code    = (string) $order->get_meta( '_upwc_ship_status' );
		echo '<section class="upwc-tracking"><h2>' . esc_html__( 'Ukrposhta delivery', 'ukrposhta-shipping-for-woocommerce' ) . '</h2><p>';
		/* translators: %s: barcode link. */
		echo wp_kses_post( sprintf( __( 'Tracking number: %s', 'ukrposhta-shipping-for-woocommerce' ), '<a href="' . esc_url( Shipments::TRACK_URL . rawurlencode( $barcode ) ) . '" target="_blank" rel="noopener">' . esc_html( $barcode ) . '</a>' ) );
		if ( '' !== $code ) {
			echo '<br>' . esc_html__( 'Status:', 'ukrposhta-shipping-for-woocommerce' ) . ' ' . esc_html( Shipments::status_label( $code ) );
		}
		echo '</p></section>';
	}
}
