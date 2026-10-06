<?php
/**
 * Emails log: every email the staging site tried to send and what happened to
 * it (blocked, forwarded or readdressed).
 *
 * Only the time, the To addresses, the subject, how many CC and BCC copies were
 * dropped and the plugin that sent it are kept. Message bodies, headers and
 * attachments are never stored, nor are CC or BCC addresses.
 */

defined( 'ABSPATH' ) || exit;

define( 'SSPW_EMAILS_OPTION', 'sspw_emails' );
define( 'SSPW_EMAILS_LIMIT', 300 );

add_action( 'admin_post_sspw_clear_emails', 'sspw_clear_emails' );
add_action( 'admin_enqueue_scripts', 'sspw_emails_style' );

function sspw_emails_style() {
	if ( ! sspw_is_settings_page( 'emails' ) ) {
		return;
	}

	wp_register_style( 'sspw-emails', false, array(), SSPW_VERSION );
	wp_enqueue_style( 'sspw-emails' );
	wp_add_inline_style( 'sspw-emails', '.sspw-req-status{display:inline-block;padding:2px 8px;border-radius:3px;font-weight:600;font-size:12px}.sspw-req-status.is-blocked{background:#fcebea;color:#b91c1c}.sspw-req-status.is-forwarded{background:#fff4e5;color:#9a5b00}.sspw-emails td{overflow-wrap:anywhere}' );
}

/**
 * The plain addresses in a To, Cc or Bcc value, without display names.
 *
 * @param string|array $value Recipients as wp_mail() accepts them.
 * @return string[]
 */
function sspw_email_addresses( $value ) {
	$list = is_array( $value ) ? $value : explode( ',', (string) $value );
	$out  = array();

	foreach ( $list as $item ) {
		if ( preg_match( '/<([^>]+)>/', (string) $item, $m ) ) {
			$item = $m[1];
		}
		$email = sanitize_email( trim( (string) $item ) );
		if ( $email ) {
			$out[] = strtolower( $email );
		}
	}

	return array_values( array_unique( $out ) );
}

/**
 * How many CC and BCC recipients the headers ask for. Only the number is kept.
 *
 * @param string|array $headers Headers as wp_mail() accepts them.
 */
function sspw_email_copy_count( $headers ) {
	$lines = is_array( $headers ) ? $headers : explode( "\n", str_replace( "\r\n", "\n", (string) $headers ) );
	$count = 0;

	foreach ( $lines as $line ) {
		if ( preg_match( '/^\s*b?cc\s*:(.*)$/i', (string) $line, $m ) ) {
			$count += count( sspw_email_addresses( $m[1] ) );
		}
	}

	return $count;
}

/**
 * Notes one email for this page load. Everything is saved once, at shutdown.
 *
 * @param string[] $to      Original To addresses.
 * @param string   $subject Subject line.
 * @param int      $copies  CC and BCC recipients dropped.
 * @param string   $status  'blocked', 'forwarded' or 'readdressed'.
 * @param string   $target  Forwarding address, when forwarded.
 */
function sspw_record_email( $to, $subject, $copies, $status, $target = '' ) {
	$to      = implode( ', ', array_slice( $to, 0, 10 ) );
	$subject = wp_strip_all_tags( (string) $subject );
	$subject = function_exists( 'mb_substr' ) ? mb_substr( $subject, 0, 200 ) : substr( $subject, 0, 200 );
	$source  = sspw_request_source();
	$key     = md5( $status . '|' . $to . '|' . $subject . '|' . $source );

	if ( empty( $GLOBALS['sspw_email_buffer'] ) ) {
		$GLOBALS['sspw_email_buffer'] = array();
		add_action( 'shutdown', 'sspw_save_emails' );
	}

	if ( isset( $GLOBALS['sspw_email_buffer'][ $key ] ) ) {
		++$GLOBALS['sspw_email_buffer'][ $key ]['count'];
		return;
	}

	$GLOBALS['sspw_email_buffer'][ $key ] = array(
		'time'    => time(),
		'status'  => $status,
		'target'  => $target,
		'to'      => $to,
		'subject' => $subject,
		'copies'  => (int) $copies,
		'source'  => $source,
		'count'   => 1,
	);
}

/**
 * Records an email from wp_mail()'s arguments.
 *
 * @param array  $atts   to, subject, message, headers, attachments. Only to, subject and the CC and BCC count are read.
 * @param string $status 'blocked' or 'forwarded'.
 * @param string $target Forwarding address.
 */
function sspw_record_email_atts( $atts, $status, $target = '' ) {
	sspw_record_email(
		sspw_email_addresses( isset( $atts['to'] ) ? $atts['to'] : '' ),
		isset( $atts['subject'] ) ? $atts['subject'] : '',
		sspw_email_copy_count( isset( $atts['headers'] ) ? $atts['headers'] : '' ),
		$status,
		$target
	);
}

/**
 * Merges this page load's emails into the stored log, newest first, capped.
 */
