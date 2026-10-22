// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The certificates table of the manager and teacher pages: search, sort and paging through the
 * search web service, and the group menu of the teacher's course form.
 *
 * @module     block_download_certificates/certificate_table
 * @copyright  2025 E-learning Touch <contact@elearningtouch.com>
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Autocomplete from 'core/form-autocomplete';
import Notification from 'core/notification';
import Templates from 'core/templates';
import {getString, getStrings} from 'core/str';

const COMPONENT = 'block_download_certificates';

/** @type {string[]} The sort keys of the template's header buttons. */
const SORTABLE = ['firstname', 'email', 'coursename', 'templatename', 'type', 'timecreated'];

/** @type {number} Pages listed on each side of the current one. */
const PAGE_SPREAD = 3;

/** @type {number} Milliseconds of quiet typing before a search runs. */
const SEARCH_DELAY = 300;

const SELECTORS = {
    container: '#cert-table-container',
    count: '#cert-result-count',
    search: '#cert-search-input',
    clear: '#cert-search-clear',
    perpage: '#cert-perpage',
    course: '#course_select',
    groupwrapper: '#group-select-wrapper',
    group: '#group_select',
    menus: ['#course_select', '#cohort_select', '#user_select'],
};

const state = {
    page: 0,
    perpage: 50,
    search: '',
    sort: 'timecreated',
    order: 'DESC',
};

/** @type {Object} The labels, loaded once by init(). */
const strings = {};

/** @type {number} Increases with every table request; a response for an older one is dropped. */
let tablerequest = 0;

/** @type {number} Increases with every group request; a response for an older one is dropped. */
let grouprequest = 0;

/** @type {boolean} Whether init() ran: a second call would bind every listener twice. */
let initialised = false;

/**
 * Load the labels the module writes itself; the template brings its own.
 *
 * @returns {Promise}
 */
const loadStrings = async() => {
    const [nosearchresults, nocertificates, allgroups] = await getStrings([
        {key: 'no_search_results', component: COMPONENT},
        {key: 'no_certificates_found', component: COMPONENT},
        {key: 'all_groups', component: COMPONENT},
    ]);
    Object.assign(strings, {nosearchresults, nocertificates, allgroups});
};

/**
 * The pager's context: the current page with up to PAGE_SPREAD pages on each side, plus the first and
 * the last.
 *
 * @param {number} page The current page, from 0.
 * @param {number} pages The number of pages.
 * @returns {Object}
 */
const buildPagination = (page, pages) => {
    const item = (number) => ({page: number, label: number + 1, active: number === page, ellipsis: false});
    const gap = {page: 0, label: '', active: false, ellipsis: true};
    const first = Math.max(0, page - PAGE_SPREAD);
    const last = Math.min(pages - 1, page + PAGE_SPREAD);
    const items = [];
    if (first > 0) {
        items.push(item(0));
        if (first > 1) {
            items.push(gap);
        }
    }
    for (let number = first; number <= last; number++) {
        items.push(item(number));
    }
    if (last < pages - 1) {
        if (last < pages - 2) {
            items.push(gap);
        }
        items.push(item(pages - 1));
    }
    return {
        haspages: pages > 1,
        previous: {page: Math.max(0, page - 1), disabled: page <= 0},
        next: {page: Math.min(pages - 1, page + 1), disabled: page >= pages - 1},
        pages: items,
    };
};

/**
 * The template context of a search result.
 *
 * @param {Object} result The search web service's result.
 * @returns {Object}
 */
const buildContext = (result) => {
    const columns = {};
    const ascending = state.order === 'ASC';
    SORTABLE.forEach((key) => {
        if (key === state.sort) {
            columns[key] = {
                ariasort: ascending ? 'ascending' : 'descending',
                icon: ascending ? 'fa-sort-up' : 'fa-sort-down',
            };
        } else {
            columns[key] = {ariasort: 'none', icon: 'fa-sort'};
        }
    });
    return {
        hasrows: result.rows.length > 0,
        emptymessage: state.search ? strings.nosearchresults : strings.nocertificates,
        columns,
        rows: result.rows,
        pagination: buildPagination(result.page, result.pages),
    };
};

/**
 * Write the range of rows shown into the result count.
 *
 * @param {Object} result The search web service's result.
 * @param {number} request The table request the result answers.
 * @returns {Promise}
 */
const updateCount = async(result, request) => {
    const element = document.querySelector(SELECTORS.count);
    if (!element) {
        return;
    }
    let text = strings.nocertificates;
    if (result.total > 0) {
        const first = result.page * result.perpage + 1;
        const last = Math.min(first + result.rows.length - 1, result.total);
        text = await getString('result_count', COMPONENT, {first, last, total: result.total});
    }
    if (request === tablerequest) {
        element.textContent = text;
    }
};

/**
 * Load and render the current page of the table.
 *
 * @returns {Promise}
 */
