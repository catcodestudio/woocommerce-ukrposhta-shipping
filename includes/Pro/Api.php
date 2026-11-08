<?php
/**
 * Ukrposhta API calls the Pro features make: addresses, clients, shipments,
 * shipment groups, print forms and status tracking.
 *
 * Contract — Ukrposhta API documentation 16.10.2026 (dev.ukrposhta.ua/documentation):
 *  - eCom   {root}/ecom/0.0.1          Bearer eCom; clients, shipments and groups also take ?token=
 *  - forms  {root}/forms/ecom/0.0.1    labels (sticker), stickers-by-barcodes, form103a
 *  - track  {root}/status-tracking/0.0.1  its own Bearer (StatusTracking)
 * {root} = https://www.ukrposhta.ua, or https://dev.ukrposhta.ua in the sandbox.
 *
 * @package CcUkrposhtaWC
 */

namespace CatCode\UkrposhtaWC\Pro;

use CatCode\UkrposhtaWC\Core\Settings;

defined( 'ABSPATH' ) || exit;

class Api {

	/** @var string */
	private $bearer;
	/** @var string */
	private $token;
	/** @var string */
	private $tracking_bearer;
	/** @var string */
	private $root;
	/** @var int */
	private $timeout = 25;

	public function __construct( string $bearer, string $token, string $tracking_bearer = '', bool $sandbox = false ) {
		$this->bearer          = trim( $bearer );
		$this->token           = trim( $token );
		$this->tracking_bearer = '' !== trim( $tracking_bearer ) ? trim( $tracking_bearer ) : $this->bearer;
		$root                  = $sandbox ? 'https://dev.ukrposhta.ua' : 'https://www.ukrposhta.ua';
		// Local test stand only: the whole API answered by a mock.
		if ( defined( 'UPWC_API_ROOT' ) && '' !== (string) constant( 'UPWC_API_ROOT' ) ) {
			$root = rtrim( (string) constant( 'UPWC_API_ROOT' ), '/' );
		}
		$this->root = $root;
	}

	/** Built from the saved settings, or null while a key is missing. */
	public static function from_settings(): ?Api {
		$bearer = Settings::secret( 'bearer' );
		$token  = Settings::secret( 'token' );
		if ( '' === $bearer || '' === $token ) {
			return null;
		}
		return new self( $bearer, $token, Settings::secret( 'tracking_bearer' ), Settings::is_sandbox() );
	}

	private function ecom( string $path ): string {
		return $this->root . '/ecom/0.0.1' . $path;
	}

	private function forms( string $path ): string {
		return $this->root . '/forms/ecom/0.0.1' . $path;
	}

	private function with_token( string $url ): string {
		return $url . ( false !== strpos( $url, '?' ) ? '&' : '?' ) . 'token=' . rawurlencode( $this->token );
	}

