<?php
/**
 * WooCommerce shipping method: Ukrposhta. Holds all plugin settings (its option
 * is `woocommerce_ukrposhta_settings`) and computes the domestic tariff.
 *
 * @package CcUkrposhtaWC
 */

namespace CatCode\UkrposhtaWC\Shipping;

use CatCode\UkrposhtaWC\Api\Client;
use CatCode\UkrposhtaWC\Core\Crypto;
use CatCode\UkrposhtaWC\Core\Settings;

defined( 'ABSPATH' ) || exit;

class Method extends \WC_Shipping_Method {

	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'ukrposhta';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'Ukrposhta', 'ukrposhta-shipping-for-woocommerce' );
		$this->method_description = __( 'Ukrposhta delivery: the customer picks a post office at checkout (region -> city -> office) and the tariff is quoted live.', 'ukrposhta-shipping-for-woocommerce' );
		$this->supports           = array( 'shipping-zones', 'instance-settings', 'settings' );

		$this->init();
	}

	public function init(): void {
		$this->init_form_fields();
		$this->init_settings();

		$this->title   = $this->get_option( 'title', __( 'Ukrposhta', 'ukrposhta-shipping-for-woocommerce' ) );
		$this->enabled = $this->get_option( 'enabled', 'yes' );

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled'          => array(
				'title'   => __( 'Enable', 'ukrposhta-shipping-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Show Ukrposhta at checkout', 'ukrposhta-shipping-for-woocommerce' ),
				'default' => 'yes',
			),
			'title'            => array(
				'title'   => __( 'Method title', 'ukrposhta-shipping-for-woocommerce' ),
				'type'    => 'text',
				'default' => __( 'Ukrposhta - delivery to a post office', 'ukrposhta-shipping-for-woocommerce' ),
			),
			'sandbox'          => array(
				'title'   => __( 'Test environment', 'ukrposhta-shipping-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Sandbox (dev.ukrposhta.ua)', 'ukrposhta-shipping-for-woocommerce' ),
				'default' => 'no',
			),
			'bearer'           => array(
				'title'       => __( 'Bearer eCom', 'ukrposhta-shipping-for-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'Issued by Ukrposhta once your eCom contract is signed. Needed for both the address classifier and the tariff. Stored encrypted. Leave empty to keep the current key.', 'ukrposhta-shipping-for-woocommerce' ),
				'default'     => '',
			),
			'sender_postcode'  => array(
				'title'       => __( 'Sender post index', 'ukrposhta-shipping-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Post index the parcels are sent from. Required for the tariff.', 'ukrposhta-shipping-for-woocommerce' ),
				'default'     => '',
			),
			'service_type'     => array(
				'title'   => __( 'Service type', 'ukrposhta-shipping-for-woocommerce' ),
				'type'    => 'select',
				'options' => array(
					'STANDARD' => __( 'Ukrposhta Standard', 'ukrposhta-shipping-for-woocommerce' ),
					'EXPRESS'  => __( 'Ukrposhta Express', 'ukrposhta-shipping-for-woocommerce' ),
				),
				'default' => 'STANDARD',
			),
			'default_cost'     => array(
				'title'       => __( 'Fallback rate, UAH', 'ukrposhta-shipping-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Used when the API is unreachable or no recipient index has been chosen yet.', 'ukrposhta-shipping-for-woocommerce' ),
				'default'     => '65',
			),
			'free_over'        => array(
				'title'       => __( 'Free shipping from, UAH', 'ukrposhta-shipping-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( '0 disables it.', 'ukrposhta-shipping-for-woocommerce' ),
				'default'     => '0',
			),
			'declared_value'   => array(
				'title'       => __( 'Declared value', 'ukrposhta-shipping-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Send the order total as the declared value', 'ukrposhta-shipping-for-woocommerce' ),
				'description' => __( 'Ukrposhta charges a percentage for this: the quote gets higher, but the parcel is insured.', 'ukrposhta-shipping-for-woocommerce' ),
				'default'     => 'yes',
			),
			'cod_gateways'     => array(
				'title'       => __( 'Cash-on-delivery payment methods', 'ukrposhta-shipping-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Comma-separated payment method IDs. For these the Ukrposhta cash-on-delivery commission is added to the tariff. The standard WooCommerce method is <code>cod</code>.', 'ukrposhta-shipping-for-woocommerce' ),
				'default'     => 'cod',
			),
			'intl_status'      => array(
				'title'       => __( 'International shipments', 'ukrposhta-shipping-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Quote deliveries outside Ukraine', 'ukrposhta-shipping-for-woocommerce' ),
				'description' => __( 'The rate is keyed by destination country and weight, no post office is picked. Add this method to a zone that contains the countries you ship to.', 'ukrposhta-shipping-for-woocommerce' ),
				'default'     => 'no',
			),
			'intl_transport'   => array(
				'title'   => __( 'Transport type', 'ukrposhta-shipping-for-woocommerce' ),
				'type'    => 'select',
				'options' => array(
					'AVIA'   => __( 'Air', 'ukrposhta-shipping-for-woocommerce' ),
					'GROUND' => __( 'Ground', 'ukrposhta-shipping-for-woocommerce' ),
				),
				'default' => 'AVIA',
			),
			'intl_package'     => array(
				'title'       => __( 'Package type', 'ukrposhta-shipping-for-woocommerce' ),
				'type'        => 'select',
				'options'     => array(
					'PARCEL'         => 'PARCEL',
					'EMS'            => 'EMS',
					'SMALL_BAG'      => 'SMALL_BAG',
					'BANDEROLE'      => 'BANDEROLE',
					'PRIME'          => 'PRIME',
					'DECLARED_VALUE' => 'DECLARED_VALUE',
					'LETTER'         => 'LETTER',
				),
				'description' => __( 'Not every type is available for every country: an unavailable combination is reported by Ukrposhta and the method is then not offered.', 'ukrposhta-shipping-for-woocommerce' ),
				'default'     => 'PARCEL',
			),
			'intl_category'    => array(
				'title'   => __( 'Content category', 'ukrposhta-shipping-for-woocommerce' ),
				'type'    => 'select',
				'options' => array(
					'SALE_OF_GOODS'     => 'SALE_OF_GOODS',
					'GIFT'              => 'GIFT',
					'MIXED_CONTENT'     => 'MIXED_CONTENT',
					'DOCUMENTS'         => 'DOCUMENTS',
					'COMMERCIAL_SAMPLE' => 'COMMERCIAL_SAMPLE',
					'RETURNING_GOODS'   => 'RETURNING_GOODS',
				),
				'default' => 'SALE_OF_GOODS',
			),
			'intl_currency'    => array(
				'title'       => __( 'Tariff currency', 'ukrposhta-shipping-for-woocommerce' ),
				'type'        => 'select',
				'options'     => array(
					'USD' => 'USD',
					'EUR' => 'EUR',
				),
				'description' => __( 'Some destinations (the US among them) are only quoted in USD.', 'ukrposhta-shipping-for-woocommerce' ),
				'default'     => 'USD',
			),
			'intl_default_cost' => array(
				'title'       => __( 'International fallback rate, UAH', 'ukrposhta-shipping-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Leave empty so a failed quote hides the method instead of inventing a price. The reason is written to WooCommerce - Status - Logs.', 'ukrposhta-shipping-for-woocommerce' ),
				'default'     => '',
			),
			'accent_color'     => array(
				'title'   => __( 'Widget accent colour', 'ukrposhta-shipping-for-woocommerce' ),
				'type'    => 'color',
				'default' => '#374151',
			),
		);
	}

	/**
	 * Pin settings to a single shared option regardless of zone instance —
	 * credentials (Bearer, sender) are store-global, not per-zone. This keeps
	 * Settings::client() and every instance reading the same blob.
	 */
	public function get_option_key() {
		return Settings::OPTION;
	}

	/** Never render stored secrets back into the form. */
	public function get_option( $key, $empty_value = null ) {
		if ( in_array( $key, Settings::SECRET_FIELDS, true ) ) {
			return '';
		}
		return parent::get_option( $key, $empty_value );
	}

	/**
	 * Encrypt secret fields at rest; empty submission keeps the previous value.
	 */
	public function process_admin_options() {
		$prev = get_option( $this->get_option_key(), array() );
		$prev = is_array( $prev ) ? $prev : array();

		$posted = array();
		foreach ( Settings::SECRET_FIELDS as $field ) {
			$field_key       = $this->get_field_key( $field );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the settings nonce before calling this.
			$posted[ $field ] = isset( $_POST[ $field_key ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $field_key ] ) ) ) : '';
		}

		$result = parent::process_admin_options();

		$opt = get_option( $this->get_option_key(), array() );
		$opt = is_array( $opt ) ? $opt : array();
		foreach ( Settings::SECRET_FIELDS as $field ) {
			if ( '' === $posted[ $field ] ) {
				// Keep the previously stored (already-encrypted) value.
				$opt[ $field ] = $prev[ $field ] ?? '';
			} else {
				$opt[ $field ] = Crypto::encrypt( $posted[ $field ] );
			}
		}
		update_option( $this->get_option_key(), $opt, 'yes' );

		return $result;
	}

	/**
	 * Is the customer paying on collection? Read from the session because the
	 * gateway is chosen after shipping is first rated; Picker::tag_packages()
	 * puts the same value into the package, so switching gateway re-rates
	 * instead of replaying the cached rate.
	 */
	private function is_cod(): bool {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return false;
		}
		$chosen = (string) WC()->session->get( 'chosen_payment_method', '' );
		if ( '' === $chosen ) {
			return false;
		}
		$ids = array_filter( array_map( 'trim', explode( ',', (string) $this->get_option( 'cod_gateways', 'cod' ) ) ) );
		return in_array( $chosen, $ids, true );
	}

	public function calculate_shipping( $package = array() ) {
		$default = (float) $this->get_option( 'default_cost', 65 );
		$cost    = $default;

		$subtotal = 0.0;
		$weight_kg = 0.0;
		foreach ( (array) ( $package['contents'] ?? array() ) as $item ) {
			$product = $item['data'] ?? null;
			$qty     = (int) ( $item['quantity'] ?? 1 );
			if ( $product ) {
				$weight_kg += (float) $product->get_weight() * $qty;
				$subtotal  += (float) ( $item['line_total'] ?? 0 );
			}
		}
		$weight_kg = wc_get_weight( $weight_kg, 'kg' );
		$weight_g  = (int) max( round( $weight_kg * 1000 ), 1 );

		// Abroad the office picker plays no part: the tariff is keyed by country
		// and weight, so the international branch runs before anything reads a
		// picked post office out of the session.
		$country = strtoupper( (string) ( $package['destination']['country'] ?? '' ) );
		if ( '' !== $country && 'UA' !== $country ) {
			$this->add_international_rate( $package, $country, $weight_g, $subtotal );
			return;
		}

		$free_over = (float) $this->get_option( 'free_over', 0 );
		if ( $free_over > 0 && $subtotal >= $free_over ) {
			$cost = 0.0;
		} else {
			$client        = Settings::client();
			$sender        = Client::postcode5( (string) $this->get_option( 'sender_postcode', '' ) );
			$recip_postidx = '';
			if ( function_exists( 'WC' ) && WC()->session ) {
				$recip_postidx = Client::postcode5( (string) WC()->session->get( 'upwc_office_postindex', '' ) );
			}
			if ( $client && '' !== $sender && '' !== $recip_postidx ) {
				$type = (string) $this->get_option( 'service_type', 'STANDARD' );

				// Declared value and cash-on-delivery are both billed by
				// Ukrposhta, so they are only sent when they actually apply —
				// otherwise every prepaid order carried a COD commission.
				$declared = ( 'yes' === $this->get_option( 'declared_value', 'yes' ) ) ? $subtotal : 0.0;
				$postpay  = $this->is_cod() ? $subtotal : 0.0;

				$resp = $client->delivery_price( $sender, $recip_postidx, $weight_g, array(), $type, 'W2W', $declared, $postpay );
				if ( ! empty( $resp['success'] ) && is_array( $resp['data'] ?? null ) ) {
					$live = $resp['data']['deliveryPrice'] ?? null;
					if ( null !== $live && (float) $live > 0 ) {
						$cost = (float) $live;
						if ( $postpay > 0 && ! empty( $resp['data']['postPayDeliveryPrice'] ) ) {
							$cost += (float) $resp['data']['postPayDeliveryPrice'];
						}
					}
				}
			}
		}

		$this->add_rate(
			array(
				'id'      => $this->get_rate_id(),
				'label'   => $this->title,
				'cost'    => $cost,
				'package' => $package,
			)
		);
	}

	/**
	 * Rate for a destination outside Ukraine.
	 *
	 * A failed quote adds NO rate and logs the reason instead of falling back to
	 * the domestic flat cost: quoting a 65 UAH parcel to Australia is worse than
	 * showing no Ukrposhta option at all. A merchant who wants a fixed price
	 * abroad sets `intl_default_cost` deliberately.
	 */
	private function add_international_rate( array $package, string $country, int $weight_g, float $subtotal ): void {
		if ( 'yes' !== $this->get_option( 'intl_status', 'no' ) ) {
			return;
		}

		$cost   = null;
		$reason = '';
		$client = Settings::client();

		if ( ! $client ) {
			$reason = 'no API key configured';
		} else {
			$resp = $client->international_delivery_price(
				$country,
				$weight_g,
				array(),
				array(
					'transportType' => (string) $this->get_option( 'intl_transport', 'AVIA' ),
					'packageType'   => (string) $this->get_option( 'intl_package', 'PARCEL' ),
					'categoryType'  => (string) $this->get_option( 'intl_category', 'SALE_OF_GOODS' ),
					'currencyCode'  => (string) $this->get_option( 'intl_currency', 'USD' ),
					'declaredPrice' => ( 'yes' === $this->get_option( 'declared_value', 'yes' ) ) ? $subtotal : 0.0,
				)
			);
			$live = $resp['data']['deliveryPrice'] ?? null;
			if ( ! empty( $resp['success'] ) && null !== $live && (float) $live > 0 ) {
				$cost = (float) $live;
			} else {
				// The API answers with a `message` for "this country cannot be
				// served with this package type", so an empty price is not always
				// an HTTP error - keep whatever it said.
				$reason = trim( (string) ( $resp['data']['message'] ?? '' ) );
				if ( '' === $reason ) {
					$reason = implode( '; ', (array) ( $resp['errors'] ?? array() ) );
				}
			}
		}

		if ( null === $cost ) {
			$fallback = (float) $this->get_option( 'intl_default_cost', 0 );
			if ( $fallback > 0 ) {
				$cost = $fallback;
			} else {
				if ( function_exists( 'wc_get_logger' ) ) {
					wc_get_logger()->warning(
						sprintf( 'Ukrposhta: no international rate for %s (%d g): %s', $country, $weight_g, $reason ),
						array( 'source' => 'ukrposhta' )
					);
				}
				return;
			}
		}

		$this->add_rate(
			array(
				'id'      => $this->get_rate_id() . ':intl',
				'label'   => $this->title . ' - ' . __( 'international', 'ukrposhta-shipping-for-woocommerce' ),
				'cost'    => $cost,
				'package' => $package,
			)
		);
	}
}
