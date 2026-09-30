<?php
/**
 * Finds a plugin that replaced wp_mail() and so could send emails around the
 * staging block, for the Emails settings and the status bar.
 *
 * The block runs inside WordPress's wp_mail(), before any mailer, so plugins
 * that send through wp_mail() (most SMTP plugins) are covered. A plugin that
 * replaces wp_mail() with its own copy is not, so that is checked live.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Name of whatever replaced WordPress's wp_mail(), or '' when it is the original.
 */
function sspw_wp_mail_owner() {
	if ( ! function_exists( 'wp_mail' ) ) {
		return '';
	}

	$file = wp_normalize_path( (string) ( new ReflectionFunction( 'wp_mail' ) )->getFileName() );

	if ( wp_normalize_path( ABSPATH . WPINC . '/pluggable.php' ) === $file ) {
		return '';
	}

	$plugins = wp_normalize_path( WP_PLUGIN_DIR ) . '/';
	if ( 0 === strpos( $file, $plugins ) ) {
		$folder = strtok( substr( $file, strlen( $plugins ) ), '/' );
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( get_plugins() as $plugin_file => $data ) {
			if ( 0 === strpos( $plugin_file, $folder . '/' ) || $plugin_file === $folder ) {
				return $data['Name'];
			}
		}
		return $folder;
	}

	/* translators: %s: file name */
	return sprintf( __( 'a must-use plugin or custom code (%s)', 'staging-superpowers' ), basename( $file ) );
}

/**
 * The report shown under the Emails settings.
 */
function sspw_email_check_html() {
	$owner = sspw_wp_mail_owner();

	if ( $owner ) {
		/* translators: %s: plugin name */
		return '<span style="color:#b32d2e">&#9888; ' . esc_html( sprintf( __( '%s replaces the WordPress email function, so the block above cannot see its emails. Its sending service may already be blocked (see Plugin check below); otherwise switch that plugin off on staging.', 'staging-superpowers' ), $owner ) ) . '</span>';
	}

	return esc_html__( 'Every email goes through the WordPress email function, where it is blocked.', 'staging-superpowers' );
}
