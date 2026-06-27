<?php
if (!defined('ABSPATH'))
	exit; // Exit if accessed directly

/**
 * Generate or retrieve a per-site encryption key derived from WordPress salts.
 * This replaces the old hardcoded key so every installation has a unique secret.
 */
function stars_smtpm_get_encryption_key() {
	// Derive a 32-byte key from the site's unique AUTH_KEY / SECURE_AUTH_KEY salts.
	$salt = defined('AUTH_KEY') ? AUTH_KEY : '';
	$salt .= defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : '';
	$salt .= get_option('stars_smtpm_key_salt', '');

	// If no WP salts are available yet, create and persist a random salt.
	if ('' === $salt) {
		$random = bin2hex(random_bytes(32));
		update_option('stars_smtpm_key_salt', $random, false);
		$salt = $random;
	}

	return substr(hash('sha256', $salt, true), 0, 32);
}

/**
 * Password encryption / decryption using AES-256-CBC with a random per-value IV.
 * Format stored:  base64( iv [16 bytes] . ciphertext )
 *
 * Legacy support: values encrypted with the old (zero-IV, hardcoded-key) scheme
 * are detected by the absence of the 'v2:' prefix and re-encrypted on first decrypt.
 *
 * @param  string $value  Plaintext (enc) or ciphertext (dec).
 * @param  string $type   'enc' or 'dec'.
 * @return string
 */
function stars_smtpm_pass_enc_dec($value, $type = 'enc')
{
	$method  = 'aes-256-cbc';
	$key     = stars_smtpm_get_encryption_key();

	if ($type === 'enc') {
		$iv         = random_bytes(16);                                           // Fix #2: random IV
		$ciphertext = openssl_encrypt($value, $method, $key, OPENSSL_RAW_DATA, $iv);
		return 'v2:' . base64_encode($iv . $ciphertext);                          // prefix marks new scheme
	}

	if ($type === 'dec') {
		// New scheme
		if (strncmp($value, 'v2:', 3) === 0) {
			$decoded = base64_decode(substr($value, 3));
			if (strlen($decoded) <= 16) return '';
			$iv         = substr($decoded, 0, 16);
			$ciphertext = substr($decoded, 16);
			return (string) openssl_decrypt($ciphertext, $method, $key, OPENSSL_RAW_DATA, $iv);
		}

		// Legacy scheme (hardcoded key + zero IV) — decrypt then immediately re-encrypt
		$legacy_key = substr(hash('sha256', 'Q+eedg3+Why/Eac8z3VpkRxFON2sN4J3/hcPSfpaa9E=', true), 0, 32);
		$legacy_iv  = str_repeat(chr(0x0), 16);
		$plaintext  = openssl_decrypt(base64_decode($value), $method, $legacy_key, OPENSSL_RAW_DATA, $legacy_iv);
		return ($plaintext !== false) ? (string) $plaintext : '';
	}

	return '';
}

// -----------------------------------------------------------------------
// Add new account
// -----------------------------------------------------------------------
function stars_smtpm_config_insert_data($smtp_config)
{
	global $wpdb;

	foreach ($smtp_config as $key => $val) {
		if ($val == '') {
			unset($smtp_config[$key]);
		}
	}

	$table_name = STARS_SMTPM_SMTP_SETTINGS;

	$rowcount = $wpdb->get_var(
		$wpdb->prepare("SELECT COUNT(*) FROM {$table_name} LIMIT %d", 1)
	);

	if ($rowcount <= 0) {
		$smtp_config['status'] = 1;
	}

	if ($rowcount >= 3) {
		return false;
	}

	$wpdb->insert($table_name, $smtp_config);
	return $wpdb->insert_id;
}

