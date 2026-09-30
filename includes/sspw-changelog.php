<?php
/**
 * Changelog: what was changed on this staging copy, so it can be redone on the
 * live site. With WooCommerce it goes to the WooCommerce logs (source
 * "staging-superpowers"), which show, filter, download and clean up the
 * entries. Without it, the latest entries are kept in one option and shown on
 * the Changelog sub-page. Only loaded when the site is armed.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'switch_theme', 'sspw_log_theme', 10, 3 );
add_action( 'activated_plugin', 'sspw_log_plugin_activated' );
add_action( 'deactivated_plugin', 'sspw_log_plugin_deactivated' );
add_action( 'upgrader_process_complete', 'sspw_log_updates', 10, 2 );
add_action( 'transition_post_status', 'sspw_log_post_status', 10, 3 );
add_action( 'before_delete_post', 'sspw_log_post_deleted' );
add_action( 'created_term', 'sspw_log_term_created', 10, 3 );
add_action( 'edited_term', 'sspw_log_term_edited', 10, 3 );
add_action( 'pre_delete_term', 'sspw_log_term_deleted', 10, 2 );
add_action( 'wp_update_nav_menu', 'sspw_log_menu' );

function sspw_log( $message ) {
	$user = wp_get_current_user();
	$who  = $user->exists() ? $user->display_name : ( ( defined( 'WP_CLI' ) && WP_CLI ) ? 'WP-CLI' : __( 'the system', 'staging-superpowers' ) );

	/* translators: 1: what changed, 2: user name */
	$entry = sprintf( __( '%1$s (by %2$s)', 'staging-superpowers' ), $message, $who );

	if ( sspw_changelog_uses_woocommerce() ) {
		wc_get_logger()->notice( $entry, array( 'source' => SSPW_LOG_SOURCE ) );
		return;
	}

	// Newest last, capped so the option never grows without limit.
	$log   = sspw_changelog_entries();
	$log[] = array( time(), $entry );
	update_option( SSPW_LOG_OPTION, array_slice( $log, -SSPW_LOG_LIMIT ), false );
}

/**
 * Block editor saves arrive as two requests (content, then meta boxes), and some
 * screens save the same thing twice, so one entry per item per minute is enough.
 */
function sspw_log_once( $key, $message ) {
	$key = 'sspw_logged_' . md5( $key );

	if ( get_transient( $key ) ) {
		return;
	}

	set_transient( $key, 1, MINUTE_IN_SECONDS );
	sspw_log( $message );
}

/**
 * One entry per changed setting, with the old and new value.
 *
 * @param string $where   Settings screen name.
 * @param array  $changes Option name => array( old, new ).
 * @param array  $fields  Option name => field definition (title, desc, type, options).
 */
function sspw_log_changes( $where, $changes, $fields ) {
	foreach ( $changes as $option => $change ) {
		// Transients, caches and other background bookkeeping are not user changes.
		if ( 0 === strpos( $option, '_' ) ) {
			continue;
		}

		$field = isset( $fields[ $option ] ) ? $fields[ $option ] : array();
		// Checkboxes are named by their own label; the title is only the group heading.
		$label = $option;
		if ( isset( $field['type'] ) && 'checkbox' === $field['type'] && ! empty( $field['desc'] ) ) {
			$label = ! empty( $field['title'] ) ? $field['title'] . ': ' . $field['desc'] : $field['desc'];
		} elseif ( ! empty( $field['title'] ) ) {
			$label = $field['title'];
		}

		if ( is_array( $change[1] ) && ! $field ) {
			sspw_log_array_setting( $where, $option, (array) $change[0], $change[1] );
			continue;
		}

		sspw_log(
			sprintf(
				/* translators: 1: settings screen, 2: setting label, 3: old value, 4: new value */
				__( 'Settings > %1$s: "%2$s" changed from %3$s to %4$s', 'staging-superpowers' ),
				$where,
				wp_strip_all_tags( $label ),
				sspw_log_value( $field, $change[0], $option ),
				sspw_log_value( $field, $change[1], $option )
			)
		);
	}
}

/**
 * Settings stored as one array per extension (payment gateways, shipping methods):
 * one entry per key that changed.
 */
