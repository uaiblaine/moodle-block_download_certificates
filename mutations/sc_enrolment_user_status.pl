# SC_enrolment_user_status: A suspended user enrolment counts as active (guard kept as a dead OR so the placeholder stays used).
s#AND \{\$p\}ue\.status = :\{\$p\}active#AND (1 = 1 OR {\$p}ue.status = :{\$p}active)#s;
