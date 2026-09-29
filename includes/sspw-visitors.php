<?php
/**
 * What shoppers and logged-out visitors see: a "this is a staging site" page,
 * or the normal site with a STAGING bar. Only loaded when the site is armed.
 */

defined( 'ABSPATH' ) || exit;

if ( in_array( sspw_get( 'sspw_visitors' ), array( 'redirect', 'lock' ), true ) ) {
	add_action( 'template_redirect', 'sspw_visitor_page', -1000 );
	add_filter( 'rest_pre_dispatch', 'sspw_visitor_store_api', 10, 3 );
} elseif ( 'bar' === sspw_get( 'sspw_visitors' ) ) {
	add_action( 'wp_head', 'sspw_visitor_bar_style' );
	add_action( 'wp_footer', 'sspw_visitor_bar' );
}

/**
 * The same bar on the visitor page and on the site, looking like the admin bar
 * so it reads as "not the real store" at a glance.
 */
function sspw_visitor_bar_html() {
	$live = sspw_live_url();

	$html  = '<div id="sspw-visitor-bar" role="note">';
	$html .= '<span class="sspw-badge">' . esc_html__( 'STAGING', 'staging-superpowers-for-woocommerce' ) . '</span>';
	$html .= '<span class="sspw-text">' . esc_html__( 'This is a test copy of the store. Orders and payments are not real.', 'staging-superpowers-for-woocommerce' ) . '</span>';
	$html .= '<span class="sspw-links">';
	if ( $live ) {
		$html .= '<a href="' . esc_url( $live ) . '">' . esc_html__( 'Go to the live store', 'staging-superpowers-for-woocommerce' ) . '</a>';
	}
	if ( ! is_user_logged_in() ) {
		$html .= '<a href="' . esc_url( wp_login_url( sspw_current_url() ) ) . '">' . esc_html__( 'Log in', 'staging-superpowers-for-woocommerce' ) . '</a>';
	}
	$html .= '</span></div>';

	return $html;
}

function sspw_visitor_bar_css() {
	return '#sspw-visitor-bar{position:fixed;top:0;left:0;right:0;z-index:999999;min-height:32px;display:flex;align-items:center;gap:12px;padding:0 12px 0 0;box-sizing:border-box;background:#c2410c;color:#fff;font:13px/32px -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif}' .
		'#sspw-visitor-bar .sspw-badge{background:#7c2d12;font-weight:700;letter-spacing:.08em;padding:0 10px;align-self:stretch}' .
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
		echo '<style id="sspw-visitor-bar-css">' . sspw_visitor_bar_css() . 'html{margin-top:32px !important}</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS.
	}
}

function sspw_visitor_bar() {
	if ( sspw_show_visitor_bar() ) {
		echo sspw_visitor_bar_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped when built.
	}
}

/**
 * Runs before WooCommerce and themes touch the request, so shoppers never reach the
 * shop, cart or checkout. 503 tells search engines the page is temporarily
 * unavailable, which keeps it out of their index.
 */
function sspw_visitor_page() {
	if ( sspw_can_see_store() ) {
		return;
	}

	$name = get_bloginfo( 'name' );
	$live = sspw_live_url();

	// 302, not 301: browsers remember a 301, and would keep sending the admin
	// to the live store even after logging in here.
	if ( 'redirect' === sspw_get( 'sspw_visitors' ) && $live ) {
		header( 'X-Robots-Tag: noindex, nofollow', true );
		wp_redirect( $live . sspw_current_path(), 302, 'Staging Superpowers' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- the admin-saved live store is on another host by design.
		exit;
	}

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
<title><?php echo esc_html( sprintf( /* translators: %s: site name */ __( 'Staging site: %s', 'staging-superpowers-for-woocommerce' ), $name ) ); ?></title>
<style>
	<?php echo sspw_visitor_bar_css(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS. ?>
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#fff7ed;color:#1c1917;font:16px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;padding:72px 16px 32px;box-sizing:border-box}
.sspw-card{max-width:520px;background:#fff;border:1px solid #fed7aa;border-radius:12px;padding:32px;box-shadow:0 10px 30px rgba(124,45,18,.08)}
.sspw-card h1{margin:0 0 12px;font-size:26px;line-height:1.25}
.sspw-card p{margin:0 0 16px}
.sspw-button{display:inline-block;background:#c2410c;color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px;font-weight:600}
.sspw-button:hover,.sspw-button:focus{background:#9a3412;color:#fff}
.sspw-small{font-size:14px;color:#57534e}
.sspw-small a{color:#9a3412}
</style>
</head>
<body>
	<?php echo sspw_visitor_bar_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped when built. ?>
<main class="sspw-card">
	<h1><?php esc_html_e( 'This is a staging site', 'staging-superpowers-for-woocommerce' ); ?></h1>
	<p>
	<?php
	/* translators: %s: site name */
	echo esc_html( sprintf( __( 'You found a private test copy of %s. It is used to try out changes safely, so nothing here is real: orders are not shipped and payments do not go through.', 'staging-superpowers-for-woocommerce' ), $name ) );
	?>
	</p>
	<?php if ( $live ) : ?>
		<p><a class="sspw-button" href="<?php echo esc_url( $live ); ?>"><?php esc_html_e( 'Go to the live store', 'staging-superpowers-for-woocommerce' ); ?></a></p>
	<?php endif; ?>
	<p class="sspw-small">
	<?php if ( is_user_logged_in() ) : ?>
		<?php
		/* translators: %s: user display name */
		echo esc_html( sprintf( __( 'You are logged in as %s, but this account cannot see the staging site.', 'staging-superpowers-for-woocommerce' ), wp_get_current_user()->display_name ) );
		?>
		<a href="<?php echo esc_url( wp_logout_url( sspw_current_url() ) ); ?>"><?php esc_html_e( 'Log out', 'staging-superpowers-for-woocommerce' ); ?></a>
	<?php else : ?>
		<?php esc_html_e( 'Work on this site?', 'staging-superpowers-for-woocommerce' ); ?>
		<a href="<?php echo esc_url( wp_login_url( sspw_current_url() ) ); ?>"><?php esc_html_e( 'Log in', 'staging-superpowers-for-woocommerce' ); ?></a>
	<?php endif; ?>
	</p>
</main>
</body>
</html>
	<?php
	exit;
}

/**
 * The cart and checkout blocks talk to the Store API directly, so the visitor
 * page alone would not stop a determined shopper (or bot) from ordering.
 */
function sspw_visitor_store_api( $result, $server, $request ) {
	if ( 0 === strpos( $request->get_route(), '/wc/store' ) && ! sspw_can_see_store() ) {
		return new WP_Error( 'sspw_staging_site', __( 'This is a staging site. Orders are not accepted.', 'staging-superpowers-for-woocommerce' ), array( 'status' => 503 ) );
	}

	return $result;
}