const loadPage = async() => {
    const container = document.querySelector(SELECTORS.container);
    if (!container) {
        return;
    }
    tablerequest += 1;
    const request = tablerequest;
    container.setAttribute('aria-busy', 'true');
    try {
        const result = await Ajax.call([{
            methodname: 'block_download_certificates_search_certificates',
            args: {
                search: state.search,
                sort: state.sort,
                order: state.order,
                page: state.page,
                perpage: state.perpage,
            },
        }])[0];
        if (request !== tablerequest) {
            return;
        }
        const {html, js} = await Templates.renderForPromise('block_download_certificates/certificate_rows', buildContext(result));
        if (request !== tablerequest) {
            return;
        }
        state.page = result.page;
        Templates.replaceNodeContents(container, html, js);
        await updateCount(result, request);
    } catch (error) {
        if (request === tablerequest) {
            Notification.exception(error);
        }
    } finally {
        if (request === tablerequest) {
            container.removeAttribute('aria-busy');
        }
    }
};

/**
 * Sort by a header's column, or turn the order round when it is the current one.
 *
 * @param {string} key The sort key.
 */
const sortBy = (key) => {
    if (state.sort === key) {
        state.order = state.order === 'ASC' ? 'DESC' : 'ASC';
    } else {
        state.sort = key;
        state.order = 'ASC';
    }
    state.page = 0;
    loadPage();
};

/**
 * Handle the header and pager buttons the template renders.
 *
 * @param {Event} event The click.
 */
const onTableClick = (event) => {
    const sortbutton = event.target.closest('[data-sort]');
    if (sortbutton) {
        sortBy(sortbutton.dataset.sort);
        return;
    }
    const pagebutton = event.target.closest('[data-page]');
    if (pagebutton && !pagebutton.disabled) {
        state.page = parseInt(pagebutton.dataset.page, 10) || 0;
        loadPage();
    }
};

/**
 * Run the search after a pause in typing, and at once when the field is cleared.
 */
const bindSearch = () => {
    const input = document.querySelector(SELECTORS.search);
    if (!input) {
        return;
    }
    let timer = null;
    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            state.search = input.value.trim();
            state.page = 0;
            loadPage();
        }, SEARCH_DELAY);
    });
    const clear = document.querySelector(SELECTORS.clear);
    if (clear) {
        clear.addEventListener('click', () => {
            clearTimeout(timer);
            input.value = '';
            state.search = '';
            state.page = 0;
            loadPage();
        });
    }
};

/**
 * Follow the rows-per-page menu.
 */
const bindPerPage = () => {
    const select = document.querySelector(SELECTORS.perpage);
    if (!select) {
        return;
    }
    state.perpage = parseInt(select.value, 10) || state.perpage;
    select.addEventListener('change', () => {
        state.perpage = parseInt(select.value, 10) || state.perpage;
        state.page = 0;
        loadPage();
    });
};

/**
 * Fill the group menu of the teacher's course form with the groups of the chosen course.
 *
 * @param {HTMLSelectElement} course The course menu.
 * @param {HTMLElement} wrapper The group menu's wrapper, hidden when the course has no group.
 * @param {HTMLSelectElement} group The group menu.
 * @returns {Promise}
 */
const loadGroups = async(course, wrapper, group) => {
    grouprequest += 1;
    const request = grouprequest;
    group.replaceChildren();
    wrapper.hidden = true;
    const courseid = parseInt(course.value, 10) || 0;
    if (!courseid) {
        return;
    }
    try {
        const {groups} = await Ajax.call([{
            methodname: 'block_download_certificates_get_groups',
            args: {courseid},
        }])[0];
        if (request !== grouprequest || !groups.length) {
            return;
        }
        group.append(new Option(strings.allgroups, ''));
        groups.forEach(({id, name}) => group.append(new Option(name, String(id))));
        wrapper.hidden = false;
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Bind the group menu of the teacher's course form, where there is one.
 */
const bindGroups = () => {
    const course = document.querySelector(SELECTORS.course);
    const wrapper = document.querySelector(SELECTORS.groupwrapper);
    const group = document.querySelector(SELECTORS.group);
    if (!course || !wrapper || !group) {
        return;
    }
    course.addEventListener('change', () => loadGroups(course, wrapper, group));
};

/**
 * Turn the download forms' menus into searchable ones.
 */
const enhanceMenus = () => {
    SELECTORS.menus.forEach((selector) => {
        if (document.querySelector(selector)) {
            Autocomplete.enhance(selector, false, false, '', false, true, '', true).catch(Notification.exception);
        }
    });
};

/**
 * Start the table.
 *
 * @returns {Promise}
 */
export const init = async() => {
    if (initialised) {
        return;
    }
    initialised = true;
    try {
        await loadStrings();
    } catch (error) {
        Notification.exception(error);
        return;
    }
    const container = document.querySelector(SELECTORS.container);
    if (container) {
        container.addEventListener('click', onTableClick);
    }
    bindSearch();
    bindPerPage();
    bindGroups();
    enhanceMenus();
    await loadPage();
};
