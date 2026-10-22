# REQ_cohort_hidden_listed: can_list_cohort() answers true for every existing cohort.
s#return \(bool\) \$cohort->visible \|\| has_capability\('moodle/cohort:view', \$context, \$scope->get_user_id\(\)\);#return true;#s;
