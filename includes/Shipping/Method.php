<?php
/**
 * WooCommerce shipping method: Ukrposhta. Holds all plugin settings (its option
 * is `woocommerce_ukrposhta_settings`) and computes the domestic tariff.
 *
 * @package CcUkrposhtaWC
 */

namespace CatCode\UkrposhtaWC\Shipping;

use CatCode\UkrposhtaWC\Core\Crypto;
use CatCode\UkrposhtaWC\Core\Settings;

defined( 'ABSPATH' ) || exit;

class Method extends \WC_Shipping_Method {

	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'ukrposhta';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'Ukrposhta / Укрпошта', 'ukrposhta-shipping-for-woocommerce' );
		$this->method_description = __( 'Доставка Укрпоштою: вибір відділення в чекауті (область→місто→відділення) та розрахунок тарифу.', 'ukrposhta-shipping-for-woocommerce' );
		$this->supports           = array( 'shipping-zones', 'instance-settings', 'settings' );

		$this->init();
	}

	public function init(): void {
		$this->init_form_fields();
		$this->init_settings();

		$this->title   = $this->get_option( 'title', __( 'Укрпошта', 'ukrposhta-shipping-for-woocommerce' ) );
		$this->enabled = $this->get_option( 'enabled', 'yes' );

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled'          => array(
				'title'   => __( 'Увімкнути', 'ukrposhta-shipping-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Показувати «Укрпошта» в чекауті', 'ukrposhta-shipping-for-woocommerce' ),
				'default' => 'yes',
			),
			'title'            => array(
				'title'   => __( 'Назва методу', 'ukrposhta-shipping-for-woocommerce' ),
				'type'    => 'text',
				'default' => __( 'Укрпошта — доставка у відділення', 'ukrposhta-shipping-for-woocommerce' ),
			),
			'sandbox'          => array(
				'title'   => __( 'Тестове середовище', 'ukrposhta-shipping-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Sandbox (dev.ukrposhta.ua)', 'ukrposhta-shipping-for-woocommerce' ),
				'default' => 'no',
			),
			'bearer'           => array(
				'title'       => __( 'Bearer eCom', 'ukrposhta-shipping-for-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'Видається Укрпоштою після договору. Потрібен для класифікатора адрес і тарифу. Зберігається зашифровано. Залиште порожнім, щоб не змінювати.', 'ukrposhta-shipping-for-woocommerce' ),
				'default'     => '',
			),
			'sender_postcode'  => array(
				'title'       => __( 'Індекс відправника', 'ukrposhta-shipping-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Поштовий індекс складу відправлення — потрібен для тарифу.', 'ukrposhta-shipping-for-woocommerce' ),
				'default'     => '',
			),
			'service_type'     => array(
				'title'   => __( 'Тип послуги', 'ukrposhta-shipping-for-woocommerce' ),
				'type'    => 'select',
				'options' => array(
					'STANDARD' => __( 'Укрпошта Стандарт', 'ukrposhta-shipping-for-woocommerce' ),
					'EXPRESS'  => __( 'Укрпошта Express', 'ukrposhta-shipping-for-woocommerce' ),
				),
				'default' => 'STANDARD',
			),
			'default_cost'     => array(
				'title'       => __( 'Тариф за замовчуванням, грн', 'ukrposhta-shipping-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Використовується, коли API недоступний або немає індексу отримувача.', 'ukrposhta-shipping-for-woocommerce' ),
				'default'     => '65',
			),
			'free_over'        => array(
				'title'       => __( 'Безкоштовно від суми, грн', 'ukrposhta-shipping-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( '0 — вимкнено.', 'ukrposhta-shipping-for-woocommerce' ),
				'default'     => '0',
			),
			'accent_color'     => array(
				'title'   => __( 'Акцентний колір віджета', 'ukrposhta-shipping-for-woocommerce' ),
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
			$posted[ $field ] = isset( $_POST[ $field_key ] ) ? trim( wp_unslash( $_POST[ $field_key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidationSanitization.InputNotSanitized
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

		$free_over = (float) $this->get_option( 'free_over', 0 );
		if ( $free_over > 0 && $subtotal >= $free_over ) {
			$cost = 0.0;
		} else {
			$client        = Settings::client();
			$sender        = (int) preg_replace( '/\D/', '', (string) $this->get_option( 'sender_postcode', '' ) );
			$recip_postidx = 0;
			if ( function_exists( 'WC' ) && WC()->session ) {
				$recip_postidx = (int) preg_replace( '/\D/', '', (string) WC()->session->get( 'upwc_office_postindex', '' ) );
			}
			if ( $client && $sender > 0 && $recip_postidx > 0 ) {
				$type = (string) $this->get_option( 'service_type', 'STANDARD' );
				$resp = $client->delivery_price( $sender, $recip_postidx, $weight_g, array(), $type, 'W2W', $subtotal );
				if ( ! empty( $resp['success'] ) && is_array( $resp['data'] ?? null ) ) {
					$live = $resp['data']['deliveryPrice'] ?? null;
					if ( null !== $live && (float) $live > 0 ) {
						$cost = (float) $live;
						if ( ! empty( $resp['data']['postPayDeliveryPrice'] ) ) {
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
}
