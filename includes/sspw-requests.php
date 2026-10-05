<?php
/**
 * Requests log: outgoing requests the staging site tried to make. Blocked ones
 * are always recorded; add-ons can record allowed ones too through the
 * sspw_log_allowed_requests filter.
 *
 * Only the method, host, path and the plugin that made the request are kept.
 * Bodies, headers, query strings and credentials are never stored.
 */

defined( 'ABSPATH' ) || exit;

define( 'SSPW_REQUESTS_OPTION', 'sspw_requests' );
define( 'SSPW_REQUESTS_LIMIT', 300 );

add_action( 'admin_post_sspw_clear_requests', 'sspw_clear_requests' );
add_action( 'admin_enqueue_scripts', 'sspw_requests_style' );

function sspw_requests_style() {
	if ( ! sspw_is_settings_page( 'requests' ) ) {
		return;
	}

	wp_register_style( 'sspw-requests', false, array(), SSPW_VERSION );
	wp_enqueue_style( 'sspw-requests' );
	wp_add_inline_style( 'sspw-requests', '.sspw-req-status{display:inline-block;padding:2px 8px;border-radius:3px;font-weight:600;font-size:12px}.sspw-req-status.is-blocked{background:#fcebea;color:#b91c1c}.sspw-req-status.is-allowed{background:#edfaef;color:#1a7f37}.sspw-requests code{background:none;padding:0;font-size:12px;overflow-wrap:anywhere}' );
}

/**
 * Requests made by WordPress itself to check for updates, and the site's own
 * background calls, are never shown: they are not a sign of anything leaking.
 */
function sspw_request_is_routine( $host ) {
	$own = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

	return '' === $host || $own === $host || 'wordpress.org' === $host || str_ends_with( $host, '.wordpress.org' );
}

/**
 * Notes one request for this page load. Everything is saved once, at shutdown.
 *
 * @param string $url    Requested URL. Only its host and path are kept.
 * @param array  $args   Request arguments. Only the method is read.
 * @param string $status 'blocked' or 'allowed'.
 */
function sspw_record_request( $url, $args, $status ) {
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	if ( sspw_request_is_routine( $host ) ) {
		return;
	}

	$path   = (string) wp_parse_url( $url, PHP_URL_PATH );
	$method = strtoupper( isset( $args['method'] ) ? (string) $args['method'] : 'GET' );
	$source = sspw_request_source();
	$key    = md5( $status . '|' . $host . '|' . $path . '|' . $method . '|' . $source );

	if ( empty( $GLOBALS['sspw_request_buffer'] ) ) {
		$GLOBALS['sspw_request_buffer'] = array();
		add_action( 'shutdown', 'sspw_save_requests' );
	}

	if ( isset( $GLOBALS['sspw_request_buffer'][ $key ] ) ) {
		++$GLOBALS['sspw_request_buffer'][ $key ]['count'];
		return;
	}

	$GLOBALS['sspw_request_buffer'][ $key ] = array(
		'time'   => time(),
		'status' => $status,
		'host'   => $host,
		'path'   => '' === $path ? '/' : $path,
		'method' => $method,
		'source' => $source,
		'count'  => 1,
	);
}

/**
 * Who made the request: the first plugin, must-use plugin or theme file in the
 * call stack, skipping this plugin. Stored as a short key, named when shown.
 */
function sspw_request_source() {
	static $dirs = null;

	// Resolved paths, so symlinked plugin folders and "./" in paths still match.
	if ( null === $dirs ) {
		$dirs  = array();
		$roots = array(
			'plugin' => WP_PLUGIN_DIR,
			'theme'  => get_theme_root(),
		);
		foreach ( $roots as $type => $root ) {
			foreach ( (array) glob( $root . '/*', GLOB_ONLYDIR ) as $folder ) {
				$real = realpath( $folder );
				if ( $real ) {
					$dirs[ wp_normalize_path( $real ) . '/' ] = $type . ':' . basename( $folder );
				}
			}
		}
		$mu = realpath( WPMU_PLUGIN_DIR );
		if ( $mu ) {
			$dirs[ wp_normalize_path( $mu ) . '/' ] = 'mu';
		}
	}

	$own    = wp_normalize_path( (string) realpath( SSPW_PLUGIN_DIR ) ) . '/';
	$frames = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- finds which plugin made the request, nothing is printed.

	foreach ( $frames as $frame ) {
		if ( empty( $frame['file'] ) ) {
			continue;
		}
		$file = wp_normalize_path( (string) realpath( $frame['file'] ) );
		if ( '' === $file || 0 === strpos( $file, $own ) ) {
			continue;
		}
		foreach ( $dirs as $dir => $source ) {
			if ( 0 === strpos( $file, $dir ) ) {
				return 'mu' === $source ? 'mu:' . strtok( substr( $file, strlen( $dir ) ), '/' ) : $source;
			}
		}
	}

	return 'core';
}

