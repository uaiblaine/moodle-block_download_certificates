# REPO_cohort_view_ignored: moodle/cohort:view is ignored: a hidden cohort is never listed, even to a viewer.
s#!\$record->visible && !has_capability\('moodle/cohort:view', \$context, \$scope->get_user_id\(\)\)#!\$record->visible#s;
