# REQ_other_users: Another user's id needs no can_see_others().
s#if \(\$filter->userid !== \$scope->get_user_id\(\) && !\$scope->can_see_others\(\)\) \{#if (false) {#s;
