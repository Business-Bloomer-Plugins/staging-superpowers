<?php
/**
 * Troubleshooting sub-page: switch plugins and the theme off and back on to find
 * conflicts. Only works while the site is armed, so a live site can never be
 * bulk-disabled from here.
 *
 * Plugins are switched silently (no activation/deactivation routines), because
 * this is a temporary test and those routines can reset schedules or settings.
 *
 * Every switch is checked: the site is loaded in the background, logged out and
 * as the current user, and if that hits a fatal error the switch is undone.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_sspw_troubleshoot', 'sspw_handle_troubleshoot' );

add_action( 'admin_post_sspw_troubleshoot_cancel', 'sspw_cancel_troubleshoot_plan' );

/**
 * Only this plugin is never switched off. WooCommerce can be, like any other.
 */
function sspw_always_kept_plugins() {
	return array( plugin_basename( SSPW_PLUGIN_FILE ) );
}

/**
 * Plugins a plugin cannot run without: its "Requires Plugins" header, and
 * WooCommerce for WooCommerce.com extensions (the "Woo" header) that do not
 * have it. "WC requires at least" alone is not enough: plugins like MailPoet
 * declare it for an optional integration and run fine without WooCommerce.
 * Anything this misses is caught by the check after switching.
 *
 * @return string[] Plugin files.
 */
function sspw_plugin_needs( $file ) {
	static $by_slug = null;

	if ( null === $by_slug ) {
		$by_slug = array();
		foreach ( array_keys( get_plugins() ) as $installed ) {
			$by_slug[ dirname( $installed ) ] = $installed;
		}
	}

	$needs = array();

	if ( class_exists( 'WP_Plugin_Dependencies' ) ) {
		WP_Plugin_Dependencies::initialize();
		foreach ( WP_Plugin_Dependencies::get_dependencies( $file ) as $slug ) {
			if ( isset( $by_slug[ $slug ] ) ) {
				$needs[] = $by_slug[ $slug ];
			}
		}
	}

	$headers = get_file_data( WP_PLUGIN_DIR . '/' . $file, array( 'woo' => 'Woo' ) );
	if ( '' !== $headers['woo'] && isset( $by_slug['woocommerce'] ) && 'woocommerce' !== dirname( $file ) ) {
		$needs[] = $by_slug['woocommerce'];
	}

	return array_values( array_unique( $needs ) );
}

/**
 * What "switch off all other plugins" will do. A plugin you keep that needs a
 * plugin going off would break, so it goes off too, and that repeats until
 * nothing left on needs something that is off.
 *
 * @param string[] $keep Plugin files ticked to stay on.
 * @return array 'off' => files, 'also' => file => names of what it needs.
 */
function sspw_plan_switch_off( $keep ) {
	$active = (array) get_option( 'active_plugins', array() );
	$keep   = array_values( array_intersect( array_unique( array_merge( sspw_always_kept_plugins(), $keep ) ), $active ) );
	$off    = array_values( array_diff( $active, $keep ) );
	$also   = array();

	do {
		$moved = false;
		foreach ( $keep as $i => $file ) {
			if ( in_array( $file, sspw_always_kept_plugins(), true ) ) {
				continue;
			}
			$missing = array_intersect( sspw_plugin_needs( $file ), $off );
			if ( $missing ) {
				$off[]         = $file;
				$also[ $file ] = array_map( 'sspw_plugin_label', $missing );
				unset( $keep[ $i ] );
				$moved = true;
			}
		}
	} while ( $moved );

	return array(
		'off'  => $off,
		'also' => $also,
	);
}

function sspw_plugin_label( $file ) {
	$all = get_plugins();

	return isset( $all[ $file ] ) ? $all[ $file ]['Name'] : $file;
}

function sspw_disabled_plugins() {
	return (array) get_option( 'sspw_disabled_plugins', array() );
}

function sspw_previous_theme() {
	return (string) get_option( 'sspw_previous_theme', '' );
}

/**
 * Newest bundled Twenty-something theme, falling back to Storefront.
 */
function sspw_default_theme() {
	$core = WP_Theme::get_core_default_theme();
	if ( $core ) {
		return $core;
	}

	$storefront = wp_get_theme( 'storefront' );

	return $storefront->exists() ? $storefront : false;
}

function sspw_troubleshoot_url() {
	return sspw_settings_url( 'troubleshooting' );
}

