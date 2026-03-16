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
 * - string $authorized_token
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wedding_gallery_status_class = 'success' === $status ? 'wg-alert-success' : 'wg-alert-error';
$wedding_gallery_status_title = 'success' === $status ? __( 'Thank you, upload complete.', 'wedding-gallery' ) : __( 'Upload could not be completed.', 'wedding-gallery' );
?>
<style>
.wg-upload-wrap {
	max-width: 620px;
	margin: 0 auto;
	padding-top: calc(16px + env(safe-area-inset-top));
	padding-right: max(16px, env(safe-area-inset-right));
	padding-bottom: calc(20px + env(safe-area-inset-bottom));
	padding-left: max(16px, env(safe-area-inset-left));
	font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
	color: #2d2433;
	font-size: 16px;
	line-height: 1.5;
}

.wg-upload-card {
	background: linear-gradient(180deg, #fffdf9 0%, #fff7f8 100%);
	border: 1px solid #f1dde2;
	border-radius: 20px;
	padding: 24px 18px;
	box-shadow: 0 10px 28px rgba(71, 27, 43, 0.08);
}

.wg-upload-title {
	margin: 0 0 8px;
	font-size: clamp(1.55rem, 6vw, 2rem);
	line-height: 1.14;
	letter-spacing: -0.01em;
	color: #4f2a3a;
}

.wg-upload-subtitle {
	margin: 0 0 22px;
	color: #6b5963;
	font-size: clamp(1rem, 4.3vw, 1.12rem);
	line-height: 1.5;
}

.wg-alert {
	border-radius: 14px;
	padding: 14px 16px;
	margin-bottom: 16px;
	font-size: 1rem;
	line-height: 1.5;
}

.wg-alert strong {
	display: block;
	margin-bottom: 6px;
	font-size: 1.02rem;
}

.wg-alert-success {
	background: #effaf1;
	border: 1px solid #b7e6c0;
	color: #1f5b2d;
}

.wg-alert-error {
	background: #fff3f2;
	border: 1px solid #f4c7c2;
	color: #822a27;
}

.wg-file-input {
	position: absolute;
	width: 1px;
	height: 1px;
	opacity: 0;
	pointer-events: none;
}

.wg-picker-btn {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 100%;
	min-height: 58px;
	padding: 14px 16px;
	border: 0;
	border-radius: 14px;
	text-align: center;
	font-size: clamp(1.08rem, 4.7vw, 1.2rem);
	font-weight: 700;
	line-height: 1.2;
	letter-spacing: 0.01em;
	background: #a33b63;
	color: #ffffff;
	cursor: pointer;
	box-sizing: border-box;
	-webkit-tap-highlight-color: transparent;
	touch-action: manipulation;
	transition: background-color 0.2s ease, transform 0.08s ease, box-shadow 0.2s ease;
}

.wg-picker-btn:hover,
.wg-picker-btn:focus-visible {
	background: #8e3258;
	box-shadow: 0 0 0 3px rgba(163, 59, 99, 0.22);
}

.wg-picker-btn:active {
	transform: translateY(1px);
}

.wg-picker-btn.is-disabled {
	opacity: 0.7;
	cursor: not-allowed;
	pointer-events: none;
}

.wg-file-summary {
	margin: 14px 2px 16px;
	font-size: 1rem;
	line-height: 1.45;
	color: #5f4f58;
	word-break: break-word;
}

.wg-hint-list {
	margin: 0 0 16px;
	padding: 14px 16px;
	background: rgba(255, 255, 255, 0.75);
	border: 1px solid #ecd8de;
	border-radius: 14px;
	font-size: 0.99rem;
	line-height: 1.55;
	color: #5b4b54;
}

.wg-hint-list p {
	margin: 0 0 8px;
}

.wg-hint-list .wg-hint-primary {
	font-weight: 700;
	color: #4f2a3a;
}

.wg-hint-list p:last-child {
	margin-bottom: 0;
}

.wg-submit-btn {
	width: 100%;
	min-height: 58px;
	padding: 14px 16px;
	border: 0;
	border-radius: 14px;
	background: #2c6f56;
	color: #ffffff;
	font-size: clamp(1.1rem, 4.9vw, 1.22rem);
	line-height: 1.2;
	letter-spacing: 0.01em;
	font-weight: 700;
	cursor: pointer;
	-webkit-tap-highlight-color: transparent;
	touch-action: manipulation;
	transition: background-color 0.2s ease, transform 0.08s ease, box-shadow 0.2s ease;
}

.wg-submit-btn:hover,
.wg-submit-btn:focus-visible {
	background: #245c47;
	box-shadow: 0 0 0 3px rgba(44, 111, 86, 0.22);
}

.wg-submit-btn:active {
	transform: translateY(1px);
}

.wg-submit-btn[disabled] {
	opacity: 0.7;
	cursor: progress;
}

.wg-progress-wrap {
	margin-top: 16px;
	padding: 14px 16px;
	background: #ffffff;
	border: 1px solid #ecd8de;
	border-radius: 14px;
}

.wg-progress-bar-track {
	position: relative;
	height: 12px;
	border-radius: 999px;
	background: #f3e6ea;
	overflow: hidden;
}

.wg-progress-bar-fill {
	position: absolute;
	top: 0;
	left: 0;
	height: 100%;
	width: 0;
	border-radius: 999px;
	background: linear-gradient(90deg, #c04d77 0%, #8c3658 100%);
	transition: width 0.2s ease;
}

.wg-progress-text {
	margin: 10px 0 0;
	font-size: 0.98rem;
	line-height: 1.45;
	color: #5f4f58;
}

@media (max-width: 380px) {
	.wg-upload-wrap {
		padding-top: calc(12px + env(safe-area-inset-top));
		padding-right: max(12px, env(safe-area-inset-right));
		padding-bottom: calc(16px + env(safe-area-inset-bottom));
		padding-left: max(12px, env(safe-area-inset-left));
	}

	.wg-upload-card {
		padding: 20px 14px;
	}
}

@media (min-width: 680px) {
	.wg-upload-wrap {
		padding-top: 24px;
		padding-right: 24px;
		padding-bottom: 24px;
		padding-left: 24px;
	}

	.wg-upload-card {
		padding: 28px 24px;
	}

	.wg-picker-btn,
	.wg-submit-btn {
		min-height: 56px;
		font-size: 1.08rem;
	}
}
</style>
<div class="wg-upload-wrap">
	<?php if ( ! $is_authorized ) : ?>
		<div class="wg-upload-card">
			<div class="wg-alert wg-alert-error">
				<strong><?php esc_html_e( 'Protected guest upload', 'wedding-gallery' ); ?></strong>
				<?php esc_html_e( 'This page is only available through the wedding QR link.', 'wedding-gallery' ); ?>
			</div>
		</div>
	<?php else : ?>
		<div class="wg-upload-card">
			<h2 class="wg-upload-title"><?php esc_html_e( 'Share your wedding moments', 'wedding-gallery' ); ?></h2>
			<p class="wg-upload-subtitle"><?php esc_html_e( 'Select photos or videos from your phone and upload them in one step.', 'wedding-gallery' ); ?></p>

			<?php if ( ! empty( $status ) && ! empty( $message ) ) : ?>
				<div class="wg-alert <?php echo esc_attr( $wedding_gallery_status_class ); ?>" role="status" aria-live="polite">
					<strong><?php echo esc_html( $wedding_gallery_status_title ); ?></strong>
					<?php echo esc_html( $message ); ?>
				</div>
			<?php endif; ?>

			<div id="wg-client-alert" class="wg-alert wg-alert-error" style="display:none;" role="alert" aria-live="assertive"></div>

			<form id="wg-upload-form" action="<?php echo esc_url( $action_url ); ?>" method="post" enctype="multipart/form-data" aria-busy="false">
				<input type="hidden" name="action" value="wg_upload" />
				<input type="hidden" id="wg_redirect_to" name="redirect_to" value="<?php echo esc_url( $redirect_url ); ?>" />
				<input type="hidden" name="<?php echo esc_attr( WG_Plugin::TOKEN_QUERY_ARG ); ?>" value="<?php echo esc_attr( $authorized_token ); ?>" />
				<?php wp_nonce_field( 'wg_upload_action_' . $authorized_token, 'wg_upload_nonce' ); ?>

				<input
					id="wg_files"
					class="wg-file-input"
					name="wg_files[]"
					type="file"
					multiple
					required
					accept=".jpg,.jpeg,.png,.webp,.mp4,.mov,image/jpeg,image/png,image/webp,video/mp4,video/quicktime"
				/>
				<label for="wg_files" id="wg_picker_btn" class="wg-picker-btn">
					<?php esc_html_e( 'Choose Photos or Videos', 'wedding-gallery' ); ?>
				</label>
				<p id="wg_file_summary" class="wg-file-summary">
					<?php esc_html_e( 'No files selected yet.', 'wedding-gallery' ); ?>
				</p>

				<div class="wg-hint-list">
					<p class="wg-hint-primary">
						<?php
						printf(
							/* translators: 1: allowed file types, 2: max file size in MB */
							esc_html__( 'Allowed: %1$s | Max per file: %2$d MB', 'wedding-gallery' ),
							esc_html( $allowed_text ),
							(int) $max_upload_mb
						);
						?>
					</p>
					<p><?php esc_html_e( 'On iPhone/Android you can choose camera, photo library/gallery, or files.', 'wedding-gallery' ); ?></p>
					<p><?php esc_html_e( 'Tip: Long phone videos are often large and may exceed the upload size limit.', 'wedding-gallery' ); ?></p>
					<p><?php esc_html_e( 'You can select multiple files at once.', 'wedding-gallery' ); ?></p>
				</div>

				<button id="wg_submit_btn" class="wg-submit-btn" type="submit">
					<?php esc_html_e( 'Upload Now', 'wedding-gallery' ); ?>
				</button>

				<div id="wg_progress_wrap" class="wg-progress-wrap" hidden>
					<div class="wg-progress-bar-track">
						<div id="wg_progress_fill" class="wg-progress-bar-fill"></div>
					</div>
					<p id="wg_progress_text" class="wg-progress-text"><?php esc_html_e( 'Preparing upload...', 'wedding-gallery' ); ?></p>
				</div>
			</form>
		</div>
	<?php endif; ?>
</div>
