(function () {
	'use strict';

	function getI18nValue(key, fallback) {
		if (window.guestUploadVaultAdminMediaI18n && window.guestUploadVaultAdminMediaI18n[key]) {
			return window.guestUploadVaultAdminMediaI18n[key];
		}
		return fallback;
	}

	function onReady() {
		var bulkForm = document.getElementById('guest_upload_vault_media_bulk_form');
		var selectAll = document.getElementById('guest_upload_vault_select_all_media');

		if (bulkForm && selectAll) {
			var rowChecks = bulkForm.querySelectorAll('.guest-upload-vault-media-select');
			selectAll.addEventListener('change', function () {
				var checked = !!selectAll.checked;
				rowChecks.forEach(function (checkbox) {
					checkbox.checked = checked;
				});
			});

			rowChecks.forEach(function (checkbox) {
				checkbox.addEventListener('change', function () {
					var allSelected = true;
					rowChecks.forEach(function (item) {
						if (!item.checked) {
							allSelected = false;
						}
					});
					selectAll.checked = allSelected;
				});
			});

			bulkForm.addEventListener('submit', function (event) {
				var submitter = event.submitter || null;
				if (!submitter) {
					return;
				}

				if (submitter.dataset.guestUploadVaultRequiresSelection === '1') {
					var hasSelection = !!bulkForm.querySelector('.guest-upload-vault-media-select:checked');
					if (!hasSelection) {
						event.preventDefault();
						window.alert(getI18nValue('noSelection', 'Please select at least one file first.'));
						return;
					}
				}
			});
		}

		var deleteButtons = document.querySelectorAll('.guest-upload-vault-delete-button');
		deleteButtons.forEach(function (button) {
			button.addEventListener('click', function (event) {
				var scope = button.dataset.guestUploadVaultDeleteScope || 'single';
				if (scope === 'selected' && bulkForm) {
					var hasSelection = !!bulkForm.querySelector('.guest-upload-vault-media-select:checked');
					if (!hasSelection) {
						event.preventDefault();
						window.alert(getI18nValue('noSelection', 'Please select at least one file first.'));
						return;
					}
				}

				var message = getI18nValue('confirmDeleteSingle', 'Delete this file permanently?');
				if (scope === 'selected') {
					message = getI18nValue('confirmDeleteSelected', 'Delete selected media permanently?');
				}
				if (scope === 'all') {
					message = getI18nValue('confirmDeleteAll', 'Delete ALL collected media permanently?');
				}
				if (!window.confirm(message)) {
					event.preventDefault();
				}
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', onReady);
	} else {
		onReady();
	}
})();
