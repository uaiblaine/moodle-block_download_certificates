# SC_enrolment_timeend: An ended enrolment counts.
s#AND \(\{\$p\}ue\.timeend = 0 OR \{\$p\}ue\.timeend > :\{\$p\}now2\)#AND (1 = 1 OR {\$p}ue.timeend > :{\$p}now2)#s;