function sspw_output_troubleshooting() {
	$notice = get_transient( 'sspw_notice_' . get_current_user_id() );
	if ( $notice ) {
		delete_transient( 'sspw_notice_' . get_current_user_id() );
		echo '<div class="notice notice-success inline"><p>' . esc_html( $notice ) . '</p></div>';
	}

	echo '<h2>' . esc_html__( 'Troubleshooting', 'staging-superpowers' ) . '</h2>';

	if ( ! sspw_is_armed() ) {
		echo '<p>' . esc_html__( 'Troubleshooting is only available while Staging Superpowers is turned on for this site, so a live site can never be switched off by accident.', 'staging-superpowers' ) . '</p>';
		return;
	}

	echo '<p>' . esc_html__( 'When something is broken, the cause is often another plugin or the theme. Switch them off here, check if the problem is gone, then switch them back on. Changes apply to the whole site, which is fine on a staging copy.', 'staging-superpowers' ) . '</p>';

	$action = admin_url( 'admin-post.php?action=sspw_troubleshoot' );
	wp_nonce_field( 'sspw_troubleshoot', 'sspw_nonce' );

	if ( current_user_can( 'activate_plugins' ) ) {
		sspw_output_plugin_tools( $action );
	}

	if ( current_user_can( 'switch_themes' ) ) {
		sspw_output_theme_tools( $action );
	}

	sspw_output_more_tools();
}

function sspw_button( $action, $task, $label, $confirm = '', $primary = false ) {
	printf(
		'<button type="submit" class="button %1$s sspw-do" formaction="%2$s" formmethod="post" name="sspw_do" value="%3$s"%4$s>%5$s</button> ',
		$primary ? 'button-primary' : '',
		esc_url( $action ),
		esc_attr( $task ),
		$confirm ? ' onclick="return confirm( ' . esc_attr( wp_json_encode( $confirm ) ) . ' );"' : '',
		esc_html( $label )
	);
}

function sspw_output_plugin_tools( $action ) {
	$all      = get_plugins();
	$active   = (array) get_option( 'active_plugins', array() );
	$kept     = sspw_always_kept_plugins();
	$disabled = array_intersect( sspw_disabled_plugins(), array_keys( $all ) );
	$plan     = get_transient( 'sspw_plan_' . get_current_user_id() );

	echo '<h3>' . esc_html__( 'Plugins', 'staging-superpowers' ) . '</h3>';

	if ( $disabled ) {
		echo '<p><strong>' . esc_html(
			sprintf(
				/* translators: %d: number of plugins */
				_n( '%d plugin is switched off for troubleshooting:', '%d plugins are switched off for troubleshooting:', count( $disabled ), 'staging-superpowers' ),
				count( $disabled )
			)
		) . '</strong> ' . esc_html( implode( ', ', array_map( 'sspw_plugin_label', $disabled ) ) ) . '</p><p>';
		sspw_button( $action, 'restore_plugins', __( 'Switch these back on', 'staging-superpowers' ), '', true );
		echo '</p>';
	}

	// Step 2: show exactly what will happen before anything changes.
	if ( is_array( $plan ) && ! empty( $plan['off'] ) ) {
		echo '<div class="notice notice-info inline"><p><strong>' . esc_html__( 'Check before switching off', 'staging-superpowers' ) . '</strong></p>';
		/* translators: %s: list of plugin names */
		echo '<p>' . esc_html( sprintf( __( 'These plugins will be switched off: %s.', 'staging-superpowers' ), implode( ', ', array_map( 'sspw_plugin_label', $plan['off'] ) ) ) ) . '</p>';
		if ( ! empty( $plan['also'] ) ) {
			echo '<p>' . esc_html__( 'Some of them were ticked to stay on, but they need a plugin that is being switched off, so they go off too:', 'staging-superpowers' ) . '</p><ul style="list-style:disc;margin-left:20px">';
			foreach ( $plan['also'] as $file => $needs ) {
				/* translators: 1: plugin name, 2: list of plugin names it needs */
				echo '<li>' . esc_html( sprintf( __( '%1$s, because it needs %2$s', 'staging-superpowers' ), sspw_plugin_label( $file ), implode( ', ', $needs ) ) ) . '</li>';
			}
			echo '</ul>';
		}
		echo '<p>' . esc_html__( 'After switching, the site is loaded in the background. If that shows a fatal error, everything is switched back on straight away.', 'staging-superpowers' ) . '</p><p>';
		sspw_button( $action, 'disable_plugins', __( 'Switch them off', 'staging-superpowers' ), '', true );
		sspw_button( admin_url( 'admin-post.php?action=sspw_troubleshoot_cancel' ), 'cancel', __( 'Cancel', 'staging-superpowers' ) );
		echo '</p></div>';
		return;
	}

	$others = array_diff( $active, $kept );

	if ( $others ) {
		echo '<p>' . esc_html__( 'Tick any plugin you want to keep on, then check what will be switched off. Staging Superpowers always stays on.', 'staging-superpowers' ) . '</p>';
		echo '<fieldset style="columns:3 280px;margin:0 0 12px">';
		foreach ( $others as $file ) {
			if ( ! isset( $all[ $file ] ) ) {
				continue;
			}
			printf(
				'<label style="display:block;margin:0 0 6px;break-inside:avoid"><input type="checkbox" name="sspw_keep[]" value="%1$s" /> %2$s</label>',
				esc_attr( $file ),
				esc_html( $all[ $file ]['Name'] )
			);
		}
		echo '</fieldset><p>';
		sspw_button( $action, 'plan_plugins', __( 'Switch off all other plugins', 'staging-superpowers' ) );
		echo '</p>';
	} else {
		echo '<p>' . esc_html__( 'No other plugins are active.', 'staging-superpowers' ) . '</p>';
	}

	if ( array_diff( array_keys( $all ), $active ) ) {
		echo '<p>';
		sspw_button(
			$action,
			'enable_all',
			__( 'Switch on all installed plugins', 'staging-superpowers' ),
			__( 'Switch on every installed plugin, including ones that were already off before you started troubleshooting?', 'staging-superpowers' )
		);
		echo '</p>';
	}
}

