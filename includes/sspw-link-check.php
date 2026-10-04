<?php
/**
 * Link check sub-page: lists every place in the database that points to the
 * other copy of the site. On live (plugin paused) that is the staging site,
 * on staging it is the live site. Report only: nothing is ever changed.
 *
 * It reads the database in slices over AJAX, so it never times out on a big
 * live site, and it only runs when someone clicks the button.
 */

defined( 'ABSPATH' ) || exit;

define( 'SSPW_LINK_CHECK_SLICE', 5000 );

add_action( 'admin_post_sspw_link_hosts', 'sspw_save_link_hosts' );
add_action( 'wp_ajax_sspw_link_check', 'sspw_link_check_ajax' );

/**
 * Exact addresses to look for, as "host[:port][/path]" like the guard's fingerprint.
 */
function sspw_link_targets() {
	if ( sspw_is_armed() ) {
		$live = sspw_live_url();
		return $live ? array( strtolower( preg_replace( '#^https?://#i', '', $live ) ) ) : array();
	}

	$targets = array_merge( (array) get_option( 'sspw_armed_history', array() ), array( sspw_armed_for() ) );
	foreach ( preg_split( '/\s+/', (string) get_option( 'sspw_link_check_hosts', '' ) ) as $line ) {
		$targets[] = strtolower( untrailingslashit( preg_replace( '#^https?://#i', '', trim( $line ) ) ) );
	}

	return array_values( array_diff( array_unique( array_filter( $targets ) ), array( sspw_site_fingerprint() ) ) );
}

/**
 * Text the database search looks for. On live it also looks for any address that
 * looks like staging; those matches are checked properly in PHP afterwards.
 */
function sspw_link_needles() {
	$needles = sspw_link_targets();

	if ( ! sspw_is_armed() ) {
		$needles = array_merge( $needles, array( '/staging.', '/stage.', '/dev.', '/test.', '/localhost', '/127.0.0.1', '.local', '.test', '.wpengine.com', '.wpenginepowered.com', '.kinsta.cloud', '.cloudwaysapps.com', '.flywheelstaging.com', '.flywheelsites.com', '.pantheonsite.io', '.wpcomstaging.com', '.instawp.xyz', '.mystagingwebsite.com' ) );
	}

	return array_unique( $needles );
}

/**
 * Addresses in a value that point to the other copy, as host => array of URLs.
 */
function sspw_link_matches( $value ) {
	$value   = str_replace( '\/', '/', (string) $value );
	$targets = sspw_link_targets();
	$self    = strtolower( (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_HOST ) );
	$found   = array();

	if ( ! preg_match_all( '#(?:https?:)?//([a-z0-9.-]+(?::\d+)?)([^\s"\'<>)\\\\]*)#i', $value, $urls, PREG_SET_ORDER ) ) {
		return $found;
	}

	foreach ( $urls as $url ) {
		$host = strtolower( $url[1] );
		$full = $host . $url[2];
		$hit  = '';

		foreach ( $targets as $target ) {
			if ( $full === $target || 0 === strpos( $full, $target . '/' ) || ( false === strpos( $target, '/' ) && $host === $target ) ) {
				$hit = $target;
				break;
			}
		}

		if ( ! $hit && ! sspw_is_armed() ) {
			$bare = preg_replace( '/:\d+$/', '', $host );
			// "dev.to" or "test.com" are real sites: a staging prefix needs a domain after it.
			$real = substr_count( $bare, '.' ) < 2 && ! preg_match( '/^(localhost|127\.0\.0\.1)$|\.(local|test|localhost)$/', $bare );
			if ( $bare !== $self && ! $real && sspw_host_looks_like_staging( $bare ) ) {
				$hit = $host;
			}
		}

		if ( $hit ) {
			$found[ $hit ][] = $url[0];
		}
	}

	return $found;
}

function sspw_link_check_steps() {
	global $wpdb;

	return array(
		'posts'    => array( $wpdb->posts, 'ID' ),
		'postmeta' => array( $wpdb->postmeta, 'meta_id' ),
		'options'  => array( $wpdb->options, 'option_id' ),
		'termmeta' => array( $wpdb->termmeta, 'meta_id' ),
		'users'    => array( $wpdb->users, 'ID' ),
		'usermeta' => array( $wpdb->usermeta, 'umeta_id' ),
	);
}

/**
 * One slice of one table: matching rows, already described for people.
 */
