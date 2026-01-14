document.addEventListener('DOMContentLoaded', function () {
    // --- Bulk Delete İşlemleri ---
    const selectAllCheckbox = document.getElementById('select-all');
    const projectCheckboxes = document.querySelectorAll('.project-checkbox');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
    const selectedCountSpan = document.getElementById('selected-count');
    const bulkDeleteForm = document.getElementById('bulk-delete-form');
    const bulkDeleteIdsInput = document.getElementById('bulk-delete-ids');

    // Tümünü seç/seçimi kaldır
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            projectCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkDeleteButton();
        });
    }

    // Tekil checkbox değişiklikleri
    projectCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function () {
            updateSelectAllCheckbox();
            updateBulkDeleteButton();
        });
    });

    // Select all checkbox'ı güncelle
    function updateSelectAllCheckbox() {
        if (selectAllCheckbox) {
            const allChecked = Array.from(projectCheckboxes).every(cb => cb.checked);
            const someChecked = Array.from(projectCheckboxes).some(cb => cb.checked);
            selectAllCheckbox.checked = allChecked;
            selectAllCheckbox.indeterminate = someChecked && !allChecked;
        }
    }

    // Bulk delete butonunu güncelle
    function updateBulkDeleteButton() {
        const selectedCount = Array.from(projectCheckboxes).filter(cb => cb.checked).length;
        if (selectedCountSpan) {
            selectedCountSpan.textContent = selectedCount;
        }

        if (bulkDeleteBtn) {
            bulkDeleteBtn.disabled = selectedCount === 0;
        }
    }

    // Toplu silme
    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', function () {
            const selectedIds = Array.from(projectCheckboxes)
                .filter(cb => cb.checked)
                .map(cb => cb.value);

            if (selectedIds.length === 0) {
                alert('Lütfen silinecek projeleri seçin!');
                return;
            }

            if (!confirm('Seçili ' + selectedIds.length + ' projeyi silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!')) {
                return;
            }

            // Formu gönder
            if (bulkDeleteIdsInput && bulkDeleteForm) {
                bulkDeleteIdsInput.value = JSON.stringify(selectedIds);
                bulkDeleteForm.submit();
            }
        });
    }

    // İlk yüklemede butonu güncelle
    updateBulkDeleteButton();


    // --- Medya Alanı İşlemleri (Ekleme/Silme) ---
    // Birleşik medya alanı ekle
    const addMediaBtn = document.getElementById('add-media-field');
    const mediaContainer = document.getElementById('media-fields-container');

    if (addMediaBtn && mediaContainer) {
        addMediaBtn.addEventListener('click', function () {
            const newField = document.createElement('div');
            newField.className = 'media-field-item flex items-center gap-3';
            newField.innerHTML = `
            <input type="file" name="media_files[]" accept="image/*,video/mp4,video/webm" class="flex-1 dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-10 rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
            <button type="button" class="remove-field-btn inline-flex items-center justify-center rounded-lg border border-error-500 bg-error-500 px-3 py-2 text-xs font-medium text-white hover:bg-error-600" style="display: none;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M6 6L18 18M6 18L18 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        `;
            mediaContainer.appendChild(newField);
            updateRemoveButtons();
        });
    }

    // Sil butonlarını güncelle
    function updateRemoveButtons() {
        const removeButtons = document.querySelectorAll('.remove-field-btn');
        removeButtons.forEach(btn => {
            btn.style.display = 'inline-flex';
            // Önceki event listener'ları kaldır
            const newBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(newBtn, btn);
            // Yeni event listener ekle
            newBtn.addEventListener('click', function () {
                const fieldItem = this.closest('.media-field-item');
                if (fieldItem) {
                    // En az bir alan kalmalı
                    const container = fieldItem.parentElement;
                    if (container.children.length > 1) {
                        fieldItem.remove();
                    }
                }
            });
        });
    }

    // İlk yüklemede sil butonlarını güncelle
    updateRemoveButtons();
});
