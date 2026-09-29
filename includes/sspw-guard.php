<?php
/**
 * Deploy guard: the plugin only does anything on the exact site URL it was armed on.
 * It arms itself only where the site clearly looks like staging; anywhere else
 * (a mistaken install on a live store, or staging copied over live) it stays off
 * until an admin confirms the site is a copy.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_notices', 'sspw_guard_notice' );
add_action( 'admin_post_sspw_arm', 'sspw_handle_arm' );
add_action( 'plugins_loaded', 'sspw_auto_arm', 5 );

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

/**
 * A copied wp-config.php can still say "production" on a staging copy. An admin can
 * confirm the copy anyway, but only for this URL: the override stops applying the
 * moment the database lands anywhere else.
 */
function sspw_production_overridden() {
	return sspw_site_fingerprint() === (string) get_option( 'sspw_production_override', '' );
}

function sspw_is_armed() {
	static $armed = null;

	if ( null === $armed ) {
		$stored = sspw_armed_for();
		$armed  = '' !== $stored && sspw_site_fingerprint() === $stored && ( ! sspw_is_production_declared() || sspw_production_overridden() );
	}

	return $armed;
}

/**
 * Signals that only a staging, development or local copy would have. A live
 * store matches none of them, so installing there by mistake changes nothing.
 */
function sspw_looks_like_staging() {
	$host  = strtolower( (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_HOST ) );
	$looks = in_array( wp_get_environment_type(), array( 'staging', 'development', 'local' ), true ) || sspw_host_looks_like_staging( $host );

	/**
	 * Whether this site should count as staging, so the plugin turns itself on.
	 *
	 * @param bool   $looks Result of the built-in checks.
	 * @param string $host  Site host name.
	 */
	return (bool) apply_filters( 'sspw_looks_like_staging', $looks, $host );
}

function sspw_host_looks_like_staging( $host ) {
	if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
		return true;
	}

	if ( preg_match( '/^(staging|stage|stg|dev|develop|test|testing|uat|qa|preview|sandbox)[0-9-]*\./', $host ) || preg_match( '/[.-](staging|stage|stg)[0-9]*[.-]/', $host ) ) {
		return true;
	}

	if ( preg_match( '/\.(local|test|localhost|invalid|example)$/', $host ) ) {
		return true;
	}

	// Temporary and staging domains that hosting companies hand out.
	$hosts = array( 'wpengine.com', 'wpenginepowered.com', 'kinsta.cloud', 'cloudwaysapps.com', 'flywheelstaging.com', 'flywheelsites.com', 'pantheonsite.io', 'instawp.xyz', 'instawp.co', 'instawp.link', 'tastewp.com', 'wpcomstaging.com', 'mystagingwebsite.com', 'myftpupload.com', 'templ.io', 'runcloud.link', 'bigscoots-staging.com', 'closte.com', 'onrocket.site', 'rapydapps.cloud', 'wpdns.site', 'jurassic.ninja' );
	foreach ( $hosts as $suffix ) {
		if ( str_ends_with( $host, '.' . $suffix ) ) {
			return true;
		}
	}

	return false;
}

function sspw_activate() {
	if ( ! sspw_is_production_declared() && sspw_looks_like_staging() ) {
		sspw_arm();
	}
}

/**
 * A staging copy refreshed to another staging address turns itself back on, so
 * nobody has to remember to click. Live-looking addresses never do.
 */
function sspw_auto_arm() {
	if ( sspw_armed_for() !== sspw_site_fingerprint() && ! sspw_is_production_declared() && sspw_looks_like_staging() ) {
		sspw_arm();
	}
}

/**
 * The time is kept so the changelog can say since when it has been recording.
 */
function sspw_arm() {
	update_option( 'sspw_armed_for', sspw_site_fingerprint(), false );
	update_option( 'sspw_armed_at', time(), false );

	if ( 'no' !== get_option( 'sspw_no_cache', 'yes' ) ) {
		sspw_purge_page_caches();
	}
}

/**
 * Pages cached before the plugin was on would still be served (and skip the
 * visitor redirect), so the known page caches are emptied once. Each call only
 * runs if that cache plugin is active.
 */
