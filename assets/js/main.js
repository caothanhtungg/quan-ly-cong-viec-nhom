document.addEventListener('DOMContentLoaded', function () {
    document.body.classList.add('app-ready');

    document.querySelectorAll('.page-content > *').forEach((section, index) => {
        section.classList.add('page-reveal');
        section.style.setProperty('--reveal-delay', `${Math.min(index * 70, 420)}ms`);
    });

    const sidebarToggleButtons = document.querySelectorAll('.js-sidebar-toggle');
    const sidebarCloseButtons = document.querySelectorAll('.js-sidebar-close');
    const sidebarBackdrop = document.querySelector('.js-sidebar-backdrop');
    const sidebarLinks = document.querySelectorAll('.sidebar-menu .nav-link');
    const closeSidebar = () => document.body.classList.remove('sidebar-open');
    const openSidebar = () => document.body.classList.add('sidebar-open');

    sidebarToggleButtons.forEach((button) => {
        button.addEventListener('click', openSidebar);
    });

    sidebarCloseButtons.forEach((button) => {
        button.addEventListener('click', closeSidebar);
    });

    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', closeSidebar);
    }

    sidebarLinks.forEach((link) => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 992) {
                closeSidebar();
            }
        });
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 992) {
            closeSidebar();
        }
    });

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
            setText('previewTaskDescription', data.description || 'Khong co mo ta');
            setText('previewAssignedName', data.assignedName || '');
            setText('previewCreatorName', data.creatorName || '');
            setText('previewStartDate', data.startDate || '');
            setText('previewDueDate', data.dueDate || '');
            setText('previewProgressText', (data.progress || '0') + '%');
            setText('previewLatestUpdate', data.latestUpdate || 'Chua co cap nhat tien do');
            setText('previewLatestSubmission', data.latestSubmission || 'Chua co bai nop');

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
                const message = this.dataset.confirmMessage || 'Ban co chac muon thuc hien thao tac nay khong?';
                const confirmClass = this.dataset.confirmClass || 'btn-danger';
                const confirmText = this.dataset.confirmText || 'Dong y';

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
