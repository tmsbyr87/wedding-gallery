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

$guest_upload_vault_status_class = 'success' === $status ? 'guest-upload-vault-alert-success' : 'guest-upload-vault-alert-error';
$guest_upload_vault_status_title = 'success' === $status ? __( 'Thank you, upload complete.', 'guest-upload-vault' ) : __( 'Upload could not be completed.', 'guest-upload-vault' );
?>
<style>
.guest-upload-vault-upload-wrap {
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

.guest-upload-vault-upload-card {
	background: linear-gradient(180deg, #fffdf9 0%, #fff7f8 100%);
	border: 1px solid #f1dde2;
	border-radius: 20px;
	padding: 24px 18px;
	box-shadow: 0 10px 28px rgba(71, 27, 43, 0.08);
}

.guest-upload-vault-upload-title {
	margin: 0 0 8px;
	font-size: clamp(1.55rem, 6vw, 2rem);
	line-height: 1.14;
	letter-spacing: -0.01em;
	color: #4f2a3a;
}

.guest-upload-vault-upload-subtitle {
	margin: 0 0 22px;
	color: #6b5963;
	font-size: clamp(1rem, 4.3vw, 1.12rem);
	line-height: 1.5;
}

.guest-upload-vault-alert {
	border-radius: 14px;
	padding: 14px 16px;
	margin-bottom: 16px;
	font-size: 1rem;
	line-height: 1.5;
}

.guest-upload-vault-alert strong {
	display: block;
	margin-bottom: 6px;
	font-size: 1.02rem;
}

.guest-upload-vault-alert-success {
	background: #effaf1;
	border: 1px solid #b7e6c0;
	color: #1f5b2d;
}

.guest-upload-vault-alert-error {
	background: #fff3f2;
	border: 1px solid #f4c7c2;
	color: #822a27;
}

.guest-upload-vault-file-input {
	position: absolute;
	width: 1px;
	height: 1px;
	opacity: 0;
	pointer-events: none;
}

.guest-upload-vault-picker-btn {
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

.guest-upload-vault-picker-btn:hover,
.guest-upload-vault-picker-btn:focus-visible {
	background: #8e3258;
	box-shadow: 0 0 0 3px rgba(163, 59, 99, 0.22);
}

.guest-upload-vault-picker-btn:active {
	transform: translateY(1px);
}

.guest-upload-vault-picker-btn.is-disabled {
	opacity: 0.7;
	cursor: not-allowed;
	pointer-events: none;
}

.guest-upload-vault-file-summary {
	margin: 14px 2px 16px;
	font-size: 1rem;
	line-height: 1.45;
	color: #5f4f58;
	word-break: break-word;
}

.guest-upload-vault-hint-list {
	margin: 0 0 16px;
	padding: 14px 16px;
	background: rgba(255, 255, 255, 0.75);
	border: 1px solid #ecd8de;
	border-radius: 14px;
	font-size: 0.99rem;
	line-height: 1.55;
	color: #5b4b54;
}

.guest-upload-vault-hint-list p {
	margin: 0 0 8px;
}

.guest-upload-vault-hint-list .guest-upload-vault-hint-primary {
	font-weight: 700;
	color: #4f2a3a;
}

.guest-upload-vault-hint-list p:last-child {
	margin-bottom: 0;
}

.guest-upload-vault-submit-btn {
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

.guest-upload-vault-submit-btn:hover,
.guest-upload-vault-submit-btn:focus-visible {
	background: #245c47;
	box-shadow: 0 0 0 3px rgba(44, 111, 86, 0.22);
}

.guest-upload-vault-submit-btn:active {
	transform: translateY(1px);
}

.guest-upload-vault-submit-btn[disabled] {
	opacity: 0.7;
	cursor: progress;
}

.guest-upload-vault-progress-wrap {
	margin-top: 16px;
	padding: 14px 16px;
	background: #ffffff;
	border: 1px solid #ecd8de;
	border-radius: 14px;
}

.guest-upload-vault-progress-bar-track {
	position: relative;
	height: 12px;
	border-radius: 999px;
	background: #f3e6ea;
	overflow: hidden;
}

.guest-upload-vault-progress-bar-fill {
	position: absolute;
	top: 0;
	left: 0;
	height: 100%;
	width: 0;
	border-radius: 999px;
	background: linear-gradient(90deg, #c04d77 0%, #8c3658 100%);
	transition: width 0.2s ease;
}

.guest-upload-vault-progress-text {
	margin: 10px 0 0;
	font-size: 0.98rem;
	line-height: 1.45;
	color: #5f4f58;
}

@media (max-width: 380px) {
	.guest-upload-vault-upload-wrap {
		padding-top: calc(12px + env(safe-area-inset-top));
		padding-right: max(12px, env(safe-area-inset-right));
		padding-bottom: calc(16px + env(safe-area-inset-bottom));
		padding-left: max(12px, env(safe-area-inset-left));
	}

	.guest-upload-vault-upload-card {
		padding: 20px 14px;
	}
}

@media (min-width: 680px) {
	.guest-upload-vault-upload-wrap {
		padding-top: 24px;
		padding-right: 24px;
		padding-bottom: 24px;
		padding-left: 24px;
	}

	.guest-upload-vault-upload-card {
		padding: 28px 24px;
	}

	.guest-upload-vault-picker-btn,
	.guest-upload-vault-submit-btn {
		min-height: 56px;
		font-size: 1.08rem;
	}
}
</style>
<div class="guest-upload-vault-upload-wrap">
	<?php if ( ! $is_authorized ) : ?>
		<div class="guest-upload-vault-upload-card">
			<div class="guest-upload-vault-alert guest-upload-vault-alert-error">
				<strong><?php esc_html_e( 'Protected guest upload', 'guest-upload-vault' ); ?></strong>
				<?php esc_html_e( 'This page is only available through the protected event link or QR code.', 'guest-upload-vault' ); ?>
			</div>
		</div>
	<?php else : ?>
		<div class="guest-upload-vault-upload-card">
			<h2 class="guest-upload-vault-upload-title"><?php esc_html_e( 'Share your event moments', 'guest-upload-vault' ); ?></h2>
			<p class="guest-upload-vault-upload-subtitle"><?php esc_html_e( 'Select photos or videos from your phone and upload them in one step.', 'guest-upload-vault' ); ?></p>

			<?php if ( ! empty( $status ) && ! empty( $message ) ) : ?>
				<div class="guest-upload-vault-alert <?php echo esc_attr( $guest_upload_vault_status_class ); ?>" role="status" aria-live="polite">
					<strong><?php echo esc_html( $guest_upload_vault_status_title ); ?></strong>
					<?php echo esc_html( $message ); ?>
				</div>
			<?php endif; ?>

			<div id="guest-upload-vault-client-alert" class="guest-upload-vault-alert guest-upload-vault-alert-error" style="display:none;" role="alert" aria-live="assertive"></div>

			<form id="guest-upload-vault-upload-form" action="<?php echo esc_url( $action_url ); ?>" method="post" enctype="multipart/form-data" aria-busy="false">
				<input type="hidden" name="action" value="guest_upload_vault_upload" />
				<input type="hidden" id="guest_upload_vault_redirect_to" name="redirect_to" value="<?php echo esc_url( $redirect_url ); ?>" />
				<input type="hidden" name="<?php echo esc_attr( Guest_Upload_Vault_Plugin::TOKEN_QUERY_ARG ); ?>" value="<?php echo esc_attr( $authorized_token ); ?>" />
				<?php wp_nonce_field( 'guest_upload_vault_upload_action_' . $authorized_token, 'guest_upload_vault_upload_nonce' ); ?>

				<input
					id="guest_upload_vault_files"
					class="guest-upload-vault-file-input"
					name="guest_upload_vault_files[]"
					type="file"
					multiple
					required
					accept=".jpg,.jpeg,.png,.webp,.mp4,.mov,image/jpeg,image/png,image/webp,video/mp4,video/quicktime"
				/>
				<label for="guest_upload_vault_files" id="guest_upload_vault_picker_btn" class="guest-upload-vault-picker-btn">
					<?php esc_html_e( 'Choose Photos or Videos', 'guest-upload-vault' ); ?>
				</label>
				<p id="guest_upload_vault_file_summary" class="guest-upload-vault-file-summary">
					<?php esc_html_e( 'No files selected yet.', 'guest-upload-vault' ); ?>
				</p>

				<div class="guest-upload-vault-hint-list">
					<p class="guest-upload-vault-hint-primary">
						<?php
						printf(
							/* translators: 1: allowed file types, 2: max file size in MB */
							esc_html__( 'Allowed: %1$s | Max per file: %2$d MB', 'guest-upload-vault' ),
							esc_html( $allowed_text ),
							(int) $max_upload_mb
						);
						?>
					</p>
					<p><?php esc_html_e( 'On iPhone/Android you can choose camera, photo library/gallery, or files.', 'guest-upload-vault' ); ?></p>
					<p><?php esc_html_e( 'Tip: Long phone videos are often large and may exceed the upload size limit.', 'guest-upload-vault' ); ?></p>
					<p><?php esc_html_e( 'You can select multiple files at once.', 'guest-upload-vault' ); ?></p>
				</div>

				<button id="guest_upload_vault_submit_btn" class="guest-upload-vault-submit-btn" type="submit">
					<?php esc_html_e( 'Upload Now', 'guest-upload-vault' ); ?>
				</button>

				<div id="guest_upload_vault_progress_wrap" class="guest-upload-vault-progress-wrap" hidden>
					<div class="guest-upload-vault-progress-bar-track">
						<div id="guest_upload_vault_progress_fill" class="guest-upload-vault-progress-bar-fill"></div>
					</div>
					<p id="guest_upload_vault_progress_text" class="guest-upload-vault-progress-text"><?php esc_html_e( 'Preparing upload...', 'guest-upload-vault' ); ?></p>
				</div>
			</form>
		</div>
	<?php endif; ?>
</div>
