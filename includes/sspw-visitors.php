<?php
/**
 * What logged-out visitors and non-staff accounts see: the site as normal, or
 * a plain message page instead. Only loaded when the site is armed.
 */

defined( 'ABSPATH' ) || exit;

// The choice is read when the page is built, so an add-on's own choice (added on
// sspw_loaded, after this file) is known by then. template_redirect never runs for
// wp-admin, wp-login.php, admin-ajax, REST, cron or WP-CLI, so those stay reachable.
add_action( 'template_redirect', 'sspw_visitor_page', -1000 );

/**
 * Runs before plugins and themes build the page, so visitors never reach any of
 * it (a shop, a checkout, a members area, a form). 503 tells search engines the
 * page is temporarily unavailable, which keeps it out of their index.
 */
function sspw_visitor_page() {
	if ( 'lock' !== sspw_get( 'sspw_visitors' ) || sspw_can_see_store() ) {
		return;
	}

	if ( sspw_visitor_path_allowed() ) {
		return;
	}

	$name = get_bloginfo( 'name' );

	// A plain message page: a customer who lands here should not have to
	// know what a staging site is. It is a complete document of its own, so its
	// styles are registered here and printed in its head with wp_print_styles().
	wp_register_style( 'sspw-visitor-page', false, array(), SSPW_VERSION );
	wp_add_inline_style(
		'sspw-visitor-page',
		'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f6f7f7;color:#1d2327;font:16px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;padding:32px 16px;box-sizing:border-box;text-align:center}' .
		'.sspw-card{max-width:480px}' .
		'.sspw-name{margin:0 0 24px;font-size:15px;letter-spacing:.08em;text-transform:uppercase;color:#646970}' .
		'.sspw-card h1{margin:0 0 12px;font-size:32px;line-height:1.2}' .
		'.sspw-card p{margin:0 0 24px}' .
		'.sspw-small{margin-top:40px;font-size:13px;color:#646970}' .
		'.sspw-small a{color:#646970}'
	);

	status_header( 503 );
	nocache_headers();
	header( 'Retry-After: 3600' );
	header( 'X-Robots-Tag: noindex, nofollow', true );
	header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );

	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $name ); ?></title>
	<?php wp_print_styles( 'sspw-visitor-page' ); ?>
</head>
<body>
<main class="sspw-card">
	<p class="sspw-name"><?php echo esc_html( $name ); ?></p>
	<h1><?php echo esc_html( sspw_get( 'sspw_message_title' ) ); ?></h1>
	<?php echo wp_kses_post( wpautop( sspw_get( 'sspw_message_text' ) ) ); ?>
	<p class="sspw-small">
	<?php if ( is_user_logged_in() ) : ?>
		<a href="<?php echo esc_url( wp_logout_url( sspw_current_url() ) ); ?>"><?php esc_html_e( 'Log out', 'staging-superpowers' ); ?></a>
	<?php else : ?>
		<a href="<?php echo esc_url( wp_login_url( sspw_current_url() ) ); ?>"><?php esc_html_e( 'Log in', 'staging-superpowers' ); ?></a>
	<?php endif; ?>
	</p>
</main>
</body>
</html>
	<?php
	exit;
}

/**
 * Front-end pages logged-out visitors can still reach: the login page (also a
 * custom one, through the login_url filter) and the WooCommerce My Account page,
 * where many stores log in.
 */
function sspw_visitor_path_allowed() {
	$paths = array( (string) wp_parse_url( wp_login_url(), PHP_URL_PATH ) );

	if ( function_exists( 'wc_get_page_permalink' ) && function_exists( 'wc_get_page_id' ) && wc_get_page_id( 'myaccount' ) > 0 ) {
		$paths[] = (string) wp_parse_url( wc_get_page_permalink( 'myaccount' ), PHP_URL_PATH );
	}

	/**
	 * Request paths logged-out visitors can still see, such as a custom login or
	 * register page. Sub-paths are included: "/account" also allows "/account/lost-password".
	 *
	 * @param string[] $paths Paths like "/my-account".
	 */
	$paths = (array) apply_filters( 'sspw_visitor_allowed_paths', $paths );

	$current = '/' . trim( (string) wp_parse_url( sspw_current_url(), PHP_URL_PATH ), '/' );
	foreach ( $paths as $path ) {
		$path = '/' . trim( (string) $path, '/' );
		if ( '/' !== $path && ( $current === $path || 0 === strpos( $current . '/', $path . '/' ) ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Kept for add-ons written for 1.2.1 and earlier, which checked a "team browser"
 * cookie. The cookie is no longer set, so checks against it simply never match.
 *
 * @deprecated 1.2.2
 */
function sspw_staff_cookie() {
	return 'sspw_staff_' . COOKIEHASH;
}