function sspw_log_array_setting( $where, $option, $before_all, $after_all ) {
	foreach ( array_unique( array_merge( array_keys( $before_all ), array_keys( $after_all ) ) ) as $key ) {
		$before = isset( $before_all[ $key ] ) ? $before_all[ $key ] : null;
		$after  = isset( $after_all[ $key ] ) ? $after_all[ $key ] : null;

		if ( $before === $after ) {
			continue;
		}

		$name = $option . ' > ' . $key;

		sspw_log(
			sprintf(
				/* translators: 1: settings tab, 2: setting label, 3: old value, 4: new value */
				__( 'Settings > %1$s: "%2$s" changed from %3$s to %4$s', 'staging-superpowers' ),
				$where,
				$name,
				sspw_log_value( array(), $before, $name ),
				sspw_log_value( array(), $after, $name )
			)
		);
	}
}

/**
 * Readable values: on/off for checkboxes, option labels for dropdowns, and
 * nothing at all for anything that looks like a secret.
 */
function sspw_log_value( $field, $value, $name ) {
	$type = isset( $field['type'] ) ? $field['type'] : '';

	if ( 'password' === $type || preg_match( '/key|secret|token|password|pass$/i', $name ) ) {
		return __( '(hidden)', 'staging-superpowers' );
	}

	if ( null === $value || '' === $value || array() === $value ) {
		return __( '(empty)', 'staging-superpowers' );
	}

	if ( 'checkbox' === $type || in_array( $value, array( 'yes', 'no' ), true ) ) {
		return 'yes' === $value ? __( 'on', 'staging-superpowers' ) : __( 'off', 'staging-superpowers' );
	}

	if ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
		$labels = array();
		foreach ( (array) $value as $one ) {
			$labels[] = isset( $field['options'][ $one ] ) && is_string( $field['options'][ $one ] ) ? $field['options'][ $one ] : $one;
		}
		$value = implode( ', ', $labels );
	}

	if ( ! is_scalar( $value ) ) {
		$value = wp_json_encode( $value );
	}

	$value = wp_strip_all_tags( (string) $value );

	return '"' . ( strlen( $value ) > 80 ? substr( $value, 0, 77 ) . '...' : $value ) . '"';
}

/*
 * Theme, plugins and updates.
 */

function sspw_log_theme( $new_name, $new_theme, $old_theme ) {
	/* translators: 1: old theme name, 2: new theme name */
	sspw_log( sprintf( __( 'Theme switched from %1$s to %2$s', 'staging-superpowers' ), $old_theme->get( 'Name' ), $new_name ) );
}

function sspw_plugin_name( $file ) {
	$plugins = get_plugins();

	return isset( $plugins[ $file ] ) ? $plugins[ $file ]['Name'] : $file;
}

function sspw_log_plugin_activated( $file ) {
	/* translators: %s: plugin name */
	sspw_log( sprintf( __( 'Plugin activated: %s', 'staging-superpowers' ), sspw_plugin_name( $file ) ) );
}

function sspw_log_plugin_deactivated( $file ) {
	/* translators: %s: plugin name */
	sspw_log( sprintf( __( 'Plugin deactivated: %s', 'staging-superpowers' ), sspw_plugin_name( $file ) ) );
}

function sspw_log_updates( $upgrader, $extra ) {
	if ( empty( $extra['action'] ) || 'update' !== $extra['action'] || empty( $extra['type'] ) ) {
		return;
	}

	if ( 'plugin' === $extra['type'] && ! empty( $extra['plugins'] ) ) {
		$plugins = get_plugins();
		foreach ( (array) $extra['plugins'] as $file ) {
			if ( isset( $plugins[ $file ] ) ) {
				/* translators: 1: plugin name, 2: version */
				sspw_log( sprintf( __( 'Plugin updated: %1$s to version %2$s', 'staging-superpowers' ), $plugins[ $file ]['Name'], $plugins[ $file ]['Version'] ) );
			}
		}
	} elseif ( 'theme' === $extra['type'] && ! empty( $extra['themes'] ) ) {
		foreach ( (array) $extra['themes'] as $slug ) {
			$theme = wp_get_theme( $slug );
			/* translators: 1: theme name, 2: version */
			sspw_log( sprintf( __( 'Theme updated: %1$s to version %2$s', 'staging-superpowers' ), $theme->get( 'Name' ), $theme->get( 'Version' ) ) );
		}
	} elseif ( 'core' === $extra['type'] ) {
		/* translators: %s: WordPress version */
		sspw_log( sprintf( __( 'WordPress updated to version %s', 'staging-superpowers' ), get_bloginfo( 'version' ) ) );
	}
}

