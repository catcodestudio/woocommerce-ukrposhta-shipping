<?php
/**
 * Order screen meta box: the post office the customer picked at checkout.
 *
 * The picker writes `_upwc_*` meta, and WordPress hides underscore-prefixed
 * keys from the Custom Fields panel — so until this box existed the merchant
 * had no way at all to see where the parcel is supposed to go. Registers for
 * both the legacy post editor and the HPOS order screen.
 *
 * @package CcUkrposhtaWC
 */

namespace CatCode\UkrposhtaWC\Admin;

defined( 'ABSPATH' ) || exit;

class OrderMetaBox {

	public function register_hooks(): void {
		add_action( 'add_meta_boxes', array( $this, 'add' ), 20, 2 );
	}

	/**
	 * @param string $screen_id     Current screen id.
	 * @param mixed  $post_or_order Post (legacy) or order (HPOS).
	 */
	public function add( $screen_id, $post_or_order ): void {
		$screens = array( 'shop_order', 'woocommerce_page_wc-orders' );
		if ( ! in_array( (string) $screen_id, $screens, true ) ) {
			return;
		}
		add_meta_box(
			'upwc-office',
			__( 'Ukrposhta', 'catcode-shipping-with-ukrposhta-for-woocommerce' ),
			array( $this, 'render' ),
			$screen_id,
			'side',
			'default'
		);
	}

	/**
	 * @param mixed $post_or_order Post (legacy) or order (HPOS).
	 */
	public function render( $post_or_order ): void {
		$order = $post_or_order instanceof \WC_Order
			? $post_or_order
			: wc_get_order( is_object( $post_or_order ) ? $post_or_order->ID : (int) $post_or_order );
		if ( ! $order ) {
			return;
		}

		$city      = (string) $order->get_meta( '_upwc_city' );
		$office    = (string) $order->get_meta( '_upwc_office' );
		$postindex = (string) $order->get_meta( '_upwc_postindex' );

		if ( '' === $office && '' === $postindex ) {
			echo '<p>' . esc_html__( 'Ukrposhta was not chosen for this order.', 'catcode-shipping-with-ukrposhta-for-woocommerce' ) . '</p>';
			return;
		}
		?>
		<p>
			<strong><?php esc_html_e( 'City:', 'catcode-shipping-with-ukrposhta-for-woocommerce' ); ?></strong>
			<?php echo esc_html( '' !== $city ? $city : '—' ); ?><br>
			<strong><?php esc_html_e( 'Post office:', 'catcode-shipping-with-ukrposhta-for-woocommerce' ); ?></strong>
			<?php echo esc_html( '' !== $office ? $office : '—' ); ?><br>
			<strong><?php esc_html_e( 'Post index:', 'catcode-shipping-with-ukrposhta-for-woocommerce' ); ?></strong>
			<?php echo esc_html( '' !== $postindex ? $postindex : '—' ); ?>
		</p>
		<?php
	}
}
