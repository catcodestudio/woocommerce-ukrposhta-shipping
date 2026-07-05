<?php
/**
 * Plugin Name: Ukrposhta Shipping for WooCommerce
 * Plugin URI: https://catcode.com.ua/plugins/ukrposhta-shipping-for-woocommerce
 * Description: Доставка Укрпоштою для WooCommerce: вибір область→місто→відділення в чекауті через офіційний Адресний класифікатор і розрахунок тарифу. Створення ТТН/наліпок — в наступному оновленні.
 * Version: 1.0.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: CatCode
 * Author URI: https://catcode.com.ua
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ukrposhta-shipping-for-woocommerce
 * Domain Path: /languages
 * WC requires at least: 7.0
 * WC tested up to: 10.7
 *
 * @package CcUkrposhtaWC
 */

defined( 'ABSPATH' ) || exit;

define( 'UPWC_VERSION', '1.0.0' );
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
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Для роботи Ukrposhta Shipping for WooCommerce потрібен активний WooCommerce.', 'ukrposhta-shipping-for-woocommerce' ) . '</p></div>';
				}
			);
			return;
		}
		\CatCode\UkrposhtaWC\Core\Plugin::instance()->boot();
	}
);