function sspw_output_theme_tools( $action ) {
	$theme    = wp_get_theme();
	$parent   = $theme->parent();
	$default  = sspw_default_theme();
	$previous = sspw_previous_theme() ? wp_get_theme( sspw_previous_theme() ) : false;

	echo '<h3>' . esc_html__( 'Theme', 'staging-superpowers' ) . '</h3><p>';

	if ( $parent ) {
		/* translators: 1: active theme name, 2: parent theme name */
		echo esc_html( sprintf( __( 'Active theme: %1$s (a child theme of %2$s).', 'staging-superpowers' ), $theme->get( 'Name' ), $parent->get( 'Name' ) ) );
	} else {
		/* translators: %s: active theme name */
		echo esc_html( sprintf( __( 'Active theme: %s.', 'staging-superpowers' ), $theme->get( 'Name' ) ) );
	}

	echo ' ' . esc_html__( 'Menus, widgets and customizer settings are kept for each theme, so switching back restores them.', 'staging-superpowers' ) . '</p><p>';

	if ( $previous && $previous->exists() && $previous->get_stylesheet() !== $theme->get_stylesheet() ) {
		/* translators: %s: theme name */
		sspw_button( $action, 'theme_restore', sprintf( __( 'Switch back to %s', 'staging-superpowers' ), $previous->get( 'Name' ) ), '', true );
	}

	if ( $parent ) {
		/* translators: %s: parent theme name */
		sspw_button( $action, 'theme_parent', sprintf( __( 'Switch to the parent theme (%s)', 'staging-superpowers' ), $parent->get( 'Name' ) ) );
	}

	if ( $default && $default->get_stylesheet() !== $theme->get_stylesheet() ) {
		/* translators: %s: theme name */
		sspw_button( $action, 'theme_default', sprintf( __( 'Switch to a default theme (%s)', 'staging-superpowers' ), $default->get( 'Name' ) ) );
	} elseif ( ! $default ) {
		esc_html_e( 'To test with a default theme, install Twenty Twenty-Five or Storefront first.', 'staging-superpowers' );
	}

	echo '</p>';
}

