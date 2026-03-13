<?php
/**
 * Frontend upload template.
 *
 * Variables available:
 * - bool   $is_authorized
 * - string $status
 * - string $message
 * - int    $max_upload_mb
 * - string $action_url
 * - string $redirect_url
 * - string $allowed_text
 * - array  $settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wg-upload-wrap">
	<?php if ( ! $is_authorized ) : ?>
		<p><?php esc_html_e( 'This upload page is protected. Please use the event QR code link.', 'wedding-gallery' ); ?></p>
	<?php else : ?>
		<?php if ( ! empty( $status ) && ! empty( $message ) ) : ?>
			<div class="<?php echo 'success' === $status ? 'notice notice-success' : 'notice notice-error'; ?>">
				<p><?php echo esc_html( $message ); ?></p>
			</div>
		<?php endif; ?>

		<form action="<?php echo esc_url( $action_url ); ?>" method="post" enctype="multipart/form-data">
			<input type="hidden" name="action" value="wg_upload" />
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_url ); ?>" />
			<input type="hidden" name="<?php echo esc_attr( WG_Plugin::TOKEN_QUERY_ARG ); ?>" value="<?php echo esc_attr( $settings['access_token'] ); ?>" />
			<?php wp_nonce_field( 'wg_upload_action', 'wg_upload_nonce' ); ?>

			<p>
				<label for="wg_files"><?php esc_html_e( 'Upload photos or videos', 'wedding-gallery' ); ?></label><br />
				<input
					id="wg_files"
					name="wg_files[]"
					type="file"
					multiple
					required
					accept=".jpg,.jpeg,.png,.webp,.mp4,image/jpeg,image/png,image/webp,video/mp4"
				/>
			</p>
			<p>
				<?php
				printf(
					/* translators: 1: allowed file types, 2: max file size in MB */
					esc_html__( 'Allowed: %1$s | Max per file: %2$d MB', 'wedding-gallery' ),
					esc_html( $allowed_text ),
					(int) $max_upload_mb
				);
				?>
			</p>
			<p>
				<button type="submit"><?php esc_html_e( 'Upload', 'wedding-gallery' ); ?></button>
			</p>
		</form>
	<?php endif; ?>
</div>
