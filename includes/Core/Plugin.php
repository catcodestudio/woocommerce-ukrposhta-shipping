<?php
/**
 * Plugin bootstrap.
 *
 * @package CcUkrposhtaWC
 */

namespace CatCode\UkrposhtaWC\Core;

use CatCode\UkrposhtaWC\Checkout\Picker;
use CatCode\UkrposhtaWC\Shipping\Method;

defined( 'ABSPATH' ) || exit;

class Plugin {

	/** @var Plugin|null */
	private static $instance = null;
	/** @var bool */
	private $booted = false;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		add_action( 'init', array( $this, 'maybe_upgrade' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );

		add_filter( 'woocommerce_shipping_methods', array( $this, 'register_method' ) );

		( new Picker() )->register_hooks();
	}

	public function register_method( array $methods ): array {
		$methods['ukrposhta'] = Method::class;
		return $methods;
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'ukrposhta-shipping-for-woocommerce', false, dirname( UPWC_BASENAME ) . '/languages' );
	}

	public function maybe_upgrade(): void {
		if ( UPWC_VERSION === get_option( 'upwc_version' ) ) {
			return;
		}
		Installer::activate();
	}
}