function sspw_link_check_slice( $step, $from ) {
	global $wpdb;

	$steps = sspw_link_check_steps();
	$to    = $from + SSPW_LINK_CHECK_SLICE;
	$likes = array();
	$args  = array();

	$columns = array(
		'posts'    => array( 'p.post_content', 'p.post_excerpt' ),
		'postmeta' => array( 'm.meta_value' ),
		'options'  => array( 'o.option_value' ),
		'termmeta' => array( 'm.meta_value' ),
		'users'    => array( 'u.user_url' ),
		'usermeta' => array( 'm.meta_value' ),
	);

	foreach ( $columns[ $step ] as $column ) {
		foreach ( sspw_link_needles() as $needle ) {
			$likes[] = $column . ' LIKE %s';
			$args[]  = '%' . $wpdb->esc_like( $needle ) . '%';
		}
	}
	$like = '( ' . implode( ' OR ', $likes ) . ' )';

	$skip_types = "'revision','oembed_cache','customize_changeset','user_request'";

	// Table and column names are fixed above; every value goes through prepare().
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter
	switch ( $step ) {
		case 'posts':
			$sql = "SELECT p.ID AS id, p.post_type, p.post_title, p.post_content, p.post_excerpt FROM {$wpdb->posts} p WHERE p.ID > %d AND p.ID <= %d AND p.post_type NOT IN ( {$skip_types} ) AND p.post_status NOT IN ( 'auto-draft', 'trash' ) AND {$like}";
			break;
		case 'postmeta':
			$sql = "SELECT m.meta_id AS id, m.post_id, m.meta_key, m.meta_value, p.post_type, p.post_title FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_id > %d AND m.meta_id <= %d AND p.post_type NOT IN ( {$skip_types} ) AND m.meta_key NOT IN ( '_edit_lock', '_wp_old_slug', '_wp_attachment_metadata', '_wp_trash_meta_status' ) AND {$like}";
			break;
		case 'options':
			$sql = "SELECT o.option_id AS id, o.option_name, o.option_value FROM {$wpdb->options} o WHERE o.option_id > %d AND o.option_id <= %d AND o.option_name NOT LIKE '\\_transient%%' AND o.option_name NOT LIKE '\\_site\\_transient%%' AND o.option_name NOT LIKE 'sspw\\_%%' AND o.option_name NOT IN ( 'home', 'siteurl', 'rewrite_rules', 'cron', 'recently_edited' ) AND {$like}";
			break;
		case 'termmeta':
			$sql = "SELECT m.meta_id AS id, m.term_id, m.meta_key, m.meta_value FROM {$wpdb->termmeta} m WHERE m.meta_id > %d AND m.meta_id <= %d AND {$like}";
			break;
		case 'users':
			$sql = "SELECT u.ID AS id, u.user_login, u.user_url FROM {$wpdb->users} u WHERE u.ID > %d AND u.ID <= %d AND {$like}";
			break;
		default:
			$sql = "SELECT m.umeta_id AS id, m.user_id, m.meta_key, m.meta_value FROM {$wpdb->usermeta} m WHERE m.umeta_id > %d AND m.umeta_id <= %d AND m.meta_key NOT IN ( 'session_tokens', '_application_passwords' ) AND m.meta_key NOT LIKE '%%capabilities' AND {$like}";
	}

	$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( array( $from, $to ), $args ) ) );
	$max  = (int) $wpdb->get_var( "SELECT MAX({$steps[ $step ][1]}) FROM {$steps[ $step ][0]}" );
	// phpcs:enable

	$found = array();
	foreach ( (array) $rows as $row ) {
		foreach ( sspw_link_describe( $step, $row ) as $place ) {
			foreach ( sspw_link_matches( $place['value'] ) as $host => $urls ) {
				$found[] = sspw_link_result( $host, $urls, $place );
			}
		}
	}

	return array(
		'found' => $found,
		'next'  => $to < $max ? $to : 0,
	);
}

/**
 * What a matching row is, in words people recognize, and where to edit it.
 * A row can hold more than one place (post content and excerpt).
 */