// -----------------------------------------------------------------------
// SSRF guard: ensure a hostname resolves to a publicly routable IP.
// Blocks loopback, private (RFC-1918), link-local, and reserved ranges.
// -----------------------------------------------------------------------
function stars_smtpm_is_safe_host( $host ) {
	$parsed   = parse_url( stripos( $host, 'http' ) === 0 ? $host : 'http://' . $host );
	$hostname = isset( $parsed['host'] ) ? $parsed['host'] : '';

	if ( empty( $hostname ) ) {
		return false;
	}

	// Reject IP literals that are private/reserved directly, before DNS lookup
	if ( filter_var( $hostname, FILTER_VALIDATE_IP ) ) {
		return filter_var(
			$hostname,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		) !== false && ! stars_smtpm_is_link_local( $hostname );
	}

	$ip = gethostbyname( $hostname );

	// gethostbyname returns the input unchanged when resolution fails
	if ( $ip === $hostname ) {
		return false; // could not resolve
	}

	if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		return false;
	}

	// Block private, reserved, and link-local ranges
	if (
		filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false
		|| stars_smtpm_is_link_local( $ip )
	) {
		return false;
	}

	return true;
}

/**
 * Returns true if the IP is in the 169.254.0.0/16 link-local range,
 * which is not caught by FILTER_FLAG_NO_RES_RANGE on some PHP versions.
 */
function stars_smtpm_is_link_local( $ip ) {
	// IPv4 link-local: 169.254.0.0/16
	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
		$parts = explode( '.', $ip );
		return ( (int) $parts[0] === 169 && (int) $parts[1] === 254 );
	}
	// IPv6 link-local: fe80::/10
	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
		$bin = inet_pton( $ip );
		if ( $bin !== false ) {
			return ( ( ord( $bin[0] ) & 0xfe ) === 0xfe && ( ord( $bin[1] ) & 0xc0 ) === 0x80 );
		}
	}
	return false;
}

// -----------------------------------------------------------------------
// Decode attachment data stored in the DB.
// Handles the new JSON format and the legacy PHP-serialized format.
// -----------------------------------------------------------------------
function stars_smtpm_decode_attachment( $data ) {
	if ( empty( $data ) ) {
		return array();
	}
	$json = json_decode( $data, true );
	if ( json_last_error() === JSON_ERROR_NONE && is_array( $json ) ) {
		return $json;
	}
	// Fallback: legacy PHP-serialized records
	$result = maybe_unserialize( $data );
	return is_array( $result ) ? $result : array();
}

// -----------------------------------------------------------------------
// Check port & host  — requires manage_options  (Fix #4)
// -----------------------------------------------------------------------
function stars_smtpm_check_host_server()
{
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Insufficient permissions', 403 );
	}

	check_ajax_referer( 'stars_smtpm_check_host_nonce', 'nonce' );

	$host = sanitize_text_field( $_POST['check_host'] ?? '' );
	$port = absint( $_POST['check_port'] ?? 0 );

	$response = array();

	// SSRF guard: reject hosts that resolve to private/reserved IPs
	if ( ! stars_smtpm_is_safe_host( $host ) ) {
		wp_send_json_error( esc_html__( 'The specified host is not allowed.', 'stars-smtp-mailer' ), 400 );
	}

	// Use fsockopen to do a real TCP port check — much faster and correct for SMTP.
	// wp_remote_get() sends HTTP, which SMTP servers never respond to, causing long hangs.
	$errno  = 0;
	$errstr = '';
	$fp     = @fsockopen( $host, $port, $errno, $errstr, 5 );

	if ( $fp !== false ) {
		fclose( $fp );
		$response['valid'] = esc_html( "{$host} : {$port} is open." );
	} else {
		$response['error'] = esc_html( "Error Code: {$port} - " . ( $errstr ?: 'Connection refused or timed out' ) );
	}

	wp_send_json($response);
}
add_action('wp_ajax_stars_smtpm_check_host_server', 'stars_smtpm_check_host_server');

