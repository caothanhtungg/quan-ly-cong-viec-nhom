document.addEventListener('DOMContentLoaded', function () {
    document.body.classList.add('app-ready');

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const pageSections = document.querySelectorAll('.page-content > *');
    const highlightTitles = document.querySelectorAll('.app-page-head h3, .dashboard-hero-copy h3');
    const staggerItems = document.querySelectorAll('.dashboard-hero-side .dashboard-hero-metric, .dashboard-stat-grid .stat-card');
    const scrollProgress = document.createElement('div');
    let scrollTicking = false;

    scrollProgress.className = 'app-scroll-progress';
    scrollProgress.setAttribute('aria-hidden', 'true');
    scrollProgress.innerHTML = '<span></span>';
    document.body.prepend(scrollProgress);

    const updateScrollProgress = () => {
        const scrollableHeight = document.documentElement.scrollHeight - window.innerHeight;
        const progress = scrollableHeight > 0 ? Math.min(window.scrollY / scrollableHeight, 1) : 0;
        scrollProgress.firstElementChild.style.width = `${progress * 100}%`;
        scrollTicking = false;
    };

    const requestScrollProgressUpdate = () => {
        if (!scrollTicking) {
            window.requestAnimationFrame(updateScrollProgress);
            scrollTicking = true;
        }
    };

    const revealObserver = 'IntersectionObserver' in window && !reduceMotion
        ? new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -30px' })
        : null;

    pageSections.forEach((section, index) => {
        section.classList.add('page-reveal');
        section.style.setProperty('--reveal-delay', `${Math.min(index * 70, 420)}ms`);
        revealObserver ? revealObserver.observe(section) : section.classList.add('is-visible');
    });

    highlightTitles.forEach((title) => {
        title.classList.add('app-text-highlight');
        revealObserver ? revealObserver.observe(title) : title.classList.add('is-visible');
    });

    staggerItems.forEach((item, index) => {
        item.classList.add('app-stagger-item');
        item.style.setProperty('--stagger-delay', `${(index % 6) * 65}ms`);
        revealObserver ? revealObserver.observe(item) : item.classList.add('is-visible');
    });

    updateScrollProgress();
    window.addEventListener('scroll', requestScrollProgressUpdate, { passive: true });
    window.addEventListener('resize', requestScrollProgressUpdate);

    const enhanceDataTables = () => {
        document.querySelectorAll('.table-responsive > table.table').forEach((table, tableIndex) => {
            const headerCells = Array.from(table.querySelectorAll('thead tr:first-child > th'));

            if (headerCells.length < 6 || table.dataset.enhancedTable === 'true') {
                return;
            }

            table.dataset.enhancedTable = 'true';
            table.classList.add('app-animated-table');

            const storageKey = `task-management:columns:${window.location.pathname}:${tableIndex}`;
            let hiddenColumns = [];

            try {
                hiddenColumns = JSON.parse(window.localStorage.getItem(storageKey) || '[]');
            } catch (error) {
                hiddenColumns = [];
            }

            hiddenColumns = Array.isArray(hiddenColumns)
                ? hiddenColumns.filter((index) => Number.isInteger(index) && index > 0 && index < headerCells.length - 1)
                : [];

            const updateColspans = () => {
                const visibleCount = headerCells.filter((_, index) => !hiddenColumns.includes(index)).length;

                table.querySelectorAll('tbody td[colspan]').forEach((cell) => {
                    cell.colSpan = visibleCount;
                });
            };

            const setColumnVisibility = (columnIndex, isVisible) => {
                table.querySelectorAll('tr').forEach((row) => {
                    const cell = row.children[columnIndex];

                    if (cell && !cell.hasAttribute('colspan')) {
                        cell.classList.toggle('app-table-column-hidden', !isVisible);
                    }
                });
            };

            const applyColumnVisibility = () => {
                headerCells.forEach((_, index) => {
                    setColumnVisibility(index, !hiddenColumns.includes(index));
                });

                updateColspans();
            };

            const controls = document.createElement('div');
            controls.className = 'app-table-tools';
            controls.innerHTML = `
                <div class="app-table-tools-copy">
                    <i class="bi bi-layout-three-columns"></i>
                    <span>Tùy chỉnh cột hiển thị</span>
                </div>
                <details class="app-column-picker">
                    <summary>
                        <i class="bi bi-sliders2"></i>
                        Cột hiển thị
                    </summary>
                    <div class="app-column-picker-panel"></div>
                </details>
            `;

            const pickerPanel = controls.querySelector('.app-column-picker-panel');

            headerCells.forEach((header, columnIndex) => {
                const isLocked = columnIndex === 0 || columnIndex === headerCells.length - 1;
                const label = document.createElement('label');
                const checkbox = document.createElement('input');
                const labelText = header.textContent.trim() || `Cột ${columnIndex + 1}`;

                checkbox.type = 'checkbox';
                checkbox.checked = !hiddenColumns.includes(columnIndex);
                checkbox.disabled = isLocked;
                checkbox.dataset.columnIndex = String(columnIndex);

                label.className = isLocked ? 'is-locked' : '';
                label.append(checkbox, document.createTextNode(labelText));
                pickerPanel.append(label);

                checkbox.addEventListener('change', () => {
                    if (checkbox.checked) {
                        hiddenColumns = hiddenColumns.filter((index) => index !== columnIndex);
                    } else {
                        hiddenColumns = [...hiddenColumns, columnIndex];
                    }

                    window.localStorage.setItem(storageKey, JSON.stringify(hiddenColumns));
                    applyColumnVisibility();
                });
            });

            table.parentElement.before(controls);
            applyColumnVisibility();

            table.querySelectorAll('tbody > tr').forEach((row, rowIndex) => {
                row.classList.add('app-table-row');
                row.style.setProperty('--table-row-delay', `${Math.min(rowIndex * 35, 280)}ms`);
            });

            window.requestAnimationFrame(() => {
                table.querySelectorAll('.app-table-row').forEach((row) => row.classList.add('is-visible'));
            });
        });
    };

    enhanceDataTables();

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

    const suppressNativePasswordReveal = (input) => {
        if (!input || input.getAttribute('type') !== 'password') {
            return;
        }

        if (input.value === '') {
            delete input.dataset.nativeRevealSuppressed;
            return;
        }

        if (input.dataset.nativeRevealSuppressed === 'true') {
            return;
        }

        const selectionStart = input.selectionStart;
        const selectionEnd = input.selectionEnd;

        // Edge/Chromium removes its built-in reveal button after script-driven input updates.
        input.value = input.value;

        if (typeof selectionStart === 'number' && typeof selectionEnd === 'number') {
            input.setSelectionRange(selectionStart, selectionEnd);
        }

        input.dataset.nativeRevealSuppressed = 'true';
    };

    document.querySelectorAll('[data-password-input]').forEach((input) => {
        input.addEventListener('focus', () => {
            suppressNativePasswordReveal(input);
        });

        input.addEventListener('input', () => {
            suppressNativePasswordReveal(input);
        });
    });

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', function () {
            const wrapper = this.closest('.auth-input-wrap');
            const input = wrapper ? wrapper.querySelector('[data-password-input]') : null;

            if (!input) {
                return;
            }

            const isPassword = input.getAttribute('type') === 'password';
            input.setAttribute('type', isPassword ? 'text' : 'password');
            this.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');

            const icon = this.querySelector('i');
            if (icon) {
                icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
            }

            if (!isPassword) {
                delete input.dataset.nativeRevealSuppressed;
                suppressNativePasswordReveal(input);
            }
        });
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
