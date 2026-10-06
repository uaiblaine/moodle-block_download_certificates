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
 * @copyright 2015 Manieer Chhettri  (Original Idea for Moodle 2.x and 3.x)
 * @copyright 2025 E-learning Touch' contact@elearningtouch.com (Maintainer)
 * @author    Thomas Clément 222384061+ClementThomasELT@users.noreply.github.com (Coder)
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Download Certificate';
$string['download_certificates:view'] = 'View download certificate block';
$string['download_certificates:manage'] = 'Manage download certificate';
$string['download_certificates:addinstance'] = 'Add a new download certificate block';
$string['download_certificates:myaddinstance'] = 'Add a new download certificate block to Dashboard';
$string['downloadcertificate'] = 'Download Certificate';

// Block specific strings.
$string['certificate_summary'] = 'Certificate Summary';
$string['total'] = 'Total';
$string['courses'] = 'Courses';
$string['recent_7days'] = 'Last 7 days';
$string['manage_certificates'] = 'Manage Certificates';
$string['download_all_quick'] = 'Download All';
$string['error_loading_block'] = 'Error loading certificate data';

// Main page strings.
$string['certificate_management'] = 'Certificate Management';
$string['download_all_certificates'] = 'Download All Certificates';
$string['confirm_download_all'] = 'Are you sure you want to download all certificates? This may take some time.';
$string['total_certificates'] = 'Total certificates';
$string['template'] = 'Template';
$string['code'] = 'Code';
$string['date_created'] = 'Date Created';
$string['download_certificate'] = 'Download Certificate';
$string['no_certificates_found'] = 'No certificates found';
$string['no_certificates_description'] = 'There are no certificates available for download at this time.';
$string['unknown'] = 'Unknown';

// Error messages.
$string['nocertificates'] = 'No certificates found to download.';
$string['cannotcreatezipfile'] = 'Cannot create ZIP file.';
$string['novalidcertificates'] = 'No valid certificates could be downloaded.';
$string['filenotfound'] = 'File not found.';
$string['certificatenotfound'] = 'Certificate not found.';

// User-specific certificate strings.
$string['my_certificates'] = 'My Certificates';
$string['my_certificates_count'] = 'My certificates';
$string['download_my_certificates'] = 'Download My Certificates';
$string['no_certificates_user'] = 'You don\'t have any certificates yet.';
$string['nocertificatesuser'] = 'No certificates found for this user.';
$string['novalidcertificatesuser'] = 'No valid certificates could be downloaded for this user.';

// Settings strings.
$string['enable'] = 'Enable plugin';
$string['enable_desc'] = 'Enable or disable the certificate download functionality.';
$string['max_certificates_display'] = 'Maximum certificates to display';
$string['max_certificates_display_desc'] = 'Maximum number of certificates to display in the block summary.';
$string['template'] = 'Certificate template';
$string['template_desc'] = 'HTML template for the certificate. Available placeholders: {fullname}, {course}, {date}, {grade}.';
$string['default_template'] = '<div style="text-align: center; font-family: Arial;">
<h1>Certificate of Completion</h1>
<p>This is to certify that</p>
<h2>{fullname}</h2>
<p>has successfully completed the course</p>
<h3>{course}</h3>
<p>on {date}</p>
<p>Grade: {grade}</p>
</div>';
$string['filename_format'] = 'Filename format';
$string['filename_format_desc'] = 'Format for certificate filenames. Available placeholders: {fullname}, {course}, {date}, {userid}.';
$string['managecertificates'] = 'Manage Certificates';

// Legacy strings for backward compatibility.
$string['certificate'] = 'Certificate';
$string['nocertificate'] = 'No certificate available';
$string['certificategenerated'] = 'Certificate generated successfully';
$string['certificateerror'] = 'Error generating certificate';

// Privacy.
$string['privacy:metadata'] = 'The Download Certificate plugin does not store any personal data.';

// Date range download strings.
$string['download_by_date_range'] = 'Download by Date Range';
$string['date_range_help'] = 'Select a date range to download all certificates issued within that period.';
$string['start_date'] = 'Start Date';
$string['end_date'] = 'End Date';
$string['download_range'] = 'Download Range';
$string['novalidcertificatesinrange'] = 'No valid certificates found in the specified date range.';

// Course download strings.
$string['download_by_course'] = 'Download by Course';
$string['course_download_help'] = 'Select a course to download all certificates issued for that course.';
$string['select_course'] = 'Select Course';
$string['choose_course'] = '-- Choose a course --';
$string['download_course_certificates'] = 'Download Course Certificates';
$string['no_courses_with_certificates'] = 'No courses found with certificates.';
$string['certificates'] = 'certificates';
$string['coursenotfound'] = 'Course not found.';
$string['nocertificatesforcourse'] = 'No certificates found for this course.';
$string['novalidcertificatesforcourse'] = 'No valid certificates could be downloaded for this course.';
$string['coursenotselected'] = 'Please select a course.';