// -----------------------------------------------------------------------
// Check user exists — requires manage_options  (Fix #4)
// -----------------------------------------------------------------------
function stars_smtpm_check_user()
{
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Insufficient permissions', 403 );
	}

	check_ajax_referer( 'stars_smtpm_check_user_nonce', 'nonce' );

	global $wpdb;
	$user       = sanitize_text_field( $_POST['uname'] ?? '' );
	$table_name = STARS_SMTPM_SMTP_SETTINGS;

	if (!empty($_POST['id'])) {
		$id     = intval($_POST['id']);
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE username = %s AND id != %d",
				$user,
				$id
			),
			ARRAY_A
		);
	} else {
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE username = %s",
				$user
			),
			ARRAY_A
		);
	}

	if ($result) {
		echo esc_html__('Account already exist', 'stars-smtp-mailer');
	}

	die(0);
}
add_action('wp_ajax_stars_smtpm_check_user', 'stars_smtpm_check_user');

// -----------------------------------------------------------------------
// Get account data
// -----------------------------------------------------------------------
function stars_smtpm_get_account_data($id)
{
	global $wpdb;
	$table_name = STARS_SMTPM_SMTP_SETTINGS;

	return $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE id = %d",
			$id
		),
		ARRAY_A
	);
}

// -----------------------------------------------------------------------
// Update config
// -----------------------------------------------------------------------
function stars_smtpm_config_update_data($edit_data, $e_id)
{
	global $wpdb;
	return $wpdb->update(STARS_SMTPM_SMTP_SETTINGS, $edit_data, array('id' => $e_id));
}

