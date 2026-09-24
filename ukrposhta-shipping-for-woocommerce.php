<?php
/**
 * Plugin Name: Ukrposhta Shipping for WooCommerce
 * Plugin URI: https://catcode.com.ua/modules/ukrposhta-shipping-for-woocommerce/
 * Description: Ukrposhta delivery for WooCommerce: the customer picks region, city and post office at checkout from the official Address Classifier, and the delivery price is quoted live. Shipment (barcode) creation and sticker printing come in a later update.
 * Version: 1.2.7
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: CatCode
 * Author URI: https://catcode.com.ua
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ukrposhta-shipping-for-woocommerce
 * Domain Path: /languages
 * WC requires at least: 6.0
 * WC tested up to: 10.7
 *
 * @package CcUkrposhtaWC
 */

defined( 'ABSPATH' ) || exit;

/*
 * The free copy from wordpress.org (catcode-shipping-with-ukrposhta-for-woocommerce) lives in its own folder and
 * ships the same classes. While it is loaded, this build does not load its own
 * code next to it: it pauses, asks to deactivate the free copy and still declares
 * the WooCommerce features it supports. The settings are shared, so nothing is lost.
 */
if ( defined( 'UPWC_FILE' ) && __FILE__ !== UPWC_FILE ) {
	// Declared while paused too, or WooCommerce lists this build as incompatible.
	add_action(
		'before_woocommerce_init',
		static function () {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
			}
		}
	);
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-warning"><p>'
				. esc_html__( 'The Pro version of Ukrposhta Shipping is paused: the free copy "CatCode Shipping with Ukrposhta for WooCommerce" from WordPress.org is active and uses the same classes. Deactivate it and Pro takes over from the next page load. The settings stay. Deactivate the free copy, do not delete it: deleting it removes the shared settings and post office table.', 'ukrposhta-shipping-for-woocommerce' )
				. '</p></div>';
		}
	);
	return;
}

define( 'UPWC_VERSION', '1.2.7' );
define( 'UPWC_FILE', __FILE__ );
define( 'UPWC_DIR', plugin_dir_path( __FILE__ ) );
define( 'UPWC_URL', plugin_dir_url( __FILE__ ) );
define( 'UPWC_BASENAME', plugin_basename( __FILE__ ) );

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'CatCode\\UkrposhtaWC\\';
		if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$path     = UPWC_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\CatCode\\UkrposhtaWC\\Core\\Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\CatCode\\UkrposhtaWC\\Core\\Installer', 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Ukrposhta Shipping for WooCommerce requires an active WooCommerce installation.', 'ukrposhta-shipping-for-woocommerce' ) . '</p></div>';
				}
			);
			return;
		}
		\CatCode\UkrposhtaWC\Core\Plugin::instance()->boot();
	}
);
