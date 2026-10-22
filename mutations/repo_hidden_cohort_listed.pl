# REPO_hidden_cohort_listed: The cohort menu lists hidden cohorts to everyone.
s#if \(!\$record->visible && !has_capability\('moodle/cohort:view', \$context, \$scope->get_user_id\(\)\)\) \{#if (false) {#s;
