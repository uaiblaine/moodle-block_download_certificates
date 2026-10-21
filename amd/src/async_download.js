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
 * Background ZIP downloads: a ZIP form whose selection reaches its threshold is queued as a task
 * instead of posted, its progress shown in a dialogue and, once the dialogue is closed or the page
 * revisited, in a banner; the ready archive is asked for by a POST into a hidden frame.
 *
 * The forms are marked with `data-async-threshold`, on the form or on its submit button, beside
 * `data-download-type` and `data-cert-count`. A form with `data-confirm` asks that question first.
 *
 * @module     block_download_certificates/async_download
 * @copyright  2025 E-learning Touch <contact@elearningtouch.com>
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Config from 'core/config';
import Modal from 'core/modal';
import ModalEvents from 'core/modal_events';
import Notification from 'core/notification';
import Templates from 'core/templates';
import {getStrings} from 'core/str';

const COMPONENT = 'block_download_certificates';
const TEMPLATE = 'block_download_certificates/task_progress';
const FRAME = 'block-download-certificates-frame';

/** @type {string} The class of the progress dialogue, which the stylesheet's tokens are declared on. */
const MODAL_CLASS = 'block-download-certificates-modal';

/** @type {string} The root of the plugin's pages, where a banner goes first. */
const PAGE_SELECTOR = '.block-download-certificates-page';

/** @type {number} Milliseconds between two status requests. */
const POLL_DELAY = 3000;

/** @type {number} The longest wait between two status requests after failures. */
const MAX_DELAY = 30000;

/** @type {number} Failed status requests in a row after which polling stops. */
const MAX_FAILURES = 5;

/** @type {number} Status requests after which polling stops: an hour at the normal pace. */
const MAX_POLLS = 1200;

/** @type {number} Status requests after a download was posted, while the task is still ready. */
const FOLLOW_ATTEMPTS = 5;

/** @type {number} Dismissed tasks remembered per user. */
const MAX_DISMISSED = 50;

/** @type {string[]} The states after which nothing changes any more. */
const FINISHED = ['ready', 'failed', 'expired', 'stopped'];

/** @type {string[]} The finished states that are failures. */
const FAILURES = ['failed', 'expired', 'stopped'];

/** @type {Object} Web service argument => form field name; the integer ones are parsed. */
const FIELDS = {
    courseid: 'courseid',
    groupid: 'groupid',
    userid: 'userid',
    cohortid: 'cohortid',
    startdate: 'start_date',
    enddate: 'end_date',
};

/** @type {string[]} The integer arguments of FIELDS. */
const INTEGER_FIELDS = ['courseid', 'groupid', 'userid', 'cohortid'];

/** @type {Object} Label name => string identifier: every string the module writes itself. */
const STRINGS = {
    title: 'async_generating',
    preparing: 'async_preparing',
    processing: 'async_processing',
    ready: 'async_ready',
    expired: 'async_expired',
    stopped: 'async_polling_stopped',
    failed: 'requestfailed',
    readyany: 'async_zip_ready',
    readyall: 'async_zip_ready_label_all',
    readycourse: 'async_zip_ready_label_course',
    readyuser: 'async_zip_ready_label_user',
    readycohort: 'async_zip_ready_label_cohort',
    readyrange: 'async_zip_ready_label_range',
};

/** @type {Object} Label name => core string identifier. */
const CORE_STRINGS = {
    confirm: 'confirm',
    download: 'download',
};

/** @type {Object} Request type => the label of its ready archive. */
const READY_LABELS = {
    all: 'readyall',
    course: 'readycourse',
    user: 'readyuser',
    cohort: 'readycohort',
    range: 'readyrange',
};

/** @type {Object} The labels, loaded once by init(). */
const strings = {};

/** @type {WeakMap} Rendered progress root => its task. */
const views = new WeakMap();

/** @type {number} The current user, part of the storage key. */
let userid = 0;

/** @type {boolean} Whether init() ran: the block and the page may both ask for the module. */
let initialised = false;

/**
 * The storage key of the user's dismissed banners.
 *
 * @returns {string}
 */