function sspw_link_describe( $step, $row ) {
	switch ( $step ) {
		case 'posts':
			$type = get_post_type_object( $row->post_type );
			$name = ( $type ? $type->labels->singular_name : $row->post_type ) . ': ' . ( '' !== $row->post_title ? $row->post_title : '#' . $row->id );
			$edit = (string) get_edit_post_link( $row->id, 'raw' );
			return sspw_link_places(
				array(
					/* translators: %s: post type and title, e.g. "Page: About us" */
					array( sprintf( __( '%s, in the content', 'staging-superpowers' ), $name ), $edit, $row->post_content ),
					/* translators: %s: post type and title, e.g. "Page: About us" */
					array( sprintf( __( '%s, in the excerpt', 'staging-superpowers' ), $name ), $edit, $row->post_excerpt ),
				)
			);

		case 'postmeta':
			if ( 'nav_menu_item' === $row->post_type ) {
				$menus = wp_get_object_terms( (int) $row->post_id, 'nav_menu', array( 'fields' => 'ids' ) );
				$title = '' !== $row->post_title ? $row->post_title : get_post_meta( (int) $row->post_id, '_menu_item_url', true );
				return sspw_link_places(
					array(
						/* translators: %s: menu item title */
						array( sprintf( __( 'Menu item: %s', 'staging-superpowers' ), $title ), admin_url( 'nav-menus.php' . ( $menus && ! is_wp_error( $menus ) ? '?action=edit&menu=' . (int) $menus[0] : '' ) ), $row->meta_value ),
					)
				);
			}
			$type  = get_post_type_object( $row->post_type );
			$name  = ( $type ? $type->labels->singular_name : $row->post_type ) . ': ' . ( '' !== $row->post_title ? $row->post_title : '#' . $row->post_id );
			$known = array(
				'_elementor_data'          => __( 'Elementor content', 'staging-superpowers' ),
				'_elementor_css'           => __( 'Elementor styles', 'staging-superpowers' ),
				'_elementor_page_settings' => __( 'Elementor page settings', 'staging-superpowers' ),
				'_fl_builder_data'         => __( 'Beaver Builder content', 'staging-superpowers' ),
				'_fl_builder_draft'        => __( 'Beaver Builder draft', 'staging-superpowers' ),
				'_et_pb_old_content'       => __( 'Divi content', 'staging-superpowers' ),
				'_wp_attached_file'        => __( 'the file path', 'staging-superpowers' ),
				'_product_image_gallery'   => __( 'the image gallery', 'staging-superpowers' ),
			);
			/* translators: %s: custom field name */
			$where = isset( $known[ $row->meta_key ] ) ? $known[ $row->meta_key ] : sprintf( __( 'custom field %s', 'staging-superpowers' ), $row->meta_key );
			/* translators: 1: post type and title, 2: where in the post, e.g. "Elementor content" */
			return sspw_link_places( array( array( sprintf( __( '%1$s, in %2$s', 'staging-superpowers' ), $name, $where ), (string) get_edit_post_link( (int) $row->post_id, 'raw' ), $row->meta_value ) ) );

		case 'options':
			return sspw_link_places( array( sspw_link_describe_option( $row ) ) );

		case 'termmeta':
			$term = get_term( (int) $row->term_id );
			if ( ! $term || is_wp_error( $term ) ) {
				return array();
			}
			$tax = get_taxonomy( $term->taxonomy );
			/* translators: 1: taxonomy and term name, e.g. "Category: Shoes", 2: field name */
			return sspw_link_places( array( array( sprintf( __( '%1$s, in custom field %2$s', 'staging-superpowers' ), ( $tax ? $tax->labels->singular_name : $term->taxonomy ) . ': ' . $term->name, $row->meta_key ), (string) get_edit_term_link( $term ), $row->meta_value ) ) );

		case 'users':
			/* translators: %s: username */
			return sspw_link_places( array( array( sprintf( __( 'User: %s, website', 'staging-superpowers' ), $row->user_login ), get_edit_user_link( (int) $row->id ), $row->user_url ) ) );

		default:
			$user = get_userdata( (int) $row->user_id );
			/* translators: 1: username, 2: field name */
			return sspw_link_places( array( array( sprintf( __( 'User: %1$s, in %2$s', 'staging-superpowers' ), $user ? $user->user_login : '#' . $row->user_id, $row->meta_key ), get_edit_user_link( (int) $row->user_id ), $row->meta_value ) ) );
	}
}

function sspw_link_places( $places ) {
	$out = array();
	foreach ( $places as $place ) {
		if ( '' !== (string) $place[2] ) {
			$out[] = array(
				'label' => $place[0],
				'edit'  => (string) $place[1],
				'value' => (string) $place[2],
			);
		}
	}

	return $out;
}

