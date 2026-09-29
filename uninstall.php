<?php
/**
 * Removes the plugin's settings when it is deleted from the Plugins screen.
 * The changelog stays in the WooCommerce logs and expires with them.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$sspw_options = array(
	'sspw_armed_for',
	'sspw_armed_at',
	'sspw_production_override',
	'sspw_live_url',
	'sspw_email_mode',
	'sspw_email_to',
	'sspw_gateways',
	'sspw_webhooks',
	'sspw_http_firewall',
	'sspw_blocked_hosts',
	'sspw_freeze_actions',
	'sspw_visitors',
	'sspw_look',
	'sspw_noindex',
	'sspw_no_cache',
	'sspw_disabled_plugins',
	'sspw_previous_theme',
	'woocommerce_sspw_test_settings',
);

foreach ( $sspw_options as $sspw_option ) {
	delete_option( $sspw_option );
}

delete_transient( 'sspw_pending_actions' );
