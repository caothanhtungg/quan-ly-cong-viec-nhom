console.log('Task Management System loaded');

document.addEventListener('DOMContentLoaded', function () {
    const previewButtons = document.querySelectorAll('.js-task-preview-btn');

    previewButtons.forEach((button) => {
        button.addEventListener('click', function () {
            const data = this.dataset;

            const setText = (id, value) => {
                const el = document.getElementById(id);
                if (el) el.textContent = value || '';
            };

            const setHtml = (id, value) => {
                const el = document.getElementById(id);
                if (el) el.innerHTML = value || '';
            };

            const progressEl = document.getElementById('previewProgressBar');
            if (progressEl) {
                const progress = data.progress || '0';
                progressEl.style.width = progress + '%';
                progressEl.setAttribute('aria-valuenow', progress);
            }

            setText('previewTaskTitle', data.title || '');
            setText('previewTaskDescription', data.description || 'Không có mô tả');
            setText('previewAssignedName', data.assignedName || '');
            setText('previewCreatorName', data.creatorName || '');
            setText('previewStartDate', data.startDate || '');
            setText('previewDueDate', data.dueDate || '');
            setText('previewProgressText', (data.progress || '0') + '%');
            setText('previewLatestUpdate', data.latestUpdate || 'Chưa có cập nhật tiến độ');
            setText('previewLatestSubmission', data.latestSubmission || 'Chưa có bài nộp');

            setHtml(
                'previewPriorityBadge',
                `<span class="badge ${data.priorityBadge || 'text-bg-secondary'}">${data.priorityText || ''}</span>`
            );

            setHtml(
                'previewStatusBadge',
                `<span class="badge ${data.statusBadge || 'text-bg-secondary'}">${data.statusText || ''}</span>`
            );

            const detailLink = document.getElementById('previewDetailLink');
            if (detailLink) detailLink.href = data.detailUrl || '#';

            const editLink = document.getElementById('previewEditLink');
            if (editLink) {
                if (data.editUrl) {
                    editLink.href = data.editUrl;
                    editLink.classList.remove('d-none');
                } else {
                    editLink.classList.add('d-none');
                }
            }

            const updateLink = document.getElementById('previewUpdateLink');
            if (updateLink) {
                if (data.updateUrl) {
                    updateLink.href = data.updateUrl;
                    updateLink.classList.remove('d-none');
                } else {
                    updateLink.classList.add('d-none');
                }
            }

            const submitLink = document.getElementById('previewSubmitLink');
            if (submitLink) {
                if (data.submitUrl) {
                    submitLink.href = data.submitUrl;
                    submitLink.classList.remove('d-none');
                } else {
                    submitLink.classList.add('d-none');
                }
            }
        });
    });

    const autoToasts = document.querySelectorAll('.js-auto-toast');
    autoToasts.forEach((toastEl) => {
        const toast = new bootstrap.Toast(toastEl, { delay: 3500 });
        toast.show();
    });

    const confirmModalEl = document.getElementById('globalConfirmModal');
    const confirmMessageEl = document.getElementById('globalConfirmMessage');
    const confirmActionEl = document.getElementById('globalConfirmAction');

    if (confirmModalEl && confirmMessageEl && confirmActionEl) {
        const confirmModal = new bootstrap.Modal(confirmModalEl);

        confirmActionEl.addEventListener('click', function () {
            const actionUrl = this.dataset.actionUrl || '';
            if (actionUrl) {
                window.location.href = actionUrl;
            }
        });

        document.querySelectorAll('.js-confirm-action').forEach((btn) => {
            btn.addEventListener('click', function (event) {
                event.preventDefault();

                const href = this.getAttribute('href') || '';
                const formId = this.dataset.confirmForm || '';
                const message = this.dataset.confirmMessage || 'Bạn có chắc muốn thực hiện thao tác này không?';
                const confirmClass = this.dataset.confirmClass || 'btn-danger';
                const confirmText = this.dataset.confirmText || 'Đồng ý';

                confirmMessageEl.textContent = message;
                confirmActionEl.className = 'btn ' + confirmClass;
                confirmActionEl.textContent = confirmText;
                confirmActionEl.dataset.actionUrl = href;

                if (formId) {
                    confirmActionEl.setAttribute('form', formId);
                    confirmActionEl.setAttribute('type', 'submit');
                    confirmActionEl.dataset.actionUrl = '';
                } else {
                    confirmActionEl.removeAttribute('form');
                    confirmActionEl.setAttribute('type', 'button');
                }

                confirmModal.show();
            });
        });
    }
});
