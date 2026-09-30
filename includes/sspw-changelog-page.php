<?php
/**
 * Changelog sub-page. The recording itself is in sspw-changelog.php, which
 * only loads while the site is armed; this page shows either way.
 */

defined( 'ABSPATH' ) || exit;

define( 'SSPW_LOG_SOURCE', 'staging-superpowers' );
define( 'SSPW_LOG_OPTION', 'sspw_changelog' );
define( 'SSPW_LOG_LIMIT', 500 );

add_action( 'admin_post_sspw_clear_changelog', 'sspw_clear_changelog' );

/**
 * WooCommerce has a log viewer with retention and downloads, so it is used
 * when available. Otherwise entries are kept in an option.
 */
function sspw_changelog_uses_woocommerce() {
	return function_exists( 'wc_get_logger' );
}

/**
 * @return array Entries, oldest first: array( timestamp, message ).
 */
function sspw_changelog_entries() {
	return (array) get_option( SSPW_LOG_OPTION, array() );
}

function sspw_output_changelog() {
	echo '<h2>' . esc_html__( 'Changelog', 'staging-superpowers' ) . '</h2>';
	echo '<p>' . esc_html__( 'Everything changed on this staging copy is recorded here, so you have a list of what to redo on your live site instead of copying this database over it (which would wipe every comment, order, sign-up or form entry made on the live site since the copy was made).', 'staging-superpowers' ) . '</p>';
	echo '<p>' . esc_html__( 'Recorded: the settings of this plugin and of WooCommerce (with the old and new value), theme and plugin changes and updates, the cart or checkout switching between blocks and classic, and posts, pages, products, coupons, categories, tags and menus created, edited or deleted.', 'staging-superpowers' ) . '</p>';

	if ( ! sspw_is_armed() ) {
		echo '<p>' . esc_html__( 'Nothing is recorded while Staging Superpowers is paused.', 'staging-superpowers' ) . '</p>';
	} elseif ( sspw_changelog_uses_woocommerce() && 'no' === get_option( 'woocommerce_logs_logging_enabled', 'yes' ) ) {
		echo '<div class="notice notice-warning inline"><p>' . wp_kses_post(
			sprintf(
				/* translators: %s: link to the WooCommerce logs settings */
				__( 'WooCommerce logging is turned off, so nothing can be recorded. Turn it on in %s.', 'staging-superpowers' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=advanced&section=logs' ) ) . '">' . esc_html__( 'WooCommerce > Settings > Advanced > Logs', 'staging-superpowers' ) . '</a>'
			)
		) . '</p></div>';
	} elseif ( get_option( 'sspw_armed_at' ) ) {
		/* translators: %s: date */
		echo '<p><strong>' . esc_html( sprintf( __( 'Recording since %s.', 'staging-superpowers' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) get_option( 'sspw_armed_at' ) ) ) ) . '</strong></p>';
	}

	if ( sspw_changelog_uses_woocommerce() ) {
		printf( '<p><a class="button button-primary" href="%1$s">%2$s</a></p>', esc_url( sspw_changelog_url() ), esc_html__( 'View the changelog', 'staging-superpowers' ) );

		echo '<p class="description">' . wp_kses_post(
			sprintf(
				/* translators: 1: number of days, 2: link to the WooCommerce logs settings */
				__( 'The changelog is kept in the WooCommerce logs. Entries are deleted after %1$d days, like all WooCommerce logs. You can change this in %2$s.', 'staging-superpowers' ),
				(int) get_option( 'woocommerce_logs_retention_period_days', 30 ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=advanced&section=logs' ) ) . '">' . esc_html__( 'WooCommerce > Settings > Advanced > Logs', 'staging-superpowers' ) . '</a>'
			)
		) . '</p>';
		return;
	}

	$entries = array_reverse( sspw_changelog_entries() );

	if ( ! $entries ) {
		echo '<p>' . esc_html__( 'Nothing has been changed yet.', 'staging-superpowers' ) . '</p>';
		return;
	}

	$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

	echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th style="width:190px">' . esc_html__( 'When', 'staging-superpowers' ) . '</th><th>' . esc_html__( 'What changed', 'staging-superpowers' ) . '</th></tr></thead><tbody>';
	foreach ( $entries as $entry ) {
		printf( '<tr><td>%1$s</td><td>%2$s</td></tr>', esc_html( wp_date( $format, (int) $entry[0] ) ), esc_html( $entry[1] ) );
	}
	echo '</tbody></table>';

	/* translators: %d: number of entries */
	echo '<p class="description">' . esc_html( sprintf( __( 'Newest first. The latest %d entries are kept.', 'staging-superpowers' ), SSPW_LOG_LIMIT ) ) . '</p><p>';
	wp_nonce_field( 'sspw_clear_changelog', 'sspw_changelog_nonce' );
	printf(
		'<button type="submit" class="button" formaction="%1$s" formmethod="post" onclick="return confirm( %2$s );">%3$s</button>',
		esc_url( admin_url( 'admin-post.php?action=sspw_clear_changelog' ) ),
		esc_attr( wp_json_encode( __( 'Delete every entry in the changelog?', 'staging-superpowers' ) ) ),
		esc_html__( 'Clear the changelog', 'staging-superpowers' )
	);
	echo '</p>';
}

function sspw_clear_changelog() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'staging-superpowers' ) );
	}

	check_admin_referer( 'sspw_clear_changelog', 'sspw_changelog_nonce' );
	delete_option( SSPW_LOG_OPTION );

	wp_safe_redirect( sspw_settings_url( 'changelog' ) );
	exit;
}

/**
 * The WooCommerce log viewer, filtered to this plugin's entries.
 */
function sspw_changelog_url() {
	return sspw_changelog_uses_woocommerce() ? admin_url( 'admin.php?page=wc-status&tab=logs&source=' . SSPW_LOG_SOURCE ) : sspw_settings_url( 'changelog' );
}
