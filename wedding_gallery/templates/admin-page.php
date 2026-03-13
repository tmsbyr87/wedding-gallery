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
 * - string $notice
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Wedding Gallery', 'wedding-gallery' ); ?></h1>

	<?php if ( 'saved' === $notice ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved.', 'wedding-gallery' ); ?></p>
		</div>
	<?php endif; ?>

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
								<?php esc_html_e( 'Rotate Token', 'wedding-gallery' ); ?>
							</button>
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
								/* translators: %s: allowed file types */
								esc_html__( 'Allowed file types: %s', 'wedding-gallery' ),
								esc_html( $allowed_text )
							);
							?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Save Settings', 'wedding-gallery' ) ); ?>
	</form>

	<h2><?php esc_html_e( 'Protected Upload URL (QR Target)', 'wedding-gallery' ); ?></h2>
	<?php if ( ! empty( $protected_upload_url ) ) : ?>
		<p>
			<code><?php echo esc_html( $protected_upload_url ); ?></code>
		</p>
	<?php else : ?>
		<p><?php esc_html_e( 'Set an Upload Page URL to generate the protected link.', 'wedding-gallery' ); ?></p>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Uploaded Files', 'wedding-gallery' ); ?></h2>
	<?php if ( empty( $uploads ) ) : ?>
		<p><?php esc_html_e( 'No uploads yet.', 'wedding-gallery' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Filename', 'wedding-gallery' ); ?></th>
					<th><?php esc_html_e( 'Type', 'wedding-gallery' ); ?></th>
					<th><?php esc_html_e( 'Size', 'wedding-gallery' ); ?></th>
					<th><?php esc_html_e( 'Uploaded', 'wedding-gallery' ); ?></th>
					<th><?php esc_html_e( 'Action', 'wedding-gallery' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $uploads as $file ) : ?>
					<tr>
						<td><?php echo esc_html( $file['name'] ); ?></td>
						<td><?php echo esc_html( $file['mime_type'] ); ?></td>
						<td><?php echo esc_html( size_format( (int) $file['size'] ) ); ?></td>
						<td><?php echo esc_html( wp_date( 'Y-m-d H:i', (int) $file['modified'] ) ); ?></td>
						<td>
							<?php
							$download_url = wp_nonce_url(
								add_query_arg(
									array(
										'action' => 'wg_download_upload',
										'file'   => $file['name'],
									),
									admin_url( 'admin-post.php' )
								),
								'wg_download_file_' . $file['name']
							);
							?>
							<a class="button button-secondary" href="<?php echo esc_url( $download_url ); ?>">
								<?php esc_html_e( 'Download', 'wedding-gallery' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
