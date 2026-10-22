# REQ_course_in_scope: A course ZIP is allowed for a course outside the user's courses.
s#if \(!\$scope->is_manager\(\) && !in_array\(\$filter->courseid, \$scope->get_course_ids\(\), true\)\) \{#if (false) {#s;