const storageKey = () => `${COMPONENT}_dismissed_${userid}`;

/**
 * The ids of the tasks whose ready banner the user dismissed; empty when storage is unavailable.
 *
 * @returns {number[]}
 */
const getDismissed = () => {
    try {
        const stored = JSON.parse(window.localStorage.getItem(storageKey()) || '[]');
        return Array.isArray(stored) ? stored : [];
    } catch (error) {
        return [];
    }
};

/**
 * Remember that the user dismissed a task's ready banner.
 *
 * @param {number} taskid The task.
 */
const rememberDismissed = (taskid) => {
    const dismissed = getDismissed().filter((id) => id !== taskid);
    dismissed.push(taskid);
    try {
        window.localStorage.setItem(storageKey(), JSON.stringify(dismissed.slice(-MAX_DISMISSED)));
    } catch (error) {
        // Private browsing or a full store: the banner comes back on the next visit.
    }
};

/**
 * A task as the module tracks it.
 *
 * @param {Object} data taskid, type, status, progress, total.
 * @returns {Object}
 */
const newTask = (data) => ({
    taskid: data.taskid,
    type: data.type,
    status: data.status,
    progress: data.progress || 0,
    total: data.total || 0,
    errormessage: '',
    downloadurl: '',
    downloaded: false,
    autodownload: false,
    autodownloaded: false,
    roots: new Set(),
    failures: 0,
    polls: 0,
    inflight: false,
});

/**
 * The state of a task, in words.
 *
 * @param {Object} task The task.
 * @returns {string}
 */
const messageFor = (task) => {
    switch (task.status) {
        case 'pending':
            return strings.preparing;
        case 'processing':
            return strings.processing;
        case 'ready':
            if (task.autodownload) {
                return strings.ready;
            }
            return strings[READY_LABELS[task.type]] || strings.readyany;
        case 'failed':
            return task.errormessage || strings.failed;
        case 'expired':
            return strings.expired;
        case 'stopped':
            return strings.stopped;
        default:
            return '';
    }
};

/**
 * The progress as a whole percentage.
 *
 * @param {Object} task The task.
 * @returns {number}
 */
const percentOf = (task) => (task.total > 0 ? Math.round(task.progress * 100 / task.total) : 0);

/**
 * The template context of a task's progress.
 *
 * @param {Object} task The task.
 * @param {boolean} banner Whether it is rendered as a banner.
 * @returns {Object}
 */
const progressContext = (task, banner) => ({
    taskid: task.taskid,
    banner,
    showhint: !banner,
    message: messageFor(task),
    percent: percentOf(task),
    progress: task.progress,
    total: task.total,
    ready: task.status === 'ready' && !task.downloaded,
});

/**
 * Bring one rendered progress up to date with its task.
 *
 * @param {HTMLElement} root The rendered progress.
 * @param {Object} task The task.
 */
const paintRoot = (root, task) => {
    const percent = percentOf(task);
    const finished = FINISHED.includes(task.status);
    const failed = FAILURES.includes(task.status);
    const message = root.querySelector('[data-region="message"]');
    const bar = root.querySelector('[data-region="bar"]');
    const detail = root.querySelector('[data-region="detail"]');
    const hint = root.querySelector('[data-region="hint"]');
    const download = root.querySelector('[data-action="download"]');

    message.textContent = messageFor(task);
    message.classList.toggle('text-danger', failed);
    bar.style.width = `${percent}%`;
    bar.setAttribute('aria-valuenow', String(percent));
    bar.textContent = `${percent}%`;
    bar.classList.toggle('progress-bar-animated', !finished);
    bar.classList.toggle('bg-success', task.status === 'ready');
    bar.classList.toggle('bg-danger', failed);
    detail.textContent = `${task.progress} / ${task.total}`;
    if (hint) {
        hint.hidden = finished;
    }
    download.hidden = task.status !== 'ready' || task.downloaded;
};

/**
 * Bring every rendered progress of a task up to date.
 *
 * @param {Object} task The task.
 */
