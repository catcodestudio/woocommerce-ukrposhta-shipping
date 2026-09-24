<?php
/**
 * Keeps a logged-in buyer's saved address when the order ships to a post office.
 *
 * The picker writes the chosen post office into the checkout address fields (they
 * are hidden while the method is selected, but WooCommerce still validates
 * them), and the order carries the post office as its delivery address. WooCommerce
 * then copies the checkout address into the customer's account: the classic
 * checkout in WC_Checkout::process_customer(), the block checkout in
 * OrderController::sync_customer_data_with_order(). The next checkout offered
 * the post office instead of the buyer's own street. While an order with this
 * method is placed, the address part of the account stays as it was; name,
 * phone and e-mail are saved as usual.
 *
 * @package CcUkrposhtaWC
 */

namespace CatCode\UkrposhtaWC\Checkout;

defined( 'ABSPATH' ) || exit;

class AccountGuard {

	/** Address props the picker (or the order it fills) may overwrite. */
	private const KEYS = array( 'address_1', 'address_2', 'city', 'state', 'postcode' );

	/** @var callable(?\WC_Order):bool Does this checkout ship with our method? */
	private $ours;

	/** @var int Customer whose account is protected in this request. */
	private $user_id = 0;

	/** @var array<string,string> Account values before the checkout, e.g. `shipping_city`. */
	private $saved = array();

	/**
	 * @param callable $ours Receives the order (block checkout) or null (classic) and tells whether it ships with our method.
	 */
	public function __construct( callable $ours ) {
		$this->ours = $ours;
	}

	public function register_hooks(): void {
		// Both fire before WooCommerce writes the customer: classic
		// process_customer() runs after checkout_process, the Store API
		// process_customer() after update_order_from_request.
		add_action( 'woocommerce_checkout_process', array( $this, 'arm' ), 1, 0 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'arm' ), 1, 1 );
		add_action( 'woocommerce_before_customer_object_save', array( $this, 'keep_address' ), 10, 1 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'restore_session' ), 99, 0 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'restore_session' ), 99, 0 );
	}

	/**
	 * Remember the account address before the checkout touches it.
	 *
	 * @param mixed $order Draft order on the block checkout, nothing on the classic one.
	 */
	public function arm( $order = null ): void {
		$user_id = get_current_user_id();
		if ( ! $user_id || $this->user_id === $user_id ) {
			return;
		}
		if ( ! call_user_func( $this->ours, $order instanceof \WC_Order ? $order : null ) ) {
			return;
		}
		$this->user_id = $user_id;
		$this->saved   = array();
		foreach ( array( 'billing', 'shipping' ) as $group ) {
			foreach ( self::KEYS as $key ) {
				$this->saved[ $group . '_' . $key ] = (string) get_user_meta( $user_id, $group . '_' . $key, true );
			}
		}
	}

	/**
	 * Put the saved address back into the account record being written. The
	 * session copy (what the checkout form shows right now) is left alone.
	 *
	 * @param mixed $customer Customer being saved.
	 */
	public function keep_address( $customer ): void {
		if ( ! $this->user_id || ! $customer instanceof \WC_Customer || $customer->get_id() !== $this->user_id || self::is_session( $customer ) ) {
			return;
		}
		$this->apply( $customer );
	}

	/**
	 * After the order is placed, the session customer still holds the post office,
	 * and it outlives the log-out: WooCommerce keys a logged-in session by the
	 * user id. Give it the account address back for the next checkout.
	 */
	public function restore_session(): void {
		if ( ! $this->user_id || ! function_exists( 'WC' ) || ! WC()->customer || WC()->customer->get_id() !== $this->user_id ) {
			return;
		}
		$this->apply( WC()->customer );
		WC()->customer->save();
	}

	private function apply( \WC_Customer $customer ): void {
		foreach ( $this->saved as $prop => $value ) {
			$getter = 'get_' . $prop;
			$setter = 'set_' . $prop;
			if ( is_callable( array( $customer, $setter ) ) && (string) $customer->{$getter}( 'edit' ) !== $value ) {
				$customer->{$setter}( $value );
			}
		}
	}

	private static function is_session( \WC_Customer $customer ): bool {
		$store = $customer->get_data_store();
		return is_object( $store ) && method_exists( $store, 'get_current_class_name' )
			&& false !== stripos( (string) $store->get_current_class_name(), 'session' );
	}
}
