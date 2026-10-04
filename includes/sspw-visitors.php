<?php
/**
 * What logged-out visitors and non-staff accounts see: the same page on the
 * live site, a "this is a staging site" page, or the normal site with a
 * STAGING bar. Only loaded when the site is armed.
 */

defined( 'ABSPATH' ) || exit;

if ( in_array( sspw_get( 'sspw_visitors' ), array( 'redirect', 'lock' ), true ) ) {
	add_action( 'template_redirect', 'sspw_visitor_page', -1000 );
	add_action( 'wp_login', 'sspw_remember_staff', 10, 2 );
	add_action( 'admin_init', 'sspw_remember_staff_session' );
} elseif ( 'bar' === sspw_get( 'sspw_visitors' ) ) {
	add_action( 'wp_enqueue_scripts', 'sspw_visitor_bar_style' );
	add_action( 'wp_footer', 'sspw_visitor_bar' );
}

/**
 * The same bar on the visitor page and on the site, looking like the admin bar
 * so it reads as "not the real site" at a glance.
 */
function sspw_visitor_bar_html() {
	$live = sspw_live_url();

	$html  = '<div id="sspw-visitor-bar" role="note">';
	$html .= '<span class="sspw-badge">' . esc_html__( 'STAGING', 'staging-superpowers' ) . '</span>';
	$html .= '<span class="sspw-text">' . esc_html__( 'This is a test copy of the site. Nothing here is real.', 'staging-superpowers' ) . '</span>';
	$html .= '<span class="sspw-links">';
	if ( $live ) {
		$path  = (string) wp_parse_url( sspw_current_url(), PHP_URL_PATH );
		$html .= '<a href="' . esc_url( $live . $path ) . '">' . esc_html__( 'Open this page on the live site', 'staging-superpowers' ) . '</a>';
	}
	if ( ! is_user_logged_in() ) {
		$html .= '<a href="' . esc_url( wp_login_url( sspw_current_url() ) ) . '">' . esc_html__( 'Log in', 'staging-superpowers' ) . '</a>';
	}
	$html .= '</span></div>';

	return $html;
}

function sspw_visitor_bar_css() {
	return '#sspw-visitor-bar{position:fixed;top:0;left:0;right:0;z-index:999999;min-height:32px;display:flex;align-items:center;gap:12px;padding:0 12px 0 0;box-sizing:border-box;background:#b91c1c;color:#fff;font:13px/32px -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif}' .
		'#sspw-visitor-bar .sspw-badge{background:#dc2626;font-weight:700;letter-spacing:.08em;padding:0 10px;align-self:stretch}' .
		'#sspw-visitor-bar .sspw-text{flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}' .
		'#sspw-visitor-bar .sspw-links{display:flex;gap:14px;white-space:nowrap}' .
		'#sspw-visitor-bar a{color:#fff;text-decoration:underline}' .
		'@media screen and (max-width:600px){#sspw-visitor-bar .sspw-text{display:none}#sspw-visitor-bar{justify-content:space-between}}';
}

/**
 * People who see the real admin bar don't need the fake one.
 */
function sspw_show_visitor_bar() {
	return ! is_admin_bar_showing();
}

function sspw_visitor_bar_style() {
	if ( sspw_show_visitor_bar() ) {
		wp_register_style( 'sspw-visitor-bar', false, array(), SSPW_VERSION );
		wp_enqueue_style( 'sspw-visitor-bar' );
		wp_add_inline_style( 'sspw-visitor-bar', sspw_visitor_bar_css() . 'html{margin-top:32px !important}' );
	}
}

function sspw_visitor_bar() {
	if ( sspw_show_visitor_bar() ) {
		echo sspw_visitor_bar_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped when built.
	}
}

/**
 * Runs before plugins and themes build the page, so visitors never reach any of
 * it (a shop, a checkout, a members area, a form). 503 tells search engines the
 * page is temporarily unavailable, which keeps it out of their index.
 */
