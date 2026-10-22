# REQ_groups_course_in_scope: The group menu answers for a course outside the user's courses.
s#if \(!\$scope->is_manager\(\) && !in_array\(\$courseid, \$scope->get_course_ids\(\), true\)\) \{#if (false) {#s;
