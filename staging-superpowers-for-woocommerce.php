<?php
/**
 * Plugin Name:          Staging Superpowers for WooCommerce
 * Description:          Make a WooCommerce staging copy safe to play with: redirect emails, swap payment gateways for a test gateway, pause webhooks, block outgoing API calls, freeze scheduled actions.
 * Version:              1.1.0
 * Requires at least:    6.5
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * WC requires at least: 8.0
 * WC tested up to:      11.1.2
 * Author:               Rodolfo Melogli
 * Author URI:           https://businessbloomer.com/
 * License:              GPL v2 or later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          staging-superpowers-for-woocommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'SSPW_VERSION', '1.1.0' );
define( 'SSPW_PLUGIN_FILE', __FILE__ );
define( 'SSPW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SSPW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

require_once SSPW_PLUGIN_DIR . 'includes/sspw-guard.php';

register_activation_hook( __FILE__, 'sspw_activate' );

add_action( 'plugins_loaded', 'sspw_init' );

/**
 * The guard (loaded above) runs even without WooCommerce so the deploy notice
 * always shows; everything else needs WooCommerce.
 */
function sspw_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	require_once SSPW_PLUGIN_DIR . 'includes/sspw-settings.php';
	require_once SSPW_PLUGIN_DIR . 'includes/sspw-troubleshoot.php';

	if ( ! sspw_is_armed() ) {
		return;
	}

	require_once SSPW_PLUGIN_DIR . 'includes/sspw-safety.php';
	require_once SSPW_PLUGIN_DIR . 'includes/sspw-gateways.php';
	require_once SSPW_PLUGIN_DIR . 'includes/sspw-visitors.php';
	require_once SSPW_PLUGIN_DIR . 'includes/sspw-changelog.php';
	require_once SSPW_PLUGIN_DIR . 'includes/sspw-cache.php';
}
