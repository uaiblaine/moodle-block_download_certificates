# MKT_email_scope_owner: email_visible() decides somebody else's scope on the current user's capabilities.
s#if \(\$scope->get_user_id\(\) !== \(int\) \$USER->id\) \{#if (false) {#s;
