# REQ_cohort_listable: A cohort the user may not list can be zipped.
s#if \(!self::can_list_cohort\(\$scope, \$filter->cohortid\)\) \{#if (false) {#s;
