<?php
/**
 * Removes the plugin's settings when it is deleted from the Plugins screen.
 * The live site address is removed too: PRO stores it under the same name.
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
	'sspw_freeze_cron',
	'sspw_lock_subscriptions',
	'sspw_visitors',
	'sspw_look',
	'sspw_noindex',
	'sspw_block_bots',
	'sspw_no_analytics',
	'sspw_no_cache',
	'sspw_disabled_plugins',
	'sspw_previous_theme',
	'sspw_changelog',
	'sspw_armed_history',
	'sspw_message_title',
	'sspw_message_text',
	'woocommerce_sspw_test_settings',
);

foreach ( $sspw_options as $sspw_option ) {
	delete_option( $sspw_option );
}

delete_transient( 'sspw_pending_actions' );
