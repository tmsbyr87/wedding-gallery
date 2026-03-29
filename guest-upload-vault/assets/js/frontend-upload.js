(function () {
	const form = document.getElementById('guest-upload-vault-upload-form');
	if (!form) {
		return;
	}

	const i18n = window.guestUploadVaultUploadI18n || {};
	const fileInput = document.getElementById('guest_upload_vault_files');
	const summary = document.getElementById('guest_upload_vault_file_summary');
	const submitBtn = document.getElementById('guest_upload_vault_submit_btn');
	const pickerBtn = document.getElementById('guest_upload_vault_picker_btn');
	const progressWrap = document.getElementById('guest_upload_vault_progress_wrap');
	const progressFill = document.getElementById('guest_upload_vault_progress_fill');
	const progressText = document.getElementById('guest_upload_vault_progress_text');
	const clientAlert = document.getElementById('guest-upload-vault-client-alert');
	let progressStepTimers = [];
	let currentProgress = 0;

	function t(key, fallback) {
		if (Object.prototype.hasOwnProperty.call(i18n, key) && i18n[key]) {
			return i18n[key];
		}
		return fallback;
	}

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
		const normalizedPercent = Math.max(0, Math.min(100, percent));
		currentProgress = Math.max(currentProgress, normalizedPercent);
		progressFill.style.width = currentProgress + '%';
		progressText.textContent = label;
	}

	function clearProgressTimers() {
		if (!progressStepTimers.length) {
			return;
		}
		progressStepTimers.forEach(function (timerId) {
			window.clearTimeout(timerId);
		});
		progressStepTimers = [];
	}

	function updateFileSummary() {
		if (!summary || !fileInput) {
			return;
		}

		const files = fileInput.files;
		if (!files || files.length === 0) {
			summary.textContent = t('noFilesSelected', 'No files selected yet.');
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

		summary.textContent = files.length + ' ' + t('filesSelectedPrefix', 'files selected:') + ' ' + firstTwo.join(', ') + (files.length > 2 ? '...' : '');
	}

	function setUploadUiBusy(isBusy) {
		form.setAttribute('aria-busy', isBusy ? 'true' : 'false');

		if (submitBtn) {
			submitBtn.disabled = isBusy;
			submitBtn.setAttribute('aria-disabled', isBusy ? 'true' : 'false');
		}
		if (pickerBtn) {
			pickerBtn.classList.toggle('is-disabled', isBusy);
			pickerBtn.setAttribute('aria-disabled', isBusy ? 'true' : 'false');
		}
	}

	if (fileInput) {
		fileInput.addEventListener('change', function () {
			clearClientError();
			updateFileSummary();
		});
	}

	setUploadUiBusy(false);

	form.addEventListener('submit', function (event) {
		if (form.dataset.guestUploadVaultSubmitting === '1') {
			event.preventDefault();
			return;
		}

		clearClientError();

		if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
			event.preventDefault();
			showClientError(t('chooseAtLeastOne', 'Please choose at least one photo or video.'));
			return;
		}

		form.dataset.guestUploadVaultSubmitting = '1';
		setUploadUiBusy(true);
		clearProgressTimers();
		currentProgress = 0;
		setProgress(12, t('uploadingKeepOpen', 'Uploading... Please keep this page open.'));

		progressStepTimers.push(window.setTimeout(function () {
			setProgress(42, t('uploadingProgress', 'Upload in progress...'));
		}, 600));

		progressStepTimers.push(window.setTimeout(function () {
			setProgress(76, t('uploadingAlmostDone', 'Almost done...'));
		}, 1800));
	});

	window.addEventListener('pageshow', function () {
		if (form.dataset.guestUploadVaultSubmitting === '1') {
			form.dataset.guestUploadVaultSubmitting = '0';
		}
		clearProgressTimers();
		setUploadUiBusy(false);
	});
})();