	/**
	 * @param mixed $body JSON body or null.
	 * @return array{success:bool,status:int,data:mixed,errors:string[],raw:string}
	 */
	private function json( string $method, string $url, $body = null, string $bearer = '' ): array {
		$args = array(
			'method'  => strtoupper( $method ),
			'timeout' => $this->timeout,
			'headers' => array(
				'Authorization' => 'Bearer ' . ( '' !== $bearer ? $bearer : $this->bearer ),
				'Accept'        => 'application/json',
			),
		);
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}
		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'status'  => 0,
				'data'    => null,
				'errors'  => array( 'network: ' . $response->get_error_message() ),
				'raw'     => '',
			);
		}
		$code    = (int) wp_remote_retrieve_response_code( $response );
		$raw     = (string) wp_remote_retrieve_body( $response );
		$decoded = json_decode( $raw, true );
		if ( $code >= 200 && $code < 300 ) {
			return array(
				'success' => true,
				'status'  => $code,
				'data'    => $decoded,
				'errors'  => array(),
				'raw'     => $raw,
			);
		}
		return array(
			'success' => false,
			'status'  => $code,
			'data'    => $decoded,
			'errors'  => self::errors_of( $decoded, $code ),
			'raw'     => substr( $raw, 0, 2000 ),
		);
	}

	/**
	 * Error texts out of an Ukrposhta answer (message / description / errors[]).
	 *
	 * @param mixed $decoded Decoded body.
	 * @return string[]
	 */
	public static function errors_of( $decoded, int $code ): array {
		$errors = array();
		if ( is_array( $decoded ) ) {
			foreach ( array( 'message', 'description', 'error' ) as $k ) {
				if ( ! empty( $decoded[ $k ] ) && is_scalar( $decoded[ $k ] ) ) {
					$errors[] = (string) $decoded[ $k ];
				}
			}
			if ( ! empty( $decoded['errors'] ) && is_array( $decoded['errors'] ) ) {
				foreach ( $decoded['errors'] as $e ) {
					$errors[] = is_array( $e ) ? (string) ( $e['message'] ?? wp_json_encode( $e ) ) : (string) $e;
				}
			}
		}
		if ( ! $errors ) {
			$errors[] = 'HTTP ' . $code;
		}
		return array_values( array_unique( $errors ) );
	}

	/**
	 * A PDF form. Ukrposhta answers with the binary file; anything else is an error.
	 *
	 * @param mixed $body JSON body (POST) or null (GET).
	 * @return array{success:bool,body?:string,errors?:string[]}
	 */
	private function pdf( string $method, string $url, $body = null ): array {
		$args = array(
			'method'  => strtoupper( $method ),
			'timeout' => $this->timeout,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->bearer,
				'Accept'        => 'application/pdf',
			),
		);
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}
		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'errors'  => array( 'network: ' . $response->get_error_message() ),
			);
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
		if ( $code >= 200 && $code < 300 && 0 === strpos( $raw, '%PDF' ) ) {
			return array(
				'success' => true,
				'body'    => $raw,
			);
		}
		return array(
			'success' => false,
			'errors'  => self::errors_of( json_decode( $raw, true ), $code ),
		);
	}

	// ---- addresses and clients (sections 2, 3) ----

	public function create_address( string $postcode ): array {
		return $this->json( 'POST', $this->ecom( '/addresses' ), array( 'postcode' => $postcode ) );
	}

	/**
	 * @param array<string,mixed> $client Client body (type, names, addressId, phoneNumber ...).
	 */
	public function create_client( array $client ): array {
		return $this->json( 'POST', $this->with_token( $this->ecom( '/clients' ) ), $client );
	}

	// ---- shipments (section 4) ----

	/**
	 * @param array<string,mixed> $shipment Shipment body.
	 */
	public function create_shipment( array $shipment ): array {
		return $this->json( 'POST', $this->with_token( $this->ecom( '/shipments' ) ), $shipment );
	}

	/** Only a shipment still in status CREATED can be deleted. */
	public function delete_shipment( string $uuid ): array {
		return $this->json( 'DELETE', $this->with_token( $this->ecom( '/shipments/' . rawurlencode( $uuid ) ) ) );
	}

	// ---- groups (section 5) ----

	public function create_group( string $name, string $client_uuid, string $type ): array {
		return $this->json(
			'POST',
			$this->with_token( $this->ecom( '/shipment-groups' ) ),
			array(
				'name'       => $name,
				'clientUuid' => $client_uuid,
				'type'       => $type,
			)
		);
	}

	public function add_to_group( string $group_uuid, string $shipment_uuid ): array {
		return $this->json( 'POST', $this->with_token( $this->ecom( '/shipment-groups/' . rawurlencode( $group_uuid ) . '/shipments/' . rawurlencode( $shipment_uuid ) ) ) );
	}

	// ---- print forms (section 10) ----

	/**
	 * @param string $size '' (100x100), SIZE_A4 or SIZE_A5.
	 */
	public function sticker( string $barcode, string $size = '' ): array {
		$url = $this->with_token( $this->forms( '/shipments/' . rawurlencode( $barcode ) . '/sticker' ) );
		if ( '' !== $size ) {
			$url .= '&size=' . rawurlencode( $size );
		}
		return $this->pdf( 'GET', $url );
	}

	/**
	 * Labels of many shipments in one PDF.
	 *
	 * @param string[] $barcodes Barcodes or uuids.
	 */
	public function stickers( array $barcodes, string $size = '' ): array {
		$body = array();
		foreach ( $barcodes as $b ) {
			$body[ (string) $b ] = new \stdClass();
		}
		$url = $this->with_token( $this->forms( '/shipments/stickers-by-barcodes' ) );
		if ( '' !== $size ) {
			$url .= '&size=' . rawurlencode( $size );
		}
		return $this->pdf( 'POST', $url, $body );
	}

	public function form103a( string $group_uuid ): array {
		return $this->pdf( 'GET', $this->with_token( $this->forms( '/shipment-groups/' . rawurlencode( $group_uuid ) . '/form103a' ) ) . '&showSenderName=true' );
	}

	// ---- status tracking ----

	/**
	 * Last status of up to 50 barcodes (POST /statuses returns every step; the
	 * caller keeps the last per barcode).
	 *
	 * @param string[] $barcodes Barcodes.
	 */
	public function statuses( array $barcodes ): array {
		return $this->json( 'POST', $this->root . '/status-tracking/0.0.1/statuses', array_values( array_slice( $barcodes, 0, 50 ) ), $this->tracking_bearer );
	}
}
