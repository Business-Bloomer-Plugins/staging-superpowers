<?php
/**
 * Popular plugins that talk to live services by other routes than email or a
 * blocked web address: publishing pings, Jetpack, scheduled backups, payment
 * test modes and analytics. Each one uses that plugin's own switch, so nothing
 * is changed in the database. Only loaded when the site is armed.
 *
 * sspw-plugin-check.php lists these plugins on the settings page.
 */

defined( 'ABSPATH' ) || exit;

if ( 'yes' === sspw_get( 'sspw_http_firewall' ) ) {
	// Pingbacks, trackbacks and update-service pings (Ping-O-Matic by default)
	// announce new posts to other sites. Core does the same outside production
	// since WordPress 7.1; this covers older versions and sites set to production.
	add_filter( 'wp_should_disable_pings_for_environment', '__return_true' );
	add_action( 'do_all_pings', 'sspw_stop_pings', 1, 0 );

	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- each plugin's own hook.

	// Jetpack's safe mode is meant for copies of a site: it stops syncing to
	// WordPress.com, which is also how Jetpack Social shares new posts.
	add_filter( 'jetpack_is_in_safe_mode', '__return_true' );

	// UpdraftPlus: scheduled backups would upload to, and prune, the live
	// site's remote storage. Backups started by hand still run.
	add_filter( 'updraftplus_boot_backup', 'sspw_no_scheduled_updraftplus', 10, 3 );

	// Payment test modes.
	add_filter( 'edd_is_test_mode', '__return_true' );
	add_filter( 'give_is_test_mode', '__return_true' );
	add_filter( 'pre_option_pmpro_gateway_environment', 'sspw_pmpro_sandbox' );

	// phpcs:enable
}

if ( 'yes' === sspw_get( 'sspw_no_analytics' ) ) {
	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- each plugin's own hook.

	// Site Kit by Google.
	foreach ( array( 'analytics-4', 'tagmanager', 'ads', 'adsense' ) as $sspw_module ) {
		add_filter( 'googlesitekit_' . $sspw_module . '_tag_blocked', '__return_true' );
		add_filter( 'googlesitekit_' . $sspw_module . '_tag_amp_blocked', '__return_true' );
	}

	// MonsterInsights.
	add_filter( 'monsterinsights_skip_tracking', '__return_true' );

	// GTM4WP: its own switch for staging copies.
	add_filter( 'gtm4wp_output_container', '__return_false' );

	// Meta pixel for WordPress: its Conversions API sends events with its own
	// code, so the blocked services list cannot stop it.
	add_filter( 'before_conversions_api_event_sent', '__return_empty_array' );

	// phpcs:enable
}

function sspw_stop_pings() {
	remove_action( 'do_all_pings', 'do_all_pingbacks' );
	remove_action( 'do_all_pings', 'do_all_trackbacks' );
	remove_action( 'do_all_pings', 'generic_ping' );
}

/**
 * Scheduled backups pass true/false for files and database; "Backup Now"
 * passes numbers, so it still works.
 */
function sspw_no_scheduled_updraftplus( $start, $files, $database ) {
	return ( is_bool( $files ) || is_bool( $database ) ) ? false : $start;
}

function sspw_pmpro_sandbox() {
	return 'sandbox';
}
