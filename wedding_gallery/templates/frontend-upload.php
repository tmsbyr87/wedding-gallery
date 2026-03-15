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

$status_class = 'success' === $status ? 'wg-alert-success' : 'wg-alert-error';
$status_title = 'success' === $status ? __( 'Thank you, upload complete.', 'wedding-gallery' ) : __( 'Upload could not be completed.', 'wedding-gallery' );
?>
<style>
.wg-upload-wrap {
	max-width: 560px;
	margin: 0 auto;
	padding: 16px;
	font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
	color: #2d2433;
}

.wg-upload-card {
	background: linear-gradient(180deg, #fffdf9 0%, #fff7f8 100%);
	border: 1px solid #f1dde2;
	border-radius: 18px;
	padding: 20px 16px;
	box-shadow: 0 10px 28px rgba(71, 27, 43, 0.08);
}

.wg-upload-title {
	margin: 0 0 6px;
	font-size: 1.4rem;
	line-height: 1.2;
	color: #4f2a3a;
}

.wg-upload-subtitle {
	margin: 0 0 18px;
	color: #6b5963;
	font-size: 0.96rem;
}

.wg-alert {
	border-radius: 12px;
	padding: 12px 14px;
	margin-bottom: 14px;
	font-size: 0.95rem;
}

.wg-alert strong {
	display: block;
	margin-bottom: 4px;
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
	display: block;
	width: 100%;
	padding: 18px 16px;
	border: 0;
	border-radius: 14px;
	text-align: center;
	font-size: 1.06rem;
	font-weight: 600;
	background: #a33b63;
	color: #ffffff;
	cursor: pointer;
	box-sizing: border-box;
}

.wg-picker-btn:hover,
.wg-picker-btn:focus {
	background: #8e3258;
}

.wg-picker-btn.is-disabled {
	opacity: 0.7;
	cursor: not-allowed;
}

.wg-file-summary {
	margin: 10px 2px 14px;
	font-size: 0.93rem;
	color: #5f4f58;
	word-break: break-word;
}

.wg-hint-list {
	margin: 0 0 14px;
	padding: 12px 14px;
	background: rgba(255, 255, 255, 0.75);
	border: 1px solid #ecd8de;
	border-radius: 12px;
	font-size: 0.93rem;
	color: #5b4b54;
}

.wg-hint-list p {
	margin: 0 0 6px;
}

.wg-hint-list p:last-child {
	margin-bottom: 0;
}

.wg-submit-btn {
	width: 100%;
	padding: 16px;
	border: 0;
	border-radius: 14px;
	background: #2c6f56;
	color: #ffffff;
	font-size: 1.05rem;
	font-weight: 700;
	cursor: pointer;
}

.wg-submit-btn:hover,
.wg-submit-btn:focus {
	background: #245c47;
}

.wg-submit-btn[disabled] {
	opacity: 0.7;
	cursor: progress;
}

.wg-progress-wrap {
	margin-top: 14px;
	padding: 12px 14px;
	background: #ffffff;
	border: 1px solid #ecd8de;
	border-radius: 12px;
}

.wg-progress-bar-track {
	position: relative;
	height: 10px;
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
	margin: 8px 0 0;
	font-size: 0.9rem;
	color: #5f4f58;
}

@media (min-width: 680px) {
	.wg-upload-wrap {
		padding: 24px;
	}

	.wg-upload-card {
		padding: 24px;
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
				<div class="wg-alert <?php echo esc_attr( $status_class ); ?>" role="status" aria-live="polite">
					<strong><?php echo esc_html( $status_title ); ?></strong>
					<?php echo esc_html( $message ); ?>
				</div>
			<?php endif; ?>

			<div id="wg-client-alert" class="wg-alert wg-alert-error" style="display:none;" role="alert" aria-live="assertive"></div>

			<form id="wg-upload-form" action="<?php echo esc_url( $action_url ); ?>" method="post" enctype="multipart/form-data">
				<input type="hidden" name="action" value="wg_upload" />
				<input type="hidden" id="wg_redirect_to" name="redirect_to" value="<?php echo esc_url( $redirect_url ); ?>" />
				<input type="hidden" name="<?php echo esc_attr( WG_Plugin::TOKEN_QUERY_ARG ); ?>" value="<?php echo esc_attr( $settings['access_token'] ); ?>" />
				<?php wp_nonce_field( 'wg_upload_action', 'wg_upload_nonce' ); ?>

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
					<p><?php esc_html_e( 'On iPhone/Android you can choose camera, photo library/gallery, or files.', 'wedding-gallery' ); ?></p>
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
<script>
(function () {
	const form = document.getElementById('wg-upload-form');
	if (!form) {
		return;
	}

	const fileInput = document.getElementById('wg_files');
	const summary = document.getElementById('wg_file_summary');
	const submitBtn = document.getElementById('wg_submit_btn');
	const pickerBtn = document.getElementById('wg_picker_btn');
	const progressWrap = document.getElementById('wg_progress_wrap');
	const progressFill = document.getElementById('wg_progress_fill');
	const progressText = document.getElementById('wg_progress_text');
	const clientAlert = document.getElementById('wg-client-alert');

	function showClientError(message) {
		if (!clientAlert) {
			return;
		}
		clientAlert.textContent = message;
		clientAlert.style.display = 'block';
	}

	function clearClientError() {
		if (!clientAlert) {
			return;
		}
		clientAlert.textContent = '';
		clientAlert.style.display = 'none';
	}

	function setProgress(percent, label) {
		if (!progressWrap || !progressFill || !progressText) {
			return;
		}
		progressWrap.hidden = false;
		progressFill.style.width = Math.max(0, Math.min(100, percent)) + '%';
		progressText.textContent = label;
	}

	function updateFileSummary() {
		if (!summary || !fileInput) {
			return;
		}

		const files = fileInput.files;
		if (!files || files.length === 0) {
			summary.textContent = '<?php echo esc_js( __( 'No files selected yet.', 'wedding-gallery' ) ); ?>';
			return;
		}

		if (files.length === 1) {
			summary.textContent = files[0].name;
			return;
		}

		const firstTwo = [];
		for (let i = 0; i < files.length && i < 2; i++) {
			firstTwo.push(files[i].name);
		}
		summary.textContent = files.length + ' <?php echo esc_js( __( 'files selected:', 'wedding-gallery' ) ); ?> ' + firstTwo.join(', ') + (files.length > 2 ? '...' : '');
	}

	function setUploadUiBusy(isBusy) {
		if (submitBtn) {
			submitBtn.disabled = isBusy;
		}
		if (pickerBtn) {
			pickerBtn.classList.toggle('is-disabled', isBusy);
		}
	}

	if (fileInput) {
		fileInput.addEventListener('change', updateFileSummary);
	}

	form.addEventListener('submit', function (event) {
		if (form.dataset.wgSubmitting === '1') {
			event.preventDefault();
			return;
		}

		clearClientError();

		if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
			event.preventDefault();
			showClientError('<?php echo esc_js( __( 'Please choose at least one photo or video.', 'wedding-gallery' ) ); ?>');
			return;
		}

		form.dataset.wgSubmitting = '1';
		setUploadUiBusy(true);
		setProgress(12, '<?php echo esc_js( __( 'Uploading... Please keep this page open.', 'wedding-gallery' ) ); ?>');
		window.setTimeout(function () {
			setProgress(42, '<?php echo esc_js( __( 'Upload in progress...', 'wedding-gallery' ) ); ?>');
		}, 600);
		window.setTimeout(function () {
			setProgress(76, '<?php echo esc_js( __( 'Almost done...', 'wedding-gallery' ) ); ?>');
		}, 1800);
	});
})();
</script>