/*
 * Content.
 */

function sspw_logged_post_types() {
	return array(
		'product'     => __( 'Product', 'staging-superpowers' ),
		'page'        => __( 'Page', 'staging-superpowers' ),
		'post'        => __( 'Post', 'staging-superpowers' ),
		'shop_coupon' => __( 'Coupon', 'staging-superpowers' ),
	);
}

function sspw_post_label( $post ) {
	$types = sspw_logged_post_types();
	$title = '' !== $post->post_title ? $post->post_title : __( '(no title)', 'staging-superpowers' );

	return sprintf( '%s "%s" (#%d)', $types[ $post->post_type ], $title, $post->ID );
}

function sspw_log_post_status( $new_status, $old_status, $post ) {
	if ( ! isset( sspw_logged_post_types()[ $post->post_type ] ) || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) || in_array( $new_status, array( 'auto-draft', 'inherit' ), true ) ) {
		return;
	}

	if ( 'trash' === $new_status ) {
		/* translators: %s: item, e.g. Product "Hoodie" (#12) */
		$message = __( '%s moved to the trash', 'staging-superpowers' );
	} elseif ( 'trash' === $old_status ) {
		/* translators: %s: item, e.g. Product "Hoodie" (#12) */
		$message = __( '%s restored from the trash', 'staging-superpowers' );
	} elseif ( in_array( $old_status, array( 'new', 'auto-draft' ), true ) ) {
		/* translators: %s: item, e.g. Product "Hoodie" (#12) */
		$message = __( '%s created', 'staging-superpowers' );
	} else {
		/* translators: %s: item, e.g. Product "Hoodie" (#12) */
		$message = __( '%s updated', 'staging-superpowers' );
	}

	sspw_log_once( $post->ID . $message, sprintf( $message, sspw_post_label( $post ) ) );
}

function sspw_log_post_deleted( $post_id ) {
	$post = get_post( $post_id );

	if ( $post && isset( sspw_logged_post_types()[ $post->post_type ] ) && 'auto-draft' !== $post->post_status ) {
		/* translators: %s: item, e.g. Product "Hoodie" (#12) */
		sspw_log( sprintf( __( '%s deleted permanently', 'staging-superpowers' ), sspw_post_label( $post ) ) );
	}
}

function sspw_logged_taxonomy( $taxonomy ) {
	$tax = get_taxonomy( $taxonomy );

	if ( ! $tax || ! ( in_array( $taxonomy, array( 'product_cat', 'product_tag', 'category', 'post_tag' ), true ) || 0 === strpos( $taxonomy, 'pa_' ) ) ) {
		return '';
	}

	return $tax->labels->singular_name;
}

function sspw_log_term( $term_id, $taxonomy, $message ) {
	$label = sspw_logged_taxonomy( $taxonomy );
	$term  = get_term( $term_id, $taxonomy );

	if ( $label && $term && ! is_wp_error( $term ) ) {
		sspw_log_once( $taxonomy . $term_id . $message, sprintf( $message, sprintf( '%s "%s"', $label, $term->name ) ) );
	}
}

function sspw_log_term_created( $term_id, $tt_id, $taxonomy ) {
	/* translators: %s: term, e.g. Product category "Hoodies" */
	sspw_log_term( $term_id, $taxonomy, __( '%s created', 'staging-superpowers' ) );
}

function sspw_log_term_edited( $term_id, $tt_id, $taxonomy ) {
	/* translators: %s: term, e.g. Product category "Hoodies" */
	sspw_log_term( $term_id, $taxonomy, __( '%s updated', 'staging-superpowers' ) );
}

function sspw_log_term_deleted( $term_id, $taxonomy ) {
	/* translators: %s: term, e.g. Product category "Hoodies" */
	sspw_log_term( $term_id, $taxonomy, __( '%s deleted', 'staging-superpowers' ) );
}

function sspw_log_menu( $menu_id ) {
	$menu = wp_get_nav_menu_object( $menu_id );

	if ( $menu ) {
		/* translators: %s: menu name */
		sspw_log_once( 'menu' . $menu_id, sprintf( __( 'Menu "%s" updated', 'staging-superpowers' ), $menu->name ) );
	}
}