const paint = (task) => task.roots.forEach((root) => paintRoot(root, task));

/**
 * Render a task's progress and attach it to the task.
 *
 * @param {Object} task The task.
 * @param {boolean} banner Whether it is rendered as a banner.
 * @returns {Promise<Object>} html and js of the render.
 */
const renderProgress = (task, banner) => Templates.renderForPromise(TEMPLATE, progressContext(task, banner));

/**
 * Track a rendered progress root.
 *
 * @param {HTMLElement|undefined} root The root.
 * @param {Object} task The task.
 */
const attach = (root, task) => {
    if (!root) {
        return;
    }
    views.set(root, task);
    task.roots.add(root);
    paintRoot(root, task);
};

/**
 * Show a task's progress in a banner at the top of the page.
 *
 * @param {Object} task The task.
 * @returns {Promise}
 */
const showBanner = async(task) => {
    const container = document.querySelector(PAGE_SELECTOR)
        || document.getElementById('region-main')
        || document.body;
    const {html, js} = await renderProgress(task, true);
    const nodes = Templates.prependNodeContents(container, html, js);
    attach(nodes.find((node) => node.nodeType === Node.ELEMENT_NODE), task);
};

/**
 * Show a task's progress in a dialogue; closing it before the end moves the progress to a banner.
 *
 * @param {Object} task The task.
 * @returns {Promise}
 */
const showDialogue = async(task) => {
    const {html} = await renderProgress(task, false);
    const modal = await Modal.create({
        title: strings.title,
        body: html,
        show: true,
        removeOnClose: true,
    });
    modal.getRoot()[0].classList.add(MODAL_CLASS);
    const root = modal.getBody()[0].querySelector('[data-region="task-progress"]');
    attach(root, task);
    modal.getRoot().on(ModalEvents.hidden, () => {
        task.roots.delete(root);
        if (!FINISHED.includes(task.status)) {
            showBanner(task).catch(Notification.exception);
        }
    });
};

/**
 * Post for a ready archive into a hidden frame, so that the page stays.
 *
 * @param {Object} task The task.
 */