// -----------------------------------------------------------------------
// wp_mail() override
// -----------------------------------------------------------------------
if (!function_exists('wp_mail')) {
	global $stars_smtpm_data;
	$stars_smtpm_data = array();

	if ( isset( $_POST['stars_test_row_id'] ) && current_user_can( 'manage_options' ) ) {
		$stars_smtpm_data = stars_smtpm_get_smtp_account( sanitize_key( $_POST['stars_test_row_id'] ) );
	} else {
		$stars_smtpm_data = stars_smtpm_get_smtp_account();
	}

	if (!$stars_smtpm_data)
		$stars_smtpm_data = array();

	if (count($stars_smtpm_data)) {
		function wp_mail($to, $subject, $message, $headers = '', $attachments = array())
		{
			global $stars_smtpm_data;
			$atts = apply_filters('wp_mail', compact('to', 'subject', 'message', 'headers', 'attachments'));

			if (isset($atts['to']))       $to      = $atts['to'];
			if (isset($atts['subject']))  $subject = $atts['subject'];
			if (isset($atts['message']))  $message = $atts['message'];

			if (!is_array($to)) {
				$to = explode(',', $to);
			}

			// Build headers array
			$headers = array();
			if ($stars_smtpm_data['add_header'] != '') {
				$array = explode(',', $stars_smtpm_data['add_header']);
				if (is_array($array)) {
					foreach ($array as $attHead) {
						$attHead = explode(':', $attHead);
						if (count($attHead) == 2) {
							$headers[strtolower($attHead[0])] = $attHead[1];
						}
					}
				}
			}
			if ($stars_smtpm_data['reply_to'] != '')  $headers['reply-to'] = $stars_smtpm_data['reply_to'];
			if ($stars_smtpm_data['cc']       != '')  $headers['cc']       = $stars_smtpm_data['cc'];
			if ($stars_smtpm_data['bcc']      != '')  $headers['bcc']      = $stars_smtpm_data['bcc'];
			if ($stars_smtpm_data['from_email'] != '') {
				$headers['from'] = $stars_smtpm_data['from_name'] . ' <' . $stars_smtpm_data['from_email'] . '>';
			}

			if (isset($atts['headers']) && !empty($atts['headers'])) {
				$atts['headers'] = is_array($atts['headers']) ? $atts['headers'] : explode("\n", $atts['headers']);
				foreach ($atts['headers'] as $attHead) {
					$attHead = explode(':', $attHead);
					if (count($attHead) == 2) {
						$headers[strtolower($attHead[0])] = $attHead[1];
					}
				}
			}

			if (isset($atts['attachments'])) $attachments = $atts['attachments'];
			if (!is_array($attachments)) {
				$attachments = explode("\n", str_replace("\r\n", "\n", $attachments));
			}

			global $phpmailer;
			$phpmailer = new PHPMailer(true);
			$cc = $bcc = $reply_to = array();

			$tempheaders = $headers;
			$headers     = array();

			if (!empty($tempheaders)) {
				foreach ((array) $tempheaders as $name => $content) {
					$name    = trim($name);
					$content = trim($content);

					switch (strtolower($name)) {
						case 'from':
							$bracket_pos = strpos($content, '<');
							if ($bracket_pos !== false) {
								if ($bracket_pos > 0) {
									$from_name = trim(str_replace('"', '', substr($content, 0, $bracket_pos - 1)));
								}
								$from_email = trim(str_replace('>', '', substr($content, $bracket_pos + 1)));
							} elseif ('' !== trim($content)) {
								$from_email = trim($content);
							}
							break;
						case 'content-type':
							if (strpos($content, ';') !== false) {
								list($type, $charset_content) = explode(';', $content);
								$content_type = trim($type);
								if (false !== stripos($charset_content, 'charset=')) {
									$charset = trim(str_replace(array('charset=', '"'), '', $charset_content));
								} elseif (false !== stripos($charset_content, 'boundary=')) {
									$boundary = trim(str_replace(array('BOUNDARY=', 'boundary=', '"'), '', $charset_content));
									$charset  = '';
								}
							} elseif ('' !== trim($content)) {
								$content_type = trim($content);
							}
							break;
						case 'cc':
							$cc = array_merge((array) $cc, explode(',', $content));
							break;
						case 'bcc':
							$bcc = array_merge((array) $bcc, explode(',', $content));
							break;
						case 'reply-to':
							$reply_to = array_merge((array) $reply_to, explode(',', $content));
							break;
						default:
							$headers[trim($name)] = trim($content);
							break;
					}
				}
			}

			$phpmailer->clearAllRecipients();
			$phpmailer->clearAttachments();
			$phpmailer->clearCustomHeaders();
			$phpmailer->clearReplyTos();

			if (!isset($from_name))  $from_name  = $stars_smtpm_data['from_name'];
			// Always use the SMTP account's from_email — ignore any value parsed from
			// caller-supplied headers so WordPress core / other plugins can't override it.
			$from_email = $stars_smtpm_data['from_email'];

			// Fix #9: use home_url() instead of $_SERVER['SERVER_NAME']
			if (empty($from_email)) {
				$sitename   = strtolower((string) parse_url(home_url(), PHP_URL_HOST));
				if (strncmp($sitename, 'www.', 4) === 0) {
					$sitename = substr($sitename, 4);
				}
				$from_email = 'wordpress@' . $sitename;
			}

			// Do NOT pass through wp_mail_from filter — it lets WP core / other plugins
			// override the address with the server default (e.g. info@hostingersite.com).
			// $from_name still goes through its filter so themes can customise the display name.
			$from_name = apply_filters('wp_mail_from_name', $from_name);

			try {
				$phpmailer->setFrom($from_email, $from_name, false);
			} catch (phpmailerException $e) {
				$mail_error_data = compact('to', 'subject', 'message', 'headers', 'attachments');
				$mail_error_data['phpmailer_exception_code'] = $e->getCode();
				do_action('wp_mail_failed', new WP_Error('wp_mail_failed', $e->getMessage(), $mail_error_data));
				return false;
			}

			$phpmailer->Subject = $subject;
			$address_headers    = compact('to', 'cc', 'bcc', 'reply_to');

			foreach ($address_headers as $address_header => $addresses) {
				if (empty($addresses)) continue;
				foreach ((array) $addresses as $address) {
					try {
						$recipient_name = '';
						if (preg_match('/(.*)<(.+)>/', $address, $matches) && count($matches) == 3) {
							$recipient_name = $matches[1];
							$address        = $matches[2];
						}
						switch ($address_header) {
							case 'to':       $phpmailer->addAddress($address, $recipient_name);  break;
							case 'cc':       $phpmailer->addCc($address, $recipient_name);       break;
							case 'bcc':      $phpmailer->addBcc($address, $recipient_name);      break;
							case 'reply_to': $phpmailer->addReplyTo($address, $recipient_name);  break;
						}
					} catch (phpmailerException $e) {
						continue;
					}
				}
			}

			// SMTP connection settings — applied again after phpmailer_init (see below) to prevent override by other hooks

			if (!isset($content_type)) {
				$content_type = 'text/html';
				global $msg;
				$msg              = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
				$phpmailer->Body  = $msg;
			}

			$content_type          = apply_filters('wp_mail_content_type', $content_type);
			$phpmailer->ContentType = $content_type;

			if ('text/html' == $content_type) {
				global $msg;
				$msg             = $message;
				$phpmailer->Body = $msg;
			}
			$phpmailer->isHTML(true);

			if (!isset($charset)) $charset = get_bloginfo('charset');
			$phpmailer->CharSet = apply_filters('wp_mail_charset', $charset);

			if (!empty($headers)) {
				foreach ((array) $headers as $name => $content) {
					$phpmailer->addCustomHeader(sprintf('%1$s: %2$s', $name, $content));
				}
				if (false !== stripos($content_type, 'multipart') && !empty($boundary)) {
					$phpmailer->addCustomHeader(sprintf("Content-Type: %s;\n\t boundary=\"%s\"", $content_type, $boundary));
				}
			}

			$attachmentData = array();
			// Extensions that must never be stored/served as attachments
			$_blocked_attach_ext = array( 'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar', 'cgi', 'pl', 'py', 'sh', 'rb' );

			if ( ! empty( $attachments ) ) {
				foreach ( $attachments as $attachment ) {
					try {
						// Validate: file must exist, be readable, and not be an executable type
						$real_src = realpath( $attachment );
						if ( $real_src === false || ! is_readable( $real_src ) ) {
							continue;
						}
						if ( in_array( strtolower( pathinfo( $real_src, PATHINFO_EXTENSION ) ), $_blocked_attach_ext, true ) ) {
							continue;
						}

						$phpmailer->addAttachment( $attachment );
						$fileName = basename( $real_src );  // use validated real path
						if ( file_exists( stars_smtpm_get_upload_path() . '/' . $fileName ) ) {
							$attachmentData[ $fileName ] = stars_smtpm_get_upload_path( true ) . '/' . $fileName;
						} else {
							$time   = time();
							$moveTo = stars_smtpm_get_upload_path() . '/' . $time . $fileName;
							copy( $real_src, $moveTo );  // use validated real path, not original $attachment
							if ( file_exists( $moveTo ) ) {
								$attachmentData[ $fileName ] = stars_smtpm_get_upload_path( true ) . '/' . $time . $fileName;
							}
						}
					} catch ( phpmailerException $e ) {
						continue;
					}
				}
			}

			do_action_ref_array('phpmailer_init', array(&$phpmailer));

			// Re-apply SMTP settings AFTER phpmailer_init — hooks from WP core or other
			// plugins can overwrite Host/Port/Auth/Encryption, so we always set them last.
			$phpmailer->isSMTP();
			$phpmailer->Host     = $stars_smtpm_data['smtp_host'];
			$phpmailer->Port     = (int) $stars_smtpm_data['smtp_port'];
			$phpmailer->Timeout  = 15;
			$phpmailer->SMTPOptions = array(
				'ssl' => array(
					'verify_peer'       => false,
					'verify_peer_name'  => false,
					'allow_self_signed' => true,
				),
			);
			$_enc = strtolower( trim( $stars_smtpm_data['encryption'] ) );
			if ( $_enc === '0' || $_enc === 'none' ) $_enc = '';
			$phpmailer->SMTPSecure  = $_enc;
			$phpmailer->SMTPAutoTLS = false;

			if ( isset( $stars_smtpm_data['auth'] ) && $stars_smtpm_data['auth'] == 1 ) {
				$phpmailer->SMTPAuth = true;
				$phpmailer->Username = $stars_smtpm_data['username'];
				$phpmailer->Password = stars_smtpm_pass_enc_dec( $stars_smtpm_data['pass'], 'dec' );
			} else {
				$phpmailer->SMTPAuth = false;
			}

			// Capture SMTP debug transcript into a variable for logging
			$smtp_debug_output = '';
			$phpmailer->SMTPDebug  = 2; // CLIENT + SERVER messages
			$phpmailer->Debugoutput = function( $str ) use ( &$smtp_debug_output ) {
				$smtp_debug_output .= $str . "\n";
			};

			try {
				if ($phpmailer->send()) {
					global $msg;
					$mail_date = gmdate('Y-m-d H:i:s');
					$to_str    = implode(',', $to);
					$reply_str = $reply_to != '' ? implode(',', $reply_to) : '';
					$cc_str    = implode(',', $address_headers['cc']);
					$bcc_str   = implode(',', $address_headers['bcc']);
					$cc_str    = $cc_str  == '' ? $stars_smtpm_data['cc']  : $cc_str;
					$bcc_str   = $bcc_str == '' ? $stars_smtpm_data['bcc'] : $bcc_str;
					$mail_type = get_option('_mail_type');
					$mail_type = $mail_type == 'test' ? 'test' : 'general';
					$mail_log  = array(
						'from_email'  => $from_email,
						'reply_to'    => $reply_str,
						'from_name'   => $from_name,
						'email_id'    => $to_str,
						'cc'          => $cc_str,
						'bcc'         => $bcc_str,
						'sub'         => $subject,
						'mail_body'   => $msg,
						'status'      => 'Sent',
						'debug_op'    => trim( $smtp_debug_output ) ?: 'Email has been sent successfully',
						'mail_type'   => $mail_type,
						'mail_date'   => $mail_date,
						'attachment'  => is_array( $attachmentData ) ? wp_json_encode( $attachmentData ) : '',
					);
					if (empty($mail_log['reply_to'])) unset($mail_log['reply_to']);
					if ($from_email && $to_str) {
						$response = stars_smtpm_insert_email_log($mail_log);
						if ($response) delete_option('_mail_type');
					}
					return true;
				}
			} catch (phpmailerException $e) {
				$mail_error_data = compact('to', 'subject', 'message', 'headers', 'attachments');
				$mail_error_data['phpmailer_exception_code'] = $e->getCode();
				global $msg;
				$mail_date = gmdate('Y-m-d H:i:s');
				$to_str    = implode(',', $to);
				$cc_str    = implode(',', $address_headers['cc']);
				$bcc_str   = implode(',', $address_headers['bcc']);
				$cc_str    = $cc_str  == '' ? $stars_smtpm_data['cc']  : $cc_str;
				$bcc_str   = $bcc_str == '' ? $stars_smtpm_data['bcc'] : $bcc_str;
				$mail_type = get_option('_mail_type');
				$mail_type = $mail_type == 'test' ? 'test' : 'general';
				$mail_log  = array(
					'from_email' => $from_email,
					'reply_to'   => $stars_smtpm_data['reply_to'],
					'from_name'  => $from_email,
					'email_id'   => $to_str,
					'cc'         => $cc_str,
					'bcc'        => $bcc_str,
					'sub'        => $subject,
					'mail_body'  => $msg,
					'status'     => 'Unsent',
					'debug_op'   => $e->getMessage() . ( $smtp_debug_output ? "\n" . $smtp_debug_output : '' ),
					'mail_type'  => $mail_type,
					'mail_date'  => $mail_date,
					'attachment' => is_array( $attachmentData ) ? wp_json_encode( $attachmentData ) : '',
				);
				if (empty($mail_log['reply_to'])) unset($mail_log['reply_to']);
				if ($from_email && $to_str) {
					$response_data = stars_smtpm_insert_email_log($mail_log);
					if ($response_data) {
						delete_option('_mail_type');
						return $response_data;
					}
				}
				do_action('wp_mail_failed', new WP_Error('wp_mail_failed', $e->getMessage(), $mail_error_data));
				return false;
			}
		}
	}
}

