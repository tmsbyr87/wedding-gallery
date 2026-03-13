<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WG_Plugin {
	const OPTION_KEY          = 'wg_settings';
	const SHORTCODE_TAG       = 'wedding_gallery_upload';
	const TOKEN_QUERY_ARG     = 'wg_token';
	const DEFAULT_MAX_SIZE_MB = 50;
	const DEFAULT_FALLBACK_SAFE_MAX_MB = 10;
	const ENCRYPTED_FILE_EXT  = '.wgenc';
	const METADATA_FILE_EXT   = '.wgmeta';

	/**
	 * @var WG_Plugin|null
	 */
	private static $instance = null;

	/**
	 * @return WG_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Creates defaults and the upload directory on plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		$current  = get_option( self::OPTION_KEY, array() );
		$settings = self::normalize_settings( $current );
		update_option( self::OPTION_KEY, $settings );

		self::create_upload_dir();
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_shortcode( self::SHORTCODE_TAG, array( $this, 'render_upload_shortcode' ) );

		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_post_wg_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_wg_upload', array( $this, 'handle_upload' ) );
		add_action( 'admin_post_nopriv_wg_upload', array( $this, 'handle_upload' ) );
		add_action( 'admin_post_wg_download_upload', array( $this, 'handle_download' ) );
	}

	/**
	 * @return array<string, string|int>
	 */
	private static function get_default_settings() {
		return array(
			'upload_page_url' => '',
			'access_token'    => '',
			'max_upload_mb'   => self::DEFAULT_MAX_SIZE_MB,
			'encryption_key'  => '',
			'key_version'     => 1,
		);
	}

	/**
	 * @param mixed $raw_settings Raw option value.
	 * @return array<string, string|int>
	 */
	private static function normalize_settings( $raw_settings ) {
		$defaults = self::get_default_settings();
		$current  = is_array( $raw_settings ) ? $raw_settings : array();
		$settings = wp_parse_args( $current, $defaults );

		$settings['upload_page_url'] = isset( $settings['upload_page_url'] ) ? esc_url_raw( (string) $settings['upload_page_url'] ) : '';
		$settings['access_token']    = isset( $settings['access_token'] ) ? sanitize_text_field( (string) $settings['access_token'] ) : '';
		$settings['max_upload_mb']   = max( 1, absint( $settings['max_upload_mb'] ) );
		$settings['key_version']     = max( 1, absint( $settings['key_version'] ) );
		$settings['encryption_key']  = isset( $settings['encryption_key'] ) ? sanitize_text_field( (string) $settings['encryption_key'] ) : '';

		if ( empty( $settings['access_token'] ) ) {
			$settings['access_token'] = wp_generate_password( 32, false, false );
		}

		$raw_key = base64_decode( $settings['encryption_key'], true );
		if ( false === $raw_key || 32 !== strlen( $raw_key ) ) {
			$settings['encryption_key'] = self::generate_encryption_key();
		}

		return $settings;
	}

	/**
	 * @return array<string, string|int>
	 */
	private function get_settings() {
		$current  = get_option( self::OPTION_KEY, array() );
		$settings = self::normalize_settings( $current );

		if ( ! is_array( $current ) || $settings !== $current ) {
			update_option( self::OPTION_KEY, $settings );
		}

		return $settings;
	}

	/**
	 * @return string
	 */
	private static function generate_encryption_key() {
		try {
			$raw_key = random_bytes( 32 );
		} catch ( Exception $exception ) {
			$seed    = wp_generate_password( 96, true, true ) . (string) wp_rand();
			$raw_key = hash( 'sha256', $seed, true );
		}

		return base64_encode( $raw_key );
	}

	/**
	 * @return array<string, string>
	 */
	private function get_allowed_mimes() {
		return array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'webp'         => 'image/webp',
			'mp4'          => 'video/mp4',
		);
	}

	/**
	 * @return string[]
	 */
	private function get_allowed_extensions() {
		return array( 'jpg', 'jpeg', 'jpe', 'png', 'webp', 'mp4' );
	}

	/**
	 * @return bool
	 */
	private function supports_encryption() {
		return function_exists( 'openssl_encrypt' ) && function_exists( 'openssl_decrypt' );
	}

	/**
	 * @param int $version
	 * @return string|false
	 */
	private function get_encryption_key_for_version( $version ) {
		$settings       = $this->get_settings();
		$current_version = max( 1, absint( $settings['key_version'] ) );
		if ( $version !== $current_version ) {
			return false;
		}

		$key_b64 = isset( $settings['encryption_key'] ) ? (string) $settings['encryption_key'] : '';
		$raw_key = base64_decode( $key_b64, true );
		if ( false === $raw_key || 32 !== strlen( $raw_key ) ) {
			return false;
		}

		return $raw_key;
	}

	/**
	 * @param string $plaintext Binary data to encrypt.
	 * @return array<string, string|int>|false
	 */
	private function encrypt_contents( $plaintext ) {
		if ( ! $this->supports_encryption() ) {
			return false;
		}

		$settings    = $this->get_settings();
		$key_version = max( 1, absint( $settings['key_version'] ) );
		$key         = $this->get_encryption_key_for_version( $key_version );
		if ( false === $key ) {
			return false;
		}

		$cipher    = 'aes-256-gcm';
		$iv_length = openssl_cipher_iv_length( $cipher );
		if ( false === $iv_length || $iv_length <= 0 ) {
			return false;
		}

		try {
			$iv = random_bytes( $iv_length );
		} catch ( Exception $exception ) {
			return false;
		}

		$tag        = '';
		$ciphertext = openssl_encrypt( $plaintext, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag );

		if ( false === $ciphertext || empty( $tag ) ) {
			return false;
		}

		return array(
			'ciphertext' => $ciphertext,
			'iv'         => base64_encode( $iv ),
			'tag'        => base64_encode( $tag ),
			'key_version' => $key_version,
		);
	}

	/**
	 * @param string               $ciphertext Binary encrypted data.
	 * @param array<string, mixed> $meta Metadata containing IV and tag.
	 * @return string|false
	 */
	private function decrypt_contents( $ciphertext, $meta ) {
		if ( ! $this->supports_encryption() ) {
			return false;
		}

		$key_version = isset( $meta['key_version'] ) ? absint( $meta['key_version'] ) : 1;
		if ( $key_version < 1 ) {
			return false;
		}

		$key = $this->get_encryption_key_for_version( $key_version );
		if ( false === $key ) {
			return false;
		}

		$iv  = isset( $meta['iv'] ) ? base64_decode( (string) $meta['iv'], true ) : false;
		$tag = isset( $meta['tag'] ) ? base64_decode( (string) $meta['tag'], true ) : false;
		if ( false === $iv || false === $tag ) {
			return false;
		}

		return openssl_decrypt( $ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
	}

	/**
	 * @param string $upload_dir
	 * @return array<string, string>
	 */
	private function generate_storage_filenames( $upload_dir ) {
		$base = trailingslashit( $upload_dir );

		for ( $attempt = 0; $attempt < 5; $attempt++ ) {
			$file_id = wp_generate_uuid4();
			$blob    = $file_id . self::ENCRYPTED_FILE_EXT;
			$meta    = $file_id . self::METADATA_FILE_EXT;

			if ( ! file_exists( $base . $blob ) && ! file_exists( $base . $meta ) ) {
				return array(
					'blob' => $blob,
					'meta' => $meta,
				);
			}
		}

		return array();
	}

	/**
	 * @param string $file_name
	 * @return bool
	 */
	private function is_encrypted_blob_file( $file_name ) {
		$ext_length = strlen( self::ENCRYPTED_FILE_EXT );

		return strlen( $file_name ) > $ext_length && substr( $file_name, -$ext_length ) === self::ENCRYPTED_FILE_EXT;
	}

	/**
	 * @param string $file_name
	 * @return bool
	 */
	private function is_metadata_file( $file_name ) {
		$ext_length = strlen( self::METADATA_FILE_EXT );

		return strlen( $file_name ) > $ext_length && substr( $file_name, -$ext_length ) === self::METADATA_FILE_EXT;
	}

	/**
	 * @param string $blob_file_name
	 * @return string
	 */
	private function get_meta_file_name_for_blob( $blob_file_name ) {
		$base_name = substr( $blob_file_name, 0, -strlen( self::ENCRYPTED_FILE_EXT ) );

		return $base_name . self::METADATA_FILE_EXT;
	}

	/**
	 * @param string $meta_path
	 * @return array<string, mixed>
	 */
	private function read_media_metadata( $meta_path ) {
		if ( ! is_file( $meta_path ) ) {
			return array();
		}

		$raw = file_get_contents( $meta_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $raw ) {
			return array();
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return array();
		}

		return $data;
	}

	/**
	 * @param string $size_value INI shorthand value.
	 * @return int Size in bytes; 0 means "no limit/unknown".
	 */
	private function ini_size_to_bytes( $size_value ) {
		$value = trim( (string) $size_value );
		if ( '' === $value || '-1' === $value ) {
			return 0;
		}

		$last_char = strtolower( substr( $value, -1 ) );
		$number    = (float) $value;

		if ( ! is_numeric( $last_char ) ) {
			$number = (float) substr( $value, 0, -1 );
		}

		switch ( $last_char ) {
			case 'g':
				$number *= 1024;
				// No break.
			case 'm':
				$number *= 1024;
				// No break.
			case 'k':
				$number *= 1024;
		}

		return (int) max( 0, $number );
	}

	/**
	 * @param int $bytes
	 * @return int
	 */
	private function bytes_to_mb_floor( $bytes ) {
		if ( $bytes <= 0 ) {
			return 0;
		}

		return max( 1, (int) floor( $bytes / MB_IN_BYTES ) );
	}

	/**
	 * @param int $configured_max_mb
	 * @return array<string, int|bool>
	 */
	private function get_upload_limit_context( $configured_max_mb ) {
		$configured_max_mb = max( 1, absint( $configured_max_mb ) );

		$upload_max_bytes  = $this->ini_size_to_bytes( (string) ini_get( 'upload_max_filesize' ) );
		$post_max_bytes    = $this->ini_size_to_bytes( (string) ini_get( 'post_max_size' ) );
		$memory_limit_bytes = $this->ini_size_to_bytes( (string) ini_get( 'memory_limit' ) );
		$memory_safe_bytes = $memory_limit_bytes > 0 ? (int) floor( $memory_limit_bytes / 4 ) : 0;

		$runtime_limits = array();
		if ( $upload_max_bytes > 0 ) {
			$runtime_limits[] = $upload_max_bytes;
		}
		if ( $post_max_bytes > 0 ) {
			$runtime_limits[] = $post_max_bytes;
		}
		if ( $memory_safe_bytes > 0 ) {
			$runtime_limits[] = $memory_safe_bytes;
		}

		if ( empty( $runtime_limits ) ) {
			$runtime_cap_bytes = self::DEFAULT_FALLBACK_SAFE_MAX_MB * MB_IN_BYTES;
		} else {
			$runtime_cap_bytes = (int) min( $runtime_limits );
		}

		$runtime_cap_mb = $this->bytes_to_mb_floor( $runtime_cap_bytes );
		if ( $runtime_cap_mb < 1 ) {
			$runtime_cap_mb    = 1;
			$runtime_cap_bytes = MB_IN_BYTES;
		}

		$effective_max_mb    = min( $configured_max_mb, $runtime_cap_mb );
		$effective_max_bytes = $effective_max_mb * MB_IN_BYTES;

		return array(
			'configured_mb'    => $configured_max_mb,
			'effective_mb'     => $effective_max_mb,
			'effective_bytes'  => $effective_max_bytes,
			'runtime_cap_mb'   => $runtime_cap_mb,
			'runtime_cap_bytes'=> $runtime_cap_bytes,
			'upload_max_mb'    => $this->bytes_to_mb_floor( $upload_max_bytes ),
			'post_max_mb'      => $this->bytes_to_mb_floor( $post_max_bytes ),
			'memory_limit_mb'  => $this->bytes_to_mb_floor( $memory_limit_bytes ),
			'memory_safe_mb'   => $this->bytes_to_mb_floor( $memory_safe_bytes ),
			'is_clamped'       => $configured_max_mb > $runtime_cap_mb,
		);
	}

	/**
	 * @return string
	 */
	private static function get_upload_dir() {
		$upload_dir = wp_upload_dir();

		return trailingslashit( $upload_dir['basedir'] ) . 'wedding-gallery';
	}

	/**
	 * @return void
	 */
	private static function create_upload_dir() {
		$dir = self::get_upload_dir();

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$index_file = trailingslashit( $dir ) . 'index.php';
		if ( ! file_exists( $index_file ) ) {
			file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
		}

		// Block direct HTTP access to uploaded media files; downloads go through admin-post handler.
		$htaccess_file = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			$htaccess_rules = "Options -Indexes\n";
			$htaccess_rules .= "<IfModule mod_authz_core.c>\n";
			$htaccess_rules .= "Require all denied\n";
			$htaccess_rules .= "</IfModule>\n";
			$htaccess_rules .= "<IfModule !mod_authz_core.c>\n";
			$htaccess_rules .= "Deny from all\n";
			$htaccess_rules .= "</IfModule>\n";
			file_put_contents( $htaccess_file, $htaccess_rules );
		}

		$web_config_file = trailingslashit( $dir ) . 'web.config';
		if ( ! file_exists( $web_config_file ) ) {
			$web_config = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
			$web_config .= "<configuration>\n";
			$web_config .= "<system.webServer>\n";
			$web_config .= "<security>\n";
			$web_config .= "<authorization>\n";
			$web_config .= "<add accessType=\"Deny\" users=\"*\" />\n";
			$web_config .= "</authorization>\n";
			$web_config .= "</security>\n";
			$web_config .= "</system.webServer>\n";
			$web_config .= "</configuration>\n";
			file_put_contents( $web_config_file, $web_config );
		}
	}

	/**
	 * @return void
	 */
	public function register_admin_menu() {
		add_menu_page(
			__( 'Wedding Gallery', 'wedding-gallery' ),
			__( 'Wedding Gallery', 'wedding-gallery' ),
			'manage_options',
			'wedding-gallery',
			array( $this, 'render_admin_page' ),
			'dashicons-format-gallery'
		);
	}

	/**
	 * @return string
	 */
	private function get_current_url() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';

		return home_url( $uri );
	}

	/**
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_upload_shortcode( $atts ) {
		self::create_upload_dir();

		$settings      = $this->get_settings();
		$current_token = isset( $_GET[ self::TOKEN_QUERY_ARG ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::TOKEN_QUERY_ARG ] ) ) : '';
		$token         = (string) $settings['access_token'];

		$is_authorized = ! empty( $current_token ) && ! empty( $token ) && hash_equals( $token, $current_token );

		// Let admins preview the page UI without a token while editing.
		if ( ! $is_authorized && current_user_can( 'manage_options' ) ) {
			$is_authorized = true;
		}

		$status  = isset( $_GET['wg_status'] ) ? sanitize_key( wp_unslash( $_GET['wg_status'] ) ) : '';
		$message = isset( $_GET['wg_message'] ) ? sanitize_text_field( wp_unslash( $_GET['wg_message'] ) ) : '';

		$upload_limits = $this->get_upload_limit_context( absint( $settings['max_upload_mb'] ) );
		$max_upload_mb = (int) $upload_limits['effective_mb'];
		$action_url    = admin_url( 'admin-post.php' );
		$redirect_url  = $this->get_current_url();
		$allowed_text  = '.jpg, .jpeg, .png, .webp, .mp4';

		ob_start();
		require WG_PLUGIN_DIR . 'templates/frontend-upload.php';

		return (string) ob_get_clean();
	}

	/**
	 * @return void
	 */
	public function handle_upload() {
		self::create_upload_dir();

		$redirect_url = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : home_url( '/' );
		$settings     = $this->get_settings();

		$nonce_ok = isset( $_POST['wg_upload_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wg_upload_nonce'] ) ), 'wg_upload_action' );
		if ( ! $nonce_ok ) {
			$this->redirect_with_message( $redirect_url, 'error', __( 'Security check failed.', 'wedding-gallery' ) );
		}

		$posted_token = isset( $_POST[ self::TOKEN_QUERY_ARG ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::TOKEN_QUERY_ARG ] ) ) : '';
		$token        = (string) $settings['access_token'];
		if ( empty( $posted_token ) || empty( $token ) || ! hash_equals( $token, $posted_token ) ) {
			$this->redirect_with_message( $redirect_url, 'error', __( 'Invalid upload token.', 'wedding-gallery' ) );
		}

		if ( empty( $_FILES['wg_files'] ) || ! is_array( $_FILES['wg_files'] ) ) {
			$this->redirect_with_message( $redirect_url, 'error', __( 'Please select at least one file.', 'wedding-gallery' ) );
		}

		$files = $_FILES['wg_files']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( empty( $files['name'] ) || ! is_array( $files['name'] ) ) {
			$this->redirect_with_message( $redirect_url, 'error', __( 'Please select at least one file.', 'wedding-gallery' ) );
		}

		$upload_limits = $this->get_upload_limit_context( absint( $settings['max_upload_mb'] ) );
		$max_upload_mb = (int) $upload_limits['effective_mb'];
		$max_bytes     = (int) $upload_limits['effective_bytes'];
		$upload_dir    = self::get_upload_dir();
		$allowed_exts  = $this->get_allowed_extensions();
		$allowed_mimes = $this->get_allowed_mimes();

		if ( ! $this->supports_encryption() ) {
			$this->redirect_with_message( $redirect_url, 'error', __( 'Upload encryption is unavailable on this server.', 'wedding-gallery' ) );
		}

		$successful = 0;
		$errors     = array();

		$total_files = count( $files['name'] );
		for ( $i = 0; $i < $total_files; $i++ ) {
			$name     = isset( $files['name'][ $i ] ) ? wp_unslash( $files['name'][ $i ] ) : '';
			$tmp_name = isset( $files['tmp_name'][ $i ] ) ? $files['tmp_name'][ $i ] : '';
			$size     = isset( $files['size'][ $i ] ) ? absint( $files['size'][ $i ] ) : 0;
			$error    = isset( $files['error'][ $i ] ) ? absint( $files['error'][ $i ] ) : UPLOAD_ERR_NO_FILE;

			if ( UPLOAD_ERR_OK !== $error ) {
				$errors[] = __( 'One file failed to upload.', 'wedding-gallery' );
				continue;
			}

			if ( empty( $tmp_name ) || ! is_uploaded_file( $tmp_name ) ) {
				$errors[] = __( 'Invalid upload source detected.', 'wedding-gallery' );
				continue;
			}

			$sanitized_name = sanitize_file_name( $name );
			if ( empty( $sanitized_name ) ) {
				$errors[] = __( 'Invalid filename detected.', 'wedding-gallery' );
				continue;
			}

			if ( $size > $max_bytes ) {
				$errors[] = sprintf(
					/* translators: 1: filename, 2: max size in MB */
					__( '%1$s exceeds maximum upload size of %2$d MB.', 'wedding-gallery' ),
					$sanitized_name,
					$max_upload_mb
				);
				continue;
			}

			$ext = strtolower( pathinfo( $sanitized_name, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, $allowed_exts, true ) ) {
				$errors[] = sprintf(
					/* translators: %s: filename */
					__( '%s has an unsupported file extension.', 'wedding-gallery' ),
					$sanitized_name
				);
				continue;
			}

			$type_data = wp_check_filetype_and_ext( $tmp_name, $sanitized_name, $allowed_mimes );
			if ( empty( $type_data['ext'] ) || empty( $type_data['type'] ) ) {
				$errors[] = sprintf(
					/* translators: %s: filename */
					__( '%s failed file type validation.', 'wedding-gallery' ),
					$sanitized_name
				);
				continue;
			}

			if ( 0 === strpos( $type_data['type'], 'image/' ) ) {
				$image_info = @getimagesize( $tmp_name ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				if ( false === $image_info ) {
					$errors[] = sprintf(
						/* translators: %s: filename */
						__( '%s is not a valid image file.', 'wedding-gallery' ),
						$sanitized_name
					);
					continue;
				}
			}

			$file_contents = file_get_contents( $tmp_name ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false === $file_contents ) {
				$errors[] = sprintf(
					/* translators: %s: filename */
					__( 'Could not read %s.', 'wedding-gallery' ),
					$sanitized_name
				);
				continue;
			}

			$encrypted_payload = $this->encrypt_contents( $file_contents );
			if ( false === $encrypted_payload ) {
				$errors[] = sprintf(
					/* translators: %s: filename */
					__( 'Could not securely store %s.', 'wedding-gallery' ),
					$sanitized_name
				);
				continue;
			}

			$storage_names = $this->generate_storage_filenames( $upload_dir );
			if ( empty( $storage_names['blob'] ) || empty( $storage_names['meta'] ) ) {
				$errors[] = __( 'Could not allocate storage for upload.', 'wedding-gallery' );
				continue;
			}

			$target_path = trailingslashit( $upload_dir ) . $storage_names['blob'];
			$meta_path   = trailingslashit( $upload_dir ) . $storage_names['meta'];

			$written_blob = file_put_contents( $target_path, $encrypted_payload['ciphertext'], LOCK_EX );
			if ( false === $written_blob ) {
				$errors[] = sprintf(
					/* translators: %s: filename */
					__( 'Could not save %s.', 'wedding-gallery' ),
					$sanitized_name
				);
				continue;
			}

			$metadata = array(
				'version'       => 1,
				'original_name' => $sanitized_name,
				'mime_type'     => $type_data['type'],
				'size'          => $size,
				'uploaded_at'   => time(),
				'key_version'   => $encrypted_payload['key_version'],
				'iv'            => $encrypted_payload['iv'],
				'tag'           => $encrypted_payload['tag'],
			);

			$metadata_json = wp_json_encode( $metadata );
			if ( false === $metadata_json ) {
				wp_delete_file( $target_path );
				$errors[] = sprintf(
					/* translators: %s: filename */
					__( 'Could not encode metadata for %s.', 'wedding-gallery' ),
					$sanitized_name
				);
				continue;
			}

			$written_meta = file_put_contents( $meta_path, $metadata_json, LOCK_EX );
			if ( false === $written_meta ) {
				wp_delete_file( $target_path );
				$errors[] = sprintf(
					/* translators: %s: filename */
					__( 'Could not save metadata for %s.', 'wedding-gallery' ),
					$sanitized_name
				);
				continue;
			}

			$successful++;
		}

		if ( $successful > 0 ) {
			$message = sprintf(
				/* translators: %d: uploaded files count */
				_n( '%d file uploaded successfully.', '%d files uploaded successfully.', $successful, 'wedding-gallery' ),
				$successful
			);
			if ( ! empty( $errors ) ) {
				$message .= ' ' . __( 'Some files were rejected.', 'wedding-gallery' );
			}
			$this->redirect_with_message( $redirect_url, 'success', $message );
		}

		$this->redirect_with_message( $redirect_url, 'error', __( 'No files were uploaded. Please check file type and size.', 'wedding-gallery' ) );
	}

	/**
	 * @param string $url
	 * @param string $status
	 * @param string $message
	 * @return void
	 */
	private function redirect_with_message( $url, $status, $message ) {
		$redirect = add_query_arg(
			array(
				'wg_status'  => $status,
				'wg_message' => $message,
			),
			$url
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * @return void
	 */
	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'wedding-gallery' ) );
		}

		check_admin_referer( 'wg_save_settings', 'wg_save_settings_nonce' );

		$settings = $this->get_settings();

		$upload_page_url = isset( $_POST['upload_page_url'] ) ? esc_url_raw( wp_unslash( $_POST['upload_page_url'] ) ) : '';
		$access_token    = isset( $_POST['access_token'] ) ? sanitize_text_field( wp_unslash( $_POST['access_token'] ) ) : '';
		$max_upload_mb   = isset( $_POST['max_upload_mb'] ) ? absint( $_POST['max_upload_mb'] ) : self::DEFAULT_MAX_SIZE_MB;
		$limit_context   = $this->get_upload_limit_context( $max_upload_mb );

		if ( isset( $_POST['rotate_token'] ) ) {
			$access_token = wp_generate_password( 32, false, false );
		}

		if ( empty( $access_token ) ) {
			$access_token = wp_generate_password( 32, false, false );
		}

		$settings['upload_page_url'] = $upload_page_url;
		$settings['access_token']    = $access_token;
		$settings['max_upload_mb']   = (int) $limit_context['effective_mb'];

		update_option( self::OPTION_KEY, $settings );

		$notice = 'saved';
		if ( (bool) $limit_context['is_clamped'] ) {
			$notice = 'saved_clamped';
		}

		$redirect = add_query_arg(
			array(
				'page'      => 'wedding-gallery',
				'wg_notice' => $notice,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * @return string
	 */
	private function get_protected_upload_url() {
		$settings = $this->get_settings();
		$page_url = (string) $settings['upload_page_url'];
		$token    = (string) $settings['access_token'];

		if ( empty( $page_url ) || empty( $token ) ) {
			return '';
		}

		return add_query_arg( self::TOKEN_QUERY_ARG, $token, $page_url );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function get_uploaded_files() {
		$upload_dir = self::get_upload_dir();

		if ( ! is_dir( $upload_dir ) ) {
			return array();
		}

		$files = array();
		$items = scandir( $upload_dir );

		if ( false === $items ) {
			return array();
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item || 'index.php' === $item || '.htaccess' === $item || 'web.config' === $item ) {
				continue;
			}

			$path = trailingslashit( $upload_dir ) . $item;
			if ( ! is_file( $path ) ) {
				continue;
			}

			if ( $this->is_metadata_file( $item ) ) {
				continue;
			}

			if ( $this->is_encrypted_blob_file( $item ) ) {
				$meta_path = trailingslashit( $upload_dir ) . $this->get_meta_file_name_for_blob( $item );
				$meta      = $this->read_media_metadata( $meta_path );

				if ( empty( $meta ) ) {
					continue;
				}

				$files[] = array(
					'name'        => isset( $meta['original_name'] ) ? sanitize_file_name( (string) $meta['original_name'] ) : $item,
					'stored_file' => $item,
					'size'        => isset( $meta['size'] ) ? absint( $meta['size'] ) : 0,
					'mime_type'   => isset( $meta['mime_type'] ) ? sanitize_text_field( (string) $meta['mime_type'] ) : '',
					'modified'    => isset( $meta['uploaded_at'] ) ? absint( $meta['uploaded_at'] ) : filemtime( $path ),
				);

				continue;
			}

			// Backward compatibility for files uploaded before encrypted storage.
			$type_data = wp_check_filetype( $item, $this->get_allowed_mimes() );
			$files[]   = array(
				'name'        => $item,
				'stored_file' => $item,
				'size'        => filesize( $path ),
				'mime_type'   => isset( $type_data['type'] ) ? $type_data['type'] : '',
				'modified'    => filemtime( $path ),
			);
		}

		usort(
			$files,
			function ( $a, $b ) {
				return (int) $b['modified'] <=> (int) $a['modified'];
			}
		);

		return $files;
	}

	/**
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'wedding-gallery' ) );
		}

		self::create_upload_dir();

		$settings             = $this->get_settings();
		$protected_upload_url = $this->get_protected_upload_url();
		$uploads              = $this->get_uploaded_files();
		$allowed_text         = '.jpg, .jpeg, .png, .webp, .mp4';
		$upload_limits        = $this->get_upload_limit_context( absint( $settings['max_upload_mb'] ) );
		$max_upload_mb        = (int) $upload_limits['configured_mb'];
		$effective_max_upload_mb = (int) $upload_limits['effective_mb'];
		$notice               = isset( $_GET['wg_notice'] ) ? sanitize_key( wp_unslash( $_GET['wg_notice'] ) ) : '';

		ob_start();
		require WG_PLUGIN_DIR . 'templates/admin-page.php';
		echo ob_get_clean();
	}

	/**
	 * @return void
	 */
	public function handle_download() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to download files.', 'wedding-gallery' ) );
		}

		$file_name = isset( $_GET['file'] ) ? sanitize_file_name( wp_unslash( $_GET['file'] ) ) : '';
		if ( empty( $file_name ) ) {
			wp_die( esc_html__( 'Missing file.', 'wedding-gallery' ) );
		}

		$nonce_ok = isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wg_download_file_' . $file_name );
		if ( ! $nonce_ok ) {
			wp_die( esc_html__( 'Invalid security token.', 'wedding-gallery' ) );
		}

		$upload_dir = realpath( self::get_upload_dir() );
		$file_path  = realpath( trailingslashit( self::get_upload_dir() ) . $file_name );

		if ( false === $upload_dir || false === $file_path ) {
			wp_die( esc_html__( 'File does not exist.', 'wedding-gallery' ) );
		}

		if ( 0 !== strpos( $file_path, $upload_dir ) || ! is_file( $file_path ) ) {
			wp_die( esc_html__( 'Invalid file path.', 'wedding-gallery' ) );
		}

		if ( $this->is_encrypted_blob_file( $file_name ) ) {
			$meta_file_name = $this->get_meta_file_name_for_blob( $file_name );
			$meta_path      = realpath( trailingslashit( self::get_upload_dir() ) . $meta_file_name );

			if ( false === $meta_path || 0 !== strpos( $meta_path, $upload_dir ) || ! is_file( $meta_path ) ) {
				wp_die( esc_html__( 'Missing file metadata.', 'wedding-gallery' ) );
			}

			$meta = $this->read_media_metadata( $meta_path );
			if ( empty( $meta ) ) {
				wp_die( esc_html__( 'Invalid file metadata.', 'wedding-gallery' ) );
			}

			$key_version = isset( $meta['key_version'] ) ? absint( $meta['key_version'] ) : 1;
			if ( false === $this->get_encryption_key_for_version( $key_version ) ) {
				wp_die( esc_html__( 'Could not decrypt file: unsupported encryption key version.', 'wedding-gallery' ) );
			}

			$ciphertext = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false === $ciphertext ) {
				wp_die( esc_html__( 'Could not read file.', 'wedding-gallery' ) );
			}

			$plaintext = $this->decrypt_contents( $ciphertext, $meta );
			if ( false === $plaintext ) {
				wp_die( esc_html__( 'Could not decrypt file. Metadata may be invalid or the key may have changed.', 'wedding-gallery' ) );
			}

			$download_name = isset( $meta['original_name'] ) ? sanitize_file_name( (string) $meta['original_name'] ) : 'wedding-upload.bin';
			if ( empty( $download_name ) ) {
				$download_name = 'wedding-upload.bin';
			}

			$allowed_types = array_values( $this->get_allowed_mimes() );
			$type          = isset( $meta['mime_type'] ) ? sanitize_text_field( (string) $meta['mime_type'] ) : '';
			if ( ! in_array( $type, $allowed_types, true ) ) {
				$type = 'application/octet-stream';
			}

			nocache_headers();
			header( 'Content-Description: File Transfer' );
			header( 'Content-Type: ' . $type );
			header( 'Content-Disposition: attachment; filename="' . basename( $download_name ) . '"' );
			header( 'Content-Length: ' . strlen( $plaintext ) );

			echo $plaintext; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		}

		$mime_type = wp_check_filetype( $file_name, $this->get_allowed_mimes() );
		$type      = ! empty( $mime_type['type'] ) ? $mime_type['type'] : 'application/octet-stream';

		nocache_headers();
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: ' . $type );
		header( 'Content-Disposition: attachment; filename="' . basename( $file_name ) . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );

		readfile( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
		exit;
	}
}
