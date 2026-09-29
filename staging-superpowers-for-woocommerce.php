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

// Business Bloomer WooCommerce Staging Toolkit ships this same code. Whichever
// plugin loads first runs it, the other steps aside, so both active never clash.
if ( defined( 'SSPW_PLUGIN_FILE' ) ) {
	return;
}

define( 'SSPW_VERSION', '1.1.0' );
define( 'SSPW_PLUGIN_FILE', __FILE__ );
define( 'SSPW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SSPW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SSPW_PLUGIN_DIR . 'includes/sspw-init.php';
require_once SSPW_PLUGIN_DIR . 'includes/sspw-guard.php';

add_action( 'before_woocommerce_init', 'sspw_declare_compatibility' );
add_action( 'plugins_loaded', 'sspw_init' );
register_activation_hook( __FILE__, 'sspw_activate' );
