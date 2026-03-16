<?php
/**
 * Admin page template.
 *
 * Variables available:
 * - array  $settings
 * - string $protected_upload_url
 * - array  $uploads
 * - string $allowed_text
 * - int    $max_upload_mb
 * - int    $effective_max_upload_mb
 * - array  $upload_limits
 * - array  $key_status
 * - int    $legacy_plaintext_count
 * - array  $upload_health_summary
 * - string $notice
 * - int    $notice_count
 * - int    $notice_skipped
 * - string $current_tab
 * - int    $media_overview_generated_at
 * - bool   $media_overview_from_cache
 * - int    $media_overview_cache_ttl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wedding_gallery_settings_tab_url = add_query_arg(
	array(
		'page' => 'wedding-gallery',
		'tab'  => 'settings',
	),
	admin_url( 'admin.php' )
);
$wedding_gallery_media_tab_url    = add_query_arg(
	array(
		'page' => 'wedding-gallery',
		'tab'  => 'media',
	),
	admin_url( 'admin.php' )
);
$wedding_gallery_help_tab_url    = add_query_arg(
	array(
		'page' => 'wedding-gallery',
		'tab'  => 'help',
	),
	admin_url( 'admin.php' )
);
$wedding_gallery_bulk_download_url = admin_url( 'admin-post.php?action=wg_bulk_download' );
$wedding_gallery_bulk_delete_url   = admin_url( 'admin-post.php?action=wg_bulk_delete' );
$wedding_gallery_refresh_media_url = admin_url( 'admin-post.php?action=wg_refresh_media_overview' );
?>

<div class="wrap wg-admin-wrap">
	<h1><?php esc_html_e( 'Wedding Gallery', 'wedding-gallery' ); ?></h1>

	<nav class="nav-tab-wrapper wg-tab-nav" aria-label="<?php esc_attr_e( 'Wedding Gallery tabs', 'wedding-gallery' ); ?>">
		<a href="<?php echo esc_url( $wedding_gallery_settings_tab_url ); ?>" class="nav-tab <?php echo esc_attr( 'settings' === $current_tab ? 'nav-tab-active' : '' ); ?>">
			<?php esc_html_e( 'Settings', 'wedding-gallery' ); ?>
		</a>
		<a href="<?php echo esc_url( $wedding_gallery_media_tab_url ); ?>" class="nav-tab <?php echo esc_attr( 'media' === $current_tab ? 'nav-tab-active' : '' ); ?>">
			<?php esc_html_e( 'Media', 'wedding-gallery' ); ?>
		</a>
		<a href="<?php echo esc_url( $wedding_gallery_help_tab_url ); ?>" class="nav-tab <?php echo esc_attr( 'help' === $current_tab ? 'nav-tab-active' : '' ); ?>">
			<?php esc_html_e( 'Help', 'wedding-gallery' ); ?>
		</a>
	</nav>

	<?php if ( 'saved' === $notice ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved.', 'wedding-gallery' ); ?></p>
		</div>
	<?php elseif ( 'saved_clamped' === $notice ) : ?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<?php
				printf(
					/* translators: %d: effective max upload in MB */
					esc_html__( 'Settings saved. Max upload size was clamped to %d MB to match server/runtime safety limits.', 'wedding-gallery' ),
					(int) $effective_max_upload_mb
				);
				?>
			</p>
		</div>
	<?php elseif ( 'delete_success' === $notice ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					/* translators: %d: deleted files count */
					esc_html__( '%d file was deleted.', 'wedding-gallery' ),
					(int) $notice_count
				);
				?>
			</p>
		</div>
	<?php elseif ( 'delete_failed' === $notice ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e( 'File could not be deleted.', 'wedding-gallery' ); ?></p>
		</div>
	<?php elseif ( 'bulk_delete_success' === $notice ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					/* translators: %d: deleted files count */
					esc_html__( '%d files were deleted.', 'wedding-gallery' ),
					(int) $notice_count
				);
				?>
			</p>
		</div>
	<?php elseif ( 'bulk_delete_partial' === $notice ) : ?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<?php
				printf(
					/* translators: 1: deleted files count, 2: skipped files count */
					esc_html__( '%1$d files were deleted. %2$d files were skipped.', 'wedding-gallery' ),
					(int) $notice_count,
					(int) $notice_skipped
				);
				?>
			</p>
		</div>
	<?php elseif ( 'bulk_delete_failed' === $notice ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e( 'No files could be deleted.', 'wedding-gallery' ); ?></p>
		</div>
	<?php elseif ( 'bulk_no_selection' === $notice ) : ?>
		<div class="notice notice-warning is-dismissible">
			<p><?php esc_html_e( 'Please select at least one file first.', 'wedding-gallery' ); ?></p>
		</div>
	<?php elseif ( 'bulk_no_files' === $notice ) : ?>
		<div class="notice notice-warning is-dismissible">
			<p><?php esc_html_e( 'No encrypted files are currently available.', 'wedding-gallery' ); ?></p>
		</div>
	<?php elseif ( 'zip_unavailable' === $notice ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e( 'Bulk ZIP download is not available on this server (ZipArchive missing).', 'wedding-gallery' ); ?></p>
		</div>
	<?php elseif ( 'zip_failed' === $notice ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e( 'Could not create ZIP archive. Please try again.', 'wedding-gallery' ); ?></p>
		</div>
	<?php elseif ( 'bulk_download_failed' === $notice ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e( 'No files could be added to ZIP download.', 'wedding-gallery' ); ?></p>
		</div>
	<?php elseif ( 'media_refreshed' === $notice ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Media diagnostics were refreshed.', 'wedding-gallery' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $upload_limits['is_clamped'] ) ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php
				printf(
					/* translators: 1: configured MB, 2: runtime cap MB */
					esc_html__( 'Current configured max is %1$d MB, but this server can safely handle up to %2$d MB. Uploads are limited to the lower value.', 'wedding-gallery' ),
					(int) $upload_limits['configured_mb'],
					(int) $upload_limits['runtime_cap_mb']
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $legacy_plaintext_count ) ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php
				printf(
					/* translators: %d: legacy file count */
					esc_html__( 'Detected %d legacy plaintext file(s). They are no longer served by this plugin. Migrate or remove them from uploads/wedding-gallery.', 'wedding-gallery' ),
					(int) $legacy_plaintext_count
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( empty( $key_status['healthy'] ) ) : ?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Encryption key status is unhealthy. Media uploads/downloads may fail until the key configuration is repaired.', 'wedding-gallery' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $upload_health_summary['missing_metadata'] ) ) : ?>
		<div class="notice notice-error">
			<p>
				<?php
				printf(
					/* translators: %d: affected files count */
					esc_html__( '%d encrypted file(s) are missing metadata. Those files cannot be downloaded until metadata is restored.', 'wedding-gallery' ),
					(int) $upload_health_summary['missing_metadata']
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $upload_health_summary['invalid_metadata'] ) ) : ?>
		<div class="notice notice-error">
			<p>
				<?php
				printf(
					/* translators: %d: affected files count */
					esc_html__( '%d file(s) have damaged or unreadable metadata. Download may fail until repaired from backup.', 'wedding-gallery' ),
					(int) $upload_health_summary['invalid_metadata']
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $upload_health_summary['metadata_tampered'] ) ) : ?>
		<div class="notice notice-error">
			<p>
				<?php
				printf(
					/* translators: %d: affected files count */
					esc_html__( '%d file(s) failed metadata integrity checks. They may have been modified or corrupted.', 'wedding-gallery' ),
					(int) $upload_health_summary['metadata_tampered']
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $upload_health_summary['unsupported_key_version'] ) ) : ?>
		<div class="notice notice-error">
			<p>
				<?php
				printf(
					/* translators: %d: affected files count */
					esc_html__( '%d file(s) require an encryption key version that is not available on this site.', 'wedding-gallery' ),
					(int) $upload_health_summary['unsupported_key_version']
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $upload_health_summary['legacy_metadata_plaintext'] ) ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php
				printf(
					/* translators: %d: affected files count */
					esc_html__( '%d file(s) still use legacy plaintext metadata. They remain downloadable, but re-uploading improves privacy.', 'wedding-gallery' ),
					(int) $upload_health_summary['legacy_metadata_plaintext']
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $upload_health_summary['decrypt_failed'] ) ) : ?>
		<div class="notice notice-error">
			<p>
				<?php
				printf(
					/* translators: %d: affected files count */
					esc_html__( '%d file(s) failed the decryption health check. The encrypted file or metadata may be corrupted.', 'wedding-gallery' ),
					(int) $upload_health_summary['decrypt_failed']
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( 'settings' === $current_tab ) : ?>
		<div class="wg-settings-card">
			<h2><?php esc_html_e( 'Settings', 'wedding-gallery' ); ?></h2>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="wg_save_settings" />
				<?php wp_nonce_field( 'wg_save_settings', 'wg_save_settings_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="upload_page_url"><?php esc_html_e( 'Upload Page URL', 'wedding-gallery' ); ?></label>
							</th>
							<td>
								<input
									type="url"
									id="upload_page_url"
									name="upload_page_url"
									class="regular-text"
									value="<?php echo esc_attr( $settings['upload_page_url'] ); ?>"
									placeholder="https://example.com/upload-page/"
								/>
								<p class="description">
									<?php
									printf(
										/* translators: %s: shortcode */
										esc_html__( 'Add shortcode %s to this page.', 'wedding-gallery' ),
										'[wedding_gallery_upload]'
									);
									?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="access_token"><?php esc_html_e( 'Access Token', 'wedding-gallery' ); ?></label>
							</th>
							<td>
								<input
									type="text"
									id="access_token"
									name="access_token"
									class="regular-text code"
									value="<?php echo esc_attr( $settings['access_token'] ); ?>"
								/>
								<p>
									<button type="submit" name="rotate_token" value="1" class="button">
										<?php esc_html_e( 'Regenerate Guest Link', 'wedding-gallery' ); ?>
									</button>
								</p>
								<p class="description">
									<?php esc_html_e( 'Anyone with the protected link can upload. Regenerating token invalidates old links and QR prints.', 'wedding-gallery' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="max_upload_mb"><?php esc_html_e( 'Max Upload Size (MB)', 'wedding-gallery' ); ?></label>
							</th>
							<td>
								<input
									type="number"
									id="max_upload_mb"
									name="max_upload_mb"
									min="1"
									step="1"
									value="<?php echo esc_attr( $max_upload_mb ); ?>"
								/>
								<p class="description">
									<?php
									printf(
										/* translators: 1: allowed file types, 2: effective MB */
										esc_html__( 'Allowed file types: %1$s. Effective per-file limit: %2$d MB.', 'wedding-gallery' ),
										esc_html( $allowed_text ),
										(int) $effective_max_upload_mb
									);
									?>
								</p>
								<p class="description">
									<?php
									printf(
										/* translators: 1: upload_max_filesize MB, 2: post_max_size MB, 3: memory_limit MB, 4: memory-safe MB */
										esc_html__( 'Runtime limits (MB): upload_max_filesize=%1$d, post_max_size=%2$d, memory_limit=%3$d, memory-safe ceiling=%4$d.', 'wedding-gallery' ),
										(int) $upload_limits['upload_max_mb'],
										(int) $upload_limits['post_max_mb'],
										(int) $upload_limits['memory_limit_mb'],
										(int) $upload_limits['memory_safe_mb']
									);
									?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="cleanup_on_uninstall"><?php esc_html_e( 'Cleanup On Uninstall', 'wedding-gallery' ); ?></label>
							</th>
							<td>
								<label for="cleanup_on_uninstall">
									<input
										type="checkbox"
										id="cleanup_on_uninstall"
										name="cleanup_on_uninstall"
										value="1"
										<?php checked( ! empty( $settings['cleanup_on_uninstall'] ) ); ?>
									/>
									<?php esc_html_e( 'Yes, permanently delete wedding media + metadata from uploads/wedding-gallery when uninstalling this plugin.', 'wedding-gallery' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Leave unchecked to keep files on disk after uninstall.', 'wedding-gallery' ); ?>
								</p>
								<p class="description">
									<?php esc_html_e( 'This setting has no effect on normal plugin deactivation.', 'wedding-gallery' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Encryption Key', 'wedding-gallery' ); ?></th>
							<td>
								<p>
									<?php
									printf(
										/* translators: 1: healthy/unhealthy, 2: version, 3: key fingerprint */
										esc_html__( 'Status: %1$s | Version: %2$d | Fingerprint: %3$s', 'wedding-gallery' ),
										! empty( $key_status['healthy'] ) ? esc_html__( 'Healthy', 'wedding-gallery' ) : esc_html__( 'Problem', 'wedding-gallery' ),
										(int) $key_status['key_version'],
										esc_html( (string) $key_status['fingerprint'] )
									);
									?>
								</p>
								<p class="description">
									<?php esc_html_e( 'Backup requirement: keep database/plugin options and uploads/wedding-gallery together in the same backup/restore set. Restoring only one can make media undecryptable.', 'wedding-gallery' ); ?>
								</p>
								<p class="description">
									<?php esc_html_e( 'Operational tip: verify restores on a staging site before the event date.', 'wedding-gallery' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( __( 'Save Settings', 'wedding-gallery' ) ); ?>
			</form>
		</div>

		<div class="wg-settings-card">
			<h2><?php esc_html_e( 'Protected Upload URL (QR Target)', 'wedding-gallery' ); ?></h2>
			<?php if ( ! empty( $protected_upload_url ) ) : ?>
				<p><code><?php echo esc_html( $protected_upload_url ); ?></code></p>
				<p>
					<label for="wg_protected_upload_url"><strong><?php esc_html_e( 'Guest Upload Link', 'wedding-gallery' ); ?></strong></label><br />
					<input
						id="wg_protected_upload_url"
						type="text"
						class="regular-text code"
						readonly
						value="<?php echo esc_attr( $protected_upload_url ); ?>"
						style="max-width: 100%; width: 520px;"
					/>
					<button type="button" class="button" id="wg_copy_link">
						<?php esc_html_e( 'Copy Link', 'wedding-gallery' ); ?>
					</button>
				</p>
				<p><strong><?php esc_html_e( 'QR Code', 'wedding-gallery' ); ?></strong></p>
				<div id="wg_qr_code" class="wg-qr-box" aria-label="<?php esc_attr_e( 'Guest upload QR code', 'wedding-gallery' ); ?>"></div>
				<p>
					<a class="button" id="wg_view_qr" href="#" target="_blank" rel="noopener" style="pointer-events: none; opacity: 0.7;">
						<?php esc_html_e( 'View QR Code', 'wedding-gallery' ); ?>
					</a>
					<a class="button button-secondary" id="wg_download_qr" href="#" download="wedding-gallery-qr.png" style="pointer-events: none; opacity: 0.7;">
						<?php esc_html_e( 'Download QR PNG', 'wedding-gallery' ); ?>
					</a>
				</p>
				<p class="description">
					<?php esc_html_e( 'QR code is generated locally in your browser. The protected upload URL is not sent to third-party QR services.', 'wedding-gallery' ); ?>
				</p>
				<p class="description">
					<?php esc_html_e( 'Print tip: open the QR image and print at high quality for guest signage.', 'wedding-gallery' ); ?>
				</p>
				<div class="wg-help-box">
					<p><strong><?php esc_html_e( 'How to use the QR code', 'wedding-gallery' ); ?></strong></p>
					<ol>
						<li><?php esc_html_e( 'Set the Upload Page URL and click Save Settings to generate the current guest link.', 'wedding-gallery' ); ?></li>
						<li><?php esc_html_e( 'Use Copy Link for messages, or print the QR code and place it at your wedding.', 'wedding-gallery' ); ?></li>
						<li><?php esc_html_e( 'Guests scan the QR code on iPhone or Android and upload from camera, photo library/gallery, or files.', 'wedding-gallery' ); ?></li>
						<li><?php esc_html_e( 'If you regenerate the guest link/token, all older printed QR codes stop working and must be reprinted.', 'wedding-gallery' ); ?></li>
					</ol>
				</div>
			<?php else : ?>
				<p><?php esc_html_e( 'Set an Upload Page URL to generate the protected link.', 'wedding-gallery' ); ?></p>
			<?php endif; ?>
		</div>
	<?php elseif ( 'media' === $current_tab ) : ?>
		<div class="wg-settings-card">
			<h2><?php esc_html_e( 'Wedding Media', 'wedding-gallery' ); ?></h2>
			<?php if ( $media_overview_generated_at > 0 ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: 1: last updated timestamp, 2: cache source label, 3: cache ttl in seconds */
						esc_html__( 'Overview last updated: %1$s. Source: %2$s. Cache TTL: %3$d seconds.', 'wedding-gallery' ),
						esc_html( wp_date( 'Y-m-d H:i:s', (int) $media_overview_generated_at ) ),
						$media_overview_from_cache ? esc_html__( 'Cached', 'wedding-gallery' ) : esc_html__( 'Live', 'wedding-gallery' ),
						(int) $media_overview_cache_ttl
					);
					?>
				</p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( $wedding_gallery_refresh_media_url ); ?>" class="wg-inline-refresh-form">
				<?php wp_nonce_field( 'wg_refresh_media_overview', 'wg_refresh_media_overview_nonce' ); ?>
				<button type="submit" class="button"><?php esc_html_e( 'Refresh Diagnostics', 'wedding-gallery' ); ?></button>
			</form>
			<?php if ( empty( $uploads ) ) : ?>
				<p><?php esc_html_e( 'No uploads yet.', 'wedding-gallery' ); ?></p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( $wedding_gallery_bulk_download_url ); ?>" id="wg_media_bulk_form">
					<?php wp_nonce_field( 'wg_bulk_download', 'wg_bulk_download_nonce' ); ?>
					<?php wp_nonce_field( 'wg_bulk_delete', 'wg_bulk_delete_nonce' ); ?>

					<div class="wg-media-toolbar">
						<button type="submit" class="button button-primary" name="wg_scope" value="selected" data-wg-requires-selection="1">
							<?php esc_html_e( 'Download Selected (ZIP)', 'wedding-gallery' ); ?>
						</button>
						<button type="submit" class="button" name="wg_scope" value="all">
							<?php esc_html_e( 'Download All (ZIP)', 'wedding-gallery' ); ?>
						</button>
						<button type="submit" class="button button-secondary wg-delete-button" name="wg_scope" value="selected" formaction="<?php echo esc_url( $wedding_gallery_bulk_delete_url ); ?>" data-wg-requires-selection="1" data-wg-delete-scope="selected">
							<?php esc_html_e( 'Delete Selected', 'wedding-gallery' ); ?>
						</button>
						<button type="submit" class="button wg-delete-button" name="wg_scope" value="all" formaction="<?php echo esc_url( $wedding_gallery_bulk_delete_url ); ?>" data-wg-delete-scope="all">
							<?php esc_html_e( 'Delete All', 'wedding-gallery' ); ?>
						</button>
					</div>

					<table class="widefat striped wg-media-table">
						<thead>
							<tr>
								<th class="check-column">
									<label class="screen-reader-text" for="wg_select_all_media"><?php esc_html_e( 'Select all files', 'wedding-gallery' ); ?></label>
									<input type="checkbox" id="wg_select_all_media" />
								</th>
								<th><?php esc_html_e( 'Preview', 'wedding-gallery' ); ?></th>
								<th><?php esc_html_e( 'Filename', 'wedding-gallery' ); ?></th>
								<th><?php esc_html_e( 'Media Type', 'wedding-gallery' ); ?></th>
								<th><?php esc_html_e( 'Size', 'wedding-gallery' ); ?></th>
								<th><?php esc_html_e( 'Uploaded', 'wedding-gallery' ); ?></th>
								<th><?php esc_html_e( 'Health', 'wedding-gallery' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'wedding-gallery' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $uploads as $wedding_gallery_file ) : ?>
								<?php
								$wedding_gallery_stored_file   = isset( $wedding_gallery_file['stored_file'] ) ? sanitize_file_name( (string) $wedding_gallery_file['stored_file'] ) : '';
								$wedding_gallery_is_encrypted  = '.wgenc' === substr( $wedding_gallery_stored_file, -6 );
								$wedding_gallery_is_selectable = $wedding_gallery_is_encrypted;
								$wedding_gallery_media_kind    = isset( $wedding_gallery_file['media_kind'] ) ? sanitize_key( (string) $wedding_gallery_file['media_kind'] ) : 'file';
								$wedding_gallery_health_status = isset( $wedding_gallery_file['health_status'] ) ? (string) $wedding_gallery_file['health_status'] : '';
								$wedding_gallery_health_label  = __( 'Unknown', 'wedding-gallery' );
								$wedding_gallery_health_class  = 'wg-health-warning';
								$wedding_gallery_can_download  = ! empty( $wedding_gallery_file['can_download'] );
								$wedding_gallery_download_url  = '';
								$wedding_gallery_view_url      = '';

								if ( $wedding_gallery_can_download && '' !== $wedding_gallery_stored_file ) {
									$wedding_gallery_download_url = wp_nonce_url(
										add_query_arg(
											array(
												'action' => 'wg_download_upload',
												'file'   => $wedding_gallery_stored_file,
											),
											admin_url( 'admin-post.php' )
										),
										'wg_download_file_' . $wedding_gallery_stored_file
									);
									$wedding_gallery_view_url = wp_nonce_url(
										add_query_arg(
											array(
												'action' => 'wg_view_upload',
												'file'   => $wedding_gallery_stored_file,
											),
											admin_url( 'admin-post.php' )
										),
										'wg_view_file_' . $wedding_gallery_stored_file
									);
								}

								switch ( $wedding_gallery_health_status ) {
									case 'ok':
										$wedding_gallery_health_label = __( 'Healthy', 'wedding-gallery' );
										$wedding_gallery_health_class = 'wg-health-ok';
										break;
									case 'missing_metadata':
										$wedding_gallery_health_label = __( 'Missing Metadata', 'wedding-gallery' );
										$wedding_gallery_health_class = 'wg-health-error';
										break;
									case 'invalid_metadata':
										$wedding_gallery_health_label = __( 'Metadata Damaged', 'wedding-gallery' );
										$wedding_gallery_health_class = 'wg-health-error';
										break;
									case 'metadata_tampered':
										$wedding_gallery_health_label = __( 'Integrity Failed', 'wedding-gallery' );
										$wedding_gallery_health_class = 'wg-health-error';
										break;
									case 'unsupported_key_version':
										$wedding_gallery_health_label = __( 'Key Version Mismatch', 'wedding-gallery' );
										$wedding_gallery_health_class = 'wg-health-error';
										break;
									case 'decrypt_failed':
										$wedding_gallery_health_label = __( 'Decrypt Failed', 'wedding-gallery' );
										$wedding_gallery_health_class = 'wg-health-error';
										break;
									case 'legacy_metadata_plaintext':
										$wedding_gallery_health_label = __( 'Legacy Metadata', 'wedding-gallery' );
										$wedding_gallery_health_class = 'wg-health-warning';
										break;
									case 'legacy_plaintext':
										$wedding_gallery_health_label = __( 'Legacy Plaintext', 'wedding-gallery' );
										$wedding_gallery_health_class = 'wg-health-warning';
										break;
								}

								$wedding_gallery_type_label = __( 'File', 'wedding-gallery' );
								if ( 'photo' === $wedding_gallery_media_kind ) {
									$wedding_gallery_type_label = __( 'Photo', 'wedding-gallery' );
								} elseif ( 'video' === $wedding_gallery_media_kind ) {
									$wedding_gallery_type_label = __( 'Video', 'wedding-gallery' );
								}
								?>
								<tr>
									<th class="check-column">
										<?php if ( $wedding_gallery_is_selectable ) : ?>
											<label class="screen-reader-text" for="<?php echo esc_attr( 'wg_media_' . md5( $wedding_gallery_stored_file ) ); ?>">
												<?php esc_html_e( 'Select media file', 'wedding-gallery' ); ?>
											</label>
											<input
												id="<?php echo esc_attr( 'wg_media_' . md5( $wedding_gallery_stored_file ) ); ?>"
												type="checkbox"
												name="wg_files[]"
												value="<?php echo esc_attr( $wedding_gallery_stored_file ); ?>"
												class="wg-media-select"
											/>
										<?php else : ?>
											<span aria-hidden="true">-</span>
										<?php endif; ?>
									</th>
									<td>
										<?php if ( 'photo' === $wedding_gallery_media_kind && $wedding_gallery_can_download && '' !== $wedding_gallery_view_url ) : ?>
											<img src="<?php echo esc_url( $wedding_gallery_view_url ); ?>" alt="" loading="lazy" class="wg-media-thumb" />
										<?php elseif ( 'video' === $wedding_gallery_media_kind ) : ?>
											<span class="dashicons dashicons-video-alt3 wg-media-icon" aria-hidden="true"></span>
										<?php else : ?>
											<span class="dashicons dashicons-media-default wg-media-icon" aria-hidden="true"></span>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( (string) $wedding_gallery_file['name'] ); ?></td>
									<td>
										<?php echo esc_html( $wedding_gallery_type_label ); ?>
										<?php if ( ! empty( $wedding_gallery_file['mime_type'] ) ) : ?>
											<br /><small><?php echo esc_html( (string) $wedding_gallery_file['mime_type'] ); ?></small>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( size_format( (int) $wedding_gallery_file['size'] ) ); ?></td>
									<td><?php echo esc_html( wp_date( 'Y-m-d H:i', (int) $wedding_gallery_file['modified'] ) ); ?></td>
									<td>
										<span class="wg-health-badge <?php echo esc_attr( $wedding_gallery_health_class ); ?>"><?php echo esc_html( $wedding_gallery_health_label ); ?></span><br />
										<small><?php echo esc_html( isset( $wedding_gallery_file['health_message'] ) ? (string) $wedding_gallery_file['health_message'] : '' ); ?></small>
									</td>
									<td class="wg-row-actions">
										<?php if ( $wedding_gallery_can_download && '' !== $wedding_gallery_view_url ) : ?>
											<a class="button button-small" href="<?php echo esc_url( $wedding_gallery_view_url ); ?>" target="_blank" rel="noopener">
												<?php esc_html_e( 'View', 'wedding-gallery' ); ?>
											</a>
										<?php endif; ?>
										<?php if ( $wedding_gallery_can_download && '' !== $wedding_gallery_download_url ) : ?>
											<a class="button button-small" href="<?php echo esc_url( $wedding_gallery_download_url ); ?>">
												<?php esc_html_e( 'Download', 'wedding-gallery' ); ?>
											</a>
										<?php endif; ?>
										<?php if ( $wedding_gallery_is_encrypted ) : ?>
											<?php
											$wedding_gallery_delete_url = wp_nonce_url(
												add_query_arg(
													array(
														'action' => 'wg_delete_upload',
														'file'   => $wedding_gallery_stored_file,
													),
													admin_url( 'admin-post.php' )
												),
												'wg_delete_file_' . $wedding_gallery_stored_file
											);
											?>
											<a href="<?php echo esc_url( $wedding_gallery_delete_url ); ?>" class="button button-small wg-delete-button" data-wg-delete-scope="single">
												<?php esc_html_e( 'Delete', 'wedding-gallery' ); ?>
											</a>
										<?php else : ?>
											<span><?php esc_html_e( 'Unavailable', 'wedding-gallery' ); ?></span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</form>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<div class="wg-settings-card">
			<h2><?php esc_html_e( 'How this plugin works', 'wedding-gallery' ); ?></h2>
			<p><?php esc_html_e( 'Wedding Gallery provides a protected guest upload page for wedding photos and videos. Guests can only upload through the tokenized link or QR code. Uploaded files are stored encrypted in uploads/wedding-gallery and can be managed by admins in the Media tab.', 'wedding-gallery' ); ?></p>
			<ol class="wg-ops-list">
				<li><?php esc_html_e( 'Create a WordPress page with shortcode [wedding_gallery_upload].', 'wedding-gallery' ); ?></li>
				<li><?php esc_html_e( 'Set that page URL in Settings and save.', 'wedding-gallery' ); ?></li>
				<li><?php esc_html_e( 'Copy the protected guest link or print the QR code.', 'wedding-gallery' ); ?></li>
				<li><?php esc_html_e( 'Guests upload photos/videos from phone camera, gallery/library, or files.', 'wedding-gallery' ); ?></li>
				<li><?php esc_html_e( 'Use the Media tab to view health status, preview, download, or delete uploads.', 'wedding-gallery' ); ?></li>
			</ol>
		</div>

		<div class="wg-settings-card">
			<h2><?php esc_html_e( 'Theme and page design info', 'wedding-gallery' ); ?></h2>
			<p><?php esc_html_e( 'The visual area above the upload card (for example hero images, logo, page header, and footer) is controlled by your active theme or page builder. The plugin itself renders the protected upload form card via shortcode inside that page.', 'wedding-gallery' ); ?></p>
		</div>

		<div class="wg-settings-card">
			<h2><?php esc_html_e( 'Pilot handoff notes', 'wedding-gallery' ); ?></h2>
			<ul class="wg-ops-list">
				<li><?php esc_html_e( 'Backup and restore database + uploads/wedding-gallery together to keep media decryptable.', 'wedding-gallery' ); ?></li>
				<li><?php esc_html_e( 'Regenerating the guest token invalidates previous guest links and printed QR codes.', 'wedding-gallery' ); ?></li>
				<li><?php esc_html_e( 'Cleanup On Uninstall runs only when the plugin is uninstalled, not when it is deactivated.', 'wedding-gallery' ); ?></li>
				<li><?php esc_html_e( 'On shared hosting, runtime and memory limits can reduce effective max upload size.', 'wedding-gallery' ); ?></li>
			</ul>
		</div>

		<div class="wg-settings-card">
			<h2><?php esc_html_e( 'Troubleshooting checklist', 'wedding-gallery' ); ?></h2>
			<ul class="wg-ops-list">
				<li><?php esc_html_e( 'If guests cannot open the upload page, check that the URL includes a valid wg_token.', 'wedding-gallery' ); ?></li>
				<li><?php esc_html_e( 'If uploads fail, verify allowed file type and max file size limits.', 'wedding-gallery' ); ?></li>
				<li><?php esc_html_e( 'If media cannot be downloaded, refresh diagnostics and check file health status in the Media tab.', 'wedding-gallery' ); ?></li>
				<li><?php esc_html_e( 'If decryption errors occur after migration, verify database/options and uploads were restored together.', 'wedding-gallery' ); ?></li>
			</ul>
		</div>
	<?php endif; ?>
</div>
