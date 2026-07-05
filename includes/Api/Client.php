<?php
/**
 * Ukrposhta REST API client (eCom 0.0.1 + Address Classifier + StatusTracking).
 *
 * @package CcUkrposhtaWC
 */

namespace CatCode\UkrposhtaWC\Api;

defined( 'ABSPATH' ) || exit;

class Client {

	/** @var string */
	private $bearer;
	/** @var string */
	private $token;
	/** @var string */
	private $tracking_bearer;
	/** @var bool */
	private $sandbox;
	/** @var int */
	private $timeout = 20;

	/** @var string */
	private $ecom;
	/** @var string */
	private $forms;
	/** @var string */
	private $classifier;
	/** @var string */
	private $tracking;

	public function __construct( string $bearer, string $token = '', bool $sandbox = false, string $tracking_bearer = '' ) {
		$this->bearer          = trim( $bearer );
		$this->token           = trim( $token );
		$this->tracking_bearer = trim( $tracking_bearer ) !== '' ? trim( $tracking_bearer ) : $this->bearer;
		$this->sandbox         = $sandbox;

		$root             = $sandbox ? 'https://dev.ukrposhta.ua' : 'https://www.ukrposhta.ua';
		$this->ecom       = $root . '/ecom/0.0.1';
		$this->forms      = $root . '/forms/ecom/0.0.1';
		$this->classifier = $root . '/address-classifier-ws';
		$this->tracking   = $root . '/status-tracking/0.0.1';
	}

