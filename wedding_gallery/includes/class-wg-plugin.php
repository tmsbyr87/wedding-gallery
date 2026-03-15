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
	const ACCESS_TOKEN_LENGTH = 48;
	const QR_CODE_DEFAULT_SIZE = 360;
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
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		add_shortcode( self::SHORTCODE_TAG, array( $this, 'render_upload_shortcode' ) );

		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_post_wg_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_wg_upload', array( $this, 'handle_upload' ) );
		add_action( 'admin_post_nopriv_wg_upload', array( $this, 'handle_upload' ) );
		add_action( 'admin_post_wg_download_upload', array( $this, 'handle_download' ) );
	}

	/**
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'wedding-gallery',
			false,
			dirname( plugin_basename( WG_PLUGIN_FILE ) ) . '/languages'
		);
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
			'cleanup_on_uninstall' => 0,
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
		$settings['cleanup_on_uninstall'] = ! empty( $settings['cleanup_on_uninstall'] ) ? 1 : 0;

		if ( empty( $settings['access_token'] ) ) {
			$settings['access_token'] = self::generate_access_token();
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
	 * @return string
	 */
	private static function generate_access_token() {
		return wp_generate_password( self::ACCESS_TOKEN_LENGTH, false, false );
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
			'mov'          => 'video/quicktime',
		);
	}

	/**
	 * @return string[]
	 */
	private function get_allowed_extensions() {
		return array( 'jpg', 'jpeg', 'jpe', 'png', 'webp', 'mp4', 'mov' );
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
	 * @param string               $blob_file_name
	 * @param array<string, mixed> $meta
	 * @return string
	 */
	private function build_metadata_integrity_payload( $blob_file_name, $meta ) {
		$payload = array(
			'blob_file'           => (string) $blob_file_name,
			'version'             => isset( $meta['version'] ) ? (string) absint( $meta['version'] ) : '',
			'uploaded_at'         => isset( $meta['uploaded_at'] ) ? (string) absint( $meta['uploaded_at'] ) : '',
			'key_version'         => isset( $meta['key_version'] ) ? (string) absint( $meta['key_version'] ) : '',
			'iv'                  => isset( $meta['iv'] ) ? (string) $meta['iv'] : '',
			'tag'                 => isset( $meta['tag'] ) ? (string) $meta['tag'] : '',
			'private_key_version' => isset( $meta['private_key_version'] ) ? (string) absint( $meta['private_key_version'] ) : '',
			'private_iv'          => isset( $meta['private_iv'] ) ? (string) $meta['private_iv'] : '',
			'private_tag'         => isset( $meta['private_tag'] ) ? (string) $meta['private_tag'] : '',
			'private_ciphertext'  => isset( $meta['private_ciphertext'] ) ? (string) $meta['private_ciphertext'] : '',
			'blob_sha256'         => isset( $meta['blob_sha256'] ) ? (string) $meta['blob_sha256'] : '',
		);

		$encoded = wp_json_encode( $payload );

		return false !== $encoded ? $encoded : '';
	}

	/**
	 * @param int $key_version
	 * @return string|false
	 */
	private function get_metadata_integrity_key_for_version( $key_version ) {
		$key = $this->get_encryption_key_for_version( $key_version );
		if ( false === $key ) {
			return false;
		}

		return hash_hmac( 'sha256', 'wedding-gallery-metadata-integrity-v1', $key, true );
	}

	/**
	 * @param string               $blob_file_name
	 * @param array<string, mixed> $meta
	 * @return string|false
	 */
	private function compute_metadata_integrity_mac( $blob_file_name, $meta ) {
		$key_version = isset( $meta['key_version'] ) ? absint( $meta['key_version'] ) : 0;
		if ( $key_version < 1 ) {
			return false;
		}

		$integrity_key = $this->get_metadata_integrity_key_for_version( $key_version );
		if ( false === $integrity_key ) {
			return false;
		}

		$payload = $this->build_metadata_integrity_payload( $blob_file_name, $meta );
		if ( '' === $payload ) {
			return false;
		}

		return hash_hmac( 'sha256', $payload, $integrity_key );
	}

	/**
	 * @param string               $blob_file_name
	 * @param string               $ciphertext
	 * @param array<string, mixed> $meta
	 * @return array<string, bool|string>
	 */
	private function verify_metadata_integrity( $blob_file_name, $ciphertext, $meta ) {
		$version = isset( $meta['version'] ) ? absint( $meta['version'] ) : 1;
		if ( $version < 2 ) {
			return array(
				'ok'     => true,
				'legacy' => true,
				'error'  => '',
			);
		}

		$required_fields = array(
			'integrity_mac',
			'blob_sha256',
			'key_version',
			'iv',
			'tag',
			'private_key_version',
			'private_iv',
			'private_tag',
			'private_ciphertext',
		);
		foreach ( $required_fields as $field ) {
			if ( empty( $meta[ $field ] ) ) {
				return array(
					'ok'     => false,
					'legacy' => false,
					'error'  => 'invalid_metadata',
				);
			}
		}

		$expected_mac = $this->compute_metadata_integrity_mac( $blob_file_name, $meta );
		if ( false === $expected_mac ) {
			return array(
				'ok'     => false,
				'legacy' => false,
				'error'  => 'unsupported_key_version',
			);
		}

		$stored_mac = (string) $meta['integrity_mac'];
		if ( ! hash_equals( $expected_mac, $stored_mac ) ) {
			return array(
				'ok'     => false,
				'legacy' => false,
				'error'  => 'metadata_tampered',
			);
		}

		$blob_hash = hash( 'sha256', $ciphertext );
		if ( ! hash_equals( (string) $meta['blob_sha256'], $blob_hash ) ) {
			return array(
				'ok'     => false,
				'legacy' => false,
				'error'  => 'metadata_tampered',
			);
		}

		return array(
			'ok'     => true,
			'legacy' => false,
			'error'  => '',
		);
	}

	/**
	 * @param array<string, mixed> $meta
	 * @return array<string, mixed>|false
	 */
	private function decode_private_metadata_fields( $meta ) {
		$version = isset( $meta['version'] ) ? absint( $meta['version'] ) : 1;
		if ( $version < 2 ) {
			return array(
				'original_name' => isset( $meta['original_name'] ) ? sanitize_file_name( (string) $meta['original_name'] ) : '',
				'mime_type'     => isset( $meta['mime_type'] ) ? sanitize_text_field( (string) $meta['mime_type'] ) : '',
				'size'          => isset( $meta['size'] ) ? absint( $meta['size'] ) : 0,
			);
		}

		$private_key_version = isset( $meta['private_key_version'] ) ? absint( $meta['private_key_version'] ) : 0;
		if ( $private_key_version < 1 ) {
			return false;
		}

		$private_iv         = isset( $meta['private_iv'] ) ? (string) $meta['private_iv'] : '';
		$private_tag        = isset( $meta['private_tag'] ) ? (string) $meta['private_tag'] : '';
		$private_ciphertext = isset( $meta['private_ciphertext'] ) ? base64_decode( (string) $meta['private_ciphertext'], true ) : false;
		if ( '' === $private_iv || '' === $private_tag || false === $private_ciphertext ) {
			return false;
		}

		if ( false === $this->get_encryption_key_for_version( $private_key_version ) ) {
			return false;
		}

		$private_meta = array(
			'key_version' => $private_key_version,
			'iv'          => $private_iv,
			'tag'         => $private_tag,
		);
		$private_plaintext = $this->decrypt_contents( $private_ciphertext, $private_meta );
		if ( false === $private_plaintext ) {
			return false;
		}

		$data = json_decode( $private_plaintext, true );
		if ( ! is_array( $data ) ) {
			return false;
		}

		return array(
			'original_name' => isset( $data['original_name'] ) ? sanitize_file_name( (string) $data['original_name'] ) : '',
			'mime_type'     => isset( $data['mime_type'] ) ? sanitize_text_field( (string) $data['mime_type'] ) : '',
			'size'          => isset( $data['size'] ) ? absint( $data['size'] ) : 0,
		);
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
		$allowed_text  = '.jpg, .jpeg, .png, .webp, .mp4, .mov';

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
		$error_codes = array();
		$rejected_file_labels = array();

		$total_files = count( $files['name'] );
		for ( $i = 0; $i < $total_files; $i++ ) {
			$name     = isset( $files['name'][ $i ] ) ? wp_unslash( $files['name'][ $i ] ) : '';
			$tmp_name = isset( $files['tmp_name'][ $i ] ) ? $files['tmp_name'][ $i ] : '';
			$size     = isset( $files['size'][ $i ] ) ? absint( $files['size'][ $i ] ) : 0;
			$error    = isset( $files['error'][ $i ] ) ? absint( $files['error'][ $i ] ) : UPLOAD_ERR_NO_FILE;

			if ( UPLOAD_ERR_OK !== $error ) {
				$this->collect_rejected_file_label( $rejected_file_labels, $name );
				if ( in_array( $error, array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) ) {
					$error_codes[] = 'too_large';
				} else {
					$error_codes[] = 'upload_failed';
				}
				continue;
			}

			if ( empty( $tmp_name ) || ! is_uploaded_file( $tmp_name ) ) {
				$this->collect_rejected_file_label( $rejected_file_labels, $name );
				$error_codes[] = 'upload_failed';
				continue;
			}

			$sanitized_name = sanitize_file_name( $name );
			if ( empty( $sanitized_name ) ) {
				$error_codes[] = 'upload_failed';
				continue;
			}

			if ( $size > $max_bytes ) {
				$this->collect_rejected_file_label( $rejected_file_labels, $sanitized_name );
				$error_codes[] = 'too_large';
				continue;
			}

			$ext = strtolower( pathinfo( $sanitized_name, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, $allowed_exts, true ) ) {
				$this->collect_rejected_file_label( $rejected_file_labels, $sanitized_name );
				$error_codes[] = 'unsupported_type';
				continue;
			}

			$type_data = wp_check_filetype_and_ext( $tmp_name, $sanitized_name, $allowed_mimes );
			if ( empty( $type_data['ext'] ) || empty( $type_data['type'] ) ) {
				$this->collect_rejected_file_label( $rejected_file_labels, $sanitized_name );
				$error_codes[] = 'unsupported_type';
				continue;
			}

			if ( 0 === strpos( $type_data['type'], 'image/' ) ) {
				$image_info = @getimagesize( $tmp_name ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				if ( false === $image_info ) {
					$this->collect_rejected_file_label( $rejected_file_labels, $sanitized_name );
					$error_codes[] = 'unsupported_type';
					continue;
				}
			}

			$file_contents = file_get_contents( $tmp_name ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false === $file_contents ) {
				$this->collect_rejected_file_label( $rejected_file_labels, $sanitized_name );
				$error_codes[] = 'processing_failed';
				continue;
			}

			$encrypted_payload = $this->encrypt_contents( $file_contents );
			if ( false === $encrypted_payload ) {
				$this->collect_rejected_file_label( $rejected_file_labels, $sanitized_name );
				$error_codes[] = 'processing_failed';
				continue;
			}

			$storage_names = $this->generate_storage_filenames( $upload_dir );
			if ( empty( $storage_names['blob'] ) || empty( $storage_names['meta'] ) ) {
				$this->collect_rejected_file_label( $rejected_file_labels, $sanitized_name );
				$error_codes[] = 'processing_failed';
				continue;
			}

			$target_path = trailingslashit( $upload_dir ) . $storage_names['blob'];
			$meta_path   = trailingslashit( $upload_dir ) . $storage_names['meta'];

			$written_blob = file_put_contents( $target_path, $encrypted_payload['ciphertext'], LOCK_EX );
			if ( false === $written_blob ) {
				$this->collect_rejected_file_label( $rejected_file_labels, $sanitized_name );
				$error_codes[] = 'processing_failed';
				continue;
			}

			$private_metadata_json = wp_json_encode(
				array(
					'original_name' => $sanitized_name,
					'mime_type'     => $type_data['type'],
					'size'          => $size,
				)
			);
			if ( false === $private_metadata_json ) {
				wp_delete_file( $target_path );
				$this->collect_rejected_file_label( $rejected_file_labels, $sanitized_name );
				$error_codes[] = 'processing_failed';
				continue;
			}

			$private_metadata_payload = $this->encrypt_contents( $private_metadata_json );
			if ( false === $private_metadata_payload ) {
				wp_delete_file( $target_path );
				$this->collect_rejected_file_label( $rejected_file_labels, $sanitized_name );
				$error_codes[] = 'processing_failed';
				continue;
			}

			$metadata = array(
				'version'             => 2,
				'uploaded_at'         => time(),
				'key_version'         => $encrypted_payload['key_version'],
				'iv'                  => $encrypted_payload['iv'],
				'tag'                 => $encrypted_payload['tag'],
				'private_key_version' => $private_metadata_payload['key_version'],
				'private_iv'          => $private_metadata_payload['iv'],
				'private_tag'         => $private_metadata_payload['tag'],
				'private_ciphertext'  => base64_encode( $private_metadata_payload['ciphertext'] ),
				'blob_sha256'         => hash( 'sha256', $encrypted_payload['ciphertext'] ),
			);

			$metadata_mac = $this->compute_metadata_integrity_mac( $storage_names['blob'], $metadata );
			if ( false === $metadata_mac ) {
				wp_delete_file( $target_path );
				$this->collect_rejected_file_label( $rejected_file_labels, $sanitized_name );
				$error_codes[] = 'processing_failed';
				continue;
			}

			$metadata['integrity_mac'] = $metadata_mac;

			$metadata_json = wp_json_encode( $metadata );
			if ( false === $metadata_json ) {
				wp_delete_file( $target_path );
				$this->collect_rejected_file_label( $rejected_file_labels, $sanitized_name );
				$error_codes[] = 'processing_failed';
				continue;
			}

			$written_meta = file_put_contents( $meta_path, $metadata_json, LOCK_EX );
			if ( false === $written_meta ) {
				wp_delete_file( $target_path );
				$this->collect_rejected_file_label( $rejected_file_labels, $sanitized_name );
				$error_codes[] = 'processing_failed';
				continue;
			}

			$successful++;
		}

		if ( $successful > 0 ) {
			$message = sprintf(
				/* translators: %d: uploaded files count */
				_n( 'Thank you. %d file was uploaded successfully.', 'Thank you. %d files were uploaded successfully.', $successful, 'wedding-gallery' ),
				$successful
			);
			if ( ! empty( $error_codes ) ) {
				$error_summary = $this->summarize_upload_errors( $error_codes, $rejected_file_labels, $max_upload_mb );
				if ( '' !== $error_summary ) {
					$message .= ' ' . $error_summary;
				} else {
					$message .= ' ' . __( 'Some files could not be uploaded.', 'wedding-gallery' );
				}
			}
			$this->redirect_with_message( $redirect_url, 'success', $message );
		}

		$error_summary = $this->summarize_upload_errors( $error_codes, $rejected_file_labels, $max_upload_mb );
		if ( '' !== $error_summary ) {
			$this->redirect_with_message(
				$redirect_url,
				'error',
				sprintf(
					/* translators: %s: upload issue summary */
					__( 'No files were uploaded. %s', 'wedding-gallery' ),
					$error_summary
				)
			);
		}

		$this->redirect_with_message( $redirect_url, 'error', __( 'No files were uploaded. Please check file type and size limits and try again.', 'wedding-gallery' ) );
	}

	/**
	 * @param array<int, string> $error_codes
	 * @param array<int, string> $rejected_file_labels
	 * @param int                $max_upload_mb
	 * @return string
	 */
	private function summarize_upload_errors( $error_codes, $rejected_file_labels, $max_upload_mb ) {
		if ( empty( $error_codes ) ) {
			return '';
		}

		$messages = array();

		if ( in_array( 'too_large', $error_codes, true ) ) {
			$messages[] = sprintf(
				/* translators: %d: max file size in MB */
				__( 'Some files are larger than %d MB.', 'wedding-gallery' ),
				$max_upload_mb
			);
		}

		if ( in_array( 'unsupported_type', $error_codes, true ) ) {
			$messages[] = __( 'Some files are not supported. Please use JPG, PNG, WEBP, MP4, or MOV.', 'wedding-gallery' );
		}

		if ( in_array( 'upload_failed', $error_codes, true ) ) {
			$messages[] = __( 'Some files did not upload. Please try again.', 'wedding-gallery' );
		}

		if ( in_array( 'processing_failed', $error_codes, true ) ) {
			$messages[] = __( 'Some files could not be processed securely. Please try again.', 'wedding-gallery' );
		}

		$messages = array_slice( $messages, 0, 2 );
		$summary  = implode( ' ', $messages );

		$file_list = $this->summarize_rejected_file_labels( $rejected_file_labels );
		if ( '' !== $file_list ) {
			$summary .= ' ' . sprintf(
				/* translators: %s: short list of filenames */
				__( 'Please review: %s.', 'wedding-gallery' ),
				$file_list
			);
		}

		return trim( $summary );
	}

	/**
	 * @param array<int, string> $rejected_file_labels
	 * @param string             $candidate
	 * @return void
	 */
	private function collect_rejected_file_label( &$rejected_file_labels, $candidate ) {
		$label = $this->format_guest_filename_for_message( $candidate );
		if ( '' === $label || in_array( $label, $rejected_file_labels, true ) ) {
			return;
		}

		$rejected_file_labels[] = $label;
	}

	/**
	 * @param string $candidate
	 * @return string
	 */
	private function format_guest_filename_for_message( $candidate ) {
		$clean = sanitize_file_name( (string) $candidate );
		if ( '' === $clean ) {
			return '';
		}

		$max_length = 36;
		if ( strlen( $clean ) <= $max_length ) {
			return $clean;
		}

		$ext  = strtolower( pathinfo( $clean, PATHINFO_EXTENSION ) );
		$base = (string) pathinfo( $clean, PATHINFO_FILENAME );
		$base = substr( $base, 0, 28 );

		if ( '' !== $ext ) {
			return $base . '...' . '.' . $ext;
		}

		return substr( $clean, 0, $max_length - 3 ) . '...';
	}

	/**
	 * @param array<int, string> $rejected_file_labels
	 * @return string
	 */
	private function summarize_rejected_file_labels( $rejected_file_labels ) {
		if ( empty( $rejected_file_labels ) ) {
			return '';
		}

		$sample = array_slice( $rejected_file_labels, 0, 2 );
		foreach ( $sample as $index => $label ) {
			$sample[ $index ] = '"' . $label . '"';
		}

		return implode( ', ', $sample );
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
		$cleanup_on_uninstall = ! empty( $_POST['cleanup_on_uninstall'] ) ? 1 : 0;

		if ( isset( $_POST['rotate_token'] ) || isset( $_POST['regenerate_guest_link'] ) ) {
			$access_token = self::generate_access_token();
		}

		if ( empty( $access_token ) ) {
			$access_token = self::generate_access_token();
		}

		$settings['upload_page_url'] = $upload_page_url;
		$settings['access_token']    = $access_token;
		$settings['max_upload_mb']   = (int) $limit_context['effective_mb'];
		$settings['cleanup_on_uninstall'] = $cleanup_on_uninstall;

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
	 * @return int
	 */
	private function get_legacy_plaintext_file_count() {
		$upload_dir = self::get_upload_dir();
		if ( ! is_dir( $upload_dir ) ) {
			return 0;
		}

		$items = scandir( $upload_dir );
		if ( false === $items ) {
			return 0;
		}

		$count = 0;
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item || 'index.php' === $item || '.htaccess' === $item || 'web.config' === $item ) {
				continue;
			}

			$path = trailingslashit( $upload_dir ) . $item;
			if ( ! is_file( $path ) ) {
				continue;
			}

			if ( $this->is_encrypted_blob_file( $item ) || $this->is_metadata_file( $item ) ) {
				continue;
			}

			$count++;
		}

		return $count;
	}

	/**
	 * @return array<string, string|int|bool>
	 */
	private function get_encryption_key_status() {
		$settings    = $this->get_settings();
		$key_version = max( 1, absint( $settings['key_version'] ) );
		$key         = $this->get_encryption_key_for_version( $key_version );

		if ( false === $key ) {
			return array(
				'healthy'     => false,
				'key_version' => $key_version,
				'fingerprint' => '',
			);
		}

		return array(
			'healthy'     => true,
			'key_version' => $key_version,
			'fingerprint' => substr( hash( 'sha256', $key ), 0, 12 ),
		);
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
				$meta_version = isset( $meta['version'] ) ? absint( $meta['version'] ) : 1;
				$status    = 'ok';
				$message   = __( 'Encrypted file is healthy and downloadable.', 'wedding-gallery' );
				$can_download = true;
				$private_fields = array(
					'original_name' => '',
					'mime_type'     => '',
					'size'          => 0,
				);
				$ciphertext = false;

				if ( ! is_file( $meta_path ) ) {
					$status       = 'missing_metadata';
					$message      = __( 'Metadata file is missing. This upload cannot be decrypted.', 'wedding-gallery' );
					$can_download = false;
				} elseif ( empty( $meta ) ) {
					$status       = 'invalid_metadata';
					$message      = __( 'Metadata looks damaged or unreadable. Download is currently unavailable.', 'wedding-gallery' );
					$can_download = false;
				} else {
					$key_version = isset( $meta['key_version'] ) ? absint( $meta['key_version'] ) : 1;
					if ( false === $this->get_encryption_key_for_version( $key_version ) ) {
						$status       = 'unsupported_key_version';
						$message      = __( 'Encrypted with a key version not available on this site.', 'wedding-gallery' );
						$can_download = false;
					} else {
						$ciphertext = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
						if ( false === $ciphertext ) {
							$status       = 'decrypt_failed';
							$message      = __( 'Could not read encrypted file data.', 'wedding-gallery' );
							$can_download = false;
						} else {
							$integrity = $this->verify_metadata_integrity( $item, $ciphertext, $meta );
							if ( empty( $integrity['ok'] ) ) {
								if ( 'unsupported_key_version' === $integrity['error'] ) {
									$status  = 'unsupported_key_version';
									$message = __( 'Encrypted with a key version not available on this site.', 'wedding-gallery' );
								} elseif ( 'metadata_tampered' === $integrity['error'] ) {
									$status  = 'metadata_tampered';
									$message = __( 'Metadata integrity check failed. File metadata or blob appears modified.', 'wedding-gallery' );
								} else {
									$status  = 'invalid_metadata';
									$message = __( 'Metadata looks damaged or unreadable. Download is currently unavailable.', 'wedding-gallery' );
								}
								$can_download = false;
							} else {
								$decoded_private = $this->decode_private_metadata_fields( $meta );
								if ( false === $decoded_private ) {
									$private_key_version = isset( $meta['private_key_version'] ) ? absint( $meta['private_key_version'] ) : 0;
									if ( $meta_version >= 2 && $private_key_version > 0 && false === $this->get_encryption_key_for_version( $private_key_version ) ) {
										$status  = 'unsupported_key_version';
										$message = __( 'Encrypted with a key version not available on this site.', 'wedding-gallery' );
									} else {
										$status  = 'invalid_metadata';
										$message = __( 'Private metadata could not be decoded.', 'wedding-gallery' );
									}
									$can_download = false;
								} else {
									$private_fields = $decoded_private;
									if ( false === $this->decrypt_contents( $ciphertext, $meta ) ) {
										$status       = 'decrypt_failed';
										$message      = __( 'Decryption check failed. The file or metadata may be corrupted.', 'wedding-gallery' );
										$can_download = false;
									} elseif ( ! empty( $integrity['legacy'] ) ) {
										$status  = 'legacy_metadata_plaintext';
										$message = __( 'Download works, but metadata is from legacy plaintext format and should be re-uploaded for better privacy.', 'wedding-gallery' );
									}
								}
							}
						}
					}
				}

				$display_name = isset( $private_fields['original_name'] ) ? sanitize_file_name( (string) $private_fields['original_name'] ) : '';
				if ( empty( $display_name ) ) {
					$display_name = $item;
				}

				$display_size = isset( $private_fields['size'] ) ? absint( $private_fields['size'] ) : 0;
				if ( $display_size < 1 ) {
					$display_size = absint( filesize( $path ) );
				}

				$files[] = array(
					'name'          => $display_name,
					'stored_file'   => $item,
					'size'          => $display_size,
					'mime_type'     => isset( $private_fields['mime_type'] ) ? sanitize_text_field( (string) $private_fields['mime_type'] ) : '',
					'modified'      => isset( $meta['uploaded_at'] ) ? absint( $meta['uploaded_at'] ) : filemtime( $path ),
					'health_status' => $status,
					'health_message'=> $message,
					'can_download'  => $can_download,
				);

				continue;
			}

			$files[] = array(
				'name'          => sanitize_file_name( $item ),
				'stored_file'   => $item,
				'size'          => absint( filesize( $path ) ),
				'mime_type'     => '',
				'modified'      => filemtime( $path ),
				'health_status' => 'legacy_plaintext',
				'health_message'=> __( 'Legacy plaintext file found. It is intentionally not served by this plugin.', 'wedding-gallery' ),
				'can_download'  => false,
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
	 * @param array<int, array<string, mixed>> $uploads
	 * @return array<string, int>
	 */
	private function get_upload_health_summary( $uploads ) {
		$summary = array(
			'ok'                      => 0,
			'legacy_metadata_plaintext' => 0,
			'missing_metadata'        => 0,
			'invalid_metadata'        => 0,
			'metadata_tampered'       => 0,
			'unsupported_key_version' => 0,
			'decrypt_failed'          => 0,
			'legacy_plaintext'        => 0,
		);

		foreach ( $uploads as $upload ) {
			$status = isset( $upload['health_status'] ) ? (string) $upload['health_status'] : '';
			if ( isset( $summary[ $status ] ) ) {
				$summary[ $status ]++;
			}
		}

		return $summary;
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
		$allowed_text         = '.jpg, .jpeg, .png, .webp, .mp4, .mov';
		$upload_limits        = $this->get_upload_limit_context( absint( $settings['max_upload_mb'] ) );
		$max_upload_mb        = (int) $upload_limits['configured_mb'];
		$effective_max_upload_mb = (int) $upload_limits['effective_mb'];
		$key_status           = $this->get_encryption_key_status();
		$upload_health_summary = $this->get_upload_health_summary( $uploads );
		$legacy_plaintext_count = (int) $upload_health_summary['legacy_plaintext'];
		$qr_code_size         = self::QR_CODE_DEFAULT_SIZE;
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

			$integrity = $this->verify_metadata_integrity( $file_name, $ciphertext, $meta );
			if ( empty( $integrity['ok'] ) ) {
				if ( 'unsupported_key_version' === $integrity['error'] ) {
					wp_die( esc_html__( 'Could not decrypt file: unsupported encryption key version.', 'wedding-gallery' ) );
				}
				if ( 'metadata_tampered' === $integrity['error'] ) {
					wp_die( esc_html__( 'Could not decrypt file. Metadata integrity check failed.', 'wedding-gallery' ) );
				}
				wp_die( esc_html__( 'Invalid file metadata.', 'wedding-gallery' ) );
			}

			$private_fields = $this->decode_private_metadata_fields( $meta );
			if ( false === $private_fields ) {
				$private_key_version = isset( $meta['private_key_version'] ) ? absint( $meta['private_key_version'] ) : 0;
				if ( $private_key_version > 0 && false === $this->get_encryption_key_for_version( $private_key_version ) ) {
					wp_die( esc_html__( 'Could not decrypt file: unsupported encryption key version.', 'wedding-gallery' ) );
				}
				wp_die( esc_html__( 'Invalid file metadata.', 'wedding-gallery' ) );
			}

			$plaintext = $this->decrypt_contents( $ciphertext, $meta );
			if ( false === $plaintext ) {
				wp_die( esc_html__( 'Could not decrypt file. Metadata may be invalid or the key may have changed.', 'wedding-gallery' ) );
			}

			$download_name = isset( $private_fields['original_name'] ) ? sanitize_file_name( (string) $private_fields['original_name'] ) : 'wedding-upload.bin';
			if ( empty( $download_name ) ) {
				$download_name = 'wedding-upload.bin';
			}

			$allowed_types = array_values( $this->get_allowed_mimes() );
			$type          = isset( $private_fields['mime_type'] ) ? sanitize_text_field( (string) $private_fields['mime_type'] ) : '';
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

		wp_die( esc_html__( 'Plaintext legacy files are not served by the plugin. Please migrate them to encrypted storage.', 'wedding-gallery' ) );
	}
}
