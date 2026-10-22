# REQ_course_without_courseid: A course request without a course is not refused.
s#if \(!\$filter->courseid\) \{(\s+throw new \\moodle_exception\('coursenotselected')#if (false) {$1#s;
