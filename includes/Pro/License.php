<?php
/**
 * CatCode licence client — the same contract as in the other modules.
 *
 *  - the owner pastes a purchased key            → activate;
 *  - or presses "Try Pro for 7 days"             → e-mail → start_trial() gets a real
 *    7-day key from POST /wp-json/catcode/v1/trial and activates it. The trial never
 *    starts by itself: a fresh install is simply the free tier;
 *  - a daily cron re-checks the stored key and refreshes the cache;
 *  - is_pro() reads that cache: valid (or within GRACE_DAYS of the last successful
 *    check) → Pro features on. A confirmed purchase stays Pro for good (latch);
 *    a trial ends with its term.
 *
 * When Pro goes off the free tier keeps working: the post office picker and the
 * live tariff stay, only shipments, labels, the registry and tracking stop.
 *
 * Licence for WooCommerce is sold for 1–5 years; the term covers updates and support.
 *
 * @package CcUkrposhtaWC
 */

namespace CatCode\UkrposhtaWC\Pro;

defined( 'ABSPATH' ) || exit;

class License {

	private const DEFAULT_ENDPOINT = 'https://catcode.com.ua/wp-json/catcode/v1/license';

	private const DEFAULT_TRIAL_ENDPOINT = 'https://catcode.com.ua/wp-json/catcode/v1/trial';

	/** Trial length — wording only; the real date comes from the server. */
	public const TRIAL_DAYS = 7;

	/** Canonical module slug — must equal the post_name of the CPT `module` on catcode.com.ua. */
	public const EXPECTED_SLUG = 'ukrposhta-shipping-for-woocommerce';

	/** Module page: buying a licence and feedback. */
	public const MODULE_URL = 'https://catcode.com.ua/modules/ukrposhta-shipping-for-woocommerce/';

	/** How long a cached verdict stays valid while the server is unreachable. */
	public const GRACE_DAYS = 14;

	private const HTTP_TIMEOUT = 8;

	/** Licence state lives in its own option, apart from the shipping settings form. */
	public const OPTION = 'upwc_license';

	/**
	 * @return array<string,mixed>
	 */
	private static function all(): array {
		$opt = get_option( self::OPTION, array() );
		return is_array( $opt ) ? $opt : array();
	}