function sspw_save_emails() {
	if ( empty( $GLOBALS['sspw_email_buffer'] ) ) {
		return;
	}

	$log = sspw_emails();
	foreach ( $GLOBALS['sspw_email_buffer'] as $key => $row ) {
		if ( isset( $log[ $key ] ) ) {
			$row['count'] += (int) $log[ $key ]['count'];
		}
		$log[ $key ] = $row;
	}
	$GLOBALS['sspw_email_buffer'] = array();

	uasort(
		$log,
		function ( $a, $b ) {
			return $b['time'] - $a['time'];
		}
	);

	update_option( SSPW_EMAILS_OPTION, array_slice( $log, 0, SSPW_EMAILS_LIMIT, true ), false );
}

/**
 * @return array Key => row (time, status, target, to, subject, copies, source, count), newest first.
 */
function sspw_emails() {
	return (array) get_option( SSPW_EMAILS_OPTION, array() );
}

/**
 * Emails stopped or rerouted so far, for the status bar.
 */
function sspw_logged_email_count() {
	$total = 0;
	foreach ( sspw_emails() as $row ) {
		$total += (int) $row['count'];
	}

	return $total;
}

function sspw_output_emails() {
	$rows = sspw_emails();

	echo '<h2>' . esc_html__( 'Emails', 'staging-superpowers' ) . '</h2>';
	echo '<p>' . esc_html__( 'Emails your staging site tried to send, and what happened to them. Blocked ones never left the site.', 'staging-superpowers' ) . '</p>';
	echo '<p class="description">' . esc_html__( 'Only the recipients, the subject and the plugin that sent each email are kept, never the message itself. CC and BCC copies are only counted.', 'staging-superpowers' ) . '</p>';

	if ( ! $rows ) {
		echo '<p><strong>' . esc_html__( 'Nothing yet.', 'staging-superpowers' ) . '</strong> ' . esc_html__( 'When the site tries to send an email, for example a password reset, a form reply or an order email, it shows up here.', 'staging-superpowers' ) . '</p>';
		return;
	}

	$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

	echo '<table class="wp-list-table widefat fixed striped sspw-table sspw-emails">';
	sspw_table_head(
		array(
			__( 'Last seen', 'staging-superpowers' ) => '12%',
			__( 'Status', 'staging-superpowers' )    => '16%',
			__( 'To', 'staging-superpowers' )        => '20%',
			__( 'Subject', 'staging-superpowers' )   => '',
			__( 'Plugin', 'staging-superpowers' )    => '13%',
			__( 'Count', 'staging-superpowers' )     => '6%',
		)
	);
	echo '<tbody>';
	foreach ( $rows as $row ) {
		if ( 'forwarded' === $row['status'] ) {
			/* translators: %s: forwarding email address */
			$status = sprintf( __( 'Forwarded to %s', 'staging-superpowers' ), $row['target'] );
			$class  = 'is-forwarded';
		} else {
			$status = __( 'Blocked', 'staging-superpowers' );
			$class  = 'is-blocked';
		}

		$to = '' !== $row['to'] ? $row['to'] : __( '(no recipient)', 'staging-superpowers' );
		if ( $row['copies'] ) {
			/* translators: %s: number of CC and BCC recipients */
			$to .= ' ' . sprintf( _n( '+ %s CC/BCC', '+ %s CC/BCC', $row['copies'], 'staging-superpowers' ), number_format_i18n( $row['copies'] ) );
		}

		printf(
			'<tr><td title="%1$s">%2$s</td><td><span class="sspw-req-status %3$s">%4$s</span></td><td>%5$s</td><td>%6$s</td><td>%7$s</td><td>%8$s</td></tr>',
			esc_attr( wp_date( $format, (int) $row['time'] ) ),
			/* translators: %s: time difference, e.g. "5 mins" */
			esc_html( sprintf( __( '%s ago', 'staging-superpowers' ), human_time_diff( (int) $row['time'] ) ) ),
			esc_attr( $class ),
			esc_html( $status ),
			esc_html( $to ),
			esc_html( '' !== $row['subject'] ? $row['subject'] : __( '(no subject)', 'staging-superpowers' ) ),
			esc_html( sspw_request_source_label( $row['source'] ) ),
			esc_html( number_format_i18n( (int) $row['count'] ) )
		);
	}
	echo '</tbody></table>';

	/* translators: %s: number of rows */
	echo '<p class="description">' . esc_html( sprintf( __( 'Newest first. Repeats of the same email are counted on one row. The latest %s rows are kept.', 'staging-superpowers' ), number_format_i18n( SSPW_EMAILS_LIMIT ) ) ) . '</p><p>';
	wp_nonce_field( 'sspw_clear_emails', 'sspw_emails_nonce' );
	printf(
		'<button type="submit" class="button" formaction="%1$s" formmethod="post" onclick="return confirm( %2$s );">%3$s</button>',
		esc_url( admin_url( 'admin-post.php?action=sspw_clear_emails' ) ),
		esc_attr( wp_json_encode( __( 'Delete every row in the emails log?', 'staging-superpowers' ) ) ),
		esc_html__( 'Clear the log', 'staging-superpowers' )
	);
	echo '</p>';
}

function sspw_clear_emails() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'staging-superpowers' ) );
	}

	check_admin_referer( 'sspw_clear_emails', 'sspw_emails_nonce' );
	delete_option( SSPW_EMAILS_OPTION );

	wp_safe_redirect( sspw_settings_url( 'emails' ) );
	exit;
}
