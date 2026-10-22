# REPO_download_scope_check: A single download skips the scope check.
s#if \(!\$scope->can_see\(\$issue->userid, \$issue->courseid\)\) \{#if (false) {#s;