function sspw_visitor_page() {
	if ( sspw_can_see_store() ) {
		return;
	}

	// A browser someone on the team has logged in with goes to the login page,
	// not the live site, so an expired session never lands them on live.
	if ( ! is_user_logged_in() && isset( $_COOKIE[ sspw_staff_cookie() ] ) ) {
		wp_safe_redirect( wp_login_url( sspw_current_url() ), 302, 'Staging Superpowers' );
		exit;
	}

	$name = get_bloginfo( 'name' );
	$live = sspw_live_url();

	// 302, not 301: browsers remember a 301, and would keep sending the admin
	// to the live site even after logging in here.
	if ( 'redirect' === sspw_get( 'sspw_visitors' ) && $live ) {
		header( 'X-Robots-Tag: noindex, nofollow', true );
		wp_redirect( $live . sspw_current_path(), 302, 'Staging Superpowers' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- the admin-saved live site is on another host by design.
		exit;
	}

	// The page below is a complete document of its own, so its styles are
	// registered here and printed in its head with wp_print_styles().
	wp_register_style( 'sspw-visitor-page', false, array(), SSPW_VERSION );
	wp_add_inline_style(
		'sspw-visitor-page',
		sspw_visitor_bar_css() .
		'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#fef2f2;color:#1c1917;font:16px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;padding:72px 16px 32px;box-sizing:border-box}' .
		'.sspw-card{max-width:520px;background:#fff;border:1px solid #fecaca;border-radius:12px;padding:32px;box-shadow:0 10px 30px rgba(185,28,28,.08)}' .
		'.sspw-card h1{margin:0 0 12px;font-size:26px;line-height:1.25}' .
		'.sspw-card p{margin:0 0 16px}' .
		'.sspw-button{display:inline-block;background:#b91c1c;color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px;font-weight:600}' .
		'.sspw-button:hover,.sspw-button:focus{background:#991b1b;color:#fff}' .
		'.sspw-small{font-size:14px;color:#57534e}' .
		'.sspw-small a{color:#b91c1c}'
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
<title><?php echo esc_html( sprintf( /* translators: %s: site name */ __( 'Staging site: %s', 'staging-superpowers' ), $name ) ); ?></title>
	<?php wp_print_styles( 'sspw-visitor-page' ); ?>
</head>
<body>
	<?php echo sspw_visitor_bar_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped when built. ?>
<main class="sspw-card">
	<h1><?php esc_html_e( 'This is a staging site', 'staging-superpowers' ); ?></h1>
	<p>
	<?php
	/* translators: %s: site name */
	echo esc_html( sprintf( __( 'You found a private test copy of %s. It is used to try out changes safely, so nothing here is real and nothing you do here reaches the real site.', 'staging-superpowers' ), $name ) );
	?>
	</p>
	<?php if ( $live ) : ?>
		<p><a class="sspw-button" href="<?php echo esc_url( $live ); ?>"><?php esc_html_e( 'Go to the live site', 'staging-superpowers' ); ?></a></p>
	<?php endif; ?>
	<p class="sspw-small">
	<?php if ( is_user_logged_in() ) : ?>
		<?php
		/* translators: %s: user display name */
		echo esc_html( sprintf( __( 'You are logged in as %s, but this account cannot see the staging site.', 'staging-superpowers' ), wp_get_current_user()->display_name ) );
		?>
		<a href="<?php echo esc_url( wp_logout_url( sspw_current_url() ) ); ?>"><?php esc_html_e( 'Log out', 'staging-superpowers' ); ?></a>
	<?php else : ?>
		<?php esc_html_e( 'Work on this site?', 'staging-superpowers' ); ?>
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
 * Marks this browser as used by the team. It grants nothing: it only decides
 * whether a logged-out visit goes to the login page or to the live site.
 */
function sspw_staff_cookie() {
	return 'sspw_staff_' . COOKIEHASH;
}

function sspw_set_staff_cookie() {
	setcookie( sspw_staff_cookie(), '1', time() + YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
}

function sspw_remember_staff( $user_login, $user ) {
	if ( user_can( $user, 'edit_posts' ) || user_can( $user, 'manage_options' ) || user_can( $user, 'manage_woocommerce' ) ) {
		sspw_set_staff_cookie();
	}
}

/**
 * People already logged in when the plugin was turned on get the cookie on their
 * next admin page, without logging in again.
 */
function sspw_remember_staff_session() {
	if ( ! isset( $_COOKIE[ sspw_staff_cookie() ] ) && sspw_can_see_store() && ! wp_doing_ajax() && ! headers_sent() ) {
		sspw_set_staff_cookie();
	}
}
