<?php
/**
 * Finds plugins that could send emails around the staging block, for the
 * Emails settings and the status bar.
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
	return sprintf( __( 'a must-use plugin or custom code (%s)', 'staging-superpowers-for-woocommerce' ), basename( $file ) );
}

/**
 * Email plugins worth knowing about, by folder. "covered" means the block
 * handles them; "check" means they can send some emails their own way.
 */
function sspw_known_email_plugins() {
	$through_wp_mail = __( 'Covered: it sends through WordPress, where the block stops every email first.', 'staging-superpowers-for-woocommerce' );
	$api_blocked     = __( 'Covered: its sending service is on the blocked services list, and the check above warns you if it replaces WordPress\'s email function.', 'staging-superpowers-for-woocommerce' );

	return array(
		'wp-mail-smtp'                       => array( 'covered', $through_wp_mail ),
		'fluent-smtp'                        => array( 'covered', $through_wp_mail ),
		'post-smtp'                          => array( 'covered', $through_wp_mail ),
		'easy-wp-smtp'                       => array( 'covered', $through_wp_mail ),
		'suremails'                          => array( 'covered', $through_wp_mail ),
		'gosmtp'                             => array( 'covered', $through_wp_mail ),
		'mailgun'                            => array( 'covered', $api_blocked ),
		'sendgrid-email-delivery-simplified' => array( 'covered', $api_blocked ),
		'sparkpost'                          => array( 'covered', $api_blocked ),
		'wpmandrill'                         => array( 'covered', $api_blocked ),
		'mailpoet'                           => array( 'check', __( 'Check: MailPoet sends newsletters from its own queue. That queue stays frozen while scheduled actions and WP-Cron are frozen, and the MailPoet Sending Service is blocked. If MailPoet sends through your own SMTP server, keep both freezes on.', 'staging-superpowers-for-woocommerce' ) ),
		'wp-ses'                             => array( 'check', __( 'Check: it sends through Amazon SES directly. Add your SES address (for example email.eu-west-1.amazonaws.com) to the blocked services.', 'staging-superpowers-for-woocommerce' ) ),
		'wp-offload-ses'                     => array( 'check', __( 'Check: it sends through Amazon SES directly. Add your SES address (for example email.eu-west-1.amazonaws.com) to the blocked services.', 'staging-superpowers-for-woocommerce' ) ),
		'wp-offload-ses-lite'                => array( 'check', __( 'Check: it sends through Amazon SES directly. Add your SES address (for example email.eu-west-1.amazonaws.com) to the blocked services.', 'staging-superpowers-for-woocommerce' ) ),
	);
}

/**
 * Active email plugins from the list above: folder => array( name, status, note ).
 */
function sspw_active_email_plugins() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$known  = sspw_known_email_plugins();
	$active = array();

	foreach ( get_plugins() as $file => $data ) {
		$folder = dirname( $file );
		if ( isset( $known[ $folder ] ) && is_plugin_active( $file ) ) {
			$active[ $folder ] = array( $data['Name'], $known[ $folder ][0], $known[ $folder ][1] );
		}
	}

	return $active;
}

/**
 * The report shown under the Emails settings.
 */
function sspw_email_check_html() {
	$owner  = sspw_wp_mail_owner();
	$active = sspw_active_email_plugins();

	$html = '<strong>' . esc_html__( 'Email check:', 'staging-superpowers-for-woocommerce' ) . '</strong> ';

	if ( $owner ) {
		/* translators: %s: plugin name */
		$html .= '<span style="color:#b32d2e">&#9888; ' . esc_html( sprintf( __( '%s replaces the WordPress email function, so the block above cannot see its emails. Its sending service may already be blocked (see below); otherwise switch that plugin off on staging.', 'staging-superpowers-for-woocommerce' ), $owner ) ) . '</span>';
	} else {
		$html .= esc_html__( 'every email goes through the WordPress email function, where it is blocked.', 'staging-superpowers-for-woocommerce' );
	}

	if ( $active ) {
		$html .= '<ul style="list-style:disc;margin:6px 0 0 20px">';
		foreach ( $active as $plugin ) {
			$html .= '<li><strong>' . esc_html( $plugin[0] ) . '</strong>: ' . esc_html( $plugin[2] ) . '</li>';
		}
		$html .= '</ul>';
	}

	return $html;
}