const download = (task) => {
    let frame = document.getElementById(FRAME);
    if (!frame) {
        frame = document.createElement('iframe');
        frame.id = FRAME;
        frame.name = FRAME;
        frame.hidden = true;
        document.body.append(frame);
    }
    const form = document.createElement('form');
    form.method = 'post';
    form.action = task.downloadurl || `${Config.wwwroot}/blocks/download_certificates/ajax/download_zip.php`;
    form.target = FRAME;
    form.hidden = true;
    [['taskid', task.taskid], ['sesskey', Config.sesskey]].forEach(([name, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = String(value);
        form.append(input);
    });
    document.body.append(form);
    form.submit();
    setTimeout(() => form.remove(), POLL_DELAY);
    // The archive is handed over once and the server expires the task when it claims it: the
    // button stays until a status request reports that, and a frame that loads while the task is
    // still ready means the server sent a page instead of the archive.
    frame.addEventListener('load', () => onFrameLoad(task), {once: true});
    followDownload(task, FOLLOW_ATTEMPTS);
};

/**
 * Ask once for a task's status and take it in; a failure leaves the task as it was.
 *
 * @param {Object} task The task.
 * @returns {Promise}
 */
const refresh = async(task) => {
    try {
        const status = await Ajax.call([{
            methodname: 'block_download_certificates_get_task_status',
            args: {taskid: task.taskid},
        }])[0];
        applyStatus(task, status);
    } catch (error) {
        // The next poll or a retry of the button asks again.
    }
};

/**
 * Ask for the status right after a download was posted, and again a few times while the task is
 * still ready (the server may not have claimed it yet); the banner then shows it as handed over.
 *
 * @param {Object} task The task.
 * @param {number} attempts Status requests left.
 */
const followDownload = (task, attempts) => {
    refresh(task).then(() => {
        if (task.status === 'ready' && attempts > 1) {
            setTimeout(() => followDownload(task, attempts - 1), POLL_DELAY);
        }
        return null;
    }).catch(Notification.exception);
};

/**
 * The hidden frame loaded: an archive is a download, so this is a page, usually an error.
 *
 * @param {Object} task The task.
 * @returns {Promise}
 */
const onFrameLoad = async(task) => {
    await refresh(task);
    if (task.status === 'ready') {
        Notification.addNotification({message: strings.failed, type: 'error'});
    }
};

/**
 * Take in a status the web service returned.
 *
 * @param {Object} task The task.
 * @param {Object} status The web service's result.
 */
const applyStatus = (task, status) => {
    task.status = status.status;
    task.progress = status.progress;
    task.total = status.total;
    task.errormessage = status.errormessage;
    task.downloadurl = status.downloadurl;
    task.downloaded = task.status === 'expired';
    paint(task);
    if (task.status === 'ready' && task.autodownload && !task.autodownloaded) {
        task.autodownloaded = true;
        download(task);
    }
};

/**
 * Ask for a task's status, then schedule the next request: one request in flight, a longer wait
 * after each failure, and a stop after too many failures or requests.
 *
 * @param {Object} task The task.
 * @returns {Promise}
 */
const poll = async(task) => {
    if (task.inflight || !task.roots.size) {
        // Nothing shows the task any more: the next visit picks it up again.
        return;
    }
    task.inflight = true;
    task.polls += 1;
    let delay = POLL_DELAY;
    try {
        const status = await Ajax.call([{
            methodname: 'block_download_certificates_get_task_status',
            args: {taskid: task.taskid},
        }])[0];
        task.failures = 0;
        applyStatus(task, status);
    } catch (error) {
        if (error && error.errorcode) {
            // The server refused, as for a task that is gone: asking again changes nothing.
            task.status = 'failed';
            task.errormessage = error.message || '';
            paint(task);
        } else {
            task.failures += 1;
            delay = Math.min(POLL_DELAY * (2 ** task.failures), MAX_DELAY);
        }
    } finally {
        task.inflight = false;
    }
    if (FINISHED.includes(task.status)) {
        return;
    }
    if (task.failures >= MAX_FAILURES || task.polls >= MAX_POLLS) {
        task.status = 'stopped';
        paint(task);
        return;
    }
    setTimeout(() => poll(task), delay);
};

/**
 * Queue a ZIP request as a background task and follow it in a dialogue.
 *
 * @param {Object} args The create_task arguments.
 * @returns {Promise}
 */
const queue = async(args) => {
    const task = newTask({taskid: 0, type: args.type, status: 'pending'});
    task.autodownload = true;
    await showDialogue(task);
    try {
        const result = await Ajax.call([{
            methodname: 'block_download_certificates_create_task',
            args,
        }])[0];
        task.taskid = result.taskid;
        task.total = result.total;
        task.roots.forEach((root) => {
            root.dataset.taskid = String(result.taskid);
        });
        paint(task);
        setTimeout(() => poll(task), POLL_DELAY);
    } catch (error) {
        // The refusal is shown where the progress was, in the dialogue or the banner that replaced it.
        task.status = 'failed';
        task.errormessage = error && error.message ? error.message : '';
        paint(task);
    }
};

/**
 * The element carrying a form's background download marks: the form or its submit button.
 *
 * @param {HTMLFormElement} form The form.
 * @returns {HTMLElement|null}
 */
const markerOf = (form) => (form.matches('[data-async-threshold]') ? form : form.querySelector('[data-async-threshold]'));

/**
 * The create_task arguments a form's fields carry.
 *
 * @param {HTMLFormElement} form The form.
 * @param {string} type The request type.
 * @returns {Object}
 */
const formArgs = (form, type) => {
    const args = {type};
    Object.entries(FIELDS).forEach(([arg, name]) => {
        const field = form.elements.namedItem(name);
        if (!field || !field.value) {
            return;
        }
        args[arg] = INTEGER_FIELDS.includes(arg) ? (parseInt(field.value, 10) || 0) : field.value;
    });
    return args;
};

/**
 * Ask a form's question in a core dialogue, then submit it again, answered.
 *
 * @param {HTMLFormElement} form The form.
 * @param {HTMLElement|null} submitter The button that submitted it.
 * @returns {Promise}
 */
const confirmThenSubmit = async(form, submitter) => {
    try {
        await Notification.saveCancelPromise(strings.confirm, form.dataset.confirm, strings.download, {
            triggerElement: submitter,
        });
    } catch (error) {
        // Cancelled: nothing is requested.
        return;
    }
    form.dataset.confirmed = 'true';
    if (submitter) {
        form.requestSubmit(submitter);
    } else {
        form.requestSubmit();
    }
};

/**
 * Ask a form's confirmation first, then queue its request instead of posting it when it reaches the
 * threshold.
 *
 * @param {Event} event The submit event.
 */
const onSubmit = (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
        return;
    }
    if (form.dataset.confirmed === 'true') {
        // Answered once for this submission: the next one asks again.
        delete form.dataset.confirmed;
    } else if (form.dataset.confirm) {
        event.preventDefault();
        confirmThenSubmit(form, event.submitter || null).catch(Notification.exception);
        return;
    }
    const marker = markerOf(form);
    if (!marker) {
        return;
    }
    const threshold = parseInt(marker.dataset.asyncThreshold, 10) || 0;
    const count = parseInt(marker.dataset.certCount, 10) || 0;
    if (!threshold || count < threshold) {
        // Small enough for the page to build at once.
        return;
    }
    event.preventDefault();
    queue(formArgs(form, marker.dataset.downloadType)).catch(Notification.exception);
};

