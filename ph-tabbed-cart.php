<?php
/**
 * Plugin Name:       Rok PH Tabbed Cart Panel
 * Description:       Slide-in panel with Wishlist / My Account / Cart tabs for WooCommerce + YITH Wishlist. Replaces the Elementor Menu Cart popup.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author: Rok Online
 * Author URI: https://rokonline.com/
 * Plugin URI: https://rokonline.com/
 * Requires Plugins:  woocommerce
 * Text Domain:       ph-tabbed-cart
 *
 * Every class, function, constant, option, CSS class, JS handle and AJAX action
 * in this plugin uses the unique prefix "phtc" / "PHTC" so it cannot clash
 * with any other plugin.
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'PHTC_VERSION' ) ) {
	define( 'PHTC_VERSION', '1.0.1' );
}
if ( ! defined( 'PHTC_FILE' ) ) {
	define( 'PHTC_FILE', __FILE__ );
}
if ( ! defined( 'PHTC_PATH' ) ) {
	define( 'PHTC_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'PHTC_URL' ) ) {
	define( 'PHTC_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! class_exists( 'PHTC_Plugin' ) ) {

	require_once PHTC_PATH . 'includes/class-phtc-settings.php';
	require_once PHTC_PATH . 'includes/class-phtc-panel.php';

	final class PHTC_Plugin {

		/**
		 * Boot the plugin after all plugins are loaded.
		 */
		public static function boot() {
			PHTC_Settings::init();

			if ( ! class_exists( 'WooCommerce' ) ) {
				add_action( 'admin_notices', array( __CLASS__, 'missing_woocommerce_notice' ) );
				return;
			}

			PHTC_Panel::init();
		}

		/**
		 * Admin notice shown when WooCommerce is not active.
		 */
		public static function missing_woocommerce_notice() {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'PH Tabbed Cart Panel needs WooCommerce to be active.', 'ph-tabbed-cart' );
			echo '</p></div>';
		}
	}

	add_action( 'plugins_loaded', array( 'PHTC_Plugin', 'boot' ) );
}