function sspw_output_more_tools() {
	$links = array();

	if ( sspw_has_woocommerce() ) {
		$links[ admin_url( 'admin.php?page=wc-status&tab=tools' ) ] = __( 'WooCommerce tools (clear transients, regenerate lookup tables, and more)', 'staging-superpowers' );
		$links[ admin_url( 'admin.php?page=wc-status&tab=logs' ) ]  = __( 'WooCommerce logs', 'staging-superpowers' );
	}

	if ( sspw_has_action_scheduler() ) {
		$links[ sspw_admin_links()['actions'] ] = __( 'Pending scheduled actions', 'staging-superpowers' );
	}

	$links[ admin_url( 'site-health.php' ) ] = __( 'Site Health', 'staging-superpowers' );

	echo '<h3>' . esc_html__( 'More tools', 'staging-superpowers' ) . '</h3><ul style="list-style:disc;margin-left:20px">';
	foreach ( $links as $url => $label ) {
		printf( '<li><a href="%1$s">%2$s</a></li>', esc_url( $url ), esc_html( $label ) );
	}
	echo '</ul>';
}

/**
 * Switch plugins on in rounds: a plugin whose "Requires Plugins" dependency is not
 * active yet is refused by WordPress, so it is retried after the others.
 * Plugins listed in $silent come back without running their activation routine.
 *
 * @return array Plugin files that could not be switched on.
 */
function sspw_activate_plugins( $files, $silent = array() ) {
	$pending = array_values( $files );

	while ( $pending ) {
		$retry = array();

		foreach ( $pending as $file ) {
			$result = activate_plugin( $file, '', false, in_array( $file, $silent, true ) );
			if ( is_wp_error( $result ) ) {
				$retry[] = $file;
			}
		}

		if ( count( $retry ) === count( $pending ) ) {
			return $retry;
		}

		$pending = $retry;
	}

	return array();
}

function sspw_activation_message( $total, $failed ) {
	$all     = get_plugins();
	$message = sprintf(
		/* translators: %d: number of plugins */
		_n( '%d plugin switched on.', '%d plugins switched on.', $total - count( $failed ), 'staging-superpowers' ),
		$total - count( $failed )
	);

	if ( $failed ) {
		$names = array_map(
			function ( $file ) use ( $all ) {
				return isset( $all[ $file ] ) ? $all[ $file ]['Name'] : $file;
			},
			$failed
		);
		/* translators: %s: list of plugin names */
		$message .= ' ' . sprintf( __( 'Could not switch on: %s', 'staging-superpowers' ), implode( ', ', $names ) );
	}

	return $message;
}

/**
 * Loads the home page logged out and the dashboard as the current user, in the
 * background. Each request carries a one-time key, so a fatal error page can
 * add the file that caused it (see sspw_mark_fatal_error()).
 *
 * @return array 'status' => 'ok', 'broken' or 'unchecked', 'culprit' => name or ''.
 */
function sspw_site_check() {
	$key = wp_generate_password( 20, false );
	set_transient( 'sspw_check_key', $key, 2 * MINUTE_IN_SECONDS );

	$cookies = array();
	foreach ( $_COOKIE as $name => $value ) {
		if ( 0 === strpos( (string) $name, 'wordpress_' ) && is_string( $value ) ) {
			$cookies[] = new WP_Http_Cookie(
				array(
					'name'  => (string) $name,
					'value' => sanitize_text_field( wp_unslash( $value ) ),
				)
			);
		}
	}

	$requests = array(
		array( home_url( '/' ), array() ),
		array( admin_url(), $cookies ),
	);
	$checked  = 0;

	foreach ( $requests as $request ) {
		$response = wp_remote_get(
			add_query_arg( 'sspw_check', $key, $request[0] ),
			array(
				'timeout'     => 30,
				'redirection' => 0,
				'cookies'     => $request[1],
				// Core's own filter for loopback requests (see class-wp-http-streams.php).
				'sslverify'   => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter.
				'headers'     => array( 'Cache-Control' => 'no-cache' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			continue;
		}

		++$checked;
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );

		if ( 500 === $code || false !== stripos( $body, 'critical error on this website' ) || preg_match( '#<b>Fatal error</b>#i', $body ) ) {
			delete_transient( 'sspw_check_key' );

			return array(
				'status'  => 'broken',
				'culprit' => sspw_fatal_culprit( $body ),
			);
		}
	}

	delete_transient( 'sspw_check_key' );

	return array(
		'status'  => $checked ? 'ok' : 'unchecked',
		'culprit' => '',
	);
}

/**
 * The plugin or theme the fatal error came from, by the path in the error.
 */
function sspw_fatal_culprit( $body ) {
	if ( ! preg_match( '#<!-- sspw-fatal-file: (.+?) -->#', $body, $m ) && ! preg_match( '#in <b>([^<]+\.php)</b> on line#', $body, $m ) ) {
		return '';
	}

	$file = wp_normalize_path( html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5 ) );

	foreach ( array_keys( get_plugins() ) as $plugin ) {
		$folder = wp_normalize_path( WP_PLUGIN_DIR . '/' . dirname( $plugin ) . '/' );
		if ( '.' !== dirname( $plugin ) && 0 === strpos( $file, $folder ) ) {
			return sspw_plugin_label( $plugin );
		}
	}

	foreach ( wp_get_themes() as $theme ) {
		if ( 0 === strpos( $file, wp_normalize_path( $theme->get_stylesheet_directory() . '/' ) ) ) {
			return $theme->get( 'Name' );
		}
	}

	return '';
}

