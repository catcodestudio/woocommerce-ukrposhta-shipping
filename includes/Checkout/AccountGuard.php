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
 * A guest who creates an account with such an order (the checkbox on either
 * checkout, or "Create account" on the block order-confirmation page) has no
 * address of their own yet: the new account gets an empty address instead of
 * the post office.
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

	/** @var bool This checkout ships with our method (set before WooCommerce writes any customer). */
	private $armed = false;

	/** @var bool The protected account was created by this request. */
	private $new_account = false;

	/** @var int Account created on the order-confirmation page; the order is linked to it right after. */
	private $pending = 0;

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
		// A guest's new account: fires inside wc_create_new_customer(), before
		// the checkout (or the confirmation page) copies the order address.
		add_action( 'woocommerce_created_customer', array( $this, 'created' ), 10, 2 );
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
		if ( $this->armed ) {
			return;
		}
		if ( ! call_user_func( $this->ours, $order instanceof \WC_Order ? $order : null ) ) {
			return;
		}
		$this->armed = true;
		$user_id     = get_current_user_id();
		if ( ! $user_id ) {
			return; // A guest: an account may still be created below, see created().
		}
		$saved = array();
		foreach ( array( 'billing', 'shipping' ) as $group ) {
			foreach ( self::KEYS as $key ) {
				$saved[ $group . '_' . $key ] = (string) get_user_meta( $user_id, $group . '_' . $key, true );
			}
		}
		$this->protect( $user_id, $saved, false );
	}

	/**
	 * A guest's account was just created. During a checkout with our method it
	 * starts without an address; on the order-confirmation page the order is
	 * not linked yet, so the decision waits for the first save (keep_address).
	 *
	 * @param mixed $customer_id New user id.
	 * @param mixed $data        User data passed to wp_insert_user(), with `source`.
	 */
	public function created( $customer_id, $data = array() ): void {
		$customer_id = (int) $customer_id;
		if ( ! $customer_id || $this->user_id ) {
			return;
		}
		if ( $this->armed ) {
			$this->protect( $customer_id, self::blank(), true );
		} elseif ( is_array( $data ) && isset( $data['source'] ) && 'delayed-account-creation' === $data['source'] ) {
			$this->pending = $customer_id;
		}
	}

	/**
	 * Put the saved address back into the account record being written. The
	 * session copy (what the checkout form shows right now) is left alone.
	 *
	 * @param mixed $customer Customer being saved.
	 */
	public function keep_address( $customer ): void {
		if ( ! $customer instanceof \WC_Customer || self::is_session( $customer ) ) {
			return;
		}
		if ( $this->pending && $customer->get_id() === $this->pending ) {
			$this->pending = 0;
			$order         = self::latest_order( $customer->get_id() );
			if ( ! $order || ! call_user_func( $this->ours, $order ) ) {
				return;
			}
			$this->protect( $customer->get_id(), self::blank(), true );
			$this->apply( $customer );
			$this->restore_session();
			return;
		}
		if ( ! $this->user_id || $customer->get_id() !== $this->user_id ) {
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
		if ( ! $this->user_id || ! function_exists( 'WC' ) || ! WC()->customer ) {
			return;
		}
		// A new account's session may still carry the guest id (0) in this request.
		$session_id = WC()->customer->get_id();
		if ( $session_id !== $this->user_id && ! ( $this->new_account && 0 === $session_id ) ) {
			return;
		}
		$this->apply( WC()->customer );
		WC()->customer->save();
	}

	/**
	 * @param array<string,string> $saved Address values to keep in the account.
	 */
	private function protect( int $user_id, array $saved, bool $new_account ): void {
		$this->user_id     = $user_id;
		$this->saved       = $saved;
		$this->new_account = $new_account;
	}

	/** @return array<string,string> An empty address for a new account. */
	private static function blank(): array {
		$out = array();
		foreach ( array( 'billing', 'shipping' ) as $group ) {
			foreach ( self::KEYS as $key ) {
				$out[ $group . '_' . $key ] = '';
			}
		}
		return $out;
	}

	private static function latest_order( int $user_id ): ?\WC_Order {
		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => 1,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);
		return ( $orders && $orders[0] instanceof \WC_Order ) ? $orders[0] : null;
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
