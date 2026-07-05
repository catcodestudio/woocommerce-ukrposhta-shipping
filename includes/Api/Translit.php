<?php
/**
 * Name hygiene + phone normalization for the Ukrposhta API.
 *
 * @package CcUkrposhtaWC
 */

namespace CatCode\UkrposhtaWC\Api;

defined( 'ABSPATH' ) || exit;

class Translit {

	private const MAP = array(
		'shch' => 'щ', 'Shch' => 'Щ', 'SHCH' => 'Щ',
		'zh' => 'ж', 'Zh' => 'Ж', 'ZH' => 'Ж',
		'kh' => 'х', 'Kh' => 'Х', 'KH' => 'Х',
		'ts' => 'ц', 'Ts' => 'Ц', 'TS' => 'Ц',
		'ch' => 'ч', 'Ch' => 'Ч', 'CH' => 'Ч',
		'sh' => 'ш', 'Sh' => 'Ш', 'SH' => 'Ш',
		'iu' => 'ю', 'Iu' => 'Ю', 'IU' => 'Ю',
		'yu' => 'ю', 'Yu' => 'Ю', 'YU' => 'Ю',
		'ia' => 'я', 'Ia' => 'Я', 'IA' => 'Я',
		'ya' => 'я', 'Ya' => 'Я', 'YA' => 'Я',
		'ie' => 'є', 'Ie' => 'Є', 'IE' => 'Є',
		'ye' => 'є', 'Ye' => 'Є', 'YE' => 'Є',
		'yi' => 'ї', 'Yi' => 'Ї', 'YI' => 'Ї',
		'a' => 'а', 'A' => 'А', 'b' => 'б', 'B' => 'Б',
		'v' => 'в', 'V' => 'В', 'h' => 'г', 'H' => 'Г',
		'g' => 'ґ', 'G' => 'Ґ', 'd' => 'д', 'D' => 'Д',
		'e' => 'е', 'E' => 'Е', 'z' => 'з', 'Z' => 'З',
		'y' => 'и', 'Y' => 'И', 'i' => 'і', 'I' => 'І',
		'j' => 'й', 'J' => 'Й', 'k' => 'к', 'K' => 'К',
		'l' => 'л', 'L' => 'Л', 'm' => 'м', 'M' => 'М',
		'n' => 'н', 'N' => 'Н', 'o' => 'о', 'O' => 'О',
		'p' => 'п', 'P' => 'П', 'r' => 'р', 'R' => 'Р',
		's' => 'с', 'S' => 'С', 't' => 'т', 'T' => 'Т',
		'u' => 'у', 'U' => 'У', 'f' => 'ф', 'F' => 'Ф',
		'c' => 'к', 'C' => 'К', 'q' => 'к', 'Q' => 'К',
		'w' => 'в', 'W' => 'В', 'x' => 'кс', 'X' => 'Кс',
	);

	public static function to_cyrillic( string $text ): string {
		if ( '' === $text || ! preg_match( '/[a-zA-Z]/', $text ) ) {
			return $text;
		}
		return strtr( $text, self::MAP );
	}

	public static function clean_name( string $text ): string {
		$text = preg_replace( '/\s+/u', ' ', trim( $text ) ) ?? '';
		return self::to_cyrillic( $text );
	}

	public static function normalize_phone( string $raw ): string {
		$digits = preg_replace( '/\D+/', '', $raw );
		if ( '' === $digits ) {
			return '';
		}
		if ( 10 === strlen( $digits ) && '0' === $digits[0] ) {
			$digits = '38' . $digits;
		} elseif ( 9 === strlen( $digits ) ) {
			$digits = '380' . $digits;
		}
		return $digits;
	}
}