function sspw_purge_page_caches() {
	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- each cache plugin's own purge hook.
	if ( function_exists( 'rocket_clean_domain' ) ) {
		rocket_clean_domain();
	}
	if ( function_exists( 'w3tc_flush_all' ) ) {
		w3tc_flush_all();
	}
	if ( function_exists( 'wp_cache_clear_cache' ) ) {
		wp_cache_clear_cache();
	}
	if ( function_exists( 'wpfc_clear_all_cache' ) ) {
		wpfc_clear_all_cache( true );
	}
	if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
		sg_cachepress_purge_cache();
	}
	if ( function_exists( 'wpo_cache_flush' ) ) {
		wpo_cache_flush();
	}
	if ( class_exists( 'WpeCommon' ) && method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) {
		WpeCommon::purge_varnish_cache();
	}
	do_action( 'litespeed_purge_all' );
	do_action( 'cache_enabler_clear_complete_cache' );
	do_action( 'breeze_clear_all_cache' );
	do_action( 'wphb_clear_page_cache' );
	// phpcs:enable
}

function sspw_guard_notice() {
	if ( sspw_is_armed() || ! current_user_can( 'manage_options' ) || ! sspw_is_notice_screen() ) {
		return;
	}

	$stored  = sspw_armed_for();
	$current = sspw_site_fingerprint();

	$confirm = ' onclick="return confirm( ' . esc_attr( wp_json_encode( __( 'Only continue if this is NOT your live store. Emails will be blocked and customers will not be able to pay. Turn Staging Superpowers on?', 'staging-superpowers-for-woocommerce' ) ) ) . ' );"';

	echo '<div class="notice notice-' . ( '' === $stored ? 'warning' : 'error' ) . '"><p><strong>' . esc_html__( 'Staging Superpowers is not turned on.', 'staging-superpowers-for-woocommerce' ) . '</strong> ';

	if ( sspw_is_production_declared() && ! sspw_production_overridden() ) {
		esc_html_e( 'This site\'s configuration says it is the live store, so emails, payments, webhooks and scheduled actions run normally. If it really is your live store, deactivate the plugin. If it is a copy of your store (for example one you cloned by hand, which keeps the live configuration), you can turn the plugin on anyway.', 'staging-superpowers-for-woocommerce' );
		echo '</p><p>';
		printf(
			'<a class="button button-primary" href="%1$s"%2$s>%3$s</a>',
			esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sspw_arm' ), 'sspw_arm' ) ),
			$confirm, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped when built above.
			/* translators: %s: current site URL */
			esc_html( sprintf( __( 'This is a copy, not my live store: turn on for %s', 'staging-superpowers-for-woocommerce' ), $current ) )
		);
		echo '</p></div>';
		return;
	}

	if ( '' === $stored ) {
		esc_html_e( 'This site\'s address does not look like a staging site, so the plugin has not turned itself on and your store runs normally. If this is your live store, deactivate the plugin. If it is a staging copy, turn it on below.', 'staging-superpowers-for-woocommerce' );
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
		'<a class="button button-primary" href="%1$s"%2$s>%3$s</a>',
		esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sspw_arm' ), 'sspw_arm' ) ),
		$confirm, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped when built above.
		/* translators: %s: current site URL */
		esc_html( sprintf( __( 'This is a staging site: turn on for %s', 'staging-superpowers-for-woocommerce' ), $current ) )
	);
	echo '</p></div>';
}

/**
 * The "not turned on" notice only appears where it helps: the Dashboard, the
 * Plugins screen and this plugin's own settings.
 */
function sspw_is_notice_screen() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! $screen ) {
		return false;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of which settings tab is open.
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

	return in_array( $screen->id, array( 'dashboard', 'plugins' ), true ) || ( 'woocommerce_page_wc-settings' === $screen->id && 'sspw' === $tab );
}

function sspw_handle_arm() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'staging-superpowers-for-woocommerce' ) );
	}

	check_admin_referer( 'sspw_arm' );

	sspw_arm();

	if ( sspw_is_production_declared() ) {
		update_option( 'sspw_production_override', sspw_site_fingerprint(), false );
	}

	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
	exit;
}
