<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WG_Plugin {
	const OPTION_KEY          = 'wg_settings';
	const SHORTCODE_TAG       = 'wedding_gallery_upload';
	const TOKEN_QUERY_ARG     = 'wg_token';
	const DEFAULT_MAX_SIZE_MB = 50;

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
		$defaults = self::get_default_settings();
		$current  = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $current ) ) {
			$current = array();
		}

		$settings = wp_parse_args( $current, $defaults );
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
			'access_token'    => wp_generate_password( 32, false, false ),
			'max_upload_mb'   => self::DEFAULT_MAX_SIZE_MB,
		);
	}

	/**
	 * @return array<string, string|int>
	 */
	private function get_settings() {
		$current  = get_option( self::OPTION_KEY, array() );
		$defaults = self::get_default_settings();

		if ( ! is_array( $current ) ) {
			$current = array();
		}

		return wp_parse_args( $current, $defaults );
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
	 * @return string
	 */
	private static function get_upload_dir() {
		$upload_dir = wp_upload_dir();

		return trailingslashit( $upload_dir['basedir'] ) . 'wedding-gallery';
	}

	/**
	 * @return string
	 */
	private function get_upload_url() {
		$upload_dir = wp_upload_dir();

		return trailingslashit( $upload_dir['baseurl'] ) . 'wedding-gallery';
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

		$max_upload_mb = max( 1, absint( $settings['max_upload_mb'] ) );
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

		$max_upload_mb = max( 1, absint( $settings['max_upload_mb'] ) );
		$max_bytes     = $max_upload_mb * MB_IN_BYTES;
		$upload_dir    = self::get_upload_dir();
		$allowed_exts  = $this->get_allowed_extensions();
		$allowed_mimes = $this->get_allowed_mimes();

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

			$sanitized_name = sanitize_file_name( $name );
			if ( empty( $sanitized_name ) ) {
				$errors[] = __( 'Invalid filename detected.', 'wedding-gallery' );
				continue;
			}

			if ( $size > $max_bytes ) {
				$errors[] = sprintf(
					/* translators: %s: filename */
					__( '%s exceeds maximum upload size.', 'wedding-gallery' ),
					$sanitized_name
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

			$target_name = wp_unique_filename( $upload_dir, $sanitized_name );
			$target_path = trailingslashit( $upload_dir ) . $target_name;

			$moved = @move_uploaded_file( $tmp_name, $target_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( ! $moved ) {
				$errors[] = sprintf(
					/* translators: %s: filename */
					__( 'Could not save %s.', 'wedding-gallery' ),
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

		if ( isset( $_POST['rotate_token'] ) ) {
			$access_token = wp_generate_password( 32, false, false );
		}

		if ( empty( $access_token ) ) {
			$access_token = wp_generate_password( 32, false, false );
		}

		$settings['upload_page_url'] = $upload_page_url;
		$settings['access_token']    = $access_token;
		$settings['max_upload_mb']   = max( 1, $max_upload_mb );

		update_option( self::OPTION_KEY, $settings );

		$redirect = add_query_arg(
			array(
				'page'      => 'wedding-gallery',
				'wg_notice' => 'saved',
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
		$upload_url = $this->get_upload_url();

		if ( ! is_dir( $upload_dir ) ) {
			return array();
		}

		$files = array();
		$items = scandir( $upload_dir );

		if ( false === $items ) {
			return array();
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item || 'index.php' === $item ) {
				continue;
			}

			$path = trailingslashit( $upload_dir ) . $item;
			if ( ! is_file( $path ) ) {
				continue;
			}

			$type_data = wp_check_filetype( $item, $this->get_allowed_mimes() );

			$files[] = array(
				'name'      => $item,
				'path'      => $path,
				'url'       => trailingslashit( $upload_url ) . rawurlencode( $item ),
				'size'      => filesize( $path ),
				'mime_type' => isset( $type_data['type'] ) ? $type_data['type'] : '',
				'modified'  => filemtime( $path ),
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
		$max_upload_mb        = max( 1, absint( $settings['max_upload_mb'] ) );
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
