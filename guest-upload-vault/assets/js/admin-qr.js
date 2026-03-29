(function () {
	'use strict';

	function setQrButtons(dataUrl) {
		var viewBtn = document.getElementById('guest_upload_vault_view_qr');
		var downloadBtn = document.getElementById('guest_upload_vault_download_qr');

		if (!viewBtn || !downloadBtn) {
			return;
		}

		if (!dataUrl) {
			viewBtn.style.pointerEvents = 'none';
			downloadBtn.style.pointerEvents = 'none';
			viewBtn.style.opacity = '0.7';
			downloadBtn.style.opacity = '0.7';
			viewBtn.href = '#';
			downloadBtn.href = '#';
			return;
		}

		viewBtn.href = dataUrl;
		downloadBtn.href = dataUrl;
		viewBtn.style.pointerEvents = '';
		downloadBtn.style.pointerEvents = '';
		viewBtn.style.opacity = '';
		downloadBtn.style.opacity = '';
	}

	function fallbackCopy(input) {
		input.focus();
		input.select();
		input.setSelectionRange(0, 99999);
		document.execCommand('copy');
	}

	function copyProtectedLink() {
		var input = document.getElementById('guest_upload_vault_protected_upload_url');
		if (!input) {
			return;
		}

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(input.value).catch(function () {
				fallbackCopy(input);
			});
			return;
		}

		fallbackCopy(input);
	}

	function readDataUrl(container) {
		var canvas = container.querySelector('canvas');
		if (canvas && typeof canvas.toDataURL === 'function') {
			setQrButtons(canvas.toDataURL('image/png'));
			return;
		}

		var img = container.querySelector('img');
		if (img && img.src) {
			setQrButtons(img.src);
			return;
		}

		setQrButtons('');
	}

	function initQrCode() {
		var container = document.getElementById('guest_upload_vault_qr_code');
		var input = document.getElementById('guest_upload_vault_protected_upload_url');
		if (!container || !input || typeof QRCode === 'undefined') {
			setQrButtons('');
			return;
		}

		var text = input.value || '';
		if (!text) {
			setQrButtons('');
			return;
		}

		var config = window.guestUploadVaultAdminQrConfig || {};
		var size = parseInt(config.qrCodeSize, 10);
		if (!size || size < 1) {
			size = 360;
		}

		container.innerHTML = '';
		new QRCode(container, {
			text: text,
			width: size,
			height: size,
			colorDark: '#000000',
			colorLight: '#ffffff',
			correctLevel: QRCode.CorrectLevel.M
		});

		readDataUrl(container);
		window.setTimeout(function () {
			readDataUrl(container);
		}, 0);
	}

	function onReady() {
		var copyBtn = document.getElementById('guest_upload_vault_copy_link');
		if (copyBtn) {
			copyBtn.addEventListener('click', copyProtectedLink);
		}
		initQrCode();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', onReady);
	} else {
		onReady();
	}
})();
