<?php
/**
 * Pro: Ukrposhta shipments from WooCommerce orders — create, delete, labels,
 * form 103a registry and status tracking. Everything is stored in order meta.
 *
 * @package CcUkrposhtaWC
 */

namespace CatCode\UkrposhtaWC\Pro;

use CatCode\UkrposhtaWC\Api\Client;
use CatCode\UkrposhtaWC\Core\Settings;

defined( 'ABSPATH' ) || exit;

class Shipments {

	public const TRACK_URL = 'https://track.ukrposhta.ua/tracking_UA.html?barcode=';

	/** Tracking event code → lifecycle (Status tracking API, table of events). */
	private const EVENTS = array(
		10601 => 'CREATED',
		10100 => 'REGISTERED',
		20700 => 'DELIVERING',
		20800 => 'DELIVERING',
		20900 => 'DELIVERING',
		21500 => 'DELIVERING',
		31100 => 'DELIVERING',
		21700 => 'IN_DEPARTMENT',
		21400 => 'STORAGE',
		31400 => 'FORWARDING',
		41000 => 'DELIVERED',
		48000 => 'DELIVERED',
		41010 => 'RETURNED',
		10600 => 'CANCELLED',
		10602 => 'CANCELLED',
		10603 => 'DELETED',
	);

	/** Lifecycles after which a shipment is not polled again. */
	private const FINAL = array( 'DELIVERED', 'RETURNED', 'CANCELLED', 'DELETED' );

	/** Human names of the lifecycle states. */
	public static function status_label( string $code ): string {
		$map = array(
			'CREATED'       => __( 'Created, waiting to be handed over', 'ukrposhta-shipping-for-woocommerce' ),
			'REGISTERED'    => __( 'Accepted at the post office', 'ukrposhta-shipping-for-woocommerce' ),
			'DELIVERING'    => __( 'On the way', 'ukrposhta-shipping-for-woocommerce' ),
			'IN_DEPARTMENT' => __( 'Arrived at the post office', 'ukrposhta-shipping-for-woocommerce' ),
			'STORAGE'       => __( 'In storage', 'ukrposhta-shipping-for-woocommerce' ),
			'FORWARDING'    => __( 'Not handed over, being forwarded', 'ukrposhta-shipping-for-woocommerce' ),
			'DELIVERED'     => __( 'Delivered', 'ukrposhta-shipping-for-woocommerce' ),
			'RETURNED'      => __( 'Returned to the sender', 'ukrposhta-shipping-for-woocommerce' ),
			'CANCELLED'     => __( 'Acceptance cancelled', 'ukrposhta-shipping-for-woocommerce' ),
			'DELETED'       => __( 'Deleted', 'ukrposhta-shipping-for-woocommerce' ),
		);
		return $map[ $code ] ?? $code;
	}

	/** Setting with a default. */
	private static function opt( string $key, $fallback = '' ) {
		return Settings::get( $key, $fallback );
	}

	/**
	 * What is still missing before shipments can be created, as sentences.
	 *
	 * @return string[]
	 */
	public static function setup_gaps(): array {
		$gaps = array();
		if ( '' === Settings::secret( 'bearer' ) ) {
			$gaps[] = __( 'Enter the Bearer eCom key.', 'ukrposhta-shipping-for-woocommerce' );
		}
		if ( '' === Settings::secret( 'token' ) ) {
			$gaps[] = __( 'Enter the counterparty token (user token) from the Ukrposhta contract.', 'ukrposhta-shipping-for-woocommerce' );
		}
		if ( '' === Client::postcode5( (string) self::opt( 'sender_postcode', '' ) ) ) {
			$gaps[] = __( 'Enter the sender post index.', 'ukrposhta-shipping-for-woocommerce' );
		}
		if ( '' === trim( (string) self::opt( 'sender_uuid', '' ) ) ) {
			$gaps[] = __( 'Create the sender: fill in the sender details and press "Create sender".', 'ukrposhta-shipping-for-woocommerce' );
		}
		return $gaps;
	}

