<?php
/**
 * Cache-first classifier lookups:
 *   - regions : transient (weekly).
 *   - cities  : transient per (region, query) (1 day).
 *   - offices : wpdb table, 7-day TTL (mirrors the OC plugin).
 *
 * @package CcUkrposhtaWC
 */

namespace CatCode\UkrposhtaWC\Api;

defined( 'ABSPATH' ) || exit;

class Cache {

	private const OFFICE_TTL = 7 * DAY_IN_SECONDS;

	public static function regions( Client $client ): array {
		$key    = 'upwc_regions';
		$cached = get_transient( $key );
		if ( is_array( $cached ) && $cached ) {
			return $cached;
		}
		$out = array();
		foreach ( Client::entries( $client->get_regions( '' ) ) as $e ) {
			$id   = (string) ( $e['REGION_ID'] ?? '' );
			$name = (string) ( $e['REGION_UA'] ?? '' );
			if ( '' === $id || '' === $name ) {
				continue;
			}
			$out[] = array(
				'id'   => $id,
				'name' => $name,
			);
		}
		usort( $out, static fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );
		if ( $out ) {
			set_transient( $key, $out, WEEK_IN_SECONDS );
		}
		return $out;
	}

	public static function cities( Client $client, string $region_id, string $query ): array {
		$query = trim( $query );
		if ( '' === $region_id || mb_strlen( $query ) < 2 ) {
			return array();
		}
		$key    = 'upwc_cities_' . md5( $region_id . '|' . mb_strtolower( $query ) );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$out = array();
		foreach ( Client::entries( $client->get_cities( $region_id, $query ) ) as $e ) {
			$cid  = (string) ( $e['CITY_ID'] ?? '' );
			$name = (string) ( $e['CITY_UA'] ?? '' );
			if ( '' === $cid || '' === $name ) {
				continue;
			}
			$out[] = array(
				'id'          => $cid,
				'district_id' => (string) ( $e['DISTRICT_ID'] ?? '' ),
				'name'        => $name,
			);
		}
		set_transient( $key, $out, DAY_IN_SECONDS );
		return $out;
	}

	public static function offices( Client $client, string $city_id, string $district_id = '', string $region_id = '' ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'upwc_offices';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- classifier cache table.
		$fresh = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE city_id = %s AND updated_at > ( NOW() - INTERVAL %d SECOND )",
				$city_id,
				self::OFFICE_TTL
			)
		);
		if ( $fresh > 0 ) {
			return self::rows( $city_id );
		}

		$entries = Client::entries( $client->get_offices_by_city( $city_id, $district_id, $region_id ) );
		if ( ! $entries ) {
			return self::rows( $city_id ); // whatever stale cache exists
		}
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE city_id = %s", $city_id ) );
		$now = current_time( 'mysql' );
		foreach ( $entries as $e ) {
			$pi = (string) ( $e['POSTINDEX'] ?? '' );
			if ( '' === $pi ) {
				continue;
			}
			$wpdb->replace(
				$table,
				array(
					'city_id'     => $city_id,
					'postindex'   => $pi,
					'name'        => (string) ( $e['PO_SHORT'] ?? ( $e['PO_LONG'] ?? '' ) ),
					'address'     => (string) ( $e['ADDRESS'] ?? '' ),
					'is_postomat' => ( '1' === (string) ( $e['POSTTERMINAL'] ?? '0' ) ) ? 1 : 0,
					'updated_at'  => $now,
				),
				array( '%s', '%s', '%s', '%s', '%d', '%s' )
			);
		}
		// phpcs:enable
		return self::rows( $city_id );
	}

	private static function rows( string $city_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'upwc_offices';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT postindex, name, address, is_postomat FROM {$table} WHERE city_id = %s ORDER BY is_postomat ASC, CAST(postindex AS UNSIGNED)", $city_id ),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return array();
		}
		return array_map(
			static fn( $r ) => array(
				'postindex'   => (string) $r['postindex'],
				'name'        => (string) $r['name'],
				'address'     => (string) $r['address'],
				'is_postomat' => (int) $r['is_postomat'],
			),
			$rows
		);
	}
}