	/**
	 * @param mixed $fallback Default.
	 * @return mixed
	 */
	private static function get( string $key, $fallback = '' ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/**
	 * @param array<string,mixed> $patch Fields to write.
	 */
	private static function update( array $patch ): void {
		update_option( self::OPTION, array_merge( self::all(), $patch ), false );
	}

	/**
	 * The Pro set in one place.
	 *
	 * @return array<string,string> feature key => human name.
	 */
	public static function pro_features(): array {
		return array(
			'shipment' => __( 'Shipment (barcode) from the order in one click: recipient, post office, weight, declared value', 'ukrposhta-shipping-for-woocommerce' ),
			'cod'      => __( 'Cash on delivery in the shipment for orders paid on collection', 'ukrposhta-shipping-for-woocommerce' ),
			'labels'   => __( 'Address labels 100x100, A4 or A5 as PDF, one by one or for many orders at once', 'ukrposhta-shipping-for-woocommerce' ),
			'bulk'     => __( 'Bulk actions in the order list: create shipments and print labels', 'ukrposhta-shipping-for-woocommerce' ),
			'registry' => __( 'Form 103a registry for handing a batch of parcels over at the post office', 'ukrposhta-shipping-for-woocommerce' ),
			'tracking' => __( 'Delivery statuses in the order and the order completed on delivery', 'ukrposhta-shipping-for-woocommerce' ),
			'customer' => __( 'Tracking number and link in the customer e-mails and the customer account', 'ukrposhta-shipping-for-woocommerce' ),
			'delete'   => __( 'Delete a shipment before it is handed over at the post office', 'ukrposhta-shipping-for-woocommerce' ),
		);
	}

	public static function endpoint(): string {
		return defined( 'CATCODE_LICENSE_API' ) ? (string) constant( 'CATCODE_LICENSE_API' ) : self::DEFAULT_ENDPOINT;
	}

	public static function trial_endpoint(): string {
		return defined( 'CATCODE_TRIAL_API' ) ? (string) constant( 'CATCODE_TRIAL_API' ) : self::DEFAULT_TRIAL_ENDPOINT;
	}

	public static function key(): string {
		return trim( (string) self::get( 'license_key', '' ) );
	}

	public static function has_license(): bool {
		return '' !== self::key();
	}

	public static function trial_started(): int {
		return (int) self::get( 'trial_started', 0 );
	}

	/** The trial is offered once per install and never once a key is stored. */
	public static function trial_available(): bool {
		return self::trial_started() <= 0 && ! self::has_license();
	}

	public static function on_trial(): bool {
		return self::is_trial_key() && self::is_pro();
	}

	/** Expiry date as the server returned it ('' = no end date). */
	public static function expires_at(): string {
		$raw = (string) self::get( 'license_expires_at', '' );
		if ( '' === $raw ) {
			return '';
		}
		$ts = (int) strtotime( $raw );
		return $ts > 0 ? gmdate( 'Y-m-d', $ts ) : '';
	}

	/** Whole days left, or -1 for a key without an end date. */
	public static function days_left(): int {
		$raw = (string) self::get( 'license_expires_at', '' );
		if ( '' === $raw ) {
			return -1;
		}
		$ts = (int) strtotime( $raw );
		if ( $ts <= 0 ) {
			return -1;
		}
		return max( 0, (int) ceil( ( $ts - time() ) / DAY_IN_SECONDS ) );
	}

	public static function is_pro(): bool {
		// A confirmed purchase is confirmed for good.
		if ( self::is_owned() ) {
			return true;
		}
		// A trial has to be inside its own window: the grace period is for paying customers.
		if ( self::is_trial_key() && 0 === self::days_left() ) {
			return false;
		}
		if ( 'valid' !== (string) self::get( 'license_status', '' ) ) {
			return false;
		}
		$checked_at = (string) self::get( 'license_checked_at', '' );
		if ( '' === $checked_at ) {
			return false;
		}
		$delta = ( time() - (int) strtotime( $checked_at . ' UTC' ) ) / DAY_IN_SECONDS;

		return $delta <= self::GRACE_DAYS;
	}

	/** Updates and support still covered? Separate from is_pro() on purpose. */
	public static function updates_active(): bool {
		if ( self::is_trial_key() ) {
			return self::days_left() > 0;
		}
		if ( ! self::has_license() ) {
			return false;
		}
		$expires = (string) self::get( 'license_expires_at', '' );
		if ( '' === $expires ) {
			return 'valid' === (string) self::get( 'license_status', '' );
		}
		return (int) strtotime( $expires ) >= time();
	}

	public static function is_trial_key(): bool {
		$kind = (string) self::get( 'license_kind', '' );
		if ( '' !== $kind ) {
			return 'trial' === $kind;
		}
		$started = self::trial_started();
		$expires = (int) strtotime( (string) self::get( 'license_expires_at', '' ) );
		return self::has_license() && $started > 0 && $expires > 0 && ( $expires - $started ) <= 8 * DAY_IN_SECONDS;
	}

	public static function is_owned(): bool {
		return self::has_license() && '1' === (string) self::get( 'license_owned', '' );
	}

	/**
	 * Gate of one feature — a site can open one feature by filter without the whole tier.
	 *
	 * @param string $feature Key from pro_features().
	 */
	public static function can( string $feature ): bool {
		return (bool) apply_filters( 'upwc_can_use_pro_feature', self::is_pro(), $feature );
	}

	/** Status line for the settings screen. */
	public static function describe(): string {
		$status     = (string) self::get( 'license_status', '' );
		$checked_at = (string) self::get( 'license_checked_at', '' );

		if ( '' === $status ) {
			return __( 'not checked yet', 'ukrposhta-shipping-for-woocommerce' );
		}

		if ( self::is_owned() ) {
			return self::updates_active()
				? __( 'purchased — the Pro features are yours for good', 'ukrposhta-shipping-for-woocommerce' )
				: __( 'purchased — the Pro features are yours for good; the updates and support term has ended', 'ukrposhta-shipping-for-woocommerce' );
		}

		if ( 'valid' === $status ) {
			if ( self::on_trial() ) {
				$left = self::days_left();
				if ( $left >= 0 ) {
					/* translators: %d: days left in the trial. */
					return sprintf( _n( 'trial — %d day left', 'trial — %d days left', $left, 'ukrposhta-shipping-for-woocommerce' ), $left );
				}
				return __( 'trial active', 'ukrposhta-shipping-for-woocommerce' );
			}
			return __( 'active — the Pro features are on', 'ukrposhta-shipping-for-woocommerce' );
		}

		if ( '' !== $checked_at ) {
			/* translators: %s: date and time of the last check. */
			return sprintf( __( 'not valid (last check %s)', 'ukrposhta-shipping-for-woocommerce' ), $checked_at );
		}
		return $status;
	}

	/**
	 * Server error code → what the shop owner should do.
	 *
	 * @param string $code Error code.
	 */
	public static function error_message( string $code ): string {
		$map = array(
			'invalid_key'      => __( 'Key not found. Check that it was copied in full, without spaces.', 'ukrposhta-shipping-for-woocommerce' ),
			'revoked'          => __( 'This key no longer works. If this is a mistake, write to support.', 'ukrposhta-shipping-for-woocommerce' ),
			'expired'          => __( 'The 7-day trial has ended. Buy a licence on catcode.com.ua to turn the Pro features back on.', 'ukrposhta-shipping-for-woocommerce' ),
			'limit_reached'    => __( 'This key is already used on another shop. Release it there first.', 'ukrposhta-shipping-for-woocommerce' ),
			'wrong_product'    => __( 'This key belongs to another CatCode product, not to Ukrposhta Shipping.', 'ukrposhta-shipping-for-woocommerce' ),
			'network'          => __( 'Could not reach our licence server. We will retry automatically in a few hours.', 'ukrposhta-shipping-for-woocommerce' ),
			'missing_key'      => __( 'Enter a licence key.', 'ukrposhta-shipping-for-woocommerce' ),
			'no_key'           => __( 'No key saved yet.', 'ukrposhta-shipping-for-woocommerce' ),
			'bad_email'        => __( 'Enter a valid e-mail address — the key will be sent there.', 'ukrposhta-shipping-for-woocommerce' ),
			'trial_used_email' => __( 'A trial has already been issued for this e-mail address.', 'ukrposhta-shipping-for-woocommerce' ),
			'trial_used_site'  => __( 'The trial has already been used on this site — one per install.', 'ukrposhta-shipping-for-woocommerce' ),
			'rate_limited'     => __( 'Too many attempts. Try again in an hour.', 'ukrposhta-shipping-for-woocommerce' ),
			'bad_response'     => __( 'The server answered with something we could not read. Try again in a few minutes.', 'ukrposhta-shipping-for-woocommerce' ),
		);
		return isset( $map[ $code ] ) ? $map[ $code ] : __( 'This key does not fit.', 'ukrposhta-shipping-for-woocommerce' );
	}

	/**
	 * Activate a key. verify() first (it does not use an activation slot), so a typo
	 * or another product's key does not burn the buyer's limit.
	 *
	 * @return array<string,mixed>
	 */
	public static function activate( string $key ): array {
		$key = trim( $key );
		if ( '' === $key ) {
			return array(
				'ok'    => false,
				'error' => 'missing_key',
			);
		}
		$pre = self::call( $key, 'verify' );
		if ( empty( $pre['ok'] ) ) {
			return $pre;
		}
		$result = self::call( $key, 'activate' );
		self::store( $key, $result );
		return $result;
	}

	/**
	 * Issue a 7-day key to this e-mail and activate it at once.
	 *
	 * @return array<string,mixed>
	 */
	public static function start_trial( string $email ): array {
		$email = trim( $email );
		if ( '' === $email || ! is_email( $email ) ) {
			return array(
				'ok'    => false,
				'error' => 'bad_email',
			);
		}
		if ( ! self::trial_available() ) {
			return array(
				'ok'    => false,
				'error' => 'trial_used_site',
			);
		}

		$response = self::post(
			self::trial_endpoint(),
			array(
				'email'       => $email,
				'module_slug' => self::EXPECTED_SLUG,
				'site_url'    => self::site_url(),
			)
		);

		if ( empty( $response['ok'] ) || empty( $response['key'] ) ) {
			if ( empty( $response['error'] ) ) {
				$response['error'] = 429 === (int) ( $response['http'] ?? 0 ) ? 'rate_limited' : 'network';
			}
			return $response;
		}

		$key        = (string) $response['key'];
		$activation = self::call( $key, 'activate' );
		// trial_started goes into the same write as the key.
		self::store(
			$key,
			$activation,
			array(
				'trial_started' => time(),
				'license_kind'  => 'trial',
			)
		);

		$activation['key']        = $key;
		$activation['expires_at'] = (string) ( $response['expires_at'] ?? ( $activation['expires_at'] ?? '' ) );

		return $activation;
	}

	/**
	 * Re-check the stored key — daily cron.
	 *
	 * @return array<string,mixed>
	 */
	public static function verify(): array {
		$key = self::key();
		if ( '' === $key ) {
			return array(
				'ok'    => false,
				'error' => 'no_key',
			);
		}
		$result = self::call( $key, 'verify' );
		self::store( $key, $result );
		return $result;
	}

	/**
	 * Release the slot on the server and clear the local cache.
	 *
	 * @return array<string,mixed>
	 */
	public static function deactivate(): array {
		$key    = self::key();
		$result = '' === $key
			? array(
				'ok'   => true,
				'note' => 'no_key_stored',
			)
			: self::call( $key, 'deactivate' );

		self::update(
			array(
				'license_key'        => '',
				'license_status'     => '',
				'license_checked_at' => '',
				'license_expires_at' => '',
				'license_data'       => '',
				'license_kind'       => '',
				'license_owned'      => '',
				// trial_started stays: one trial per install.
			)
		);

		return $result;
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function call( string $key, string $action ): array {
		$data = self::post(
			self::endpoint(),
			array(
				'key'      => $key,
				'site_url' => self::site_url(),
				'action'   => $action,
			)
		);

		if ( ! empty( $data['error'] ) && in_array( $data['error'], array( 'network', 'bad_response' ), true ) ) {
			return $data;
		}

		$data['verified_at'] = gmdate( 'Y-m-d H:i:s' );

		// The server answers with module_slug (post_name of the module) and product_slug
		// (a product meta, or the same slug). Either matching means it is our key.
		if ( ! empty( $data['ok'] ) ) {
			$slugs = array_filter( array( (string) ( $data['module_slug'] ?? '' ), (string) ( $data['product_slug'] ?? '' ) ) );
			if ( array() !== $slugs && ! in_array( self::EXPECTED_SLUG, $slugs, true ) ) {
				$data['ok']    = false;
				$data['error'] = 'wrong_product';
			}
		}

		if ( empty( $data['ok'] ) && empty( $data['error'] ) ) {
			$http = (int) ( $data['http'] ?? 0 );
			if ( ! empty( $data['expired'] ) ) {
				$data['error'] = 'expired';
			} elseif ( 429 === $http ) {
				$data['error'] = 'rate_limited';
			} elseif ( 401 === $http ) {
				$data['error'] = 'invalid_key';
			} elseif ( 403 === $http ) {
				$data['error'] = 'limit_reached';
			}
		}

		return $data;
	}

	/**
	 * @param array<string,mixed> $payload Body.
	 * @return array<string,mixed>
	 */
	private static function post( string $url, array $payload ): array {
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => self::HTTP_TIMEOUT,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'      => false,
				'error'   => 'network',
				'message' => $response->get_error_message(),
				'http'    => 0,
			);
		}

		$http = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		// Every real answer of our server carries `ok`. Without it somebody else
		// answered (WAF, proxy, rest_no_route) — "could not ask", not "bad key".
		if ( ! is_array( $data ) || ! array_key_exists( 'ok', $data ) ) {
			return array(
				'ok'    => false,
				'error' => 'bad_response',
				'http'  => $http,
				'raw'   => substr( $raw, 0, 500 ),
			);
		}

		$data['http'] = $http;
		return $data;
	}