	/**
	 * @return array{success:bool,status:int,data:mixed,errors:string[],raw:string}
	 */
	private function request( string $method, string $url, $body = null, string $bearer = '' ): array {
		$bearer  = '' !== $bearer ? $bearer : $this->bearer;
		$headers = array(
			'Authorization' => 'Bearer ' . $bearer,
			'Accept'        => 'application/json',
		);
		$args = array(
			'method'  => strtoupper( $method ),
			'timeout' => $this->timeout,
			'headers' => $headers,
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
				'errors'  => array( $response->get_error_message() ),
				'raw'     => '',
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
		$decoded = json_decode( $raw, true );
		$ok      = $code >= 200 && $code < 300;

		if ( ! $ok ) {
			$errors = array();
			if ( is_array( $decoded ) ) {
				if ( ! empty( $decoded['message'] ) ) {
					$errors[] = (string) $decoded['message'];
				}
				if ( ! empty( $decoded['error'] ) ) {
					$errors[] = (string) $decoded['error'];
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
			return array(
				'success' => false,
				'status'  => $code,
				'data'    => $decoded,
				'errors'  => $errors,
				'raw'     => $raw,
			);
		}

		return array(
			'success' => true,
			'status'  => $code,
			'data'    => $decoded,
			'errors'  => array(),
			'raw'     => $raw,
		);
	}

	private function ecom_url( string $path, bool $with_token = true ): string {
		$url = $this->ecom . $path;
		if ( $with_token && '' !== $this->token ) {
			$url .= ( false !== strpos( $url, '?' ) ? '&' : '?' ) . 'token=' . rawurlencode( $this->token );
		}
		return $url;
	}

	public function test_connection(): array {
		return $this->request( 'GET', $this->classifier . '/get_regions_by_region_ua?region_name=' . rawurlencode( 'Київ' ) );
	}

	// ---- address classifier ----

	public function get_regions( string $name = '' ): array {
		return $this->request( 'GET', $this->classifier . '/get_regions_by_region_ua?region_name=' . rawurlencode( $name ) );
	}

	public function get_cities( string $region_id, string $city_name, string $district_id = '' ): array {
		$url = $this->classifier . '/get_city_by_region_id_and_district_id_and_city_ua'
			. '?region_id=' . rawurlencode( $region_id )
			. '&district_id=' . rawurlencode( $district_id )
			. '&city_ua=' . rawurlencode( $city_name );
		return $this->request( 'GET', $url );
	}

	public function get_offices_by_city( string $city_id, string $district_id = '', string $region_id = '' ): array {
		$url = $this->classifier . '/get_postoffices_by_city_id'
			. '?city_id=' . rawurlencode( $city_id )
			. '&district_id=' . rawurlencode( $district_id )
			. '&region_id=' . rawurlencode( $region_id );
		return $this->request( 'GET', $url );
	}

	/** Normalize classifier Entries.Entry to a list. */
	public static function entries( array $resp ): array {
		$data = $resp['data'] ?? null;
		if ( ! is_array( $data ) ) {
			return array();
		}
		$entry = $data['Entries']['Entry'] ?? null;
		if ( null === $entry ) {
			return array();
		}
		return isset( $entry[0] ) ? $entry : array( $entry );
	}

	// ---- delivery price ----

	public function delivery_price( int $sender_postcode, int $recipient_postcode, int $weight_g, array $dims = array(), string $type = 'STANDARD', string $delivery_type = 'W2W', float $declared = 0, float $postpay = 0 ): array {
		$body = array(
			'weight'       => max( $weight_g, 1 ),
			'length'       => max( (int) ( $dims['length'] ?? 20 ), 1 ),
			'width'        => max( (int) ( $dims['width'] ?? 20 ), 1 ),
			'height'       => max( (int) ( $dims['height'] ?? 10 ), 1 ),
			'addressFrom'  => array( 'postcode' => $sender_postcode ),
			'addressTo'    => array( 'postcode' => $recipient_postcode ),
			'type'         => $type,
			'deliveryType' => $delivery_type,
		);
		if ( $declared > 0 ) {
			$body['declaredPrice'] = $declared;
		}
		if ( $postpay > 0 ) {
			$body['postPay'] = $postpay;
		}
		return $this->request( 'POST', $this->ecom . '/domestic/delivery-price', $body, $this->bearer );
	}

	// ---- clients + shipments ----

	public function create_client( array $fields ): array {
		return $this->request( 'POST', $this->ecom_url( '/clients' ), $fields, $this->bearer );
	}

	/**
	 * Create a shipment. See OC ShipmentService for the arg contract.
	 */
	public function create_shipment( array $args ): array {
		$name  = Translit::clean_name( (string) ( $args['recipient_name'] ?? '' ) );
		$parts = preg_split( '/\s+/', trim( $name ) ) ?: array();
		$first  = (string) ( $parts[0] ?? 'Отримувач' );
		$last   = (string) ( $parts[1] ?? $first );
		$middle = (string) ( $parts[2] ?? '' );
		$phone  = Translit::normalize_phone( (string) ( $args['recipient_phone'] ?? '' ) );
		$postcode = preg_replace( '/\D/', '', (string) ( $args['recipient_postcode'] ?? '' ) );

		$recipient = $this->create_client(
			array(
				'name'        => trim( "$last $first $middle" ),
				'firstName'   => $first,
				'lastName'    => $last,
				'middleName'  => $middle,
				'phoneNumber' => $phone,
				'type'        => 'INDIVIDUAL',
				'addresses'   => array(
					array(
						'postcode' => $postcode,
						'country'  => 'UA',
						'main'     => true,
					),
				),
			)
		);
		if ( empty( $recipient['success'] ) || empty( $recipient['data']['uuid'] ) ) {
			return $recipient['success'] ? array(
				'success' => false,
				'errors'  => array( 'recipient uuid missing' ),
				'data'    => $recipient['data'],
			) : $recipient;
		}
		$recipient_uuid = (string) $recipient['data']['uuid'];

		$dims   = $args['dims'] ?? array();
		$parcel = array(
			'weight' => max( (int) ( $args['weight'] ?? 1000 ), 1 ),
			'length' => max( (int) ( $dims['length'] ?? 20 ), 1 ),
			'width'  => max( (int) ( $dims['width'] ?? 20 ), 1 ),
			'height' => max( (int) ( $dims['height'] ?? 10 ), 1 ),
		);
		if ( ! empty( $args['declaredPrice'] ) ) {
			$parcel['declaredPrice'] = (float) $args['declaredPrice'];
		}
		if ( ! empty( $args['description'] ) ) {
			$parcel['description'] = mb_substr( (string) $args['description'], 0, 120 );
		}

		$body = array(
			'sender'          => array( 'uuid' => (string) ( $args['sender_uuid'] ?? '' ) ),
			'recipient'       => array( 'uuid' => $recipient_uuid ),
			'deliveryType'    => (string) ( $args['deliveryType'] ?? 'W2W' ),
			'paidByRecipient' => (bool) ( $args['paidByRecipient'] ?? true ),
			'type'            => (string) ( $args['type'] ?? 'STANDARD' ),
			'parcels'         => array( $parcel ),
		);
		if ( ! empty( $args['cod'] ) && (float) $args['cod'] > 0 ) {
			$body['postPay']               = (float) $args['cod'];
			$body['transferPostPayToCard'] = (bool) ( $args['cod_to_card'] ?? true );
		}

		return $this->request( 'POST', $this->ecom_url( '/shipments' ), $body, $this->bearer );
	}

	public function sticker_pdf( string $barcode_or_uuid, string $size = '' ): array {
		$url = $this->forms . '/shipments/' . rawurlencode( $barcode_or_uuid ) . '/sticker';
		if ( '' !== $this->token ) {
			$url .= '?token=' . rawurlencode( $this->token );
		}
		if ( '' !== $size ) {
			$url .= ( false !== strpos( $url, '?' ) ? '&' : '?' ) . 'size=' . rawurlencode( $size );
		}
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => $this->timeout,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->bearer,
					'Accept'        => 'application/pdf',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'errors'  => array( $response->get_error_message() ),
			);
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		if ( $code >= 400 ) {
			return array(
				'success' => false,
				'errors'  => array( 'sticker HTTP ' . $code ),
				'body'    => $body,
			);
		}
		return array(
			'success'      => true,
			'body'         => $body,
			'content_type' => (string) ( wp_remote_retrieve_header( $response, 'content-type' ) ?: 'application/pdf' ),
		);
	}

	public function track_status( string $barcode, string $lang = 'ua' ): array {
		$url = $this->tracking . '/statuses?barcode=' . rawurlencode( $barcode ) . '&lang=' . rawurlencode( $lang );
		return $this->request( 'GET', $url, null, $this->tracking_bearer );
	}
}
