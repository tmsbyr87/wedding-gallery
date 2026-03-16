(function () {
	'use strict';

	function getI18nValue(key, fallback) {
		if (window.wgAdminMediaI18n && window.wgAdminMediaI18n[key]) {
			return window.wgAdminMediaI18n[key];
		}
		return fallback;
	}

	function onReady() {
		var bulkForm = document.getElementById('wg_media_bulk_form');
		var selectAll = document.getElementById('wg_select_all_media');

		if (bulkForm && selectAll) {
			var rowChecks = bulkForm.querySelectorAll('.wg-media-select');
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

				if (submitter.dataset.wgRequiresSelection === '1') {
					var hasSelection = !!bulkForm.querySelector('.wg-media-select:checked');
					if (!hasSelection) {
						event.preventDefault();
						window.alert(getI18nValue('noSelection', 'Please select at least one file first.'));
						return;
					}
				}
			});
		}

		var deleteButtons = document.querySelectorAll('.wg-delete-button');
		deleteButtons.forEach(function (button) {
			button.addEventListener('click', function (event) {
				var scope = button.dataset.wgDeleteScope || 'single';
				if (scope === 'selected' && bulkForm) {
					var hasSelection = !!bulkForm.querySelector('.wg-media-select:checked');
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
					message = getI18nValue('confirmDeleteAll', 'Delete ALL wedding media permanently?');
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