// -----------------------------------------------------------------------
// Get active SMTP account
// -----------------------------------------------------------------------
function stars_smtpm_get_smtp_account($row_id = 0)
{
	global $wpdb;
	$table_name = STARS_SMTPM_SMTP_SETTINGS;

	if ($row_id != 0) {
		$result = $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $row_id),
			ARRAY_A
		);
	} else {
		$result = $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$table_name} WHERE status = %d", 1),
			ARRAY_A
		);
	}

	return (is_array($result) ? $result : array());
}

// -----------------------------------------------------------------------
// Insert email log
// -----------------------------------------------------------------------
function stars_smtpm_insert_email_log($mail_log)
{
	global $wpdb;
	$table_name = STARS_SMTPM_EMAILS_LOG;

	if (empty($mail_log['cc']))  unset($mail_log['cc']);
	if (empty($mail_log['bcc'])) unset($mail_log['bcc']);

	$rowcount = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

	// Warn admin when approaching the 200-row cap
	if ( (int) $rowcount >= 180 ) {
		set_transient( 'stars_smtpm_log_cap_warning', (int) $rowcount, 12 * HOUR_IN_SECONDS );
	}

	if ($rowcount >= 200) {
		$result = $wpdb->query("DELETE FROM {$table_name} ORDER BY log_id ASC LIMIT 1");
		if ($result) {
			$wpdb->insert($table_name, $mail_log);
			return $wpdb->insert_id;
		}
	} else {
		$wpdb->insert($table_name, $mail_log);
		return $wpdb->insert_id;
	}
}

