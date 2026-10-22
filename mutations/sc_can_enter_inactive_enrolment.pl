# SC_can_enter_inactive_enrolment: A suspended enrolment still lets the user enter (is_enrolled without onlyactive).
s#is_enrolled\(\$context, \$userid, '', true\)#is_enrolled(\$context, \$userid, '', false)#s;
