/**
 * Async download module for block_download_certificates.
 *
 * Intercepts batch download form submissions when certificate count >= threshold,
 * creates an async task, and displays progress via polling.
 *
 * Features:
 * - Modal progress dialog on initial download launch
 * - Persistent top-of-page banner when modal is closed or on page revisit
 * - Continuous polling until task completes, regardless of modal state
 *
 * @module     block_download_certificates/async_download
 * @package    block_download_certificates
 * @copyright  2025 E-learning Touch'
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function ($) {

    /** @const {number} Minimum certificates to trigger async mode */
    var ASYNC_THRESHOLD = 30;

    /** @const {number} Polling interval in milliseconds */
    var POLL_INTERVAL = 3000;

    /** @var {number|null} Current polling timer */
    var pollTimer = null;

    /** @var {string} Base URL for AJAX calls */
    var ajaxBase = M.cfg.wwwroot + '/blocks/download_certificates/ajax/';

    /** @var {Object|null} Current active task state tracked for banner updates */
    var activeTask = null;

    // =========================================================================
    // Persistance des banners fermés (localStorage)
    // =========================================================================

    /** @const {string} Clé localStorage pour les IDs de tâches fermées */
    var DISMISSED_STORAGE_KEY = 'dc_dismissed_ready_tasks';

    /**
     * Retourne la liste des IDs de tâches "ready" fermées par l'utilisateur.
     *
     * @return {Array<number>}
     */
    function getDismissedTasks() {
        try {
            return JSON.parse(localStorage.getItem(DISMISSED_STORAGE_KEY) || '[]');
        } catch (e) {
            return [];
        }
    }

    /**
     * Mémorise un ID de tâche comme fermé pour ne plus l'afficher.
     *
     * @param {number} taskid
     */
    function dismissTask(taskid) {
        var dismissed = getDismissedTasks();
        if (dismissed.indexOf(taskid) === -1) {
            dismissed.push(taskid);
            // Garder seulement les 50 derniers pour ne pas surcharger le localStorage.
            if (dismissed.length > 50) {
                dismissed = dismissed.slice(-50);
            }
            localStorage.setItem(DISMISSED_STORAGE_KEY, JSON.stringify(dismissed));
        }
    }

    /**
     * Vérifie si une tâche a déjà été fermée par l'utilisateur.
     *
     * @param {number} taskid
     * @return {boolean}
     */
    function isTaskDismissed(taskid) {
        return getDismissedTasks().indexOf(taskid) !== -1;
    }

    /**
     * Initialize the async download module.
     *
     * @param {Object} config Configuration from PHP
     * @param {string} config.sesskey Session key
     * @param {Array} config.pending_tasks Any pending/ready tasks for this user
     */
    function init(config) {
        var sesskey = config.sesskey || M.cfg.sesskey;

        // Bind select change events to update cert counts.
        bindSelectUpdates();

        // Intercept form submissions.
        interceptForms(sesskey);

        // Intercept "Download All" button.
        interceptDownloadAll(sesskey);

        // Check for existing tasks — show banner (not modal) on page revisit.
        if (config.pending_tasks && config.pending_tasks.length > 0) {
            config.pending_tasks.forEach(function (task) {
                if (task.status === 'ready') {
                    // Ne pas ré-afficher les banners que l'utilisateur a explicitement fermés.
                    if (!isTaskDismissed(task.taskid)) {
                        showReadyBanner(task);
                    }
                } else if (task.status === 'pending' || task.status === 'processing') {
                    // On page revisit: show banner, NOT modal.
                    activeTask = {
                        taskid: task.taskid,
                        progress: task.progress || 0,
                        total: task.total || 0,
                        status: task.status
                    };
                    showProgressBanner(activeTask);
                    startPolling(task.taskid, sesskey);
                }
            });
        }
    }

    /**
     * Bind change events on select elements to dynamically update data-cert-count.
     */
    function bindSelectUpdates() {
        var forms = document.querySelectorAll('form[data-download-type]');
        forms.forEach(function (form) {
            var select = form.querySelector('select');
            if (select) {
                select.addEventListener('change', function () {
                    var selectedOption = select.options[select.selectedIndex];
                    var count = selectedOption ? (parseInt(selectedOption.getAttribute('data-count'), 10) || 0) : 0;
                    form.setAttribute('data-cert-count', count);
                });
            }
        });
    }

    /**
     * Intercept form submissions for batch downloads.
     *
     * @param {string} sesskey Session key
     */
    function interceptForms(sesskey) {
        var forms = document.querySelectorAll('form[data-cert-count]');
        forms.forEach(function (form) {
            form.addEventListener('submit', function (e) {
                var certCount = parseInt(form.getAttribute('data-cert-count'), 10);
                if (certCount >= ASYNC_THRESHOLD) {
                    e.preventDefault();
                    var type = form.getAttribute('data-download-type');
                    var params = form.getAttribute('data-download-params');
                    try {
                        params = JSON.parse(params);
                    } catch (ex) {
                        params = {};
                    }
                    // Also grab form field values.
                    if (type === 'course') {
                        var courseSelect = form.querySelector('[name="courseid"]');
                        if (courseSelect) {
                            params.courseid = parseInt(courseSelect.value, 10);
                        }
                    } else if (type === 'user') {
                        var userSelect = form.querySelector('[name="userid"]');
                        if (userSelect) {
                            params.userid = parseInt(userSelect.value, 10);
                        }
                    } else if (type === 'cohort') {
                        var cohortSelect = form.querySelector('[name="cohortid"]');
                        if (cohortSelect) {
                            params.cohortid = parseInt(cohortSelect.value, 10);
                        }
                    } else if (type === 'range') {
                        var startDate = form.querySelector('[name="start_date"]');
                        var endDate = form.querySelector('[name="end_date"]');
                        if (startDate && endDate) {
                            params.startdate = Math.floor(new Date(startDate.value).getTime() / 1000);
                            params.enddate = Math.floor(new Date(endDate.value).getTime() / 1000) + 86399;
                        }
                    }
                    createAsyncTask(type, params, sesskey);
                }
                // If below threshold, let the form submit normally.
            });
        });
    }

    /**
     * Intercept the "Download All" button.
     *
     * @param {string} sesskey Session key
     */
    function interceptDownloadAll(sesskey) {
        var downloadAllBtns = document.querySelectorAll('[data-download-all]');
        if (!downloadAllBtns.length) {
            return;
        }

        downloadAllBtns.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                createAsyncTask('all', {}, sesskey);
            });
        });
    }

    /**
     * Create an async download task via AJAX.
     *
     * @param {string} type Download type
     * @param {Object} params Download parameters
     * @param {string} sesskey Session key
     */
    function createAsyncTask(type, params, sesskey) {
        showProgressModal(null, 0, sesskey);

        $.ajax({
            url: ajaxBase + 'create_task.php',
            method: 'POST',
            data: {
                type: type,
                params: JSON.stringify(params),
                sesskey: sesskey,
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    activeTask = {
                        taskid: response.taskid,
                        progress: 0,
                        total: response.total,
                        status: 'pending'
                    };
                    updateProgressModal(response.taskid, 0, response.total, 'pending');
                    startPolling(response.taskid, sesskey);
                } else {
                    showError(response.error || 'Unknown error');
                }
            },
            error: function () {
                showError('Network error. Please try again.');
            }
        });
    }

    /**
     * Start polling for task status.
     *
     * @param {number} taskid Task ID
     * @param {string} sesskey Session key
     */
    function startPolling(taskid, sesskey) {
        stopPolling();
        pollTimer = setInterval(function () {
            $.ajax({
                url: ajaxBase + 'task_status.php',
                method: 'GET',
                data: { taskid: taskid },
                dataType: 'json',
                success: function (response) {
                    if (!response.success) {
                        stopPolling();
                        showError(response.error || 'Status check failed');
                        return;
                    }

                    // Update active task state.
                    if (activeTask) {
                        activeTask.progress = response.progress;
                        activeTask.total = response.total;
                        activeTask.status = response.status;
                    }

                    // Update modal if visible.
                    updateProgressModal(taskid, response.progress, response.total, response.status);

                    // Always update banner if visible.
                    updateProgressBanner(response.progress, response.total, response.status);

                    if (response.status === 'ready') {
                        stopPolling();
                        triggerDownload(response.download_url);
                        // Show completion in banner.
                        showReadyInBanner(response.download_url, response.total);
                    } else if (response.status === 'failed') {
                        stopPolling();
                        showError(response.error_message || 'Task failed');
                        showErrorInBanner(response.error_message || 'Task failed');
                    } else if (response.status === 'expired') {
                        stopPolling();
                        showError('Download has expired.');
                        showErrorInBanner('Download has expired.');
                    }
                },
                error: function () {
                    // Keep polling on network errors, will retry.
                }
            });
        }, POLL_INTERVAL);
    }

    /**
     * Stop polling.
     */
    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    // =========================================================================
    // Progress Banner (persistent, top of page)
    // =========================================================================

    /**
     * Show a persistent progress banner at the top of the page.
     *
     * @param {Object} task Task data {taskid, progress, total, status}
     */
    function showProgressBanner(task) {
        // Remove existing banner if any.
        var existing = document.getElementById('async-progress-banner');
        if (existing) {
            existing.remove();
        }

        var percent = task.total > 0 ? Math.round((task.progress / task.total) * 100) : 0;

        var bannerHtml =
            '<div id="async-progress-banner" class="async-progress-banner">' +
            '<div class="d-flex align-items-center">' +
            '<i class="fa fa-cog fa-spin mr-2" id="banner-spinner"></i>' +
            '<span class="mr-3" id="banner-status-text">' +
            M.util.get_string('async_generating', 'block_download_certificates') +
            '</span>' +
            '<div class="progress flex-grow-1 mr-3" style="height: 20px; min-width: 200px;">' +
            '<div id="banner-progress-bar" ' +
            'class="progress-bar progress-bar-striped progress-bar-animated bg-primary" ' +
            'role="progressbar" style="width: ' + percent + '%;" ' +
            'aria-valuenow="' + percent + '" aria-valuemin="0" aria-valuemax="100">' +
            percent + '%' +
            '</div>' +
            '</div>' +
            '<span class="text-muted small" id="banner-progress-detail">' +
            task.progress + ' / ' + task.total +
            '</span>' +
            '</div>' +
            '</div>';

        var mainBlock = document.querySelector('.block-download-certificat-main')
            || document.getElementById('page-content')
            || document.body;
        mainBlock.insertAdjacentHTML('afterbegin', bannerHtml);
    }

    /**
     * Update the progress banner with new values.
     *
     * @param {number} progress Current progress
     * @param {number} total Total certificates
     * @param {string} status Current status
     */
    function updateProgressBanner(progress, total, status) {
        var bar = document.getElementById('banner-progress-bar');
        var detail = document.getElementById('banner-progress-detail');
        var statusText = document.getElementById('banner-status-text');
        var spinner = document.getElementById('banner-spinner');

        if (!bar) {
            return;
        }

        var percent = total > 0 ? Math.round((progress / total) * 100) : 0;

        bar.style.width = percent + '%';
        bar.setAttribute('aria-valuenow', percent);
        bar.textContent = percent + '%';

        if (detail) {
            detail.textContent = progress + ' / ' + total;
        }

        if (statusText && status === 'processing') {
            statusText.textContent = M.util.get_string('async_processing', 'block_download_certificates');
        }

        if (spinner && status === 'processing') {
            spinner.className = 'fa fa-cog fa-spin mr-2';
        }
    }

    /**
     * Transform the progress banner into a success/ready state.
     *
     * @param {string} downloadUrl URL to download the ZIP
     * @param {number} total Total certificates
     */
    function showReadyInBanner(downloadUrl, total) {
        var banner = document.getElementById('async-progress-banner');
        if (!banner) {
            return;
        }

        banner.className = 'async-progress-banner async-progress-banner-success';
        banner.innerHTML =
            '<div class="d-flex align-items-center justify-content-between">' +
            '<div>' +
            '<i class="fa fa-check-circle text-success mr-2"></i>' +
            '<strong>' + M.util.get_string('async_ready', 'block_download_certificates') + '</strong>' +
            ' <span class="text-muted">(' + total + ' ' +
            M.util.get_string('certificates', 'block_download_certificates') + ')</span>' +
            '</div>' +
            '<div>' +
            '<a href="' + downloadUrl + '" class="btn btn-sm btn-success mr-2">' +
            '<i class="fa fa-download mr-1"></i>' +
            M.util.get_string('async_download', 'block_download_certificates') +
            '</a>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary" id="banner-dismiss">' +
            '<i class="fa fa-times"></i>' +
            '</button>' +
            '</div>' +
            '</div>';

        var dismissBtn = document.getElementById('banner-dismiss');
        if (dismissBtn) {
            dismissBtn.addEventListener('click', function () {
                banner.remove();
            });
        }
    }

    /**
     * Show error state in the banner.
     *
     * @param {string} message Error message
     */
    function showErrorInBanner(message) {
        var banner = document.getElementById('async-progress-banner');
        if (!banner) {
            return;
        }

        banner.className = 'async-progress-banner async-progress-banner-error';
        banner.innerHTML =
            '<div class="d-flex align-items-center justify-content-between">' +
            '<div>' +
            '<i class="fa fa-exclamation-triangle text-danger mr-2"></i>' +
            '<span class="text-danger">' + message + '</span>' +
            '</div>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary" id="banner-dismiss-error">' +
            '<i class="fa fa-times"></i>' +
            '</button>' +
            '</div>';

        var dismissBtn = document.getElementById('banner-dismiss-error');
        if (dismissBtn) {
            dismissBtn.addEventListener('click', function () {
                banner.remove();
            });
        }
    }

    // =========================================================================
    // Progress Modal (initial launch only)
    // =========================================================================

    /**
     * Show/create the progress modal.
     *
     * @param {number|null} taskid Task ID
     * @param {number} total Total certificates
     * @param {string} sesskey Session key
     */
    function showProgressModal(taskid, total, sesskey) {
        // Remove existing modal if any.
        var existing = document.getElementById('async-download-modal');
        if (existing) {
            existing.remove();
        }

        var modalHtml = '<div id="async-download-modal" class="modal fade show" style="display:block;" tabindex="-1">' +
            '<div class="modal-dialog modal-dialog-centered">' +
            '<div class="modal-content">' +
            '<div class="modal-header bg-primary text-white" style="justify-content: space-between;">' +
            '<h5 class="modal-title"><i class="fa fa-download"></i> ' +
            M.util.get_string('async_generating', 'block_download_certificates') + '</h5>' +
            '<button type="button" class="cert-close-btn" id="async-close-btn" aria-label="Close">' +
            '<span aria-hidden="true">&times;</span></button > ' +
            '</div>' +
            '<div class="modal-body text-center">' +
            '<div id="async-status-text" class="mb-3">' +
            '<i class="fa fa-spinner fa-spin fa-2x"></i>' +
            '<p class="mt-2">' + M.util.get_string('async_preparing', 'block_download_certificates') + '</p>' +
            '</div>' +
            '<div class="progress" style="height: 25px;">' +
            '<div id="async-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" ' + ' role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>' +
            '</div>' +
            '<p id="async-progress-detail" class="text-muted mt-2 small">0 / ' + total + '</p>' +
            '<div class="alert alert-info mt-3 small" id="async-info-message">' +
            '<i class="fa fa-info-circle"></i> ' +
            M.util.get_string('async_can_close', 'block_download_certificates') +
            '</div>' +
            '</div>' +
            '</div></div></div>';

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        var closeBtn = document.getElementById('async-close-btn');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                hideProgressModal();
            });
        }
    }

    /**
     * Update the progress modal.
     *
     * @param {number} taskid Task ID
     * @param {number} progress Current progress
     * @param {number} total Total certificates
     * @param {string} status Current status
     */
    function updateProgressModal(taskid, progress, total, status) {
        var progressBar = document.getElementById('async-progress-bar');
        var progressDetail = document.getElementById('async-progress-detail');
        var statusText = document.getElementById('async-status-text');
        var infoMessage = document.getElementById('async-info-message');

        if (!progressBar) {
            return;
        }

        var percent = total > 0 ? Math.round((progress / total) * 100) : 0;

        progressBar.style.width = percent + '%';
        progressBar.setAttribute('aria-valuenow', percent);
        progressBar.textContent = percent + '%';

        if (progressDetail) {
            progressDetail.textContent = progress + ' / ' + total;
        }

        if (statusText) {
            if (status === 'processing') {
                statusText.innerHTML = '<i class="fa fa-cog fa-spin fa-2x"></i>' +
                    '<p class="mt-2">' + M.util.get_string('async_processing', 'block_download_certificates') + '</p>';
            } else if (status === 'ready') {
                statusText.innerHTML = '<i class="fa fa-check-circle fa-2x text-success"></i>' +
                    '<p class="mt-2">' + M.util.get_string('async_ready', 'block_download_certificates') + '</p>';
                progressBar.classList.remove('progress-bar-animated', 'bg-primary');
                progressBar.classList.add('bg-success');

                // Hide the info message when ready.
                if (infoMessage) {
                    infoMessage.style.display = 'none';
                }

                // Auto-close modal after 3 seconds (banner stays visible).
                setTimeout(function () {
                    hideProgressModal();
                }, 3000);
            }
        }
    }

    /**
     * Hide and remove the progress modal. Shows progress banner if task is still active.
     * Supprime aussi tous les backdrops résiduels générés par Bootstrap/Moodle.
     */
    function hideProgressModal() {
        var modal = document.getElementById('async-download-modal');
        if (modal) {
            modal.remove();
        }

        // Supprimer tous les backdrops (le nôtre + celui généré automatiquement par Bootstrap).
        document.querySelectorAll('.modal-backdrop').forEach(function (bd) {
            bd.remove();
        });

        // S'assurer que le body n'est plus verrouillé par le modal.
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';

        // If there's an active task still in progress, show the banner.
        if (activeTask && (activeTask.status === 'pending' || activeTask.status === 'processing')) {
            showProgressBanner(activeTask);
        }
    }

    /**
     * Show an error in the modal.
     *
     * @param {string} message Error message
     */
    function showError(message) {
        var statusText = document.getElementById('async-status-text');
        var progressBar = document.getElementById('async-progress-bar');

        if (statusText) {
            statusText.innerHTML = '<i class="fa fa-exclamation-triangle fa-2x text-danger"></i>' +
                '<p class="mt-2 text-danger">' + message + '</p>';
        }

        if (progressBar) {
            progressBar.classList.remove('progress-bar-animated', 'bg-primary');
            progressBar.classList.add('bg-danger');
        }

        // Show close button.
        var closeBtn = document.getElementById('async-close-btn');
        if (closeBtn) {
            closeBtn.style.display = '';
        }
    }

    /**
     * Trigger file download.
     *
     * @param {string} url Download URL
     */
    function triggerDownload(url) {
        var iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = url;
        document.body.appendChild(iframe);

        // Clean up iframe after 30 seconds.
        setTimeout(function () {
            if (iframe.parentNode) {
                iframe.parentNode.removeChild(iframe);
            }
        }, 30000);
    }

    /**
     * Show a "ZIP ready" banner at the top of the page.
     *
     * @param {Object} task Task data
     */
    function showReadyBanner(task) {
        var downloadUrl = M.cfg.wwwroot + '/blocks/download_certificates/ajax/download_zip.php' +
            '?taskid=' + task.taskid + '&sesskey=' + M.cfg.sesskey;

        // Build a label describing the archive.
        var typeLabel = task.type || 'all';
        var label = M.util.get_string('async_zip_ready_label_' + typeLabel, 'block_download_certificates');
        // Fallback if specific string not found.
        if (!label || label.indexOf('[[') === 0) {
            label = M.util.get_string('async_zip_ready', 'block_download_certificates');
        }

        var bannerId = 'async-ready-banner-' + task.taskid;
        var bannerHtml = '<div id="' + bannerId + '" ' +
            'class="alert alert-success alert-dismissible fade show mx-3 mt-2" role="alert">' +
            '<i class="fa fa-check-circle"></i> ' +
            label + ' (' + task.total + ' ' + M.util.get_string('certificates', 'block_download_certificates') + ')' +
            ' <a href="' + downloadUrl + '" class="btn btn-sm btn-success ml-2">' +
            '<i class="fa fa-download"></i> ' +
            M.util.get_string('async_download', 'block_download_certificates') + '</a>' +
            '<button type="button" class="cert-close-btn cert-close-btn-dark js-dismiss-ready-banner"' +
            ' data-taskid="' + task.taskid + '"' +
            ' data-dismiss="alert" data-bs-dismiss="alert" aria-label="Close">' +
            '<span aria-hidden="true">&times;</span></button></div>';

        var mainBlock = document.querySelector('.block-download-certificat-main')
            || document.getElementById('page-content')
            || document.body;
        mainBlock.insertAdjacentHTML('afterbegin', bannerHtml);

        // Mémoriser la fermeture dans localStorage pour ne pas ré-afficher au rechargement.
        var bannerEl = document.getElementById(bannerId);
        if (bannerEl) {
            var closeBtn = bannerEl.querySelector('.js-dismiss-ready-banner');
            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    dismissTask(parseInt(this.getAttribute('data-taskid'), 10));
                });
            }
        }
    }

    return {
        init: init
    };
});