	/** Phone as Ukrposhta's own examples write it: 0XXXXXXXXX. */
	public static function phone( string $raw ): string {
		$d = (string) preg_replace( '/\D+/', '', $raw );
		if ( 12 === strlen( $d ) && 0 === strpos( $d, '380' ) ) {
			return substr( $d, 2 );
		}
		if ( 9 === strlen( $d ) ) {
			return '0' . $d;
		}
		return $d;
	}

	/**
	 * Name split into last / first / middle for an INDIVIDUAL client.
	 *
	 * @return array{0:string,1:string,2:string} last, first, middle.
	 */
	private static function split_name( string $first, string $last ): array {
		$first = trim( preg_replace( '/\s+/u', ' ', $first ) );
		$last  = trim( preg_replace( '/\s+/u', ' ', $last ) );
		$middle = '';
		if ( '' !== $first && false !== strpos( $first, ' ' ) ) {
			list( $first, $middle ) = array_pad( explode( ' ', $first, 2 ), 2, '' );
		}
		if ( '' === $last && false !== strpos( $first, ' ' ) ) {
			list( $first, $last ) = explode( ' ', $first, 2 );
		}
		return array( $last, $first, $middle );
	}

	/**
	 * Create the sender client from the settings and store its uuid.
	 *
	 * @return array{ok:bool,message:string}
	 */
	public static function create_sender(): array {
		$bearer = Settings::secret( 'bearer' );
		$token  = Settings::secret( 'token' );
		if ( '' === $bearer || '' === $token ) {
			return array(
				'ok'      => false,
				'message' => __( 'Save the Bearer eCom key and the counterparty token first.', 'ukrposhta-shipping-for-woocommerce' ),
			);
		}
		$postcode = Client::postcode5( (string) self::opt( 'sender_postcode', '' ) );
		$phone    = self::phone( (string) self::opt( 'sender_phone', '' ) );
		$type     = (string) self::opt( 'sender_type', 'INDIVIDUAL' );
		if ( '' === $postcode || strlen( $phone ) < 10 ) {
			return array(
				'ok'      => false,
				'message' => __( 'Fill in the sender post index and phone and save the settings.', 'ukrposhta-shipping-for-woocommerce' ),
			);
		}
		$api  = new Api( $bearer, $token, Settings::secret( 'tracking_bearer' ), Settings::is_sandbox() );
		$addr = $api->create_address( $postcode );
		if ( empty( $addr['success'] ) || empty( $addr['data']['id'] ) ) {
			return array(
				'ok'      => false,
				'message' => self::fail_text( __( 'Ukrposhta did not accept the sender address', 'ukrposhta-shipping-for-woocommerce' ), $addr ),
			);
		}
		$client = array(
			'type'        => in_array( $type, array( 'INDIVIDUAL', 'PRIVATE_ENTREPRENEUR', 'COMPANY' ), true ) ? $type : 'INDIVIDUAL',
			'addressId'   => (int) $addr['data']['id'],
			'phoneNumber' => $phone,
		);
		if ( 'COMPANY' === $client['type'] ) {
			$client['name']   = trim( (string) self::opt( 'sender_company', '' ) );
			$client['edrpou'] = (string) preg_replace( '/\D/', '', (string) self::opt( 'sender_code', '' ) );
		} else {
			$client['lastName']   = trim( (string) self::opt( 'sender_last_name', '' ) );
			$client['firstName']  = trim( (string) self::opt( 'sender_first_name', '' ) );
			$client['middleName'] = trim( (string) self::opt( 'sender_middle_name', '' ) );
			if ( 'PRIVATE_ENTREPRENEUR' === $client['type'] ) {
				$client['tin']  = (string) preg_replace( '/\D/', '', (string) self::opt( 'sender_code', '' ) );
				$client['name'] = trim( (string) self::opt( 'sender_company', '' ) );
				if ( '' === $client['name'] ) {
					unset( $client['name'] );
				}
			}
			if ( '' === $client['middleName'] ) {
				unset( $client['middleName'] );
			}
		}
		$iban = strtoupper( (string) preg_replace( '/\s+/', '', (string) self::opt( 'sender_iban', '' ) ) );
		if ( '' !== $iban ) {
			$client['bankAccount'] = $iban;
		}
		$resp = $api->create_client( $client );
		if ( empty( $resp['success'] ) || empty( $resp['data']['uuid'] ) ) {
			return array(
				'ok'      => false,
				'message' => self::fail_text( __( 'Ukrposhta did not create the sender', 'ukrposhta-shipping-for-woocommerce' ), $resp ),
			);
		}
		$opt                = Settings::all();
		$opt['sender_uuid'] = (string) $resp['data']['uuid'];
		update_option( Settings::OPTION, $opt, 'yes' );
		return array(
			'ok'      => true,
			/* translators: %s: sender uuid. */
			'message' => sprintf( __( 'Sender created: %s', 'ukrposhta-shipping-for-woocommerce' ), $opt['sender_uuid'] ),
		);
	}

