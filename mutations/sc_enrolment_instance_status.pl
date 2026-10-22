# SC_enrolment_instance_status: A disabled enrol instance still counts.
s#AND \{\$p\}e\.status = :\{\$p\}enabled#AND (1 = 1 OR {\$p}e.status = :{\$p}enabled)#s;