/**
 * Widgets, theme settings and plugin settings all live in options.
 */
function sspw_link_describe_option( $row ) {
	global $wp_registered_sidebars, $wp_widget_factory;

	$name  = $row->option_name;
	$value = maybe_unserialize( $row->option_value );

	if ( 0 === strpos( $name, 'widget_' ) && is_array( $value ) ) {
		$base = substr( $name, 7 );
		foreach ( $value as $number => $instance ) {
			if ( is_int( $number ) && sspw_link_matches( wp_json_encode( $instance ) ) ) {
				$type    = $base;
				$sidebar = __( 'no widget area', 'staging-superpowers' );
				foreach ( (array) $wp_widget_factory->widgets as $widget ) {
					if ( $widget->id_base === $base ) {
						$type = $widget->name;
					}
				}
				foreach ( (array) get_option( 'sidebars_widgets', array() ) as $area => $ids ) {
					if ( is_array( $ids ) && in_array( $base . '-' . $number, $ids, true ) ) {
						$sidebar = isset( $wp_registered_sidebars[ $area ]['name'] ) ? $wp_registered_sidebars[ $area ]['name'] : $area;
					}
				}
				/* translators: 1: widget type, e.g. "Text", 2: widget area, e.g. "Footer" */
				return array( sprintf( __( 'Widget: %1$s widget in %2$s', 'staging-superpowers' ), $type, $sidebar ), admin_url( 'widgets.php' ), wp_json_encode( $instance ) );
			}
		}
	}

	if ( 0 === strpos( $name, 'theme_mods_' ) && is_array( $value ) ) {
		foreach ( $value as $key => $setting ) {
			if ( sspw_link_matches( is_scalar( $setting ) ? (string) $setting : wp_json_encode( $setting ) ) ) {
				/* translators: %s: theme setting, e.g. "header image" */
				return array( sprintf( __( 'Theme setting: %s', 'staging-superpowers' ), str_replace( array( '_', '-' ), ' ', (string) $key ) ), admin_url( 'customize.php' ), is_scalar( $setting ) ? (string) $setting : wp_json_encode( $setting ) );
			}
		}
	}

	/* translators: %s: option name */
	return array( sprintf( __( 'Setting: %s', 'staging-superpowers' ), $name ), '', is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) );
}

/**
 * One result row: a short snippet around the first match, and whether it is an image.
 */
function sspw_link_result( $host, $urls, $place ) {
	$value = str_replace( '\/', '/', $place['value'] );
	$pos   = (int) strpos( $value, $urls[0] );
	$start = max( 0, $pos - 70 );
	$image = '';

	foreach ( $urls as $url ) {
		if ( preg_match( '#/([^/?\#]+\.(?:jpe?g|png|gif|webp|avif|svg))#i', $url, $file ) ) {
			$image = $file[1];
			break;
		}
	}

	return array(
		'host'   => $host,
		'label'  => $place['label'],
		'edit'   => $place['edit'],
		'image'  => $image,
		'before' => ( $start > 0 ? '…' : '' ) . wp_strip_all_tags( substr( $value, $start, $pos - $start ) ),
		'match'  => $urls[0],
		'after'  => wp_strip_all_tags( substr( $value, $pos + strlen( $urls[0] ), 70 ) ) . '…',
		'count'  => count( $urls ),
	);
}

function sspw_link_check_ajax() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( __( 'Sorry, you are not allowed to do that.', 'staging-superpowers' ), 403 );
	}

	check_ajax_referer( 'sspw_link_check' );

	$step = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : '';
	$from = isset( $_POST['from'] ) ? absint( $_POST['from'] ) : 0;

	if ( ! isset( sspw_link_check_steps()[ $step ] ) ) {
		wp_send_json_error( __( 'Unknown step.', 'staging-superpowers' ) );
	}

	wp_send_json_success( sspw_link_check_slice( $step, $from ) );
}