/**
 * Merges this page load's requests into the stored log, newest first, capped.
 */
function sspw_save_requests() {
	if ( empty( $GLOBALS['sspw_request_buffer'] ) ) {
		return;
	}

	$log = sspw_requests();
	foreach ( $GLOBALS['sspw_request_buffer'] as $key => $row ) {
		if ( isset( $log[ $key ] ) ) {
			$row['count'] += (int) $log[ $key ]['count'];
		}
		$log[ $key ] = $row;
	}
	$GLOBALS['sspw_request_buffer'] = array();

	uasort(
		$log,
		function ( $a, $b ) {
			return $b['time'] - $a['time'];
		}
	);

	update_option( SSPW_REQUESTS_OPTION, array_slice( $log, 0, SSPW_REQUESTS_LIMIT, true ), false );
}

/**
 * @return array Key => row (time, status, host, path, method, source, count), newest first.
 */
function sspw_requests() {
	return (array) get_option( SSPW_REQUESTS_OPTION, array() );
}

/**
 * Blocked requests so far, for the status bar.
 */
function sspw_blocked_request_count() {
	$total = 0;
	foreach ( sspw_requests() as $row ) {
		if ( 'blocked' === $row['status'] ) {
			$total += (int) $row['count'];
		}
	}

	return $total;
}

/**
 * Allowed requests are recorded only when an add-on asks for them.
 */
function sspw_record_allowed_request( $response, $context, $transport, $args, $url ) {
	/**
	 * Also record requests that were not blocked.
	 *
	 * @param bool $log Default false.
	 */
	if ( 'response' === $context && apply_filters( 'sspw_log_allowed_requests', false ) ) {
		sspw_record_request( $url, $args, 'allowed' );
	}
}

function sspw_request_source_label( $source ) {
	static $plugins = null;

	list( $type, $slug ) = array_pad( explode( ':', $source, 2 ), 2, '' );

	if ( 'plugin' === $type ) {
		if ( null === $plugins ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugins = get_plugins();
		}
		foreach ( $plugins as $file => $data ) {
			if ( 0 === strpos( $file, $slug . '/' ) || $file === $slug ) {
				return $data['Name'];
			}
		}
		return $slug;
	}

	if ( 'theme' === $type ) {
		$theme = wp_get_theme( $slug );
		/* translators: %s: theme name */
		return sprintf( __( '%s (theme)', 'staging-superpowers' ), $theme->exists() ? $theme->get( 'Name' ) : $slug );
	}

	if ( 'mu' === $type ) {
		/* translators: %s: file name */
		return sprintf( __( '%s (must-use plugin)', 'staging-superpowers' ), $slug );
	}

	return __( 'WordPress', 'staging-superpowers' );
}