	private static function site_url(): string {
		$parts = wp_parse_url( home_url() );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}
		$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] : 'https';
		return strtolower( $scheme ) . '://' . strtolower( $parts['host'] );
	}

	/**
	 * @param array<string,mixed> $result Server response.
	 * @param array<string,mixed> $extra  Fields written in the same record.
	 */
	private static function store( string $key, array $result, array $extra = array() ): void {
		$patch = array( 'license_key' => $key );

		if ( self::key() !== $key ) {
			$patch['license_kind']  = '';
			$patch['license_owned'] = '';
		}

		$transport_failure = ! empty( $result['error'] )
			&& in_array( $result['error'], array( 'network', 'bad_response', 'rate_limited' ), true );

		if ( $transport_failure ) {
			$patch['license_data'] = (string) wp_json_encode( $result, JSON_UNESCAPED_UNICODE );
			foreach ( $extra as $field => $value ) {
				$patch[ $field ] = $value;
			}
			self::update( $patch );
			return;
		}

		$patch['license_status']     = ! empty( $result['ok'] ) ? 'valid' : 'invalid';
		$patch['license_checked_at'] = (string) ( $result['verified_at'] ?? gmdate( 'Y-m-d H:i:s' ) );
		$patch['license_data']       = (string) wp_json_encode( $result, JSON_UNESCAPED_UNICODE );

		if ( isset( $result['expires_at'] ) ) {
			$patch['license_expires_at'] = (string) $result['expires_at'];
		}

		$kind = self::kind_of( $result, $extra );
		if ( '' !== $kind ) {
			$patch['license_kind'] = $kind;
		}
		if ( 'purchase' === $kind && ! empty( $result['ok'] ) ) {
			$patch['license_owned'] = '1';
		}

		foreach ( $extra as $field => $value ) {
			$patch[ $field ] = $value;
		}
		self::update( $patch );
	}

	/**
	 * @param array<string,mixed> $result Server response.
	 * @param array<string,mixed> $extra  Explicit fields.
	 */
	private static function kind_of( array $result, array $extra ): string {
		if ( isset( $extra['license_kind'] ) ) {
			return (string) $extra['license_kind'];
		}
		if ( array_key_exists( 'trial', $result ) ) {
			return ! empty( $result['trial'] ) ? 'trial' : 'purchase';
		}
		if ( ! empty( $result['is_trial'] ) ) {
			return 'trial';
		}
		return '';
	}
}
