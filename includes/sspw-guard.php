<?php
/**
 * Deploy guard: the plugin only does anything on the exact site URL it was armed on.
 * If the database moves to another URL (staging pushed to live, or a fresh clone),
 * every feature switches off until an admin re-arms it on the new URL.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_notices', 'sspw_guard_notice' );
add_action( 'admin_post_sspw_arm', 'sspw_handle_arm' );

/**
 * Raw option instead of home_url() so language/CDN plugins filtering the URL
 * can't make the fingerprint drift on every request.
 */
function sspw_site_fingerprint() {
	$home = (string) get_option( 'home' );
	$home = preg_replace( '#^https?://#i', '', $home );

	return strtolower( untrailingslashit( $home ) );
}

/**
 * Only an explicitly set "production" counts: WordPress defaults to production
 * when nothing is set, and most cloned staging sites never set it.
 */
function sspw_is_production_declared() {
	$declared = defined( 'WP_ENVIRONMENT_TYPE' ) || false !== getenv( 'WP_ENVIRONMENT_TYPE' );

	return $declared && 'production' === wp_get_environment_type();
}

function sspw_armed_for() {
	return (string) get_option( 'sspw_armed_for', '' );
}

function sspw_is_armed() {
	static $armed = null;

	if ( null === $armed ) {
		$stored = sspw_armed_for();
		$armed  = '' !== $stored && sspw_site_fingerprint() === $stored && ! sspw_is_production_declared();
	}

	return $armed;
}

function sspw_activate() {
	if ( sspw_is_production_declared() ) {
		return;
	}

	update_option( 'sspw_armed_for', sspw_site_fingerprint(), false );
}

function sspw_guard_notice() {
	if ( sspw_is_armed() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$stored  = sspw_armed_for();
	$current = sspw_site_fingerprint();

	echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Staging Superpowers is paused.', 'staging-superpowers-for-woocommerce' ) . '</strong> ';

	if ( sspw_is_production_declared() ) {
		esc_html_e( 'This site declares itself as production (WP_ENVIRONMENT_TYPE), so emails, payments, webhooks and scheduled actions run normally. Deactivate the plugin if this is your live store.', 'staging-superpowers-for-woocommerce' );
		echo '</p></div>';
		return;
	}

	if ( '' === $stored ) {
		esc_html_e( 'It has not been turned on for this site yet, so emails, payments, webhooks and scheduled actions run normally.', 'staging-superpowers-for-woocommerce' );
	} else {
		printf(
			/* translators: 1: site URL the plugin was armed on, 2: current site URL */
			esc_html__( 'It was turned on for %1$s but this site is now %2$s, so emails, payments, webhooks and scheduled actions run normally. If this is your live store, deactivate the plugin.', 'staging-superpowers-for-woocommerce' ),
			'<code>' . esc_html( $stored ) . '</code>',
			'<code>' . esc_html( $current ) . '</code>'
		);
	}

	echo '</p><p>';
	printf(
		'<a class="button button-primary" href="%s">%s</a>',
		esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sspw_arm' ), 'sspw_arm' ) ),
		/* translators: %s: current site URL */
		esc_html( sprintf( __( 'This is a staging site: turn on for %s', 'staging-superpowers-for-woocommerce' ), $current ) )
	);
	echo '</p></div>';
}

function sspw_handle_arm() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'staging-superpowers-for-woocommerce' ) );
	}

	check_admin_referer( 'sspw_arm' );

	if ( ! sspw_is_production_declared() ) {
		update_option( 'sspw_armed_for', sspw_site_fingerprint(), false );
	}

	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
	exit;
}
