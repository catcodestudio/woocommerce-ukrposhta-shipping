<?php
/**
 * At-rest obfuscation for stored secrets (Bearer, token).
 * XOR against a per-install secret derived from WP salts + DB name. Not
 * cryptographic-grade — defense in depth against casual DB-dump leaks.
 *
 * @package CcUkrposhtaWC
 */

namespace CatCode\UkrposhtaWC\Core;

defined( 'ABSPATH' ) || exit;

class Crypto {

	private const PREFIX = 'upw$';

	private static function secret(): string {
		$material = ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' ) . ( defined( 'DB_NAME' ) ? DB_NAME : '' );
		return hash( 'sha256', 'UkrposhtaWC|' . $material, true );
	}

	public static function encrypt( string $plain ): string {
		if ( '' === $plain ) {
			return '';
		}
		$secret = self::secret();
		$bytes  = '';
		for ( $i = 0, $n = strlen( $plain ); $i < $n; $i++ ) {
			$bytes .= chr( ord( $plain[ $i ] ) ^ ord( $secret[ $i % strlen( $secret ) ] ) );
		}
		return self::PREFIX . base64_encode( $bytes );
	}

	public static function decrypt( string $stored ): string {
		if ( '' === $stored ) {
			return '';
		}
		if ( 0 !== strncmp( $stored, self::PREFIX, strlen( self::PREFIX ) ) ) {
			return $stored; // legacy plaintext
		}
		$bytes = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true );
		if ( false === $bytes ) {
			return '';
		}
		$secret = self::secret();
		$out    = '';
		for ( $i = 0, $n = strlen( $bytes ); $i < $n; $i++ ) {
			$out .= chr( ord( $bytes[ $i ] ) ^ ord( $secret[ $i % strlen( $secret ) ] ) );
		}
		return $out;
	}
}
