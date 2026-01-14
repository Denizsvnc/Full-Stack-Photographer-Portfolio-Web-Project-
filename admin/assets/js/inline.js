// Admin Panel Inline JavaScript

// Bulk Delete Form Handler
(function() {
	document.addEventListener('DOMContentLoaded', function() {
		var bulkDeleteForm = document.getElementById('bulk-delete-form');
		var selectAllCheckbox = document.getElementById('select-all');
		var projectCheckboxes = document.querySelectorAll('input[name="selected_projects[]"]');
		var bulkDeleteBtn = document.getElementById('bulk-delete-btn');
		
		if (selectAllCheckbox && projectCheckboxes.length > 0) {
			selectAllCheckbox.addEventListener('change', function() {
				projectCheckboxes.forEach(function(checkbox) {
					checkbox.checked = selectAllCheckbox.checked;
				});
				updateBulkDeleteButton();
			});
			
			projectCheckboxes.forEach(function(checkbox) {
				checkbox.addEventListener('change', function() {
					updateBulkDeleteButton();
					// Select all checkbox'ı güncelle
					var allChecked = Array.from(projectCheckboxes).every(function(cb) {
						return cb.checked;
					});
					selectAllCheckbox.checked = allChecked;
				});
			});
		}
		
		function updateBulkDeleteButton() {
			if (bulkDeleteBtn) {
				var selectedCount = Array.from(projectCheckboxes).filter(function(cb) {
					return cb.checked;
				}).length;
				
				if (selectedCount > 0) {
					bulkDeleteBtn.style.display = 'inline-flex';
					bulkDeleteBtn.textContent = 'Seçilenleri Sil (' + selectedCount + ')';
				} else {
					bulkDeleteBtn.style.display = 'none';
				}
			}
		}
		
		if (bulkDeleteBtn) {
			bulkDeleteBtn.addEventListener('click', function(e) {
				e.preventDefault();
				var selectedIds = Array.from(projectCheckboxes)
					.filter(function(cb) { return cb.checked; })
					.map(function(cb) { return cb.value; });
				
				if (selectedIds.length === 0) {
					alert('Lütfen silmek istediğiniz projeleri seçin.');
					return;
				}
				
				if (confirm('Seçili ' + selectedIds.length + ' projeyi silmek istediğinize emin misiniz?')) {
					var idsInput = document.getElementById('bulk-delete-ids');
					if (idsInput && bulkDeleteForm) {
						idsInput.value = JSON.stringify(selectedIds);
						bulkDeleteForm.submit();
					}
				}
			});
		}
	});
})();

// Remove Field Button Handler
(function() {
	document.addEventListener('DOMContentLoaded', function() {
		var removeButtons = document.querySelectorAll('.remove-field-btn');
		var addButtons = document.querySelectorAll('.add-field-btn');
		
		removeButtons.forEach(function(btn) {
			btn.addEventListener('click', function() {
				var fieldItem = this.closest('.media-field-item');
				if (fieldItem) {
					fieldItem.remove();
				}
			});
		});
		
		addButtons.forEach(function(btn) {
			btn.addEventListener('click', function() {
				var container = this.closest('.media-fields-container');
				if (container) {
					var template = container.querySelector('.media-field-item');
					if (template) {
						var clone = template.cloneNode(true);
						var input = clone.querySelector('input[type="file"]');
						if (input) {
							input.value = '';
						}
						var removeBtn = clone.querySelector('.remove-field-btn');
						if (removeBtn) {
							removeBtn.classList.add('show');
							removeBtn.addEventListener('click', function() {
								clone.remove();
							});
						}
						container.appendChild(clone);
					}
				}
			});
		});
	});
})();

// Notification System
(function() {
	window.showAdminNotification = function(message, type) {
		var notification = document.createElement('div');
		notification.className = 'admin-notification ' + 
			(type === 'success' ? 'bg-success-50 border border-success-200 text-success-800' : 
			 'bg-error-50 border border-error-200 text-error-800');
		notification.textContent = message;
		document.body.appendChild(notification);
		
		setTimeout(function() {
			notification.classList.add('fade-out');
			setTimeout(function() {
				if (notification.parentNode) {
					notification.parentNode.removeChild(notification);
				}
			}, 300);
		}, 3000);
	};
})();

// HTML Escape Function
(function() {
	window.escapeHtml = function(text) {
		if (!text) return '';
		var map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
	};
})();

