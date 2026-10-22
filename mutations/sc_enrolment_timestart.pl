# SC_enrolment_timestart: An enrolment that has not started counts.
s#AND \{\$p\}ue\.timestart < :\{\$p\}now1#AND (1 = 1 OR {\$p}ue.timestart < :{\$p}now1)#s;