/**
 * Keep a marked form's count in step with the option chosen in its menu.
 *
 * @param {Event} event The change event.
 */
const onChange = (event) => {
    const select = event.target;
    if (!(select instanceof HTMLSelectElement) || !select.form) {
        return;
    }
    const marker = markerOf(select.form);
    const option = select.selectedOptions[0];
    // Only the main menu's options carry a count; a group narrows the course count, never widens it.
    if (!marker || !option || option.dataset.count === undefined) {
        return;
    }
    marker.dataset.certCount = option.dataset.count;
};

/**
 * Handle the download and dismiss buttons of a rendered progress.
 *
 * @param {Event} event The click.
 */
const onClick = (event) => {
    const button = event.target.closest('[data-region="task-progress"] [data-action]');
    if (!button) {
        return;
    }
    const root = button.closest('[data-region="task-progress"]');
    const task = views.get(root);
    if (!task) {
        return;
    }
    if (button.dataset.action === 'download') {
        download(task);
    } else if (button.dataset.action === 'dismiss') {
        task.roots.delete(root);
        root.remove();
        if (task.status === 'ready') {
            rememberDismissed(task.taskid);
        }
    }
};

/**
 * Start the module: listen to the marked forms and show the user's unfinished and ready tasks.
 *
 * @param {Object} config userid, and pendingtasks: taskid, type, status, progress, total of each.
 * @returns {Promise}
 */
export const init = async(config) => {
    if (initialised) {
        return;
    }
    initialised = true;
    userid = config.userid;
    try {
        const requests = [
            ...Object.values(STRINGS).map((key) => ({key, component: COMPONENT})),
            ...Object.values(CORE_STRINGS).map((key) => ({key, component: 'core'})),
        ];
        const values = await getStrings(requests);
        [...Object.keys(STRINGS), ...Object.keys(CORE_STRINGS)].forEach((name, index) => {
            strings[name] = values[index];
        });
    } catch (error) {
        Notification.exception(error);
        return;
    }
    document.addEventListener('submit', onSubmit);
    document.addEventListener('change', onChange);
    document.addEventListener('click', onClick);

    const dismissed = getDismissed();
    const pending = (config.pendingtasks || []).filter((data) => {
        return data.status !== 'ready' || !dismissed.includes(data.taskid);
    });
    await Promise.all(pending.map(async(data) => {
        const task = newTask(data);
        await showBanner(task);
        if (!FINISHED.includes(task.status)) {
            setTimeout(() => poll(task), POLL_DELAY);
        }
    }));
};
