<?php
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
 * English language strings for block_download_certificates plugin.
 *
 * @package   block_download_certificates
 * @copyright 2015 Manieer Chhettri
 * @copyright 2025 E-learning Touch <contact@elearningtouch.com>
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accessdenied'] = 'Access denied.';
$string['all_groups'] = 'All groups';
$string['async_can_close'] = 'You can close this window and continue browsing. The archive will keep generating and will be ready when you return to this page.';
$string['async_close'] = 'Close';
$string['async_download'] = 'Download';
$string['async_generating'] = 'Generating ZIP archive';
$string['async_preparing'] = 'Preparing your download...';
$string['async_processing'] = 'Processing certificates...';
$string['async_ready'] = 'Your ZIP is ready! Download will start automatically.';
$string['async_zip_ready'] = 'A certificate ZIP archive is ready for download.';
$string['async_zip_ready_label_all'] = 'Your ZIP archive of all certificates is ready for download.';
$string['async_zip_ready_label_cohort'] = 'Your ZIP archive of cohort certificates is ready for download.';
$string['async_zip_ready_label_course'] = 'Your ZIP archive of course certificates is ready for download.';
$string['async_zip_ready_label_range'] = 'Your ZIP archive of certificates (date range) is ready for download.';
$string['async_zip_ready_label_user'] = 'Your ZIP archive of user certificates is ready for download.';
$string['cannotcreatezipfile'] = 'Cannot create ZIP file.';
$string['cannotdownloadcertificate'] = 'Cannot download certificate. Please try again later or contact an administrator.';
$string['certificate_management'] = 'Certificate Management';
$string['certificate_summary'] = 'Certificate Summary';
$string['certificatenotfound'] = 'Certificate not found.';
$string['certificates'] = 'certificates';
$string['choose_cohort'] = 'Choose a cohort...';
$string['choose_course'] = '-- Choose a course --';
$string['choose_group'] = 'Choose a group';
$string['choose_user'] = 'Choose a user...';
$string['cohort_download_help'] = 'Select a cohort to download all certificates for its members.';
$string['cohortnotselected'] = 'No cohort selected.';
$string['confirm_download_all'] = 'Are you sure you want to download all certificates? This may take some time.';
$string['course_download_help'] = 'Select a course to download all certificates issued for that course.';
$string['coursenotfound'] = 'Course not found.';
$string['coursenotselected'] = 'Please select a course.';
$string['courses'] = 'Courses';
$string['date_created'] = 'Date Created';
$string['date_range_help'] = 'Select a date range to download all certificates issued within that period.';
$string['download_all_certificates'] = 'Download All Certificates';
$string['download_all_quick'] = 'Download All';
$string['download_certificate'] = 'Download Certificate';
$string['download_certificates:addinstance'] = 'Add a new download certificate block';
$string['download_certificates:manage'] = 'Manage download certificate';
$string['download_certificates:myaddinstance'] = 'Add a new download certificate block to Dashboard';
$string['download_certificates:view'] = 'View download certificate block';
$string['download_cohort_certificates'] = 'Download Cohort Certificates';
$string['download_course_certificates'] = 'Download Course Certificates';
$string['download_my_certificates'] = 'Download My Certificates';
$string['download_range'] = 'Download Range';
$string['download_user_certificates'] = 'Download Certificates';
$string['end_date'] = 'End Date';
$string['error_loading_block'] = 'Error loading certificate data';
$string['filenotfound'] = 'File not found.';
$string['invalidaction'] = 'Invalid action.';
$string['invalidaterange'] = 'Invalid date range selected. Please ensure the start date is before the end date.';
$string['invalidgroup'] = 'Invalid or inaccessible group.';
$string['invalidparameters'] = 'Invalid parameters.';
$string['invalidtype'] = 'Invalid download type.';
$string['manage_certificates'] = 'Manage Certificates';
$string['members'] = 'members';
$string['my_certificates'] = 'My Certificates';
$string['my_certificates_count'] = 'My certificates';
$string['no_certificates_description'] = 'There are no certificates available for download at this time.';
$string['no_certificates_found'] = 'No certificates found';
$string['no_certificates_user'] = 'You don\'t have any certificates yet.';
$string['no_search_results'] = 'No certificates found matching your search.';
$string['no_teacher_courses'] = 'You have no courses as a teacher.';
$string['nocertificates'] = 'No certificates found to download.';
$string['nocertificatescohort'] = 'No certificates found for this cohort members.';
$string['nocertificatesforcourse'] = 'No certificates found for this course.';
$string['nocertificatesinrange'] = 'No certificates found in the specified date range.';
$string['nocertificatesuser'] = 'No certificates found for this user.';
$string['nocohortmembers'] = 'No members found in this cohort.';
$string['novalidcertificates'] = 'No valid certificates could be downloaded.';
$string['novalidcertificatescohort'] = 'No valid certificates could be downloaded for this cohort.';
$string['novalidcertificatesforcourse'] = 'No valid certificates could be downloaded for this course.';
$string['novalidcertificatesinrange'] = 'No valid certificates found in the specified date range.';
$string['novalidcertificatesuser'] = 'No valid certificates found for this user.';
$string['pluginname'] = 'Download Certificate';
$string['privacy:metadata:block_download_certificates_tasks'] = 'Records of the certificate ZIP downloads that a user requested.';
$string['privacy:metadata:block_download_certificates_tasks:error'] = 'The error message recorded when the download failed.';
$string['privacy:metadata:block_download_certificates_tasks:filepath'] = 'The location of the generated ZIP archive of certificates on the server.';
$string['privacy:metadata:block_download_certificates_tasks:params'] = 'The filter chosen for the download, such as the course, user, cohort or date range.';
$string['privacy:metadata:block_download_certificates_tasks:status'] = 'The state of the download request.';
$string['privacy:metadata:block_download_certificates_tasks:timecreated'] = 'The time the download was requested.';
$string['privacy:metadata:block_download_certificates_tasks:timemodified'] = 'The time the download request was last updated.';
$string['privacy:metadata:block_download_certificates_tasks:type'] = 'The kind of download requested (all, course, user, cohort or date range).';
$string['privacy:metadata:block_download_certificates_tasks:userid'] = 'The ID of the user who requested the download.';
$string['privacy:path:tasks'] = 'Certificate download requests';
$string['recent_7days'] = 'Last 7 days';
$string['start_date'] = 'Start Date';
$string['tab_cohort'] = 'By cohort';
$string['tab_course'] = 'By course';
$string['tab_date_range'] = 'By date range';
$string['tab_user'] = 'By user';
$string['task_cleanup_expired'] = 'Clean up expired certificate download tasks';
$string['task_generate_zip'] = 'Generate certificate ZIP archive';
$string['tasknotfound'] = 'Download task not found.';
$string['tasknotready'] = 'Download task is not ready yet.';
$string['teacher_certificates'] = 'Certificates from your students';
$string['teacher_no_certificates'] = 'No certificates found within your scope.';
$string['teacher_panel'] = 'Teacher Panel';
$string['teacher_scope_groups'] = 'Scope: your groups ({$a})';
$string['template'] = 'Certificate template';
$string['total'] = 'Total';
$string['total_certificates'] = 'Total certificates';
$string['type'] = 'Type';
$string['unknown'] = 'Unknown';
$string['user_download_help'] = 'Select a user to download all their certificates as a ZIP file.';
