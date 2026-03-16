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
	const MEDIA_OVERVIEW_CACHE_KEY = 'wg_media_overview_v1';
	const MEDIA_OVERVIEW_CACHE_TTL = 120;

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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_wg_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_wg_upload', array( $this, 'handle_upload' ) );
		add_action( 'admin_post_nopriv_wg_upload', array( $this, 'handle_upload' ) );
		add_action( 'admin_post_wg_download_upload', array( $this, 'handle_download' ) );
		add_action( 'admin_post_wg_view_upload', array( $this, 'handle_view_upload' ) );
		add_action( 'admin_post_wg_delete_upload', array( $this, 'handle_delete_upload' ) );
		add_action( 'admin_post_wg_bulk_download', array( $this, 'handle_bulk_download' ) );
		add_action( 'admin_post_wg_bulk_delete', array( $this, 'handle_bulk_delete' ) );
		add_action( 'admin_post_wg_refresh_media_overview', array( $this, 'handle_refresh_media_overview' ) );
	}

	/**
	 * @return void
	 */
	private function enqueue_frontend_upload_script() {
		wp_register_script(
			'wg-frontend-upload',
			WG_PLUGIN_URL . 'assets/js/frontend-upload.js',
			array(),
			WG_PLUGIN_VERSION,
			true
		);

		$i18n_data = array(
			'noFilesSelected'      => __( 'No files selected yet.', 'wedding-gallery' ),
			'filesSelectedPrefix'  => __( 'files selected:', 'wedding-gallery' ),
			'chooseAtLeastOne'     => __( 'Please choose at least one photo or video.', 'wedding-gallery' ),
			'uploadingKeepOpen'    => __( 'Uploading... Please keep this page open.', 'wedding-gallery' ),
			'uploadingProgress'    => __( 'Upload in progress...', 'wedding-gallery' ),
			'uploadingAlmostDone'  => __( 'Almost done...', 'wedding-gallery' ),
		);

		$json_i18n_data = wp_json_encode( $i18n_data );
		if ( false !== $json_i18n_data ) {
			wp_add_inline_script( 'wg-frontend-upload', 'window.wgUploadI18n = ' . $json_i18n_data . ';', 'before' );
		}

		wp_enqueue_script( 'wg-frontend-upload' );
	}

	/**
	 * @param string $hook_suffix
	 * @return void
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'toplevel_page_wedding-gallery' !== $hook_suffix ) {
			return;
		}

		wp_register_script(
			'wg-admin-qrcode',
			WG_PLUGIN_URL . 'assets/js/qrcode.min.js',
			array(),
			WG_PLUGIN_VERSION,
			true
		);
		wp_register_script(
			'wg-admin-qr',
			WG_PLUGIN_URL . 'assets/js/admin-qr.js',
			array( 'wg-admin-qrcode' ),
			WG_PLUGIN_VERSION,
			true
		);
		wp_register_script(
			'wg-admin-media',
			WG_PLUGIN_URL . 'assets/js/admin-media.js',
			array(),
			WG_PLUGIN_VERSION,
			true
		);
		wp_register_style(
			'wg-admin',
			WG_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WG_PLUGIN_VERSION
		);

		$config_json = wp_json_encode(
			array(
				'qrCodeSize' => self::QR_CODE_DEFAULT_SIZE,
			)
		);
		if ( false !== $config_json ) {
			wp_add_inline_script( 'wg-admin-qr', 'window.wgAdminQrConfig = ' . $config_json . ';', 'before' );
		}

		$media_i18n_json = wp_json_encode(
			array(
				'confirmDeleteSingle'   => __( 'Delete this file permanently?', 'wedding-gallery' ),
				'confirmDeleteSelected' => __( 'Delete selected media permanently?', 'wedding-gallery' ),
				'confirmDeleteAll'      => __( 'Delete ALL wedding media permanently?', 'wedding-gallery' ),
				'noSelection'           => __( 'Please select at least one file first.', 'wedding-gallery' ),
			)
		);
		if ( false !== $media_i18n_json ) {
			wp_add_inline_script( 'wg-admin-media', 'window.wgAdminMediaI18n = ' . $media_i18n_json . ';', 'before' );
		}

		wp_enqueue_script( 'wg-admin-qrcode' );
		wp_enqueue_script( 'wg-admin-qr' );
		wp_enqueue_script( 'wg-admin-media' );
		wp_enqueue_style( 'wg-admin' );
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
		$uri = '/';
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			if ( '' === $uri || '/' !== substr( $uri, 0, 1 ) ) {
				$uri = '/';
			}
		}

		return home_url( $uri );
	}

	/**
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_upload_shortcode( $atts ) {
		self::create_upload_dir();

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();

		$settings      = $this->get_settings();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only token check for protected page access.
		$current_token = isset( $_GET[ self::TOKEN_QUERY_ARG ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::TOKEN_QUERY_ARG ] ) ) : '';
		$token         = (string) $settings['access_token'];

		$is_authorized = ! empty( $current_token ) && ! empty( $token ) && hash_equals( $token, $current_token );
		$authorized_token = $is_authorized ? $current_token : '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only UI message state.
		$status  = isset( $_GET['wg_status'] ) ? sanitize_key( wp_unslash( $_GET['wg_status'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only UI message state.
		$message = isset( $_GET['wg_message'] ) ? sanitize_text_field( wp_unslash( $_GET['wg_message'] ) ) : '';

		$upload_limits = $this->get_upload_limit_context( absint( $settings['max_upload_mb'] ) );
		$max_upload_mb = (int) $upload_limits['effective_mb'];
		$action_url    = admin_url( 'admin-post.php' );
		$redirect_url  = $this->get_current_url();
		$allowed_text  = '.jpg, .jpeg, .png, .webp, .mp4, .mov';

		$this->enqueue_frontend_upload_script();

		ob_start();
		require WG_PLUGIN_DIR . 'templates/frontend-upload.php';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template output escapes dynamic data at source.
		return (string) ob_get_clean();
	}

	/**
	 * @return void
	 */
	public function handle_upload() {
		self::create_upload_dir();

		$redirect_url = home_url( '/' );
		$settings     = $this->get_settings();
		$posted_token = isset( $_POST[ self::TOKEN_QUERY_ARG ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::TOKEN_QUERY_ARG ] ) ) : '';
		$token        = (string) $settings['access_token'];
		if ( empty( $posted_token ) || empty( $token ) || ! hash_equals( $token, $posted_token ) ) {
			$this->redirect_with_message( $redirect_url, 'error', __( 'Invalid upload token.', 'wedding-gallery' ) );
		}

		$nonce_ok = isset( $_POST['wg_upload_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wg_upload_nonce'] ) ), 'wg_upload_action_' . $posted_token );
		if ( ! $nonce_ok ) {
			$this->redirect_with_message( $redirect_url, 'error', __( 'Security check failed.', 'wedding-gallery' ) );
		}
		$redirect_url = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : home_url( '/' );

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
		$too_large_media_kinds = array();

		$total_files = count( $files['name'] );
		for ( $i = 0; $i < $total_files; $i++ ) {
			$name     = isset( $files['name'][ $i ] ) ? wp_unslash( $files['name'][ $i ] ) : '';
			$tmp_name = isset( $files['tmp_name'][ $i ] ) ? $files['tmp_name'][ $i ] : '';
			$size     = isset( $files['size'][ $i ] ) ? absint( $files['size'][ $i ] ) : 0;
			$error    = isset( $files['error'][ $i ] ) ? absint( $files['error'][ $i ] ) : UPLOAD_ERR_NO_FILE;

			if ( UPLOAD_ERR_OK !== $error ) {
				if ( in_array( $error, array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) ) {
					$error_codes[] = 'too_large';
					$too_large_media_kinds[] = $this->detect_media_kind_from_filename( $name );
				} else {
					$error_codes[] = 'upload_failed';
				}
				continue;
			}

			if ( empty( $tmp_name ) || ! is_uploaded_file( $tmp_name ) ) {
				$error_codes[] = 'upload_failed';
				continue;
			}

			$sanitized_name = sanitize_file_name( $name );
			if ( empty( $sanitized_name ) ) {
				$error_codes[] = 'upload_failed';
				continue;
			}

			if ( $size > $max_bytes ) {
				$error_codes[] = 'too_large';
				$too_large_media_kinds[] = $this->detect_media_kind_from_filename( $sanitized_name );
				continue;
			}

			$ext = strtolower( pathinfo( $sanitized_name, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, $allowed_exts, true ) ) {
				$error_codes[] = 'unsupported_type';
				continue;
			}

			$type_data = wp_check_filetype_and_ext( $tmp_name, $sanitized_name, $allowed_mimes );
			if ( empty( $type_data['ext'] ) || empty( $type_data['type'] ) ) {
				$error_codes[] = 'unsupported_type';
				continue;
			}

			if ( 0 === strpos( $type_data['type'], 'image/' ) ) {
				$image_info = @getimagesize( $tmp_name ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				if ( false === $image_info ) {
					$error_codes[] = 'unsupported_type';
					continue;
				}
			}

			$file_contents = file_get_contents( $tmp_name ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false === $file_contents ) {
				$error_codes[] = 'processing_failed';
				continue;
			}

			$encrypted_payload = $this->encrypt_contents( $file_contents );
			if ( false === $encrypted_payload ) {
				$error_codes[] = 'processing_failed';
				continue;
			}

			$storage_names = $this->generate_storage_filenames( $upload_dir );
			if ( empty( $storage_names['blob'] ) || empty( $storage_names['meta'] ) ) {
				$error_codes[] = 'processing_failed';
				continue;
			}

			$target_path = trailingslashit( $upload_dir ) . $storage_names['blob'];
			$meta_path   = trailingslashit( $upload_dir ) . $storage_names['meta'];

			$written_blob = file_put_contents( $target_path, $encrypted_payload['ciphertext'], LOCK_EX );
			if ( false === $written_blob ) {
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
				$error_codes[] = 'processing_failed';
				continue;
			}

			$private_metadata_payload = $this->encrypt_contents( $private_metadata_json );
			if ( false === $private_metadata_payload ) {
				wp_delete_file( $target_path );
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
				$error_codes[] = 'processing_failed';
				continue;
			}

			$metadata['integrity_mac'] = $metadata_mac;

			$metadata_json = wp_json_encode( $metadata );
			if ( false === $metadata_json ) {
				wp_delete_file( $target_path );
				$error_codes[] = 'processing_failed';
				continue;
			}

			$written_meta = file_put_contents( $meta_path, $metadata_json, LOCK_EX );
			if ( false === $written_meta ) {
				wp_delete_file( $target_path );
				$error_codes[] = 'processing_failed';
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
			if ( ! empty( $error_codes ) ) {
				$error_summary = $this->summarize_upload_errors( $error_codes, $max_upload_mb, $too_large_media_kinds );
				if ( '' !== $error_summary ) {
					$message .= ' ' . $error_summary;
				} else {
					$message .= ' ' . __( 'Some files could not be uploaded.', 'wedding-gallery' );
				}
			}
			$this->invalidate_media_overview_cache();
			$this->redirect_with_message( $redirect_url, 'success', $message );
		}

		$error_summary = $this->summarize_upload_errors( $error_codes, $max_upload_mb, $too_large_media_kinds );
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
	 * @param int                $max_upload_mb
	 * @param array<int, string> $too_large_media_kinds
	 * @return string
	 */
	private function summarize_upload_errors( $error_codes, $max_upload_mb, $too_large_media_kinds ) {
		if ( empty( $error_codes ) ) {
			return '';
		}

		$counts = array_count_values( $error_codes );
		$messages = array();
		$too_large_count = isset( $counts['too_large'] ) ? absint( $counts['too_large'] ) : 0;
		$unsupported_count = isset( $counts['unsupported_type'] ) ? absint( $counts['unsupported_type'] ) : 0;
		$failed_count = 0;
		if ( isset( $counts['upload_failed'] ) ) {
			$failed_count += absint( $counts['upload_failed'] );
		}
		if ( isset( $counts['processing_failed'] ) ) {
			$failed_count += absint( $counts['processing_failed'] );
		}

		if ( $too_large_count > 0 ) {
			$too_large_kind = $this->get_uniform_media_kind( $too_large_media_kinds, $too_large_count );
			if ( 'video' === $too_large_kind ) {
				$messages[] = sprintf(
					/* translators: %1$d: rejected videos count, %2$d: max upload size in MB */
					_n( '%1$d video was too large to upload (max %2$d MB).', '%1$d videos were too large to upload (max %2$d MB).', $too_large_count, 'wedding-gallery' ),
					$too_large_count,
					$max_upload_mb
				);
			} elseif ( 'photo' === $too_large_kind ) {
				$messages[] = sprintf(
					/* translators: %1$d: rejected photos count, %2$d: max upload size in MB */
					_n( '%1$d photo was too large to upload (max %2$d MB).', '%1$d photos were too large to upload (max %2$d MB).', $too_large_count, 'wedding-gallery' ),
					$too_large_count,
					$max_upload_mb
				);
			} else {
				$messages[] = sprintf(
					/* translators: %1$d: rejected files count, %2$d: max upload size in MB */
					_n( '%1$d file was too large to upload (max %2$d MB).', '%1$d files were too large to upload (max %2$d MB).', $too_large_count, 'wedding-gallery' ),
					$too_large_count,
					$max_upload_mb
				);
			}
		}

		if ( $unsupported_count > 0 ) {
			$messages[] = sprintf(
				/* translators: %d: unsupported files count */
				_n( '%d file was not supported.', '%d files were not supported.', $unsupported_count, 'wedding-gallery' ),
				$unsupported_count
			);
		}

		if ( $failed_count > 0 ) {
			$messages[] = sprintf(
				/* translators: %d: files that could not be uploaded */
				_n( '%d file could not be uploaded. Please try again.', '%d files could not be uploaded. Please try again.', $failed_count, 'wedding-gallery' ),
				$failed_count
			);
		}

		return trim( implode( ' ', array_slice( $messages, 0, 2 ) ) );
	}

	/**
	 * @param array<int, string> $too_large_media_kinds
	 * @param int                $too_large_count
	 * @return string
	 */
	private function get_uniform_media_kind( $too_large_media_kinds, $too_large_count ) {
		if ( empty( $too_large_media_kinds ) || $too_large_count < 1 ) {
			return 'file';
		}

		$media = array_slice( $too_large_media_kinds, 0, $too_large_count );
		if ( empty( $media ) ) {
			return 'file';
		}

		$normalized = array();
		foreach ( $media as $kind ) {
			$kind = sanitize_key( (string) $kind );
			$normalized[] = in_array( $kind, array( 'photo', 'video', 'file' ), true ) ? $kind : 'file';
		}

		$unique = array_unique( $normalized );
		if ( 1 === count( $unique ) ) {
			return (string) reset( $unique );
		}

		return 'file';
	}

	/**
	 * @param string $candidate
	 * @return string
	 */
	private function detect_media_kind_from_filename( $candidate ) {
		$clean = sanitize_file_name( (string) $candidate );
		$ext   = strtolower( (string) pathinfo( $clean, PATHINFO_EXTENSION ) );

		if ( in_array( $ext, array( 'jpg', 'jpeg', 'jpe', 'png', 'webp', 'heic', 'heif' ), true ) ) {
			return 'photo';
		}

		if ( in_array( $ext, array( 'mp4', 'mov', 'm4v', '3gp', 'webm' ), true ) ) {
			return 'video';
		}

		return 'file';
	}

	/**
	 * @param string $mime_type
	 * @param string $candidate
	 * @return string
	 */
	private function detect_media_kind( $mime_type, $candidate ) {
		$mime_type = sanitize_text_field( (string) $mime_type );
		if ( 0 === strpos( $mime_type, 'image/' ) ) {
			return 'photo';
		}
		if ( 0 === strpos( $mime_type, 'video/' ) ) {
			return 'video';
		}

		return $this->detect_media_kind_from_filename( $candidate );
	}

	/**
	 * @return string
	 */
	private function get_admin_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin UI state.
		$requested_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';

		return in_array( $requested_tab, array( 'settings', 'media', 'help' ), true ) ? $requested_tab : 'settings';
	}

	/**
	 * @param string               $tab
	 * @param array<string, mixed> $args
	 * @return string
	 */
	private function get_admin_page_url( $tab, $args = array() ) {
		$tab = sanitize_key( $tab );
		if ( ! in_array( $tab, array( 'settings', 'media', 'help' ), true ) ) {
			$tab = 'settings';
		}

		$base_args = array(
			'page' => 'wedding-gallery',
			'tab'  => $tab,
		);
		$query_args = array_merge( $base_args, $args );

		return add_query_arg( $query_args, admin_url( 'admin.php' ) );
	}

	/**
	 * @param string               $tab
	 * @param string               $notice
	 * @param array<string, mixed> $extra_args
	 * @return void
	 */
	private function redirect_admin_notice( $tab, $notice, $extra_args = array() ) {
		$args = array_merge(
			array(
				'wg_notice' => sanitize_key( $notice ),
			),
			$extra_args
		);

		wp_safe_redirect( $this->get_admin_page_url( $tab, $args ) );
		exit;
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

		$this->redirect_admin_notice( 'settings', $notice );
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
				$mime_type = isset( $private_fields['mime_type'] ) ? sanitize_text_field( (string) $private_fields['mime_type'] ) : '';

				$files[] = array(
					'name'          => $display_name,
					'stored_file'   => $item,
					'size'          => $display_size,
					'mime_type'     => $mime_type,
					'media_kind'    => $this->detect_media_kind( $mime_type, $display_name ),
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
				'media_kind'    => $this->detect_media_kind_from_filename( $item ),
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
	 * @return string
	 */
	private function get_media_overview_cache_key() {
		return self::MEDIA_OVERVIEW_CACHE_KEY;
	}

	/**
	 * @return void
	 */
	private function invalidate_media_overview_cache() {
		delete_transient( $this->get_media_overview_cache_key() );
	}

	/**
	 * @param bool $force_refresh
	 * @return array<string, mixed>
	 */
	private function get_media_overview_data( $force_refresh = false ) {
		$cache_key = $this->get_media_overview_cache_key();
		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if (
				is_array( $cached ) &&
				isset( $cached['uploads'] ) &&
				is_array( $cached['uploads'] ) &&
				isset( $cached['upload_health_summary'] ) &&
				is_array( $cached['upload_health_summary'] ) &&
				isset( $cached['generated_at'] )
			) {
				$cached['from_cache'] = true;

				return $cached;
			}
		}

		$uploads              = $this->get_uploaded_files();
		$upload_health_summary = $this->get_upload_health_summary( $uploads );
		$payload              = array(
			'uploads'               => $uploads,
			'upload_health_summary' => $upload_health_summary,
			'generated_at'          => time(),
			'from_cache'            => false,
		);

		set_transient( $cache_key, $payload, self::MEDIA_OVERVIEW_CACHE_TTL );

		return $payload;
	}

	/**
	 * @param string $path
	 * @param string $directory
	 * @return bool
	 */
	private function is_path_within_directory( $path, $directory ) {
		$normalized_path      = wp_normalize_path( (string) $path );
		$normalized_directory = trailingslashit( wp_normalize_path( (string) $directory ) );

		return '' !== $normalized_path && '' !== $normalized_directory && 0 === strpos( $normalized_path, $normalized_directory );
	}

	/**
	 * @return string|false
	 */
	private function get_upload_dir_realpath() {
		self::create_upload_dir();
		$upload_dir = realpath( self::get_upload_dir() );
		if ( false === $upload_dir || ! is_dir( $upload_dir ) ) {
			return false;
		}

		return $upload_dir;
	}

	/**
	 * @param string $file_name
	 * @return string|false
	 */
	private function resolve_existing_upload_file_path( $file_name ) {
		$clean_name = sanitize_file_name( (string) $file_name );
		if ( '' === $clean_name ) {
			return false;
		}

		$upload_dir = $this->get_upload_dir_realpath();
		if ( false === $upload_dir ) {
			return false;
		}

		$candidate_path = trailingslashit( $upload_dir ) . $clean_name;
		$resolved_path  = realpath( $candidate_path );
		if ( false === $resolved_path || ! is_file( $resolved_path ) ) {
			return false;
		}

		if ( ! $this->is_path_within_directory( $resolved_path, $upload_dir ) ) {
			return false;
		}

		return $resolved_path;
	}

	/**
	 * @param string $file_name
	 * @return array<string, string>|WP_Error
	 */
	private function resolve_encrypted_file_paths( $file_name ) {
		$clean_name = sanitize_file_name( (string) $file_name );
		if ( '' === $clean_name || ! $this->is_encrypted_blob_file( $clean_name ) ) {
			return new WP_Error( 'invalid_file', __( 'Invalid file.', 'wedding-gallery' ) );
		}

		$blob_path = $this->resolve_existing_upload_file_path( $clean_name );
		if ( false === $blob_path ) {
			return new WP_Error( 'missing_file', __( 'File does not exist.', 'wedding-gallery' ) );
		}

		$meta_file_name = $this->get_meta_file_name_for_blob( $clean_name );
		$meta_path      = $this->resolve_existing_upload_file_path( $meta_file_name );
		if ( false === $meta_path ) {
			return new WP_Error( 'missing_metadata', __( 'Missing file metadata.', 'wedding-gallery' ) );
		}

		return array(
			'file_name'      => $clean_name,
			'blob_path'      => $blob_path,
			'meta_path'      => $meta_path,
			'meta_file_name' => $meta_file_name,
		);
	}

	/**
	 * @param string $file_name
	 * @return array<string, mixed>|WP_Error
	 */
	private function get_decrypted_media_payload( $file_name ) {
		$resolved = $this->resolve_encrypted_file_paths( $file_name );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$meta = $this->read_media_metadata( $resolved['meta_path'] );
		if ( empty( $meta ) ) {
			return new WP_Error( 'invalid_metadata', __( 'Invalid file metadata.', 'wedding-gallery' ) );
		}

		$key_version = isset( $meta['key_version'] ) ? absint( $meta['key_version'] ) : 1;
		if ( false === $this->get_encryption_key_for_version( $key_version ) ) {
			return new WP_Error( 'unsupported_key_version', __( 'Could not decrypt file: unsupported encryption key version.', 'wedding-gallery' ) );
		}

		$ciphertext = file_get_contents( $resolved['blob_path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $ciphertext ) {
			return new WP_Error( 'read_failed', __( 'Could not read file.', 'wedding-gallery' ) );
		}

		$integrity = $this->verify_metadata_integrity( $resolved['file_name'], $ciphertext, $meta );
		if ( empty( $integrity['ok'] ) ) {
			if ( 'unsupported_key_version' === $integrity['error'] ) {
				return new WP_Error( 'unsupported_key_version', __( 'Could not decrypt file: unsupported encryption key version.', 'wedding-gallery' ) );
			}
			if ( 'metadata_tampered' === $integrity['error'] ) {
				return new WP_Error( 'metadata_tampered', __( 'Could not decrypt file. Metadata integrity check failed.', 'wedding-gallery' ) );
			}

			return new WP_Error( 'invalid_metadata', __( 'Invalid file metadata.', 'wedding-gallery' ) );
		}

		$private_fields = $this->decode_private_metadata_fields( $meta );
		if ( false === $private_fields ) {
			$private_key_version = isset( $meta['private_key_version'] ) ? absint( $meta['private_key_version'] ) : 0;
			if ( $private_key_version > 0 && false === $this->get_encryption_key_for_version( $private_key_version ) ) {
				return new WP_Error( 'unsupported_key_version', __( 'Could not decrypt file: unsupported encryption key version.', 'wedding-gallery' ) );
			}

			return new WP_Error( 'invalid_metadata', __( 'Invalid file metadata.', 'wedding-gallery' ) );
		}

		$plaintext = $this->decrypt_contents( $ciphertext, $meta );
		if ( false === $plaintext ) {
			return new WP_Error( 'decrypt_failed', __( 'Could not decrypt file. Metadata may be invalid or the key may have changed.', 'wedding-gallery' ) );
		}

		$download_name = isset( $private_fields['original_name'] ) ? sanitize_file_name( (string) $private_fields['original_name'] ) : 'wedding-upload.bin';
		if ( '' === $download_name ) {
			$download_name = 'wedding-upload.bin';
		}

		$allowed_types = array_values( $this->get_allowed_mimes() );
		$type          = isset( $private_fields['mime_type'] ) ? sanitize_text_field( (string) $private_fields['mime_type'] ) : '';
		if ( ! in_array( $type, $allowed_types, true ) ) {
			$type = 'application/octet-stream';
		}

		return array(
			'content'       => $plaintext,
			'size'          => strlen( $plaintext ),
			'mime_type'     => $type,
			'download_name' => $download_name,
			'stored_file'   => $resolved['file_name'],
		);
	}

	/**
	 * @param array<string, mixed> $payload
	 * @param bool                 $inline
	 * @return void
	 */
	private function output_decrypted_media_response( $payload, $inline = false ) {
		$download_name = isset( $payload['download_name'] ) ? sanitize_file_name( (string) $payload['download_name'] ) : 'wedding-upload.bin';
		if ( '' === $download_name ) {
			$download_name = 'wedding-upload.bin';
		}

		$mime_type = isset( $payload['mime_type'] ) ? sanitize_text_field( (string) $payload['mime_type'] ) : 'application/octet-stream';
		$size      = isset( $payload['size'] ) ? absint( $payload['size'] ) : 0;
		$content   = isset( $payload['content'] ) ? (string) $payload['content'] : '';

		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: ' . $mime_type );
		header( 'Content-Disposition: ' . ( $inline ? 'inline' : 'attachment' ) . '; filename="' . basename( $download_name ) . '"' );
		if ( $size > 0 ) {
			header( 'Content-Length: ' . $size );
		}

		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * @return string
	 */
	private function get_bulk_scope_from_request() {
		$scope = isset( $_POST['wg_scope'] ) ? sanitize_key( wp_unslash( $_POST['wg_scope'] ) ) : 'selected';

		return 'all' === $scope ? 'all' : 'selected';
	}

	/**
	 * @return array<int, string>
	 */
	private function get_selected_blob_files_from_request() {
		$selected = isset( $_POST['wg_files'] ) && is_array( $_POST['wg_files'] ) ? wp_unslash( $_POST['wg_files'] ) : array();
		$files    = array();

		foreach ( $selected as $item ) {
			$clean = sanitize_file_name( (string) $item );
			if ( '' !== $clean && $this->is_encrypted_blob_file( $clean ) ) {
				$files[] = $clean;
			}
		}

		return array_values( array_unique( $files ) );
	}

	/**
	 * @return array<int, string>
	 */
	private function get_all_encrypted_blob_files() {
		$upload_dir = $this->get_upload_dir_realpath();
		if ( false === $upload_dir ) {
			return array();
		}

		$items = scandir( $upload_dir );
		if ( false === $items ) {
			return array();
		}

		$files = array();
		foreach ( $items as $item ) {
			if ( ! $this->is_encrypted_blob_file( $item ) ) {
				continue;
			}

			if ( false !== $this->resolve_existing_upload_file_path( $item ) ) {
				$files[] = sanitize_file_name( $item );
			}
		}

		return array_values( array_unique( $files ) );
	}

	/**
	 * @return array<int, string>
	 */
	private function get_bulk_target_files() {
		if ( 'all' === $this->get_bulk_scope_from_request() ) {
			return $this->get_all_encrypted_blob_files();
		}

		return $this->get_selected_blob_files_from_request();
	}

	/**
	 * @param string $file_name
	 * @return bool
	 */
	private function delete_encrypted_blob_and_metadata( $file_name ) {
		$clean_name = sanitize_file_name( (string) $file_name );
		if ( '' === $clean_name || ! $this->is_encrypted_blob_file( $clean_name ) ) {
			return false;
		}

		$blob_path = $this->resolve_existing_upload_file_path( $clean_name );
		if ( false === $blob_path ) {
			return false;
		}

		wp_delete_file( $blob_path );
		if ( is_file( $blob_path ) ) {
			return false;
		}

		$meta_path = $this->resolve_existing_upload_file_path( $this->get_meta_file_name_for_blob( $clean_name ) );
		if ( false !== $meta_path ) {
			wp_delete_file( $meta_path );
		}

		return true;
	}

	/**
	 * @param string               $candidate_name
	 * @param array<string, int> &$used_names
	 * @return string
	 */
	private function get_unique_zip_entry_name( $candidate_name, &$used_names ) {
		$base_name = sanitize_file_name( (string) $candidate_name );
		if ( '' === $base_name ) {
			$base_name = 'wedding-upload.bin';
		}

		if ( ! isset( $used_names[ $base_name ] ) ) {
			$used_names[ $base_name ] = 1;

			return $base_name;
		}

		$used_names[ $base_name ]++;
		$count = $used_names[ $base_name ];
		$name  = pathinfo( $base_name, PATHINFO_FILENAME );
		$ext   = pathinfo( $base_name, PATHINFO_EXTENSION );
		$next  = $name . '-' . $count;
		if ( '' !== $ext ) {
			$next .= '.' . $ext;
		}

		return sanitize_file_name( $next );
	}

	/**
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'wedding-gallery' ) );
		}

		self::create_upload_dir();

		$current_tab             = $this->get_admin_tab();
		$settings                = $this->get_settings();
		$protected_upload_url    = $this->get_protected_upload_url();
		$uploads                 = array();
		$allowed_text            = '.jpg, .jpeg, .png, .webp, .mp4, .mov';
		$upload_limits           = $this->get_upload_limit_context( absint( $settings['max_upload_mb'] ) );
		$max_upload_mb           = (int) $upload_limits['configured_mb'];
		$effective_max_upload_mb = (int) $upload_limits['effective_mb'];
		$key_status              = $this->get_encryption_key_status();
		$upload_health_summary   = $this->get_upload_health_summary( array() );
		$media_overview_generated_at = 0;
		$media_overview_from_cache   = false;
		$media_overview_cache_ttl    = self::MEDIA_OVERVIEW_CACHE_TTL;
		if ( 'media' === $current_tab ) {
			$media_overview            = $this->get_media_overview_data( false );
			$uploads                   = isset( $media_overview['uploads'] ) && is_array( $media_overview['uploads'] ) ? $media_overview['uploads'] : array();
			$upload_health_summary     = isset( $media_overview['upload_health_summary'] ) && is_array( $media_overview['upload_health_summary'] ) ? $media_overview['upload_health_summary'] : $upload_health_summary;
			$media_overview_generated_at = isset( $media_overview['generated_at'] ) ? absint( $media_overview['generated_at'] ) : 0;
			$media_overview_from_cache = ! empty( $media_overview['from_cache'] );
		}
		$legacy_plaintext_count = 'media' === $current_tab ? (int) $upload_health_summary['legacy_plaintext'] : 0;
		$qr_code_size            = self::QR_CODE_DEFAULT_SIZE;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin notice state.
		$notice                  = isset( $_GET['wg_notice'] ) ? sanitize_key( wp_unslash( $_GET['wg_notice'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin notice state.
		$notice_count            = isset( $_GET['wg_count'] ) ? absint( wp_unslash( $_GET['wg_count'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin notice state.
		$notice_skipped          = isset( $_GET['wg_skipped'] ) ? absint( wp_unslash( $_GET['wg_skipped'] ) ) : 0;

		require WG_PLUGIN_DIR . 'templates/admin-page.php';
	}

	/**
	 * @return void
	 */
	public function handle_download() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to download files.', 'wedding-gallery' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified below.
		$file_name = isset( $_GET['file'] ) ? sanitize_file_name( wp_unslash( $_GET['file'] ) ) : '';
		if ( '' === $file_name ) {
			wp_die( esc_html__( 'Missing file.', 'wedding-gallery' ) );
		}

		$nonce_ok = isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wg_download_file_' . $file_name );
		if ( ! $nonce_ok ) {
			wp_die( esc_html__( 'Invalid security token.', 'wedding-gallery' ) );
		}

		$payload = $this->get_decrypted_media_payload( $file_name );
		if ( is_wp_error( $payload ) ) {
			wp_die( esc_html( $payload->get_error_message() ) );
		}

		$this->output_decrypted_media_response( $payload, false );
	}

	/**
	 * @return void
	 */
	public function handle_view_upload() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this file.', 'wedding-gallery' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified below.
		$file_name = isset( $_GET['file'] ) ? sanitize_file_name( wp_unslash( $_GET['file'] ) ) : '';
		if ( '' === $file_name ) {
			wp_die( esc_html__( 'Missing file.', 'wedding-gallery' ) );
		}

		$nonce_ok = isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wg_view_file_' . $file_name );
		if ( ! $nonce_ok ) {
			wp_die( esc_html__( 'Invalid security token.', 'wedding-gallery' ) );
		}

		$payload = $this->get_decrypted_media_payload( $file_name );
		if ( is_wp_error( $payload ) ) {
			wp_die( esc_html( $payload->get_error_message() ) );
		}

		$this->output_decrypted_media_response( $payload, true );
	}

	/**
	 * @return void
	 */
	public function handle_delete_upload() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to delete files.', 'wedding-gallery' ) );
		}

		$file_name = isset( $_POST['file'] ) ? sanitize_file_name( wp_unslash( $_POST['file'] ) ) : '';
		if ( '' === $file_name ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified below.
			$file_name = isset( $_GET['file'] ) ? sanitize_file_name( wp_unslash( $_GET['file'] ) ) : '';
		}
		if ( '' === $file_name ) {
			$this->redirect_admin_notice( 'media', 'delete_failed' );
		}

		$nonce_ok = isset( $_POST['wg_delete_file_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wg_delete_file_nonce'] ) ), 'wg_delete_file_' . $file_name );
		if ( ! $nonce_ok ) {
			$nonce_ok = isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wg_delete_file_' . $file_name );
		}
		if ( ! $nonce_ok ) {
			wp_die( esc_html__( 'Invalid security token.', 'wedding-gallery' ) );
		}

		if ( $this->delete_encrypted_blob_and_metadata( $file_name ) ) {
			$this->invalidate_media_overview_cache();
			$this->redirect_admin_notice( 'media', 'delete_success', array( 'wg_count' => 1 ) );
		}

		$this->redirect_admin_notice( 'media', 'delete_failed' );
	}

	/**
	 * @return void
	 */
	public function handle_refresh_media_overview() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'wedding-gallery' ) );
		}

		check_admin_referer( 'wg_refresh_media_overview', 'wg_refresh_media_overview_nonce' );

		$this->invalidate_media_overview_cache();
		$this->get_media_overview_data( true );

		$this->redirect_admin_notice( 'media', 'media_refreshed' );
	}

	/**
	 * @return void
	 */
	public function handle_bulk_delete() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to delete files.', 'wedding-gallery' ) );
		}

		check_admin_referer( 'wg_bulk_delete', 'wg_bulk_delete_nonce' );

		$scope = $this->get_bulk_scope_from_request();
		$files = $this->get_bulk_target_files();
		if ( empty( $files ) ) {
			$empty_notice = 'all' === $scope ? 'bulk_no_files' : 'bulk_no_selection';
			$this->redirect_admin_notice( 'media', $empty_notice );
		}

		$deleted = 0;
		$skipped = 0;
		foreach ( $files as $file_name ) {
			if ( $this->delete_encrypted_blob_and_metadata( $file_name ) ) {
				$deleted++;
			} else {
				$skipped++;
			}
		}

		if ( $deleted > 0 && $skipped > 0 ) {
			$this->invalidate_media_overview_cache();
			$this->redirect_admin_notice(
				'media',
				'bulk_delete_partial',
				array(
					'wg_count'   => $deleted,
					'wg_skipped' => $skipped,
				)
			);
		}

		if ( $deleted > 0 ) {
			$this->invalidate_media_overview_cache();
			$this->redirect_admin_notice( 'media', 'bulk_delete_success', array( 'wg_count' => $deleted ) );
		}

		$this->redirect_admin_notice( 'media', 'bulk_delete_failed', array( 'wg_skipped' => $skipped ) );
	}

	/**
	 * @return void
	 */
	public function handle_bulk_download() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to download files.', 'wedding-gallery' ) );
		}

		check_admin_referer( 'wg_bulk_download', 'wg_bulk_download_nonce' );

		$scope = $this->get_bulk_scope_from_request();
		$files = $this->get_bulk_target_files();
		if ( empty( $files ) ) {
			$empty_notice = 'all' === $scope ? 'bulk_no_files' : 'bulk_no_selection';
			$this->redirect_admin_notice( 'media', $empty_notice );
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->redirect_admin_notice( 'media', 'zip_unavailable' );
		}

		$temp_zip = wp_tempnam( 'wedding-gallery-export.zip' );
		if ( false === $temp_zip ) {
			$this->redirect_admin_notice( 'media', 'zip_failed' );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $temp_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			wp_delete_file( $temp_zip );
			$this->redirect_admin_notice( 'media', 'zip_failed' );
		}

		$written    = 0;
		$skipped    = 0;
		$used_names = array();

		foreach ( $files as $file_name ) {
			$payload = $this->get_decrypted_media_payload( $file_name );
			if ( is_wp_error( $payload ) ) {
				$skipped++;
				continue;
			}

			$zip_entry = $this->get_unique_zip_entry_name( $payload['download_name'], $used_names );
			if ( ! $zip->addFromString( $zip_entry, $payload['content'] ) ) {
				$skipped++;
				continue;
			}

			$written++;
		}

		if ( $skipped > 0 ) {
			$zip->addFromString(
				'_wedding-gallery-export-report.txt',
				sprintf(
					/* translators: %d: skipped files count in ZIP export */
					__( '%d file(s) were skipped because they are not currently decryptable.', 'wedding-gallery' ),
					$skipped
				)
			);
		}

		$zip->close();

		if ( $written < 1 ) {
			wp_delete_file( $temp_zip );
			$this->redirect_admin_notice( 'media', 'bulk_download_failed', array( 'wg_skipped' => $skipped ) );
		}

		$archive_name = 'wedding-gallery-media-' . gmdate( 'Ymd-His' ) . '.zip';

		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . basename( $archive_name ) . '"' );
		header( 'Content-Length: ' . absint( filesize( $temp_zip ) ) );

		readfile( $temp_zip ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
		wp_delete_file( $temp_zip );
		exit;
	}
}