function sspw_save_link_hosts() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'staging-superpowers' ) );
	}

	check_admin_referer( 'sspw_link_hosts' );

	$lines = isset( $_POST['sspw_link_check_hosts'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sspw_link_check_hosts'] ) ) : '';
	update_option( 'sspw_link_check_hosts', $lines, false );

	wp_safe_redirect( sspw_settings_url( 'links' ) );
	exit;
}

function sspw_output_link_check() {
	$armed   = sspw_is_armed();
	$targets = sspw_link_targets();

	echo '<h2>' . esc_html__( 'Link check', 'staging-superpowers' ) . '</h2>';

	if ( $armed ) {
		echo '<p>' . esc_html__( 'Finds every place on this staging site that points to your live site: links, images and files in pages, page builder content, widgets, menus, theme settings and plugin settings. Clicking one of those while you work here takes you to the live site, where a change would be real. Nothing is changed: this only lists them.', 'staging-superpowers' ) . '</p>';

		if ( ! $targets ) {
			echo '<p><strong>' . wp_kses_post(
				sprintf(
					/* translators: %s: link to the settings */
					__( 'Set your live site address in the %s first, so the check knows what to look for.', 'staging-superpowers' ),
					'<a href="' . esc_url( sspw_settings_url() ) . '">' . esc_html__( 'Protection settings', 'staging-superpowers' ) . '</a>'
				)
			) . '</strong></p>';
			return;
		}
	} else {
		echo '<p>' . esc_html__( 'Run this on your live site. It finds every place that still points to a staging copy: links, images and files in pages, page builder content, widgets, menus, theme settings and plugin settings. They break or show old content when the staging copy changes or goes away. Nothing is changed: this only lists them.', 'staging-superpowers' ) . '</p>';
	}

	if ( $targets ) {
		echo '<p>' . esc_html__( 'Looking for:', 'staging-superpowers' ) . ' <code>' . implode( '</code> <code>', array_map( 'esc_html', $targets ) ) . '</code>' . ( $armed ? '' : ' ' . esc_html__( 'and any other address that looks like staging, such as staging.yoursite.com or a hosting company\'s staging domain.', 'staging-superpowers' ) ) . '</p>';
	} elseif ( ! $armed ) {
		echo '<p>' . esc_html__( 'Looking for any address that looks like staging, such as staging.yoursite.com or a hosting company\'s staging domain.', 'staging-superpowers' ) . '</p>';
	}

	if ( ! $armed ) {
		echo '<p><label for="sspw_link_check_hosts"><strong>' . esc_html__( 'Your staging addresses', 'staging-superpowers' ) . '</strong></label><br>';
		echo '<span class="description">' . esc_html__( 'If your staging site has an address that does not look like staging, add it here, one per line, for example myshop-copy.com.', 'staging-superpowers' ) . '</span><br>';
		echo '<textarea id="sspw_link_check_hosts" name="sspw_link_check_hosts" rows="3" cols="50" class="code">' . esc_textarea( (string) get_option( 'sspw_link_check_hosts', '' ) ) . '</textarea><br>';
		wp_nonce_field( 'sspw_link_hosts' );
		printf( '<button type="submit" class="button" formaction="%1$s">%2$s</button></p>', esc_url( admin_url( 'admin-post.php?action=sspw_link_hosts' ) ), esc_html__( 'Save addresses', 'staging-superpowers' ) );
	}

	echo '<p><button type="button" class="button button-primary" id="sspw-link-run">' . esc_html__( 'Check links', 'staging-superpowers' ) . '</button> <span id="sspw-link-status" role="status"></span></p>';
	echo '<div id="sspw-link-results"></div>';

	wp_enqueue_script( 'sspw-link-check', SSPW_PLUGIN_URL . 'assets/js/link-check.js', array(), SSPW_VERSION, true );
	wp_localize_script(
		'sspw-link-check',
		'sspwLinkCheck',
		array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'sspw_link_check' ),
			'steps' => array_keys( sspw_link_check_steps() ),
			'i18n'  => array(
				'running' => __( 'Checking…', 'staging-superpowers' ),
				'done'    => __( 'Done.', 'staging-superpowers' ),
				'none'    => __( 'Nothing found. No place points to the other copy.', 'staging-superpowers' ),
				'failed'  => __( 'The check stopped because of an error. Reload the page and try again.', 'staging-superpowers' ),
				/* translators: 1: number of places, 2: address */
				'group'   => __( '%1$d places point to %2$s', 'staging-superpowers' ),
				/* translators: %s: address */
				'one'     => __( '1 place points to %s', 'staging-superpowers' ),
				'edit'    => __( 'Edit', 'staging-superpowers' ),
				'image'   => __( 'Image used:', 'staging-superpowers' ),
				/* translators: %d: number of places not shown */
				'more'    => __( 'And %d more.', 'staging-superpowers' ),
			),
		)
	);
}
