/**
 * Certificate table module for block_download_certificates.
 *
 * Handles dynamic loading, pagination, sorting, and searching of the
 * certificates table via AJAX calls to search_certificates.php.
 *
 * @module     block_download_certificates/certificate_table
 * @package    block_download_certificates
 * @copyright  2025 E-learning Touch <contact@elearningtouch.com>
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/form-autocomplete'], function ($, Autocomplete) {

    /** @var {string} Base URL for AJAX calls */
    var ajaxBase = M.cfg.wwwroot + '/blocks/download_certificates/ajax/';

    /** @var {Object} Current state */
    var state = {
        page: 0,
        perpage: 50,
        search: '',
        sort: 'timecreated',
        order: 'DESC',
        total: 0,
        sesskey: ''
    };

    /** @var {number|null} Search debounce timer */
    var searchTimer = null;

    /** Badge colors per certificate type */
    var typeBadges = {
        'tool_certificate': { label: 'Tool Certificate', cls: 'badge-primary' },
        'customcert': { label: 'Custom Cert', cls: 'badge-success' },
        'mod_certificate': { label: 'Mod Certificate', cls: 'badge-info' },
        'mod_simplecertificate': { label: 'Simple Cert', cls: 'badge-warning' },
        'mod_certificatebeautiful': { label: 'Beautiful Cert', cls: 'badge-secondary' }
    };

    /**
     * Initialize the certificate table module.
     *
     * @param {Object} config Configuration from PHP
     * @param {string} config.sesskey Session key
     * @param {number} config.total_count Total certificates
     */
    function init(config) {
        state.sesskey = config.sesskey || M.cfg.sesskey;
        state.total = config.total_count || 0;

        // Bind events.
        bindSearchInput();
        bindPerPageSelect();
        bindSortHeaders();
        bindCourseGroupSelector();

        // Initial load.
        loadPage();

        // Initialize autocomplete on selects.
        initAutocomplete();
    }

    /**
     * Initialize Moodle core autocomplete on the 3 filter selects.
     */
    function initAutocomplete() {
        var selects = ['#course_select', '#cohort_select', '#user_select'];
        selects.forEach(function (selector) {
            var el = document.querySelector(selector);
            if (el) {
                Autocomplete.enhance(
                    selector,
                    false, // No tags.
                    false, // No ajax.
                    '', // No placeholder override.
                    false, // Not case sensitive.
                    true, // Show suggestions on focus.
                    '', // No no-selection string.
                    true // Close on select.
                );
            }
        });
    }

    /**
     * Bind the search input with debounce.
     */
    function bindSearchInput() {
        var input = document.getElementById('cert-search-input');
        if (!input) {
            return;
        }

        input.addEventListener('input', function () {
            if (searchTimer) {
                clearTimeout(searchTimer);
            }
            searchTimer = setTimeout(function () {
                state.search = input.value.trim();
                state.page = 0;
                loadPage();
            }, 300);
        });

        // Clear button.
        var clearBtn = document.getElementById('cert-search-clear');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                input.value = '';
                state.search = '';
                state.page = 0;
                loadPage();
            });
        }
    }

    /**
     * Bind per-page select change.
     */
    function bindPerPageSelect() {
        var select = document.getElementById('cert-perpage');
        if (!select) {
            return;
        }

        select.addEventListener('change', function () {
            state.perpage = parseInt(select.value, 10);
            state.page = 0;
            loadPage();
        });
    }

    /**
     * Bind sort headers in the table.
     */
    function bindSortHeaders() {
        document.addEventListener('click', function (e) {
            var header = e.target.closest('[data-sort]');
            if (!header) {
                return;
            }

            var sortField = header.getAttribute('data-sort');
            if (state.sort === sortField) {
                state.order = state.order === 'ASC' ? 'DESC' : 'ASC';
            } else {
                state.sort = sortField;
                state.order = 'DESC';
            }
            state.page = 0;
            loadPage();
        });
    }

    /**
     * Écoute le changement de cours et charge les groupes de l'enseignant
     * via AJAX. Affiche le sélecteur de groupe si ≥1 groupe est disponible.
     */
    function bindCourseGroupSelector() {
        var courseSelect = document.getElementById('course_select');
        var groupWrapper = document.getElementById('group-select-wrapper');
        var groupSelect  = document.getElementById('group_select');

        if (!courseSelect || !groupWrapper || !groupSelect) {
            return;
        }

        courseSelect.addEventListener('change', function () {
            var courseid = courseSelect.value;

            // Réinitialiser et masquer le sélecteur de groupe.
            groupWrapper.style.display = 'none';
            groupSelect.innerHTML = '';

            if (!courseid) {
                return;
            }

            // Appel AJAX pour récupérer les groupes du cours.
            $.ajax({
                url: ajaxBase + 'get_groups_for_course.php',
                method: 'GET',
                data: {
                    courseid: courseid,
                    sesskey: state.sesskey
                },
                dataType: 'json',
                success: function (response) {
                    if (!response.success || !response.groups || response.groups.length === 0) {
                        // Pas de groupes : ne pas afficher le sélecteur.
                        return;
                    }

                    // Construire les options : d'abord "Tous les groupes".
                    var allLabel = M.util.get_string('all_groups', 'block_download_certificates') ||
                                  'Tous les groupes';
                    var html = '<option value="">' + allLabel + '</option>';

                    response.groups.forEach(function (g) {
                        html += '<option value="' + g.id + '">' +
                            escapeHtml(g.name) + '</option>';
                    });

                    groupSelect.innerHTML = html;
                    groupWrapper.style.display = '';
                }
            });
        });
    }

    /**
     * Load a page of certificates via AJAX.
     */
    function loadPage() {
        var container = document.getElementById('cert-table-container');
        if (!container) {
            return;
        }

        // Show loading.
        showLoading(container);

        $.ajax({
            url: ajaxBase + 'search_certificates.php',
            method: 'GET',
            data: {
                search: state.search,
                page: state.page,
                perpage: state.perpage,
                sort: state.sort,
                order: state.order,
                sesskey: state.sesskey
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    state.total = response.total;
                    renderTable(container, response.data);
                    renderPagination(response.total, response.page, response.perpage, response.pages);
                    updateResultCount(response.total);
                } else {
                    container.innerHTML = '<div class="alert alert-danger">' +
                        '<i class="fa fa-exclamation-triangle"></i> ' + (response.error || 'Error loading data') +
                        '</div>';
                }
            },
            error: function () {
                container.innerHTML = '<div class="alert alert-danger">' +
                    '<i class="fa fa-exclamation-triangle"></i> Network error. Please try again.' +
                    '</div>';
            }
        });
    }

    /**
     * Show loading spinner in container.
     *
     * @param {HTMLElement} container
     */
    function showLoading(container) {
        container.innerHTML = '<div class="text-center py-4">' +
            '<i class="fa fa-spinner fa-spin fa-2x text-primary"></i>' +
            '<p class="mt-2 text-muted">' +
            (M.util.get_string('loading', 'core') || 'Loading...') +
            '</p></div>';
    }

    /**
     * Render the certificate table.
     *
     * @param {HTMLElement} container
     * @param {Array} data Certificate records
     */
    function renderTable(container, data) {
        if (!data || data.length === 0) {
            container.innerHTML = '<div class="alert alert-info text-center">' +
                '<i class="fa fa-info-circle fa-2x mb-2"></i>' +
                '<p>' + (state.search ?
                    (M.util.get_string('no_search_results', 'block_download_certificates') || 'No results found for your search.') :
                    (M.util.get_string('no_certificates_found', 'block_download_certificates') || 'No certificates found.')) +
                '</p></div>';
            return;
        }

        var sortIcon = function (field) {
            if (state.sort !== field) {
                return '<i class="fa fa-sort text-muted ml-1"></i>';
            }
            return state.order === 'ASC' ?
                '<i class="fa fa-sort-up ml-1"></i>' :
                '<i class="fa fa-sort-down ml-1"></i>';
        };

        var html = '<div class="table-responsive">' +
            '<table class="table table-striped table-hover cert-table">' +
            '<thead>' +
            '<tr>' +
            '<th data-sort="firstname" role="button" class="sortable-header">' +
            (M.util.get_string('user', 'core') || 'User') + sortIcon('firstname') + '</th>' +
            '<th data-sort="email" role="button" class="sortable-header">' +
            (M.util.get_string('email', 'core') || 'Email') + sortIcon('email') + '</th>' +
            '<th data-sort="coursename" role="button" class="sortable-header">' +
            (M.util.get_string('course', 'core') || 'Course') + sortIcon('coursename') + '</th>' +
            '<th data-sort="templatename" role="button" class="sortable-header">' +
            (M.util.get_string('template', 'block_download_certificates') || 'Template') + sortIcon('templatename') + '</th>' +
            '<th data-sort="cert_type" role="button" class="sortable-header">' +
            (M.util.get_string('type', 'block_download_certificates') || 'Type') + sortIcon('cert_type') + '</th>' +
            '\x3cth data-sort="timecreated" role="button" class="sortable-header"\x3e' +
            (M.util.get_string('date_created', 'block_download_certificates') || 'Date') + sortIcon('timecreated') + '\x3c/th\x3e' +
            '\x3cth class="text-center"\x3e' +
            (M.util.get_string('actions', 'core') || 'Actions') + '\x3c/th\x3e' +
            '\x3c/tr\x3e' +
            '\x3c/thead\x3e' +
            '\x3ctbody\x3e';

        data.forEach(function (cert, index) {
            var badge = typeBadges[cert.cert_type] || { label: cert.cert_type, cls: 'badge-dark' };
            html += '<tr class="cert-row" style="animation-delay:' + (index * 20) + 'ms">' +
                '<td><strong>' + escapeHtml(cert.username) + '</strong></td>' +
                '<td><small class="text-muted">' + escapeHtml(cert.email) + '</small></td>' +
                '<td>' + escapeHtml(cert.coursename) + '</td>' +
                '<td>' + escapeHtml(cert.templatename) + '</td>' +
                '<td><span class="badge ' + badge.cls + '">' + escapeHtml(badge.label) + '</span></td>' +
                '<td><small>' + escapeHtml(cert.timecreated) + '</small></td>' +
                '<td class="text-center"><a href="' + escapeHtml(cert.download_url) + '" ' +
                'class="btn btn-sm btn-outline-primary" title="' +
                (M.util.get_string('download_certificate', 'block_download_certificates') || 'Download') + '">' +
                '<i class="fa fa-download"></i></a></td>' +
                '</tr>';
        });

        html += '</tbody></table></div>';
        container.innerHTML = html;
    }

    /**
     * Render pagination controls.
     *
     * @param {number} total Total results
     * @param {number} page Current page
     * @param {number} perpage Items per page
     * @param {number} pages Total pages
     */
    function renderPagination(total, page, perpage, pages) {
        var container = document.getElementById('cert-pagination');
        if (!container) {
            return;
        }

        if (pages <= 1) {
            container.innerHTML = '';
            return;
        }

        var html = '<nav aria-label="Certificate pagination"><ul class="pagination pagination-sm justify-content-center">';

        // Previous button.
        html += '<li class="page-item ' + (page === 0 ? 'disabled' : '') + '">' +
            '<a class="page-link" href="#" data-page="' + (page - 1) + '" aria-label="Previous">' +
            '<i class="fa fa-chevron-left"></i></a></li>';

        // Page numbers (show max 7 pages).
        var startPage = Math.max(0, page - 3);
        var endPage = Math.min(pages - 1, startPage + 6);
        startPage = Math.max(0, endPage - 6);

        if (startPage > 0) {
            html += '<li class="page-item"><a class="page-link" href="#" data-page="0">1</a></li>';
            if (startPage > 1) {
                html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
        }

        for (var i = startPage; i <= endPage; i++) {
            html += '<li class="page-item ' + (i === page ? 'active' : '') + '">' +
                '<a class="page-link" href="#" data-page="' + i + '">' + (i + 1) + '</a></li>';
        }

        if (endPage < pages - 1) {
            if (endPage < pages - 2) {
                html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
            html += '<li class="page-item"><a class="page-link" href="#" data-page="' + (pages - 1) + '">' +
                pages + '</a></li>';
        }

        // Next button.
        html += '<li class="page-item ' + (page >= pages - 1 ? 'disabled' : '') + '">' +
            '<a class="page-link" href="#" data-page="' + (page + 1) + '" aria-label="Next">' +
            '<i class="fa fa-chevron-right"></i></a></li>';

        html += '</ul></nav>';

        container.innerHTML = html;

        // Bind click events.
        container.querySelectorAll('[data-page]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                var newPage = parseInt(link.getAttribute('data-page'), 10);
                if (newPage >= 0 && newPage < pages && newPage !== state.page) {
                    state.page = newPage;
                    loadPage();
                    // Scroll to top of table.
                    var tableTop = document.getElementById('cert-table-container');
                    if (tableTop) {
                        tableTop.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
            });
        });
    }

    /**
     * Update the result count display.
     *
     * @param {number} total Total results
     */
    function updateResultCount(total) {
        var el = document.getElementById('cert-result-count');
        if (el) {
            var start = state.page * state.perpage + 1;
            var end = Math.min(start + state.perpage - 1, total);
            if (total === 0) {
                el.textContent = '0 ' +
                    (M.util.get_string('certificates', 'block_download_certificates') || 'certificates');
            } else {
                el.textContent = start + '-' + end + ' / ' + total + ' ' +
                    (M.util.get_string('certificates', 'block_download_certificates') || 'certificates');
            }
        }
    }

    /**
     * Escape HTML entities.
     *
     * @param {string} text
     * @return {string}
     */
    function escapeHtml(text) {
        if (!text) {
            return '';
        }
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    return {
        init: init
    };
});