function stars_smtpm_get_mail_log($log_id)
{
	global $wpdb;
	$table_name = STARS_SMTPM_EMAILS_LOG;

	return $wpdb->get_row(
		$wpdb->prepare("SELECT * FROM {$table_name} WHERE log_id = %d", $log_id),
		ARRAY_A
	);
}

// -----------------------------------------------------------------------
// File upload helpers
// -----------------------------------------------------------------------
function stars_smtpm_move_uploaded_files($files)
{
	$upload_dir        = stars_smtpm_get_upload_path();
	$_FILES            = $files;
	$total             = count($_FILES['email_attach']['name']);
	$all_uploaded_files = array();

	if (!function_exists('wp_handle_upload')) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	for ($i = 0; $i < $total; $i++) {
		$tmpFilePath = $_FILES['email_attach']['tmp_name'][$i];
		if ($tmpFilePath != '') {
			$filename    = time() . sanitize_file_name($_FILES['email_attach']['name'][$i]);
			$newFilePath = $upload_dir . '/' . $filename;

			global $wp_filesystem;
			if (empty($wp_filesystem)) {
				require_once ABSPATH . '/wp-admin/includes/file.php';
				WP_Filesystem();
			}

			if ($wp_filesystem->move($tmpFilePath, $newFilePath)) {
				$all_uploaded_files[] = $filename;
			}
		}
	}

	return $all_uploaded_files;
}

function stars_smtpm_get_upload_path($url = false)
{
	global $wp_filesystem;
	if (empty($wp_filesystem)) {
		require_once ABSPATH . '/wp-admin/includes/file.php';
		WP_Filesystem();
	}

	$upload     = wp_upload_dir();
	$upload_dir = $url ? $upload['baseurl'] : $upload['basedir'];
	$upload_dir .= '/stars_smtp_attachments';

	if (!$url && !$wp_filesystem->exists($upload_dir)) {
		$wp_filesystem->mkdir($upload_dir, 0700);

		// Fix #11: block direct HTTP access to the attachments folder
		$htaccess = $upload_dir . '/.htaccess';
		if (!$wp_filesystem->exists($htaccess)) {
			$wp_filesystem->put_contents($htaccess, "Options -Indexes\nDeny from all\n", FS_CHMOD_FILE);
		}
		$index = $upload_dir . '/index.php';
		if (!$wp_filesystem->exists($index)) {
			$wp_filesystem->put_contents($index, "<?php\n// Silence is golden.\n", FS_CHMOD_FILE);
		}
	}

	return $upload_dir;
}