// User download strings.
$string['download_by_user'] = 'Download by User';
$string['user_download_help'] = 'Select a user to download all their certificates as a ZIP file.';
$string['select_user'] = 'Select User';
$string['choose_user'] = 'Choose a user...';
$string['download_user_certificates'] = 'Download Certificates';
$string['no_users_with_certificates'] = 'No users with certificates found.';
$string['cannotdownloadusercertificates'] = 'Cannot download user certificates.';
$string['nocertificatesuser'] = 'No certificates found for this user.';
$string['novalidcertificatesuser'] = 'No valid certificates found for this user.';

// Precise download strings.
$string['download_precise'] = 'Precise Download';
$string['precise_download_help'] = 'Download specific certificates by selecting individual items.';
$string['select_certificates'] = 'Select Certificates';
$string['download_selected'] = 'Download Selected';
$string['nocertificatesselected'] = 'No certificates selected.';
$string['novalidcertificatesselected'] = 'No valid certificates could be downloaded from selection.';

// Date range validation and progress strings.
$string['invalidaterange'] = 'Invalid date range selected. Please ensure the start date is before the end date.';
$string['downloadinprogress'] = 'Download in progress...';

// Cohort download strings.
$string['download_by_cohort'] = 'Download by Cohort';
$string['cohort_download_help'] = 'Select a cohort to download all certificates for its members.';
$string['select_cohort'] = 'Select Cohort';
$string['choose_cohort'] = 'Choose a cohort...';
$string['members'] = 'members';
$string['download_cohort_certificates'] = 'Download Cohort Certificates';
$string['no_cohorts_with_certificates'] = 'No cohorts found with certificates.';
$string['cohortnotselected'] = 'No cohort selected.';
$string['nocohortmembers'] = 'No members found in this cohort.';
$string['nocertificatescohort'] = 'No certificates found for this cohort members.';
$string['novalidcertificatescohort'] = 'No valid certificates could be downloaded for this cohort.';

// Customcert specific strings.
$string['customcert_certificate'] = 'Custom Certificate';
$string['customcert_not_available'] = 'Custom Certificate plugin not available.';

$string['cannotdownloadcertificate'] = 'Cannot download certificate. Please try again later or contact an administrator.';

// Async download strings.
$string['task_generate_zip'] = 'Generate certificate ZIP archive';
$string['task_cleanup_expired'] = 'Clean up expired certificate download tasks';
$string['async_generating'] = 'Generating ZIP archive';
$string['async_preparing'] = 'Preparing your download...';
$string['async_processing'] = 'Processing certificates...';
$string['async_ready'] = 'Your ZIP is ready! Download will start automatically.';
$string['async_zip_ready'] = 'A certificate ZIP archive is ready for download.';
$string['async_download_started'] = 'Your download is being prepared in the background. You can continue browsing.';
$string['tasknotfound'] = 'Download task not found.';
$string['tasknotready'] = 'Download task is not ready yet.';
$string['accessdenied'] = 'Access denied.';
$string['invalidtype'] = 'Invalid download type.';
$string['nocertificatesinrange'] = 'No certificates found in the specified date range.';
$string['async_close'] = 'Close';
$string['async_download'] = 'Download';
$string['async_can_close'] = 'You can close this window and continue browsing. The archive will keep generating and will be ready when you return to this page.';
$string['async_zip_ready_label_all'] = 'Your ZIP archive of all certificates is ready for download.';
$string['async_zip_ready_label_course'] = 'Your ZIP archive of course certificates is ready for download.';
$string['async_zip_ready_label_user'] = 'Your ZIP archive of user certificates is ready for download.';
$string['async_zip_ready_label_cohort'] = 'Your ZIP archive of cohort certificates is ready for download.';
$string['async_zip_ready_label_range'] = 'Your ZIP archive of certificates (date range) is ready for download.';

// Table and search strings.
$string['type'] = 'Type';
$string['no_search_results'] = 'No certificates found matching your search.';
$string['search_placeholder'] = 'Search by name, email, course, template...';
$string['per_page'] = 'Per page';
$string['showing_results'] = 'Showing {$a->start}-{$a->end} of {$a->total}';

// Tab labels (short).
$string['tab_date_range'] = 'By date range';
$string['tab_course'] = 'By course';
$string['tab_cohort'] = 'By cohort';
$string['tab_user'] = 'By user';

// RGPD Data.
$string['privacy:metadata'] = 'The Download Certificates plugin does not store any personal data.';

// Teacher access strings.
$string['teacher_panel'] = 'Teacher Panel';
$string['teacher_scope_courses'] = 'Scope: your courses';
$string['teacher_scope_groups'] = 'Scope: your groups ({$a})';
$string['no_teacher_courses'] = 'You have no courses as a teacher.';
$string['teacher_certificates'] = 'Certificates from your students';
$string['access_denied_certificate'] = 'Access denied: this certificate is not within your scope.';
$string['teacher_no_certificates'] = 'No certificates found within your scope.';
$string['all_groups'] = 'All groups';
$string['choose_group'] = 'Choose a group';
$string['invalidgroup'] = 'Invalid or inaccessible group.';
