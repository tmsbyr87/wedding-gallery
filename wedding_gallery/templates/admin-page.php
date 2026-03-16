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
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Wedding Gallery', 'wedding_gallery' ); ?></h1>

	<?php if ( 'saved' === $notice ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved.', 'wedding_gallery' ); ?></p>
		</div>
	<?php elseif ( 'saved_clamped' === $notice ) : ?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<?php
				printf(
					/* translators: %d: effective max upload in MB */
					esc_html__( 'Settings saved. Max upload size was clamped to %d MB to match server/runtime safety limits.', 'wedding_gallery' ),
					(int) $effective_max_upload_mb
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $upload_limits['is_clamped'] ) ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php
				printf(
					/* translators: 1: configured MB, 2: runtime cap MB */
					esc_html__( 'Current configured max is %1$d MB, but this server can safely handle up to %2$d MB. Uploads are limited to the lower value.', 'wedding_gallery' ),
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
					esc_html__( 'Detected %d legacy plaintext file(s). They are no longer served by this plugin. Migrate or remove them from uploads/wedding-gallery.', 'wedding_gallery' ),
					(int) $legacy_plaintext_count
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( empty( $key_status['healthy'] ) ) : ?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Encryption key status is unhealthy. Media uploads/downloads may fail until the key configuration is repaired.', 'wedding_gallery' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $upload_health_summary['missing_metadata'] ) ) : ?>
		<div class="notice notice-error">
			<p>
				<?php
				printf(
					/* translators: %d: affected files count */
					esc_html__( '%d encrypted file(s) are missing metadata. Those files cannot be downloaded until metadata is restored.', 'wedding_gallery' ),
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
					esc_html__( '%d file(s) have damaged or unreadable metadata. Download may fail until repaired from backup.', 'wedding_gallery' ),
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
					esc_html__( '%d file(s) failed metadata integrity checks. They may have been modified or corrupted.', 'wedding_gallery' ),
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
					esc_html__( '%d file(s) require an encryption key version that is not available on this site.', 'wedding_gallery' ),
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
					esc_html__( '%d file(s) still use legacy plaintext metadata. They remain downloadable, but re-uploading improves privacy.', 'wedding_gallery' ),
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
					esc_html__( '%d file(s) failed the decryption health check. The encrypted file or metadata may be corrupted.', 'wedding_gallery' ),
					(int) $upload_health_summary['decrypt_failed']
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<style>
	.wg-health-badge {
		display: inline-block;
		padding: 2px 8px;
		border-radius: 999px;
		font-size: 12px;
		font-weight: 600;
		line-height: 1.7;
	}
	.wg-health-ok { background: #e8f7ec; color: #1d5e30; }
	.wg-health-warning { background: #fff7e6; color: #8a5a00; }
	.wg-health-error { background: #fdecea; color: #8b1f17; }
	.wg-ops-list {
		margin: 0;
		padding-left: 20px;
	}
	.wg-ops-list li {
		margin-bottom: 6px;
	}
	</style>

	<div class="notice notice-info">
		<p><strong><?php esc_html_e( 'Pilot Handoff Notes', 'wedding_gallery' ); ?></strong></p>
		<ul class="wg-ops-list">
			<li><?php esc_html_e( 'Backup and restore database + uploads/wedding-gallery together to keep media decryptable.', 'wedding_gallery' ); ?></li>
			<li><?php esc_html_e( 'Regenerating the guest token invalidates previous guest links and printed QR codes.', 'wedding_gallery' ); ?></li>
			<li><?php esc_html_e( 'Cleanup On Uninstall runs only when the plugin is uninstalled, not when it is deactivated.', 'wedding_gallery' ); ?></li>
			<li><?php esc_html_e( 'On shared hosting, runtime and memory limits can reduce effective max upload size.', 'wedding_gallery' ); ?></li>
		</ul>
	</div>

	<h2><?php esc_html_e( 'Settings', 'wedding_gallery' ); ?></h2>
	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="wg_save_settings" />
		<?php wp_nonce_field( 'wg_save_settings', 'wg_save_settings_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="upload_page_url"><?php esc_html_e( 'Upload Page URL', 'wedding_gallery' ); ?></label>
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
								esc_html__( 'Add shortcode %s to this page.', 'wedding_gallery' ),
								'[wedding_gallery_upload]'
							);
							?>
						</p>
					</td>
				</tr>
					<tr>
						<th scope="row">
							<label for="access_token"><?php esc_html_e( 'Access Token', 'wedding_gallery' ); ?></label>
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
										<?php esc_html_e( 'Regenerate Guest Link', 'wedding_gallery' ); ?>
									</button>
								</p>
								<p class="description">
									<?php esc_html_e( 'Anyone with the protected link can upload. Regenerating token invalidates old links and QR prints.', 'wedding_gallery' ); ?>
								</p>
							</td>
						</tr>
					<tr>
						<th scope="row">
							<label for="max_upload_mb"><?php esc_html_e( 'Max Upload Size (MB)', 'wedding_gallery' ); ?></label>
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
									esc_html__( 'Allowed file types: %1$s. Effective per-file limit: %2$d MB.', 'wedding_gallery' ),
									esc_html( $allowed_text ),
									(int) $effective_max_upload_mb
								);
								?>
							</p>
							<p class="description">
								<?php
								printf(
									/* translators: 1: upload_max_filesize MB, 2: post_max_size MB, 3: memory_limit MB, 4: memory-safe MB */
									esc_html__( 'Runtime limits (MB): upload_max_filesize=%1$d, post_max_size=%2$d, memory_limit=%3$d, memory-safe ceiling=%4$d.', 'wedding_gallery' ),
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
							<label for="cleanup_on_uninstall"><?php esc_html_e( 'Cleanup On Uninstall', 'wedding_gallery' ); ?></label>
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
								<?php esc_html_e( 'Yes, permanently delete wedding media + metadata from uploads/wedding-gallery when uninstalling this plugin.', 'wedding_gallery' ); ?>
							</label>
								<p class="description">
									<?php esc_html_e( 'Leave unchecked to keep files on disk after uninstall.', 'wedding_gallery' ); ?>
								</p>
								<p class="description">
									<?php esc_html_e( 'This setting has no effect on normal plugin deactivation.', 'wedding_gallery' ); ?>
								</p>
							</td>
						</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Encryption Key', 'wedding_gallery' ); ?></th>
						<td>
							<p>
								<?php
								printf(
									/* translators: 1: healthy/unhealthy, 2: version, 3: key fingerprint */
									esc_html__( 'Status: %1$s | Version: %2$d | Fingerprint: %3$s', 'wedding_gallery' ),
									! empty( $key_status['healthy'] ) ? esc_html__( 'Healthy', 'wedding_gallery' ) : esc_html__( 'Problem', 'wedding_gallery' ),
									(int) $key_status['key_version'],
									esc_html( (string) $key_status['fingerprint'] )
								);
								?>
							</p>
								<p class="description">
									<?php esc_html_e( 'Backup requirement: keep database/plugin options and uploads/wedding-gallery together in the same backup/restore set. Restoring only one can make media undecryptable.', 'wedding_gallery' ); ?>
								</p>
								<p class="description">
									<?php esc_html_e( 'Operational tip: verify restores on a staging site before the event date.', 'wedding_gallery' ); ?>
								</p>
							</td>
						</tr>
				</tbody>
			</table>

		<?php submit_button( __( 'Save Settings', 'wedding_gallery' ) ); ?>
	</form>

	<h2><?php esc_html_e( 'Protected Upload URL (QR Target)', 'wedding_gallery' ); ?></h2>
	<?php if ( ! empty( $protected_upload_url ) ) : ?>
		<p>
			<code><?php echo esc_html( $protected_upload_url ); ?></code>
		</p>
		<p>
			<label for="wg_protected_upload_url"><strong><?php esc_html_e( 'Guest Upload Link', 'wedding_gallery' ); ?></strong></label><br />
			<input
				id="wg_protected_upload_url"
				type="text"
				class="regular-text code"
				readonly
				value="<?php echo esc_attr( $protected_upload_url ); ?>"
				style="max-width: 100%; width: 520px;"
			/>
			<button type="button" class="button" id="wg_copy_link">
				<?php esc_html_e( 'Copy Link', 'wedding_gallery' ); ?>
			</button>
		</p>
			<p><strong><?php esc_html_e( 'QR Code', 'wedding_gallery' ); ?></strong></p>
			<div
				id="wg_qr_code"
				style="display: inline-block; border: 1px solid #dcdcde; padding: 8px; background: #fff; line-height: 0;"
				aria-label="<?php esc_attr_e( 'Guest upload QR code', 'wedding_gallery' ); ?>"
			></div>
			<p>
				<a class="button" id="wg_view_qr" href="#" target="_blank" rel="noopener" style="pointer-events: none; opacity: 0.7;">
					<?php esc_html_e( 'View QR Code', 'wedding_gallery' ); ?>
				</a>
				<a class="button button-secondary" id="wg_download_qr" href="#" download="wedding-gallery-qr.png" style="pointer-events: none; opacity: 0.7;">
					<?php esc_html_e( 'Download QR PNG', 'wedding_gallery' ); ?>
				</a>
			</p>
			<p class="description">
				<?php esc_html_e( 'QR code is generated locally in your browser. The protected upload URL is not sent to third-party QR services.', 'wedding_gallery' ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'Print tip: open the QR image and print at high quality for guest signage.', 'wedding_gallery' ); ?>
			</p>
			<div style="margin-top: 14px; background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 4px; padding: 12px 14px; max-width: 760px;">
				<p style="margin: 0 0 8px;"><strong><?php esc_html_e( 'How to use the QR code', 'wedding_gallery' ); ?></strong></p>
				<ol style="margin: 0; padding-left: 20px;">
					<li><?php esc_html_e( 'Set the Upload Page URL and click Save Settings to generate the current guest link.', 'wedding_gallery' ); ?></li>
					<li><?php esc_html_e( 'Use Copy Link for messages, or print the QR code and place it at your wedding.', 'wedding_gallery' ); ?></li>
					<li><?php esc_html_e( 'Guests scan the QR code on iPhone or Android and upload from camera, photo library/gallery, or files.', 'wedding_gallery' ); ?></li>
					<li><?php esc_html_e( 'If you regenerate the guest link/token, all older printed QR codes stop working and must be reprinted.', 'wedding_gallery' ); ?></li>
				</ol>
			</div>
		<?php else : ?>
			<p><?php esc_html_e( 'Set an Upload Page URL to generate the protected link.', 'wedding_gallery' ); ?></p>
		<?php endif; ?>

	<h2><?php esc_html_e( 'Uploaded Files', 'wedding_gallery' ); ?></h2>
		<?php if ( empty( $uploads ) ) : ?>
			<p><?php esc_html_e( 'No uploads yet.', 'wedding_gallery' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Filename', 'wedding_gallery' ); ?></th>
						<th><?php esc_html_e( 'Type', 'wedding_gallery' ); ?></th>
						<th><?php esc_html_e( 'Size', 'wedding_gallery' ); ?></th>
						<th><?php esc_html_e( 'Uploaded', 'wedding_gallery' ); ?></th>
						<th><?php esc_html_e( 'Health', 'wedding_gallery' ); ?></th>
						<th><?php esc_html_e( 'Action', 'wedding_gallery' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $uploads as $file ) : ?>
						<?php
						$health_status = isset( $file['health_status'] ) ? (string) $file['health_status'] : '';
						$health_label  = __( 'Unknown', 'wedding_gallery' );
						$health_class  = 'wg-health-warning';
						switch ( $health_status ) {
							case 'ok':
								$health_label = __( 'Healthy', 'wedding_gallery' );
								$health_class = 'wg-health-ok';
								break;
							case 'missing_metadata':
								$health_label = __( 'Missing Metadata', 'wedding_gallery' );
								$health_class = 'wg-health-error';
								break;
							case 'invalid_metadata':
								$health_label = __( 'Metadata Damaged', 'wedding_gallery' );
								$health_class = 'wg-health-error';
								break;
							case 'metadata_tampered':
								$health_label = __( 'Integrity Failed', 'wedding_gallery' );
								$health_class = 'wg-health-error';
								break;
							case 'unsupported_key_version':
								$health_label = __( 'Key Version Mismatch', 'wedding_gallery' );
								$health_class = 'wg-health-error';
								break;
							case 'decrypt_failed':
								$health_label = __( 'Decrypt Failed', 'wedding_gallery' );
								$health_class = 'wg-health-error';
								break;
							case 'legacy_metadata_plaintext':
								$health_label = __( 'Legacy Metadata', 'wedding_gallery' );
								$health_class = 'wg-health-warning';
								break;
							case 'legacy_plaintext':
								$health_label = __( 'Legacy Plaintext', 'wedding_gallery' );
								$health_class = 'wg-health-warning';
								break;
						}
						?>
						<tr>
							<td><?php echo esc_html( $file['name'] ); ?></td>
							<td><?php echo esc_html( $file['mime_type'] ); ?></td>
							<td><?php echo esc_html( size_format( (int) $file['size'] ) ); ?></td>
							<td><?php echo esc_html( wp_date( 'Y-m-d H:i', (int) $file['modified'] ) ); ?></td>
							<td>
								<span class="wg-health-badge <?php echo esc_attr( $health_class ); ?>"><?php echo esc_html( $health_label ); ?></span><br />
								<small><?php echo esc_html( isset( $file['health_message'] ) ? (string) $file['health_message'] : '' ); ?></small>
							</td>
							<td>
								<?php if ( ! empty( $file['can_download'] ) ) : ?>
									<?php
									$download_url = wp_nonce_url(
										add_query_arg(
											array(
											'action' => 'wg_download_upload',
											'file'   => $file['stored_file'],
										),
										admin_url( 'admin-post.php' )
									),
										'wg_download_file_' . $file['stored_file']
									);
									?>
									<a class="button button-secondary" href="<?php echo esc_url( $download_url ); ?>">
										<?php esc_html_e( 'Download', 'wedding_gallery' ); ?>
									</a>
								<?php else : ?>
									<span><?php esc_html_e( 'Unavailable', 'wedding_gallery' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