/**
 * Message after undoing a switch that broke the site.
 */
function sspw_rollback_message( $check ) {
	if ( $check['culprit'] ) {
		/* translators: %s: plugin or theme name */
		return sprintf( __( 'That broke the site: %s caused a fatal error. Nothing was changed, everything is back as it was.', 'staging-superpowers' ), $check['culprit'] );
	}

	return __( 'That broke the site with a fatal error. Nothing was changed, everything is back as it was.', 'staging-superpowers' );
}

function sspw_unchecked_note( $check ) {
	return 'unchecked' === $check['status'] ? ' ' . __( 'The site could not load itself in the background to check for errors, so check it yourself.', 'staging-superpowers' ) : '';
}

function sspw_cancel_troubleshoot_plan() {
	check_admin_referer( 'sspw_troubleshoot', 'sspw_nonce' );
	delete_transient( 'sspw_plan_' . get_current_user_id() );
	wp_safe_redirect( sspw_troubleshoot_url() );
	exit;
}

function sspw_troubleshoot_done( $message ) {
	set_transient( 'sspw_notice_' . get_current_user_id(), $message, MINUTE_IN_SECONDS );
	wp_safe_redirect( sspw_troubleshoot_url() );
	exit;
}

function sspw_handle_troubleshoot() {
	check_admin_referer( 'sspw_troubleshoot', 'sspw_nonce' );

	if ( ! sspw_is_armed() ) {
		wp_die( esc_html__( 'Troubleshooting is only available while Staging Superpowers is turned on for this site.', 'staging-superpowers' ) );
	}

	$do = isset( $_POST['sspw_do'] ) ? sanitize_key( wp_unslash( $_POST['sspw_do'] ) ) : '';

	if ( in_array( $do, array( 'plan_plugins', 'disable_plugins', 'restore_plugins', 'enable_all' ), true ) && ! current_user_can( 'activate_plugins' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'staging-superpowers' ) );
	}

	if ( 0 === strpos( $do, 'theme_' ) && ! current_user_can( 'switch_themes' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'staging-superpowers' ) );
	}

	require_once ABSPATH . 'wp-admin/includes/plugin.php';

	switch ( $do ) {
		case 'plan_plugins':
			$ticked = isset( $_POST['sspw_keep'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['sspw_keep'] ) ) : array();
			$plan   = sspw_plan_switch_off( $ticked );

			if ( ! $plan['off'] ) {
				sspw_troubleshoot_done( __( 'Nothing to switch off.', 'staging-superpowers' ) );
			}

			set_transient( 'sspw_plan_' . get_current_user_id(), $plan, HOUR_IN_SECONDS );
			wp_safe_redirect( sspw_troubleshoot_url() );
			exit;

		case 'disable_plugins':
			$plan = get_transient( 'sspw_plan_' . get_current_user_id() );
			delete_transient( 'sspw_plan_' . get_current_user_id() );

			$before = (array) get_option( 'active_plugins', array() );
			$off    = is_array( $plan ) ? array_values( array_intersect( (array) $plan['off'], $before ) ) : array();
			$off    = array_values( array_diff( $off, sspw_always_kept_plugins() ) );

			if ( ! $off ) {
				sspw_troubleshoot_done( __( 'Nothing to switch off.', 'staging-superpowers' ) );
			}

			deactivate_plugins( $off, true );

			$check = sspw_site_check();
			if ( 'broken' === $check['status'] ) {
				update_option( 'active_plugins', $before );
				sspw_troubleshoot_done( sspw_rollback_message( $check ) );
			}

			if ( function_exists( 'sspw_log' ) && $off ) {
				/* translators: %s: list of plugin names */
				sspw_log( sprintf( __( 'Troubleshooting: plugins switched off: %s', 'staging-superpowers' ), implode( ', ', array_map( 'sspw_plugin_name', $off ) ) ) );
			}

			// Several rounds add up, so "switch these back on" restores everything.
			update_option( 'sspw_disabled_plugins', array_values( array_unique( array_merge( sspw_disabled_plugins(), $off ) ) ), false );

			/* translators: %d: number of plugins */
			sspw_troubleshoot_done( sprintf( _n( '%d plugin switched off.', '%d plugins switched off.', count( $off ), 'staging-superpowers' ), count( $off ) ) . sspw_unchecked_note( $check ) );
			break;

		case 'restore_plugins':
			$before = (array) get_option( 'active_plugins', array() );
			$back   = array_values( array_intersect( sspw_disabled_plugins(), array_keys( get_plugins() ) ) );
			$failed = sspw_activate_plugins( $back, $back );

			$check = sspw_site_check();
			if ( 'broken' === $check['status'] ) {
				update_option( 'active_plugins', $before );
				sspw_troubleshoot_done( sspw_rollback_message( $check ) );
			}

			if ( function_exists( 'sspw_log' ) && $back ) {
				/* translators: %s: list of plugin names */
				sspw_log( sprintf( __( 'Troubleshooting: plugins switched back on: %s', 'staging-superpowers' ), implode( ', ', array_map( 'sspw_plugin_name', array_diff( $back, $failed ) ) ) ) );
			}

			// Anything that could not come back stays listed, so it is not forgotten.
			if ( $failed ) {
				update_option( 'sspw_disabled_plugins', $failed, false );
			} else {
				delete_option( 'sspw_disabled_plugins' );
			}

			sspw_troubleshoot_done( sspw_activation_message( count( $back ), $failed ) . sspw_unchecked_note( $check ) );
			break;

		case 'enable_all':
			// Plugins turned off by troubleshooting come back silently; plugins that were never on
			// get a normal activation, so they can create their tables and defaults.
			$before   = (array) get_option( 'active_plugins', array() );
			$inactive = array_values( array_diff( array_keys( get_plugins() ), $before ) );
			$failed   = sspw_activate_plugins( $inactive, sspw_disabled_plugins() );

			$check = sspw_site_check();
			if ( 'broken' === $check['status'] ) {
				update_option( 'active_plugins', $before );
				sspw_troubleshoot_done( sspw_rollback_message( $check ) );
			}

			if ( function_exists( 'sspw_log' ) && $inactive ) {
				/* translators: %s: list of plugin names */
				sspw_log( sprintf( __( 'Troubleshooting: all installed plugins switched on: %s', 'staging-superpowers' ), implode( ', ', array_map( 'sspw_plugin_name', array_diff( $inactive, $failed ) ) ) ) );
			}

			delete_option( 'sspw_disabled_plugins' );

			sspw_troubleshoot_done( sspw_activation_message( count( $inactive ), $failed ) . sspw_unchecked_note( $check ) );
			break;

		case 'theme_parent':
		case 'theme_default':
		case 'theme_restore':
			$current = get_stylesheet();

			if ( 'theme_restore' === $do ) {
				$target = sspw_previous_theme();
			} elseif ( 'theme_parent' === $do ) {
				$target = get_template();
			} else {
				$default = sspw_default_theme();
				$target  = $default ? $default->get_stylesheet() : '';
			}

			$theme = $target ? wp_get_theme( $target ) : false;
			if ( ! $theme || ! $theme->exists() || $target === $current ) {
				sspw_troubleshoot_done( __( 'That theme is not available.', 'staging-superpowers' ) );
			}

			switch_theme( $target );

			$check = sspw_site_check();
			if ( 'broken' === $check['status'] ) {
				switch_theme( $current );
				sspw_troubleshoot_done( sspw_rollback_message( $check ) );
			}

			// Remember the theme from before troubleshooting started, not each step in between.
			if ( 'theme_restore' === $do ) {
				delete_option( 'sspw_previous_theme' );
			} elseif ( '' === sspw_previous_theme() ) {
				update_option( 'sspw_previous_theme', $current, false );
			}

			/* translators: %s: theme name */
			sspw_troubleshoot_done( sprintf( __( 'Switched to %s.', 'staging-superpowers' ), $theme->get( 'Name' ) ) . sspw_unchecked_note( $check ) );
			break;
	}

	wp_safe_redirect( sspw_troubleshoot_url() );
	exit;
}
