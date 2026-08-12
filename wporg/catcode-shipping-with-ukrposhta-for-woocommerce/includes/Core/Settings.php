<?php
/**
 * Central access to the shipping-method settings + a Client factory.
 * Settings live under the WooCommerce shipping method option
 * `woocommerce_ukrposhta_settings`. Secret fields are stored encrypted.
 *
 * @package CcUkrposhtaWC
 */

namespace CatCode\UkrposhtaWC\Core;

use CatCode\UkrposhtaWC\Api\Client;

defined( 'ABSPATH' ) || exit;

class Settings {

	public const OPTION = 'woocommerce_ukrposhta_settings';

	/** Secret fields stored encrypted at rest. Read-only build needs only the
	 * eCom Bearer (Address Classifier + tariff both authorize with it). */
	public const SECRET_FIELDS = array( 'bearer' );

	public static function all(): array {
		$opt = get_option( self::OPTION, array() );
		return is_array( $opt ) ? $opt : array();
	}

	public static function get( string $key, $default = '' ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/** Decrypt a secret field. */
	public static function secret( string $key ): string {
		$raw = (string) self::get( $key, '' );
		return '' === $raw ? '' : Crypto::decrypt( $raw );
	}

	public static function is_sandbox(): bool {
		return 'yes' === self::get( 'sandbox', 'no' );
	}

	/** Build an API client from stored credentials, or null if no Bearer. */
	public static function client(): ?Client {
		$bearer = self::secret( 'bearer' );
		if ( '' === $bearer ) {
			return null;
		}
		// Read-only build: classifier + tariff authorize with the Bearer alone.
		return new Client( $bearer, '', self::is_sandbox() );
	}
}
