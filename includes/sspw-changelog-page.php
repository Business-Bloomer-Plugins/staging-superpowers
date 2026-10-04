<?php
/**
 * Changelog sub-page. The recording itself is in sspw-changelog.php, which
 * only loads while the site is armed; this page shows either way.
 */

defined( 'ABSPATH' ) || exit;

define( 'SSPW_LOG_OPTION', 'sspw_changelog' );
define( 'SSPW_LOG_LIMIT', 1000 );

add_action( 'admin_post_sspw_clear_changelog', 'sspw_clear_changelog' );

/**
 * @return array Entries, oldest first: array( timestamp, message ).
 */
function sspw_changelog_entries() {
	return (array) get_option( SSPW_LOG_OPTION, array() );
}

function sspw_output_changelog() {
	echo '<h2>' . esc_html__( 'Changelog', 'staging-superpowers' ) . '</h2>';
	echo '<p>' . esc_html__( 'Everything changed on this staging copy is recorded here, so you have a list of what to redo on your live site instead of copying this database over it (which would wipe every comment, order, sign-up or form entry made on the live site since the copy was made).', 'staging-superpowers' ) . '</p>';
	if ( sspw_has_woocommerce() ) {
		echo '<p>' . esc_html__( 'Recorded: the settings of this plugin and of WooCommerce (with the old and new value), theme and plugin changes and updates, the cart or checkout switching between blocks and classic, and posts, pages, products, coupons, categories, tags and menus created, edited or deleted.', 'staging-superpowers' ) . '</p>';
	} else {
		echo '<p>' . esc_html__( 'Recorded: the settings of this plugin (with the old and new value), theme and plugin changes and updates, and posts, pages, categories, tags and menus created, edited or deleted.', 'staging-superpowers' ) . '</p>';
	}

	if ( ! sspw_is_armed() ) {
		echo '<p>' . esc_html__( 'Nothing is recorded while Staging Superpowers is paused.', 'staging-superpowers' ) . '</p>';
	} elseif ( get_option( 'sspw_armed_at' ) ) {
		/* translators: %s: date */
		echo '<p><strong>' . esc_html( sprintf( __( 'Recording since %s.', 'staging-superpowers' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) get_option( 'sspw_armed_at' ) ) ) ) . '</strong></p>';
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

	/* translators: %s: number of entries */
	echo '<p class="description">' . esc_html( sprintf( __( 'Newest first. The latest %s changes are kept.', 'staging-superpowers' ), number_format_i18n( SSPW_LOG_LIMIT ) ) ) . '</p><p>';
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
	return sspw_settings_url( 'changelog' );
}