	/** Error sentence for the admin: what we tried + what Ukrposhta said. */
	private static function fail_text( string $what, array $resp ): string {
		$errors = isset( $resp['errors'] ) && is_array( $resp['errors'] ) ? $resp['errors'] : array();
		if ( 0 === (int) ( $resp['status'] ?? -1 ) ) {
			return $what . ': ' . __( 'Ukrposhta did not answer, try again in a minute.', 'ukrposhta-shipping-for-woocommerce' );
		}
		return $what . ( $errors ? ': ' . implode( '; ', $errors ) : '.' );
	}

	/** Is this order shipped with Ukrposhta (a post office was picked)? */
	public static function is_ukrposhta_order( \WC_Order $order ): bool {
		return '' !== (string) $order->get_meta( '_upwc_postindex' );
	}

	/** Total weight in grams, with the default for products without weight. */
	public static function weight_g( \WC_Order $order ): int {
		$kg = 0.0;
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$product = $item->get_product();
			if ( $product && (float) $product->get_weight() > 0 ) {
				$kg += wc_get_weight( (float) $product->get_weight(), 'kg' ) * (int) $item->get_quantity();
			}
		}
		if ( $kg <= 0 ) {
			$kg = (float) str_replace( ',', '.', (string) self::opt( 'default_weight', '0.5' ) );
		}
		return (int) max( 1, round( $kg * 1000 ) );
	}

	/** Order paid on collection, by the cash-on-delivery gateways in the settings. */
	public static function is_cod_order( \WC_Order $order ): bool {
		$ids = array_filter( array_map( 'trim', explode( ',', (string) self::opt( 'cod_gateways', 'cod' ) ) ) );
		return in_array( (string) $order->get_payment_method(), $ids, true );
	}

	/** Goods value: items after discounts, without shipping. */
	private static function goods_value( \WC_Order $order ): float {
		return round( (float) $order->get_subtotal() - (float) $order->get_discount_total(), 2 );
	}

	/**
	 * The shipment body for an order — also shown in the order box before creation.
	 *
	 * @return array<string,mixed>
	 */
	public static function draft( \WC_Order $order ): array {
		$goods = max( 0.0, self::goods_value( $order ) );
		$cod   = 0.0;
		if ( 'yes' === self::opt( 'pro_cod', 'yes' ) && self::is_cod_order( $order ) ) {
			$cod = 'goods' === self::opt( 'pro_cod_amount', 'total' ) ? $goods : round( (float) $order->get_total(), 2 );
		}
		$declared = 'yes' === self::opt( 'declared_value', 'yes' ) ? $goods : 0.0;
		// Cash on delivery may not exceed the declared value (section 4, postPay).
		if ( $cod > 0 && $declared < $cod ) {
			$declared = $cod;
		}
		return array(
			'weight'   => self::weight_g( $order ),
			'length'   => max( 1, (int) self::opt( 'default_length', 30 ) ),
			'width'    => max( 1, (int) self::opt( 'default_width', 20 ) ),
			'height'   => max( 1, (int) self::opt( 'default_height', 10 ) ),
			'declared' => $declared,
			'cod'      => $cod,
			'type'     => (string) self::opt( 'service_type', 'STANDARD' ),
			'postcode' => Client::postcode5( (string) $order->get_meta( '_upwc_postindex' ) ),
		);
	}

	/**
	 * Create the shipment for an order.
	 *
	 * @return array{ok:bool,message:string,barcode?:string}
	 */
	public static function create( \WC_Order $order ): array {
		if ( ! License::can( 'shipment' ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Creating shipments is a Pro feature.', 'ukrposhta-shipping-for-woocommerce' ),
			);
		}
		$existing = (string) $order->get_meta( '_upwc_barcode' );
		if ( '' !== $existing ) {
			return array(
				'ok'      => true,
				/* translators: %s: barcode. */
				'message' => sprintf( __( 'The shipment already exists: %s', 'ukrposhta-shipping-for-woocommerce' ), $existing ),
				'barcode' => $existing,
			);
		}
		if ( ! self::is_ukrposhta_order( $order ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'No Ukrposhta post office was picked for this order.', 'ukrposhta-shipping-for-woocommerce' ),
			);
		}
		$gaps = self::setup_gaps();
		if ( $gaps ) {
			return array(
				'ok'      => false,
				'message' => implode( ' ', $gaps ),
			);
		}

		global $wpdb;
		$lock = 'upwc_ship_' . $order->get_id();
		// One shipment per order even on a double click or two admins at once.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'The shipment for this order is being created right now. Reload the page in a few seconds.', 'ukrposhta-shipping-for-woocommerce' ),
			);
		}
		try {
			$fresh = wc_get_order( $order->get_id() );
			if ( $fresh && '' !== (string) $fresh->get_meta( '_upwc_barcode' ) ) {
				return array(
					'ok'      => true,
					/* translators: %s: barcode. */
					'message' => sprintf( __( 'The shipment already exists: %s', 'ukrposhta-shipping-for-woocommerce' ), (string) $fresh->get_meta( '_upwc_barcode' ) ),
					'barcode' => (string) $fresh->get_meta( '_upwc_barcode' ),
				);
			}
			return self::create_locked( $fresh ? $fresh : $order );
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/**
	 * @return array{ok:bool,message:string,barcode?:string}
	 */
	private static function create_locked( \WC_Order $order ): array {
		$api   = Api::from_settings();
		$draft = self::draft( $order );
		if ( ! $api ) {
			return array(
				'ok'      => false,
				'message' => implode( ' ', self::setup_gaps() ),
			);
		}

		$phone = self::phone( (string) ( $order->get_shipping_phone() ? $order->get_shipping_phone() : $order->get_billing_phone() ) );
		$first = $order->get_shipping_first_name() ? $order->get_shipping_first_name() : $order->get_billing_first_name();
		$last  = $order->get_shipping_last_name() ? $order->get_shipping_last_name() : $order->get_billing_last_name();
		list( $last, $first, $middle ) = self::split_name( (string) $first, (string) $last );

		if ( strlen( $phone ) < 10 ) {
			return self::fail( $order, __( 'The customer phone is missing or too short — Ukrposhta needs a mobile number for delivery to a post office.', 'ukrposhta-shipping-for-woocommerce' ) );
		}
		if ( mb_strlen( $first ) < 2 || mb_strlen( $last ) < 2 ) {
			return self::fail( $order, __( 'Ukrposhta needs the first and last name of the recipient, at least 2 letters each. Edit the shipping name of the order.', 'ukrposhta-shipping-for-woocommerce' ) );
		}

		$recipient = (string) $order->get_meta( '_upwc_recipient_uuid' );
		if ( '' === $recipient ) {
			$addr = $api->create_address( $draft['postcode'] );
			if ( empty( $addr['success'] ) || empty( $addr['data']['id'] ) ) {
				return self::fail( $order, self::fail_text( __( 'Ukrposhta did not accept the recipient post office', 'ukrposhta-shipping-for-woocommerce' ), $addr ) );
			}
			$client = array(
				'type'        => 'INDIVIDUAL',
				'lastName'    => $last,
				'firstName'   => $first,
				'addressId'   => (int) $addr['data']['id'],
				'phoneNumber' => $phone,
				'externalId'  => 'wc-' . $order->get_id(),
			);
			if ( '' !== $middle ) {
				$client['middleName'] = $middle;
			}
			$email = (string) $order->get_billing_email();
			if ( '' !== $email ) {
				$client['email'] = $email;
			}
			$resp = $api->create_client( $client );
			if ( empty( $resp['success'] ) || empty( $resp['data']['uuid'] ) ) {
				return self::fail( $order, self::fail_text( __( 'Ukrposhta did not create the recipient', 'ukrposhta-shipping-for-woocommerce' ), $resp ) );
			}
			$recipient = (string) $resp['data']['uuid'];
			$order->update_meta_data( '_upwc_recipient_uuid', $recipient );
			$order->save();
		}

		$parcel = array(
			'weight' => $draft['weight'],
			'length' => $draft['length'],
			'width'  => $draft['width'],
			'height' => $draft['height'],
		);
		if ( $draft['declared'] > 0 ) {
			$parcel['declaredPrice'] = $draft['declared'];
		}
		$body = array(
			'sender'            => array( 'uuid' => (string) self::opt( 'sender_uuid', '' ) ),
			'recipient'         => array( 'uuid' => $recipient ),
			'type'              => $draft['type'],
			'deliveryType'      => 'W2W',
			'paidByRecipient'   => 'yes' === self::opt( 'pro_paid_by_recipient', 'yes' ),
			'externalId'        => (string) $order->get_order_number(),
			/* translators: %s: order number. */
			'description'       => sprintf( __( 'Order %s', 'ukrposhta-shipping-for-woocommerce' ), $order->get_order_number() ),
			'onFailReceiveType' => (string) self::opt( 'pro_on_fail', 'RETURN' ),
			'sms'               => 'yes' === self::opt( 'pro_sms', 'no' ),
			'parcels'           => array( $parcel ),
		);
		if ( $draft['cod'] > 0 ) {
			$body['postPay']                = $draft['cod'];
			$body['postPayPaidByRecipient'] = 'yes' === self::opt( 'pro_cod_fee_recipient', 'yes' );
			// To a bank account only for a FOP or a company sender (section 4, postPay).
			if ( 'INDIVIDUAL' !== (string) self::opt( 'sender_type', 'INDIVIDUAL' ) && '' !== trim( (string) self::opt( 'sender_iban', '' ) ) ) {
				$body['transferPostPayToBankAccount'] = true;
			}
		}

		$resp = $api->create_shipment( $body );
		if ( empty( $resp['success'] ) || empty( $resp['data']['barcode'] ) ) {
			return self::fail( $order, self::fail_text( __( 'Ukrposhta did not create the shipment', 'ukrposhta-shipping-for-woocommerce' ), $resp ) );
		}
		$barcode = (string) $resp['data']['barcode'];
		$order->update_meta_data( '_upwc_barcode', $barcode );
		$order->update_meta_data( '_upwc_shipment_uuid', (string) ( $resp['data']['uuid'] ?? '' ) );
		$order->update_meta_data( '_upwc_delivery_price', (string) ( $resp['data']['deliveryPrice'] ?? '' ) );
		$order->update_meta_data( '_upwc_cod', (string) $draft['cod'] );
		$order->update_meta_data( '_upwc_ship_status', 'CREATED' );
		$order->update_meta_data( '_upwc_ship_status_at', gmdate( 'Y-m-d H:i:s' ) );
		$order->update_meta_data( '_upwc_ship_error', '' );
		$order->delete_meta_data( '_upwc_group_uuid' );
		$order->save();
		$note = $draft['cod'] > 0
			/* translators: 1: barcode, 2: cash on delivery amount. */
			? sprintf( __( 'Ukrposhta: shipment %1$s created, cash on delivery %2$s.', 'ukrposhta-shipping-for-woocommerce' ), $barcode, wp_strip_all_tags( wc_price( $draft['cod'], array( 'currency' => $order->get_currency() ) ) ) )
			/* translators: %s: barcode. */
			: sprintf( __( 'Ukrposhta: shipment %s created.', 'ukrposhta-shipping-for-woocommerce' ), $barcode );
		$order->add_order_note( $note );

		return array(
			'ok'      => true,
			/* translators: %s: barcode. */
			'message' => sprintf( __( 'Shipment created: %s', 'ukrposhta-shipping-for-woocommerce' ), $barcode ),
			'barcode' => $barcode,
		);
	}

	/**
	 * Remember the failure on the order (shown in the box) and say it.
	 *
	 * @return array{ok:bool,message:string}
	 */
	private static function fail( \WC_Order $order, string $message ): array {
		$order->update_meta_data( '_upwc_ship_error', $message );
		$order->save();
		return array(
			'ok'      => false,
			'message' => $message,
		);
	}

	/**
	 * Delete a shipment that is not handed over yet.
	 *
	 * @return array{ok:bool,message:string}
	 */
	public static function delete( \WC_Order $order ): array {
		if ( ! License::can( 'delete' ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Deleting shipments is a Pro feature.', 'ukrposhta-shipping-for-woocommerce' ),
			);
		}
		$uuid    = (string) $order->get_meta( '_upwc_shipment_uuid' );
		$barcode = (string) $order->get_meta( '_upwc_barcode' );
		if ( '' === $barcode ) {
			return array(
				'ok'      => false,
				'message' => __( 'This order has no shipment.', 'ukrposhta-shipping-for-woocommerce' ),
			);
		}
		$status = (string) $order->get_meta( '_upwc_ship_status' );
		if ( '' !== $status && 'CREATED' !== $status ) {
			return array(
				'ok'      => false,
				'message' => __( 'The shipment is already accepted at the post office and cannot be deleted.', 'ukrposhta-shipping-for-woocommerce' ),
			);
		}
		$api = Api::from_settings();
		if ( ! $api ) {
			return array(
				'ok'      => false,
				'message' => implode( ' ', self::setup_gaps() ),
			);
		}
		$resp = $api->delete_shipment( '' !== $uuid ? $uuid : $barcode );
		if ( empty( $resp['success'] ) ) {
			return array(
				'ok'      => false,
				'message' => self::fail_text( __( 'Ukrposhta did not delete the shipment', 'ukrposhta-shipping-for-woocommerce' ), $resp ),
			);
		}
		foreach ( array( '_upwc_barcode', '_upwc_shipment_uuid', '_upwc_delivery_price', '_upwc_cod', '_upwc_ship_status', '_upwc_ship_status_at', '_upwc_ship_event', '_upwc_group_uuid', '_upwc_tracked_at' ) as $k ) {
			$order->delete_meta_data( $k );
		}
		$order->save();
		/* translators: %s: barcode. */
		$order->add_order_note( sprintf( __( 'Ukrposhta: shipment %s deleted.', 'ukrposhta-shipping-for-woocommerce' ), $barcode ) );
		return array(
			'ok'      => true,
			/* translators: %s: barcode. */
			'message' => sprintf( __( 'Shipment %s deleted.', 'ukrposhta-shipping-for-woocommerce' ), $barcode ),
		);
	}

	/** Label size option → API value. */
	public static function label_size(): string {
		$size = (string) self::opt( 'pro_label_size', '' );
		return in_array( $size, array( 'SIZE_A4', 'SIZE_A5' ), true ) ? $size : '';
	}

	/**
	 * Labels for orders: one PDF.
	 *
	 * @param \WC_Order[] $orders Orders.
	 * @return array{ok:bool,message:string,pdf?:string}
	 */
	public static function labels( array $orders ): array {
		if ( ! License::can( 'labels' ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Printing labels is a Pro feature.', 'ukrposhta-shipping-for-woocommerce' ),
			);
		}
		$barcodes = array();
		foreach ( $orders as $o ) {
			$b = (string) $o->get_meta( '_upwc_barcode' );
			if ( '' !== $b ) {
				$barcodes[] = $b;
			}
		}
		if ( ! $barcodes ) {
			return array(
				'ok'      => false,
				'message' => __( 'None of the selected orders has a shipment yet. Create the shipments first.', 'ukrposhta-shipping-for-woocommerce' ),
			);
		}
		$api = Api::from_settings();
		if ( ! $api ) {
			return array(
				'ok'      => false,
				'message' => implode( ' ', self::setup_gaps() ),
			);
		}
		$resp = 1 === count( $barcodes ) ? $api->sticker( $barcodes[0], self::label_size() ) : $api->stickers( $barcodes, self::label_size() );
		if ( empty( $resp['success'] ) ) {
			return array(
				'ok'      => false,
				'message' => self::fail_text( __( 'Ukrposhta did not return the labels', 'ukrposhta-shipping-for-woocommerce' ), $resp ),
			);
		}
		return array(
			'ok'      => true,
			'message' => '',
			'pdf'     => (string) $resp['body'],
		);
	}

	/**
	 * Form 103a for orders: a new shipment group with all their shipments.
	 *
	 * @param \WC_Order[] $orders Orders.
	 * @return array{ok:bool,message:string,pdf?:string}
	 */
	public static function registry( array $orders ): array {
		if ( ! License::can( 'registry' ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'The form 103a registry is a Pro feature.', 'ukrposhta-shipping-for-woocommerce' ),
			);
		}
		$with = array();
		foreach ( $orders as $o ) {
			if ( '' !== (string) $o->get_meta( '_upwc_shipment_uuid' ) ) {
				$with[] = $o;
			}
		}
		if ( ! $with ) {
			return array(
				'ok'      => false,
				'message' => __( 'None of the selected orders has a shipment yet. Create the shipments first.', 'ukrposhta-shipping-for-woocommerce' ),
			);
		}
		$api = Api::from_settings();
		if ( ! $api ) {
			return array(
				'ok'      => false,
				'message' => implode( ' ', self::setup_gaps() ),
			);
		}
		// All of them already in one group → print that group again.
		$groups = array_unique( array_map( static function ( $o ) {
			return (string) $o->get_meta( '_upwc_group_uuid' );
		}, $with ) );
		if ( 1 === count( $groups ) && '' !== reset( $groups ) ) {
			$group = (string) reset( $groups );
		} else {
			$created = $api->create_group(
				/* translators: %s: date and time. */
				sprintf( __( 'Shop %s', 'ukrposhta-shipping-for-woocommerce' ), wp_date( 'd.m.Y H:i' ) ),
				(string) self::opt( 'sender_uuid', '' ),
				(string) self::opt( 'service_type', 'STANDARD' )
			);
			if ( empty( $created['success'] ) || empty( $created['data']['uuid'] ) ) {
				return array(
					'ok'      => false,
					'message' => self::fail_text( __( 'Ukrposhta did not create the shipment group', 'ukrposhta-shipping-for-woocommerce' ), $created ),
				);
			}
			$group  = (string) $created['data']['uuid'];
			$failed = array();
			foreach ( $with as $o ) {
				$add = $api->add_to_group( $group, (string) $o->get_meta( '_upwc_shipment_uuid' ) );
				if ( empty( $add['success'] ) ) {
					$failed[] = $o->get_order_number() . ' (' . implode( '; ', (array) ( $add['errors'] ?? array() ) ) . ')';
					continue;
				}
				$o->update_meta_data( '_upwc_group_uuid', $group );
				$o->save();
			}
			if ( count( $failed ) === count( $with ) ) {
				return array(
					'ok'      => false,
					/* translators: %s: order numbers with reasons. */
					'message' => sprintf( __( 'Ukrposhta did not add the shipments to the group: %s', 'ukrposhta-shipping-for-woocommerce' ), implode( ', ', $failed ) ),
				);
			}
		}
		$resp = $api->form103a( $group );
		if ( empty( $resp['success'] ) ) {
			return array(
				'ok'      => false,
				'message' => self::fail_text( __( 'Ukrposhta did not return form 103a', 'ukrposhta-shipping-for-woocommerce' ), $resp ),
			);
		}
		return array(
			'ok'      => true,
			'message' => '',
			'pdf'     => (string) $resp['body'],
		);
	}

	/**
	 * Refresh the delivery status of the given orders (≤ 50 per call).
	 *
	 * @param \WC_Order[] $orders Orders with a barcode.
	 * @return int Orders whose status changed.
	 */
	public static function track( array $orders ): int {
		if ( ! License::can( 'tracking' ) ) {
			return 0;
		}
		$by = array();
		foreach ( $orders as $o ) {
			$b = (string) $o->get_meta( '_upwc_barcode' );
			if ( '' !== $b ) {
				$by[ $b ] = $o;
			}
		}
		if ( ! $by ) {
			return 0;
		}
		$api = Api::from_settings();
		if ( ! $api ) {
			return 0;
		}
		$changed = 0;
		foreach ( array_chunk( array_keys( $by ), 50 ) as $chunk ) {
			$resp = $api->statuses( $chunk );
			$last = array();
			if ( ! empty( $resp['success'] ) && is_array( $resp['data'] ) ) {
				foreach ( $resp['data'] as $row ) {
					if ( ! is_array( $row ) || empty( $row['barcode'] ) ) {
						continue;
					}
					$b = (string) $row['barcode'];
					if ( ! isset( $last[ $b ] ) || (int) ( $row['step'] ?? 0 ) >= (int) ( $last[ $b ]['step'] ?? 0 ) ) {
						$last[ $b ] = $row;
					}
				}
			}
			foreach ( $chunk as $b ) {
				$o = $by[ $b ];
				$o->update_meta_data( '_upwc_tracked_at', gmdate( 'Y-m-d H:i:s' ) );
				if ( isset( $last[ $b ] ) && self::apply_status( $o, $last[ $b ] ) ) {
					++$changed;
				}
				$o->save();
			}
		}
		return $changed;
	}

	/**
	 * Store one tracking row on the order; true when the status changed.
	 *
	 * @param array<string,mixed> $row Status row.
	 */
	private static function apply_status( \WC_Order $order, array $row ): bool {
		$event = (int) ( $row['event'] ?? 0 );
		$code  = self::EVENTS[ $event ] ?? '';
		if ( '' === $code ) {
			$code = 'DELIVERING';
		}
		if ( (string) $order->get_meta( '_upwc_ship_event' ) === (string) $event && (string) $order->get_meta( '_upwc_ship_status' ) === $code ) {
			return false;
		}
		$name = trim( (string) ( $row['eventName'] ?? '' ) );
		$where = trim( (string) ( $row['name'] ?? '' ) );
		$order->update_meta_data( '_upwc_ship_status', $code );
		$order->update_meta_data( '_upwc_ship_event', (string) $event );
		$order->update_meta_data( '_upwc_ship_event_name', $name );
		$order->update_meta_data( '_upwc_ship_status_at', (string) ( $row['date'] ?? gmdate( 'Y-m-d H:i:s' ) ) );
		$order->add_order_note(
			/* translators: 1: barcode, 2: status, 3: post office. */
			sprintf( __( 'Ukrposhta %1$s: %2$s%3$s', 'ukrposhta-shipping-for-woocommerce' ), (string) $order->get_meta( '_upwc_barcode' ), '' !== $name ? $name : self::status_label( $code ), '' !== $where ? ' (' . $where . ')' : '' )
		);
		$target = '';
		if ( 'DELIVERED' === $code ) {
			$target = (string) self::opt( 'pro_status_delivered', 'completed' );
		} elseif ( 'RETURNED' === $code ) {
			$target = (string) self::opt( 'pro_status_returned', '' );
		}
		$target = (string) preg_replace( '/^wc-/', '', $target );
		if ( '' !== $target && $order->get_status() !== $target && array_key_exists( 'wc-' . $target, wc_get_order_statuses() ) ) {
			$order->set_status( $target, __( 'Ukrposhta status:', 'ukrposhta-shipping-for-woocommerce' ) . ' ' . self::status_label( $code ) . '.' );
		}
		return true;
	}

	/**
	 * Cron: orders with an open shipment that were not checked for 2 hours.
	 */
	public static function cron(): void {
		if ( ! License::can( 'tracking' ) || 'yes' !== self::opt( 'pro_tracking', 'yes' ) ) {
			return;
		}
		$orders = wc_get_orders(
			array(
				'limit'      => 100,
				'type'       => 'shop_order',
				'status'     => array_keys( wc_get_order_statuses() ),
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_upwc_barcode',
						'compare' => 'EXISTS',
					),
				),
				'date_created' => '>' . ( time() - 60 * DAY_IN_SECONDS ),
			)
		);
		$due = array();
		foreach ( $orders as $o ) {
			if ( in_array( (string) $o->get_meta( '_upwc_ship_status' ), self::FINAL, true ) ) {
				continue;
			}
			$at = (string) $o->get_meta( '_upwc_tracked_at' );
			if ( '' !== $at && ( time() - (int) strtotime( $at . ' UTC' ) ) < 2 * HOUR_IN_SECONDS ) {
				continue;
			}
			$due[] = $o;
		}
		if ( $due ) {
			self::track( array_slice( $due, 0, 50 ) );
		}
	}
}