function sspw_output_requests() {
	$rows = sspw_requests();

	echo '<h2>' . esc_html__( 'Requests', 'staging-superpowers' ) . '</h2>';
	echo '<p>' . esc_html__( 'Requests your staging site tried to make. Blocked ones never left the site.', 'staging-superpowers' ) . '</p>';
	echo '<p class="description">' . esc_html__( 'Only the address and the plugin that made each request are kept, never what was sent. WordPress update checks and the site\'s own background calls are not listed.', 'staging-superpowers' ) . '</p>';

	if ( ! $rows ) {
		echo '<p><strong>' . esc_html__( 'Nothing yet.', 'staging-superpowers' ) . '</strong> ' . esc_html__( 'When a plugin tries to reach a blocked service, for example a payment, email marketing or analytics service, it shows up here.', 'staging-superpowers' ) . '</p>';
		/**
		 * Output after the requests table (or its empty state).
		 */
		do_action( 'sspw_requests_after_table' );
		return;
	}

	$format  = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
	$actions = array();
	foreach ( $rows as $key => $row ) {
		/**
		 * Buttons or links for one row of the requests table.
		 *
		 * @param array  $actions HTML strings.
		 * @param array  $row     time, status, host, path, method, source, count.
		 * @param string $key     Row key.
		 */
		$actions[ $key ] = (array) apply_filters( 'sspw_requests_row_actions', array(), $row, $key );
	}
	$has_actions = (bool) array_filter( $actions );

	$columns = array(
		__( 'Last seen', 'staging-superpowers' ) => '12%',
		__( 'Status', 'staging-superpowers' )    => '8%',
		__( 'Host', 'staging-superpowers' )      => '',
		__( 'Path', 'staging-superpowers' )      => '',
		__( 'Method', 'staging-superpowers' )    => '6%',
		__( 'Plugin', 'staging-superpowers' )    => '13%',
		__( 'Count', 'staging-superpowers' )     => '6%',
	);
	if ( $has_actions ) {
		$columns[ __( 'Actions', 'staging-superpowers' ) ] = '12%';
	}

	echo '<table class="wp-list-table widefat fixed striped sspw-table sspw-requests">';
	sspw_table_head( $columns );
	echo '<tbody>';
	foreach ( $rows as $key => $row ) {
		$blocked = 'blocked' === $row['status'];
		printf(
			'<tr><td title="%1$s">%2$s</td><td><span class="sspw-req-status %3$s">%4$s</span></td><td><code>%5$s</code></td><td><code>%6$s</code></td><td>%7$s</td><td>%8$s</td><td>%9$s</td>',
			esc_attr( wp_date( $format, (int) $row['time'] ) ),
			/* translators: %s: time difference, e.g. "5 mins" */
			esc_html( sprintf( __( '%s ago', 'staging-superpowers' ), human_time_diff( (int) $row['time'] ) ) ),
			$blocked ? 'is-blocked' : 'is-allowed',
			$blocked ? esc_html__( 'Blocked', 'staging-superpowers' ) : esc_html__( 'Allowed', 'staging-superpowers' ),
			esc_html( $row['host'] ),
			esc_html( $row['path'] ),
			esc_html( $row['method'] ),
			esc_html( sspw_request_source_label( $row['source'] ) ),
			esc_html( number_format_i18n( (int) $row['count'] ) )
		);
		if ( $has_actions ) {
			echo '<td>' . wp_kses_post( implode( ' ', $actions[ $key ] ) ) . '</td>';
		}
		echo '</tr>';
	}
	echo '</tbody></table>';

	/* translators: %s: number of rows */
	echo '<p class="description">' . esc_html( sprintf( __( 'Newest first. Repeats of the same request are counted on one row. The latest %s rows are kept.', 'staging-superpowers' ), number_format_i18n( SSPW_REQUESTS_LIMIT ) ) ) . '</p><p>';
	wp_nonce_field( 'sspw_clear_requests', 'sspw_requests_nonce' );
	printf(
		'<button type="submit" class="button" formaction="%1$s" formmethod="post" onclick="return confirm( %2$s );">%3$s</button>',
		esc_url( admin_url( 'admin-post.php?action=sspw_clear_requests' ) ),
		esc_attr( wp_json_encode( __( 'Delete every row in the requests log?', 'staging-superpowers' ) ) ),
		esc_html__( 'Clear the log', 'staging-superpowers' )
	);
	echo '</p>';

	/** This action is documented above. */
	do_action( 'sspw_requests_after_table' );
}

function sspw_clear_requests() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'staging-superpowers' ) );
	}

	check_admin_referer( 'sspw_clear_requests', 'sspw_requests_nonce' );
	delete_option( SSPW_REQUESTS_OPTION );

	wp_safe_redirect( sspw_settings_url( 'requests' ) );
	exit;
}
