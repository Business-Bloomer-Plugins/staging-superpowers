<?php
/**
 * Plugin Name:          Staging Superpowers
 * Description:          Make a staging copy of your site safe to test on: block emails, block outside services, freeze scheduled tasks and hide staging from search engines. With WooCommerce, it also swaps payment methods for a test gateway and pauses webhooks.
 * Version:              1.3.0
 * Requires at least:    6.5
 * Requires PHP:         7.4
 * WC requires at least: 8.0
 * WC tested up to:      11.1.2
 * Author:               Rodolfo Melogli
 * Author URI:           https://businessbloomer.com/
 * License:              GPL v2 or later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          staging-superpowers
 */

defined( 'ABSPATH' ) || exit;

// Business Bloomer WooCommerce Staging Toolkit ships this same code. Whichever
// plugin loads first runs it, the other steps aside, so both active never clash.
if ( defined( 'SSPW_PLUGIN_FILE' ) ) {
	return;
}

define( 'SSPW_VERSION', '1.3.0' );
define( 'SSPW_PLUGIN_FILE', __FILE__ );
define( 'SSPW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SSPW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SSPW_PLUGIN_DIR . 'includes/sspw-init.php';
require_once SSPW_PLUGIN_DIR . 'includes/sspw-guard.php';

// Registered this early so a fatal error in a plugin loaded after this one can be traced.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only compared with a one-time key.
if ( isset( $_GET['sspw_check'] ) ) {
	add_filter( 'wp_php_error_message', 'sspw_mark_fatal_error', 10, 2 );
}

// Uncanny Automator decides whether to run recipes while it loads, before plugins_loaded.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Uncanny Automator's hooks.
add_filter( 'automator_run_automator_actions', 'sspw_hold_automator' );
add_filter( 'automator_should_enqueue_trigger', 'sspw_hold_automator' );
// phpcs:enable

add_action( 'before_woocommerce_init', 'sspw_declare_compatibility' );
add_action( 'plugins_loaded', 'sspw_init' );
register_activation_hook( __FILE__, 'sspw_activate' );
